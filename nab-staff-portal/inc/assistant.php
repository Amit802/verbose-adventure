<?php
/**
 * Reply Assistant: reads a customer's email, the parts of our documents that
 * match it and (optionally) the member's portal details, and asks OpenAI for a
 * one line summary, a few tags and a reply in NAB's voice.
 *
 * Uses the same OpenAI key and model as Nabby in the member portal
 * (nab_portal_ai_config()). Card, bank and SIN numbers are hidden before
 * anything is sent. Nothing is sent to the member: staff copy the reply.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function nab_staff_ai_config() {
    $cfg = function_exists( 'nab_portal_ai_config' ) ? nab_portal_ai_config() : [ 'key' => '', 'model' => 'gpt-4o-mini' ];
    return apply_filters( 'nab_staff_ai_config', [ 'key' => (string) ( $cfg['key'] ?? '' ), 'model' => (string) ( $cfg['model'] ?? 'gpt-4o-mini' ) ] );
}

/** Hide card, SIN and bank account numbers. Returns [ text, count hidden ]. */
function nab_staff_mask( $text ) {
    $n = 0;
    $text = preg_replace_callback( '/\b(?:\d[ -]?){12,18}\d\b/', function () use ( &$n ) { $n++; return '[card number hidden]'; }, $text );
    $text = preg_replace_callback( '/\b\d{3}[ -]\d{3}[ -]\d{3}\b|\b(?<!\d)\d{9}(?!\d)\b/', function () use ( &$n ) { $n++; return '[SIN or account number hidden]'; }, $text );
    $text = preg_replace_callback( '/\b\d{5}[ -]\d{3}\b|\b\d{7,12}\b/', function ( $m ) use ( &$n ) {
        // Leave 10 digit phone numbers alone when they look like one (area code first digit 2-9).
        if ( preg_match( '/^[2-9]\d{9}$/', $m[0] ) ) return $m[0];
        $n++;
        return '[account number hidden]';
    }, $text );
    return [ $text, $n ];
}

/**
 * The parts of our switched-on documents that best match the email, up to
 * about $budget characters. Small libraries are sent whole.
 */
function nab_staff_ai_context( $email, $budget = 24000 ) {
    $docs = nab_staff_docs( true );
    $all  = '';
    foreach ( $docs as $d ) $all .= "\n\n### " . get_the_title( $d ) . "\n" . $d->post_content;
    if ( mb_strlen( $all ) <= $budget ) return [ trim( $all ), array_map( 'get_the_title', $docs ) ];

    $words = array_filter( array_unique( preg_split( '/[^a-z0-9]+/', strtolower( $email ) ) ), function ( $w ) {
        return strlen( $w ) > 3 && ! in_array( $w, [ 'this', 'that', 'with', 'have', 'from', 'your', 'what', 'when', 'will', 'thanks', 'thank', 'hello', 'would', 'could', 'please', 'there', 'they', 'been' ], true );
    } );
    $chunks = [];
    foreach ( $docs as $d ) {
        $title = get_the_title( $d );
        foreach ( preg_split( "/\n\s*\n/", (string) $d->post_content ) as $para ) {
            $para = trim( $para );
            if ( $para === '' ) continue;
            foreach ( str_split( $para, 1500 ) as $piece ) {
                $low   = strtolower( $piece . ' ' . $title );
                $score = 0;
                foreach ( $words as $w ) $score += substr_count( $low, $w );
                $chunks[] = [ 'title' => $title, 'text' => $piece, 'score' => $score ];
            }
        }
    }
    usort( $chunks, function ( $a, $b ) { return $b['score'] <=> $a['score']; } );
    $out = ''; $used = [];
    foreach ( $chunks as $c ) {
        if ( mb_strlen( $out ) + mb_strlen( $c['text'] ) > $budget ) continue;
        $out .= "\n\n### " . $c['title'] . "\n" . $c['text'];
        $used[ $c['title'] ] = true;
    }
    return [ trim( $out ), array_keys( $used ) ];
}

function nab_staff_ai_tones() {
    return [
        'standard' => 'warm, clear and professional',
        'short'    => 'short and to the point, a few sentences only',
        'friendly' => 'extra warm and friendly, still professional',
        'formal'   => 'formal and polite',
    ];
}

/** Call OpenAI and return the decoded JSON, or [ 'error' => ... ]. */
function nab_staff_ai_call( $messages, $max_tokens = 900 ) {
    $cfg = nab_staff_ai_config();
    if ( $cfg['key'] === '' ) return [ 'error' => 'The AI is not set up yet. Add the OpenAI key on the Dashboard page settings (OpenAI / AI Features).' ];

    // Per person limit, so a stuck page can't run up the bill.
    $uid   = get_current_user_id();
    $key   = 'nab_staff_ai_' . $uid . '_' . gmdate( 'YmdH' );
    $count = (int) get_transient( $key );
    if ( $count >= (int) apply_filters( 'nab_staff_ai_hourly_limit', 120 ) ) return [ 'error' => 'You\'ve used the assistant a lot this hour. Please try again in a little while.' ];
    set_transient( $key, $count + 1, HOUR_IN_SECONDS );

    $res = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 45,
        'headers' => [ 'Authorization' => 'Bearer ' . $cfg['key'], 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [
            'model'           => $cfg['model'],
            'temperature'     => 0.4,
            'max_tokens'      => $max_tokens,
            'response_format' => [ 'type' => 'json_object' ],
            'messages'        => $messages,
            'user'            => 'nab-staff-' . $uid,
        ] ),
    ] );
    if ( is_wp_error( $res ) ) {
        error_log( '[NAB Staff] OpenAI request failed: ' . $res->get_error_message() );
        return [ 'error' => 'Could not reach the AI. Please try again.' ];
    }
    $code = (int) wp_remote_retrieve_response_code( $res );
    $body = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( $code !== 200 ) {
        error_log( '[NAB Staff] OpenAI returned ' . $code . ': ' . wp_remote_retrieve_body( $res ) );
        return [ 'error' => $code === 401 ? 'The OpenAI key isn\'t working. Please check it in the Dashboard page settings.' : 'The AI is busy right now. Please try again in a minute.' ];
    }
    $json = json_decode( (string) ( $body['choices'][0]['message']['content'] ?? '' ), true );
    if ( ! is_array( $json ) ) return [ 'error' => 'The AI gave an answer we couldn\'t read. Please try again.' ];
    return $json;
}

/** Clean up the AI's reply text: no em dashes, no emoji, tidy line breaks. */
function nab_staff_ai_tidy( $text ) {
    $text = preg_replace( '/\s*\x{2014}\s*/u', ', ', (string) $text );
    $text = preg_replace( '/\s*\x{2013}\s*/u', ' - ', $text );
    $text = preg_replace( '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $text );
    $text = preg_replace( '/[ \t]+(?=[\n,.!?]|$)/m', '', $text );
    $text = preg_replace( '/[ \t]{2,}/', ' ', $text );
    return trim( preg_replace( "/\n{3,}/", "\n\n", str_replace( "\r", '', $text ) ) );
}

function nab_staff_ai_system( $tone, $agent ) {
    $tones = nab_staff_ai_tones();
    return "You help the Customer Care team at NAB Solutions reply to member emails.\n"
        . "NAB Solutions is a Canadian company in Calgary. Members pay a weekly membership ($20.45 a week by pre-authorized debit) for credit education and coaching, a Lending Finder Program that matches them with third-party lenders, and an online member portal with credit tools. NAB is not a lender and does not guarantee approvals.\n\n"
        . "Rules:\n"
        . "- Base every fact about policies, fees, dates and procedures only on the NAB documents provided. Never invent a policy, fee, refund, timeline or promise.\n"
        . "- If the documents don't answer the question, write a polite reply that says the team will look into it and get back to them, and say so in the summary.\n"
        . "- Use the member details only if given. Never mention that you are an AI or that text was hidden.\n"
        . "- Write in Canadian English, plain and human, like a helpful person on the team. No em dashes, no emoji, no bullet symbols unless steps are needed.\n"
        . "- Tone: " . ( $tones[ $tone ] ?? $tones['standard'] ) . ".\n"
        . "- Start with \"Hi\" and the member's first name if known. End with \"Kind regards,\" then \"" . $agent . "\" and \"NAB Solutions Customer Care\" on separate lines.\n\n"
        . "Answer as JSON with these keys:\n"
        . "summary: one short sentence on what the member wants (start with their first name if known)\n"
        . "tags: 1 to 3 short topic labels, e.g. Cancellation, Refund request, Billing, Login, Loans, Disputes, Collections, Pause, Account update\n"
        . "mood: calm, unhappy or angry\n"
        . "reply: the email reply\n"
        . "covered: true if the documents answered the question, false if not";
}

/** Suggest a reply. */
function nab_staff_ai_reply() {
    $raw   = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    $email = sanitize_email( wp_unslash( $_POST['member'] ?? '' ) );
    $tone  = sanitize_key( $_POST['tone'] ?? 'standard' );
    if ( mb_strlen( trim( $raw ) ) < 5 ) return [ 'error' => 'Paste the customer\'s email first.' ];
    list( $message, $hidden ) = nab_staff_mask( mb_substr( $raw, 0, 8000 ) );

    list( $context, $sources ) = nab_staff_ai_context( $message );
    $brief = $email ? nab_staff_member_brief( $email ) : null;
    $agent = wp_get_current_user()->first_name ?: 'The NAB team';

    $user = "NAB documents:\n" . ( $context !== '' ? $context : '(No documents have been added yet.)' ) . "\n\n"
          . ( $brief ? "Member details from our portal:\n" . $brief['ai'] . "\n" : '' )
          . "The member's email:\n\"\"\"\n" . $message . "\n\"\"\"";
    $ai = nab_staff_ai_call( [ [ 'role' => 'system', 'content' => nab_staff_ai_system( $tone, $agent ) ], [ 'role' => 'user', 'content' => $user ] ] );
    if ( isset( $ai['error'] ) ) return $ai;

    return [
        'summary' => nab_staff_ai_tidy( $ai['summary'] ?? '' ),
        'tags'    => array_slice( array_map( 'sanitize_text_field', (array) ( $ai['tags'] ?? [] ) ), 0, 3 ),
        'mood'    => in_array( $ai['mood'] ?? '', [ 'calm', 'unhappy', 'angry' ], true ) ? $ai['mood'] : 'calm',
        'reply'   => nab_staff_ai_tidy( $ai['reply'] ?? '' ),
        'covered' => ! empty( $ai['covered'] ),
        'sources' => $context !== '' ? $sources : [],
        'hidden'  => $hidden,
        'member'  => $brief ? $brief['card'] : null,
        'noDocs'  => $context === '',
    ];
}

/** Rewrite an existing reply in another tone (keeps the facts). */
function nab_staff_ai_retone() {
    $reply = sanitize_textarea_field( wp_unslash( $_POST['reply'] ?? '' ) );
    $tone  = sanitize_key( $_POST['tone'] ?? 'standard' );
    if ( trim( $reply ) === '' ) return [ 'error' => 'There is no reply to change yet.' ];
    $tones = nab_staff_ai_tones();
    $ai = nab_staff_ai_call( [
        [ 'role' => 'system', 'content' => "Rewrite this customer service email so it is " . ( $tones[ $tone ] ?? $tones['standard'] ) . ". Keep every fact, date, amount and promise exactly the same, keep the greeting name and sign-off. No em dashes, no emoji. Canadian English. Answer as JSON: {\"reply\": \"...\"}" ],
        [ 'role' => 'user', 'content' => mb_substr( $reply, 0, 8000 ) ],
    ], 800 );
    if ( isset( $ai['error'] ) ) return $ai;
    return [ 'reply' => nab_staff_ai_tidy( $ai['reply'] ?? '' ) ];
}
