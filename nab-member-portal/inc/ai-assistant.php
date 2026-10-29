<?php
/**
 * NAB Member Portal — NAB AI Assistant (v1.12.0)
 *
 * One assistant, two places to talk to it:
 *   - the full page   templates/page-nab-chatbot.php
 *   - the chat widget  bottom-right corner of every other portal page
 *                      (printed here on wp_footer)
 * Both use assets/js/nab-ai.js and the same AJAX action, nab_ai_chat, and
 * share one conversation per browser tab session.
 *
 * The answers still follow NAB's own AI Knowledge Base
 * (nab_get_ai_knowledge_base() in inc/helpers.php — unchanged), and the
 * suggested questions are the same six the chatbot page always had
 * (nab_portal_ai_prompts()).
 *
 * Settings (ACF, Dashboard page → OpenAI / AI Features): nab_openai_key,
 * nab_ai_model, nab_ai_enabled. Without a key the assistant runs in
 * offline mode with built-in answers (in nab-ai.js).
 *
 * @package NAB_Member_Portal
 * @since   1.12.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** The suggested questions (unchanged from the original chatbot page). */
function nab_portal_ai_prompts() {
    return apply_filters( 'nab_portal_ai_prompts', [
        [ 'icon' => 'trending-up', 'tone' => 'green',  'label' => 'Improve my credit score',      'hint' => 'Get a personalised action plan', 'q' => 'How can I improve my credit score fast?' ],
        [ 'icon' => 'shield-check', 'tone' => 'indigo', 'label' => 'Dispute credit report errors', 'hint' => 'Step-by-step dispute guidance', 'q' => 'How do I dispute an error on my credit report?' ],
        [ 'icon' => 'credit-card', 'tone' => 'purple',  'label' => 'Best credit card for me',      'hint' => 'Canadian card recommendations', 'q' => 'What credit card is best for someone rebuilding credit in Canada?' ],
        [ 'icon' => 'gauge', 'tone' => 'teal',        'label' => 'Credit utilization explained', 'hint' => 'Optimise your ratio',           'q' => 'How does credit utilization affect my score and what should I aim for?' ],
        [ 'icon' => 'clock', 'tone' => 'orange',        'label' => 'How long do negatives last?',  'hint' => 'Canadian credit bureau rules',  'q' => 'How long does a missed payment stay on my credit report in Canada?' ],
        [ 'icon' => 'landmark', 'tone' => 'blue',     'label' => 'Equifax vs TransUnion',        'hint' => 'Understanding both bureaus',    'q' => 'What is the difference between Equifax and TransUnion in Canada?' ],
    ] );
}

/** OpenAI settings from the Dashboard page: [ key, model, enabled ]. */
function nab_portal_ai_config() {
    static $cfg = null;
    if ( $cfg !== null ) return $cfg;
    $dash_pages = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dashboard', 'number' => 1 ] );
    $dash_id    = ! empty( $dash_pages ) ? $dash_pages[0]->ID : 0;
    $has_acf    = function_exists( 'get_field' );
    $key        = $dash_id && $has_acf ? (string) get_field( 'nab_openai_key', $dash_id ) : '';
    $model      = $dash_id && $has_acf ? ( get_field( 'nab_ai_model', $dash_id ) ?: 'gpt-4o-mini' ) : 'gpt-4o-mini';
    $enabled    = $dash_id && $has_acf ? get_field( 'nab_ai_enabled', $dash_id ) : true;
    $cfg = [
        'key'     => trim( $key ),
        'model'   => $model,
        'enabled' => $enabled === null ? true : (bool) $enabled, // ACF returns null before the field is first saved
    ];
    $cfg['live'] = $cfg['key'] !== '' && $cfg['enabled'];
    return $cfg;
}

/**
 * What the assistant may know about the member, so answers can be specific
 * ("your utilization is 62%") instead of generic. Totals only — no account
 * numbers, addresses or other personal details are sent.
 */
function nab_portal_ai_member_context( $uid ) {
    $lines = [];
    $user  = get_userdata( $uid );
    $first = get_user_meta( $uid, 'nab_first_name', true ) ?: ( $user ? $user->first_name : '' );
    if ( $first ) $lines[] = 'First name: ' . $first;
    if ( function_exists( 'nab_get_member_tier' ) && ( $tier = nab_get_member_tier( $uid ) ) ) $lines[] = 'Plan: ' . $tier;
    if ( function_exists( 'nab_get_member_since' ) && ( $since = nab_get_member_since( $uid, 'F Y' ) ) ) $lines[] = 'Member since: ' . $since;
    if ( $goal = get_user_meta( $uid, 'nab_credit_goal', true ) )  $lines[] = 'Credit goal: ' . $goal;
    if ( $track = get_user_meta( $uid, 'nab_track', true ) )       $lines[] = 'Education track: ' . $track;

    if ( ! function_exists( 'nab_dash_get_chart_data' ) ) return implode( "\n", $lines );
    $d   = nab_dash_get_chart_data( $uid );
    $fmt = function( $v ) { return '$' . number_format( (float) $v, 0 ); };

    // Credit score and how it has moved
    $hist = $d['score'] ?? [];
    if ( $hist ) {
        $last = end( $hist );
        $line = 'Credit score: ' . $last['s'] . ' (as of ' . $last['d'] . ')';
        if ( count( $hist ) > 1 ) {
            $first_s = reset( $hist );
            $diff    = $last['s'] - $first_s['s'];
            $line   .= ', ' . ( $diff >= 0 ? 'up ' : 'down ' ) . abs( $diff ) . ' points since ' . $first_s['d'];
        }
        $lines[] = $line;
    } elseif ( $score = (int) get_user_meta( $uid, 'nab_credit_score', true ) ) {
        $lines[] = 'Credit score: ' . $score;
    }

    // Credit card utilization
    $bal = $lim = 0;
    foreach ( $d['util']['cards'] ?? [] as $c ) { $bal += $c['bal']; $lim += $c['lim']; }
    if ( $lim > 0 ) $lines[] = 'Credit cards: ' . count( $d['util']['cards'] ) . ' card(s), ' . $fmt( $bal ) . ' owed on ' . $fmt( $lim ) . ' of limits (' . round( 100 * $bal / $lim ) . '% utilization)';

    // Net worth / debts
    if ( ! empty( $d['accounts'] ) ) {
        $nw = $d['networth'];
        $lines[] = 'Accounts entered: assets ' . $fmt( $nw['assets'] ) . ', debts ' . $fmt( $nw['debts'] ) . ', net worth ' . $fmt( $nw['net'] );
    }

    // This month's cash flow and budget
    $month = $d['month'];
    foreach ( $d['cashflow'] as $row ) {
        if ( $row['m'] !== $month ) continue;
        $spent = array_sum( array_map( 'floatval', $row['exp'] ) );
        $lines[] = 'This month so far: income ' . $fmt( $row['inc'] ) . ', spending ' . $fmt( $spent );
    }
    if ( function_exists( 'nab_budget_get' ) && ( $budget = nab_budget_get( $uid ) ) ) {
        $lines[] = 'Monthly budget: ' . $fmt( array_sum( array_map( 'floatval', $budget ) ) );
    }

    // Emergency fund
    if ( ! empty( $d['ef']['goal'] ) ) $lines[] = 'Emergency fund: ' . $fmt( $d['ef']['saved'] ) . ' saved of a ' . $fmt( $d['ef']['goal'] ) . ' goal';

    // Upcoming and overdue bills (Payment Calendar)
    if ( function_exists( 'nab_cal_get_bills' ) && ( $bills = nab_cal_get_bills( $uid ) ) ) {
        $paid  = nab_cal_get_paid( $uid );
        $today = current_time( 'Y-m-d' );
        $from  = wp_date( 'Y-m-d', strtotime( $today . ' -30 days' ) );
        $to    = wp_date( 'Y-m-d', strtotime( $today . ' +14 days' ) );
        $due   = [];
        foreach ( $bills as $b ) {
            foreach ( nab_cal_bill_dates( $b, $from, $to ) as $dt ) {
                if ( ! empty( $b['autopay'] ) || ! empty( $paid[ $b['id'] . '@' . $dt ] ) ) continue;
                $due[] = $b['name'] . ' ' . $fmt( $b['amount'] ) . ( $dt < $today ? ' OVERDUE since ' : ' due ' ) . $dt;
            }
        }
        if ( $due ) $lines[] = 'Unpaid bills (overdue or due in the next 14 days): ' . implode( '; ', array_slice( $due, 0, 8 ) );
    }
    return implode( "\n", $lines );
}

/** Full system prompt: knowledge base (unchanged) + member context + answer style. */
function nab_portal_ai_system_prompt( $uid, $page = '' ) {
    $context = nab_portal_ai_member_context( $uid );
    $today   = wp_date( 'l, F j, Y' );
    return "You are the NAB Credit Assistant, an expert AI credit advisor inside the NAB Solutions member portal for Canadian members.\n\n"
        . ( function_exists( 'nab_get_ai_knowledge_base' ) ? nab_get_ai_knowledge_base() . "\n\n" : '' )
        . "TODAY: {$today}\n"
        . ( $page ? "The member is currently on this portal page: {$page}\n" : '' )
        . ( $context ? "\nMEMBER CONTEXT (from their portal; use it when it helps, never invent numbers that are not here):\n{$context}\n" : "\nMEMBER CONTEXT: The member has not entered any data yet. If personal numbers would help, suggest adding them on the dashboard.\n" )
        . "\nYOUR NAME: In the portal you appear as Nabby, the NAB Solutions mascot. If a member asks your name or who you are, say you are Nabby, NAB Solutions' AI assistant.\n"
        . "\nHOW TO ANSWER:\n"
        . "- Answer the actual question first, in plain everyday language. Address the member by first name now and then, not in every reply.\n"
        . "- When the member context is relevant, use their real numbers (for example their utilization or an overdue bill) so the advice is specific to them.\n"
        . "- Keep replies under about 150 words unless the member asks for detail. Use short paragraphs, and use a numbered or bulleted list for steps.\n"
        . "- Format with simple Markdown only: **bold**, lists, and links. No tables, no headings larger than ###.\n"
        . "- When a portal tool would help, name it exactly (Credit Score Simulator, Utilization Checker, Dispute Center, Budget Planner, Payment Calendar, Net Worth Calculator, Emergency Fund Planner, Card Matcher, Lending Finder Program, Learning Center, Support Center, Book Specialist).\n"
        . "- Be encouraging and honest. Never guarantee approvals or score changes. Only discuss credit, personal finance, and NAB Solutions topics, and recommend a licensed professional for important legal, tax, or financial decisions.\n"
        . "- If the member writes in French, reply in French.";
}

/** Simple per-member limit so a stuck script or abuse can't run up the OpenAI bill. */
function nab_portal_ai_rate_ok( $uid ) {
    $key = 'nab_ai_rate_' . $uid;
    $max = (int) apply_filters( 'nab_portal_ai_hourly_limit', 60 );
    $win = get_transient( $key );
    if ( ! is_array( $win ) || empty( $win['t'] ) ) $win = [ 'n' => 0, 't' => time() ];
    if ( $win['n'] >= $max ) return false;
    $win['n']++;
    set_transient( $key, $win, max( 60, HOUR_IN_SECONDS - ( time() - $win['t'] ) ) );
    return true;
}

/* ═══════════════════════════════════════════════════════════
   AJAX — nab_ai_chat
   Accepts the chatbot page's own nonce (nab_ai_chat) or the portal
   nonce every page has (nab_portal_nonce), used by the widget.
   Reply is plain text / simple Markdown; the browser escapes it and
   renders the Markdown (nab-ai.js), so no HTML from the model is trusted.
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_ai_chat', function() {
    $nonce = $_POST['nonce'] ?? '';
    if ( ! wp_verify_nonce( $nonce, 'nab_ai_chat' ) && ! wp_verify_nonce( $nonce, 'nab_portal_nonce' ) ) {
        wp_send_json_error( [ 'reply' => '', 'code' => 'session_expired' ], 403 );
    }
    $uid     = get_current_user_id();
    $message = mb_substr( trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) ), 0, 1000 );
    $page    = mb_substr( sanitize_text_field( wp_unslash( $_POST['page'] ?? '' ) ), 0, 60 );
    $history = json_decode( wp_unslash( $_POST['history'] ?? '[]' ), true );
    if ( ! is_array( $history ) ) $history = [];

    $cfg = nab_portal_ai_config();
    if ( ! $message || ! $cfg['live'] ) wp_send_json_error( [ 'reply' => '' ] );
    if ( ! nab_portal_ai_rate_ok( $uid ) ) wp_send_json_error( [ 'reply' => '', 'code' => 'rate_limited' ] );

    $messages = [ [ 'role' => 'system', 'content' => nab_portal_ai_system_prompt( $uid, $page ) ] ];
    foreach ( array_slice( $history, -10 ) as $h ) {
        $role    = $h['role'] ?? '';
        $content = is_string( $h['content'] ?? null ) ? mb_substr( sanitize_textarea_field( $h['content'] ), 0, 2000 ) : '';
        if ( in_array( $role, [ 'user', 'assistant' ], true ) && $content !== '' ) $messages[] = [ 'role' => $role, 'content' => $content ];
    }
    // The browser includes the new question at the end of the history; don't send it twice.
    $lastMsg = end( $messages );
    if ( ! ( $lastMsg['role'] === 'user' && $lastMsg['content'] === $message ) ) $messages[] = [ 'role' => 'user', 'content' => $message ];

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $cfg['key'],
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model'       => $cfg['model'],
            'max_tokens'  => 700,
            'temperature' => 0.5,
            'messages'    => $messages,
            'user'        => 'nab-member-' . $uid, // lets OpenAI trace abuse per member without any personal data
        ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( 'NAB AI Chat request failed: ' . $response->get_error_message() );
        wp_send_json_error( [ 'reply' => '', 'code' => 'unreachable' ] );
    }
    $http  = wp_remote_retrieve_response_code( $response );
    $body  = json_decode( wp_remote_retrieve_body( $response ), true );
    $reply = trim( (string) ( $body['choices'][0]['message']['content'] ?? '' ) );

    // OpenAI error (expired key, no credits, etc.)
    if ( $reply === '' ) {
        $err      = $body['error']['message'] ?? 'Unknown error';
        $err_code = $body['error']['code']    ?? '';
        error_log( 'NAB AI Chat OpenAI error (HTTP ' . $http . '): ' . $err );
        wp_send_json_error( [ 'reply' => '', 'error' => $err, 'code' => $err_code ] );
    }
    wp_send_json_success( [ 'reply' => wp_strip_all_tags( $reply ), 'format' => 'markdown' ] );
} );

/* ═══════════════════════════════════════════════════════════
   SCRIPT + WIDGET
   ═══════════════════════════════════════════════════════════ */

/** Settings both the page and the widget need, printed before nab-ai.js. */
function nab_portal_ai_js_settings() {
    $uid  = get_current_user_id();
    $user = wp_get_current_user();
    $first = get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ?: '';
    $last  = get_user_meta( $uid, 'nab_last_name', true ) ?: $user->last_name ?: '';
    $tool  = function_exists( 'nab_portal_current_tool' ) ? nab_portal_current_tool() : '';
    $tools = function_exists( 'nab_portal_tools' ) ? nab_portal_tools() : [];
    $nav   = function_exists( 'nab_get_nav_links' ) ? nab_get_nav_links() : [];
    return [
        'ajax'     => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'nab_portal_nonce' ),
        'live'     => nab_portal_ai_config()['live'],
        'user'     => $uid,
        'first'    => $first,
        'initials' => strtoupper( mb_substr( $first ?: 'M', 0, 1 ) . mb_substr( $last, 0, 1 ) ),
        'page'     => $tool && isset( $tools[ $tool ] ) ? preg_replace( '/^NAB /', '', $tools[ $tool ]['name'] ) : '',
        'pageUrl'  => ( $nav['chatbot'] ?? '#' ) !== '#' ? $nav['chatbot'] : ( function_exists( 'nab_get_template_url' ) ? nab_get_template_url( 'nab-chatbot' ) : '#' ),
        'support'  => $nav['support'] ?? '#',
        'prompts'  => nab_portal_ai_prompts(),
        'mascotImg'  => nab_portal_nabby_image_url() !== '',
        'mascotHtml' => nab_portal_nabby_image_url() ? '<img src="' . esc_url( nab_portal_nabby_image_url() ) . '" alt="">' : nab_portal_nabby_svg(),
    ];
}

/** Chat bubble styles shared by the widget and the full page. */
function nab_portal_ai_message_css() {
    ?>
<style>
.nab-ai-msg{display:flex;margin:0 0 12px}
.nab-ai-msg.user{justify-content:flex-end}
.nab-ai-bubble{max-width:86%;padding:10px 13px;border-radius:14px;font-size:13.5px;line-height:1.55;word-wrap:break-word;overflow-wrap:anywhere}
.nab-ai-msg.ai .nab-ai-bubble{background:#fff;border:1px solid var(--aiw-line,#E8EBF0);border-top-left-radius:4px;color:#1F2A3A}
.nab-ai-msg.user .nab-ai-bubble{background:#0D5C9B;color:#fff;border-top-right-radius:4px;white-space:pre-wrap}
.nab-ai-bubble p{margin:0 0 8px}.nab-ai-bubble p:last-child{margin-bottom:0}
.nab-ai-bubble ul,.nab-ai-bubble ol{margin:0 0 8px;padding-left:20px}.nab-ai-bubble li{margin-bottom:3px}
.nab-ai-bubble a{color:#0D5C9B;font-weight:600}
.nab-ai-msg.ai .nab-ai-bubble strong{font-weight:700;color:#0F1B2D}
.nab-ai-bubble h4{font-size:13.5px;margin:0 0 6px}
.nab-ai-bubble.is-error{background:#FFF8E6;border-color:#F5DFA6}
.nab-ai-typing span{display:inline-block;width:6px;height:6px;background:#9AA4B2;border-radius:50%;margin:0 2px;animation:nabAiDot 1.2s infinite}
.nab-ai-typing span:nth-child(2){animation-delay:.2s}.nab-ai-typing span:nth-child(3){animation-delay:.4s}
@keyframes nabAiDot{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-5px)}}
</style>
    <?php
}

/** Should the floating widget show on this request? */
function nab_portal_ai_widget_enabled() {
    if ( ! is_user_logged_in() || is_admin() ) return false;
    $tool = function_exists( 'nab_portal_current_tool' ) ? nab_portal_current_tool() : '';
    if ( ! $tool || $tool === 'nab-chatbot' ) return false; // the chatbot page is the full-size version
    $cfg = nab_portal_ai_config();
    return (bool) apply_filters( 'nab_portal_ai_widget_enabled', $cfg['enabled'], $tool );
}

add_action( 'wp_enqueue_scripts', function() {
    $tool = function_exists( 'nab_portal_current_tool' ) ? nab_portal_current_tool() : '';
    if ( ! is_user_logged_in() || ! $tool ) return;
    if ( $tool !== 'nab-chatbot' && ! nab_portal_ai_widget_enabled() ) return;
    wp_enqueue_script( 'nab-ai', NAB_URL . 'assets/js/nab-ai.js', [], NAB_VERSION, true );
    wp_add_inline_script( 'nab-ai', 'window.nabAiSettings=' . wp_json_encode( nab_portal_ai_js_settings(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) . ';', 'before' );
}, 20 );

add_action( 'wp_footer', function() {
    if ( ! nab_portal_ai_widget_enabled() ) return;
    $s = nab_portal_ai_js_settings();
    ?>
<div class="nab-aiw" id="nabAiw" data-state="closed">
  <button type="button" class="nab-aiw-launch" id="nabAiwLaunch" aria-controls="nabAiwPanel" aria-expanded="false">
    <?php echo nab_portal_nabby_mark( 34, 'nab-aiw-mark' ); ?><span class="nab-nabby-word">Ask Nabby</span>
  </button>
  <section class="nab-aiw-panel" id="nabAiwPanel" role="dialog" aria-label="Nabby, NAB AI Assistant" hidden>
    <header class="nab-aiw-head">
      <?php echo nab_portal_nabby_mark( 38 ); ?>
      <div class="nab-aiw-title">
        <strong>Nabby <span>· NAB AI Assistant</span></strong>
        <small><?php echo $s['live'] ? 'Usually replies in a few seconds' : 'Quick answers to common questions'; ?></small>
      </div>
      <button type="button" class="nab-aiw-hbtn" data-aiw-new title="New chat" aria-label="Start a new chat"><?php echo nab_portal_icon( 'square-pen', 18 ); ?></button>
      <?php if ( $s['pageUrl'] && $s['pageUrl'] !== '#' ) : ?>
      <a class="nab-aiw-hbtn nab-aiw-hide-sm" href="<?php echo esc_url( $s['pageUrl'] ); ?>" title="Open full screen" aria-label="Open the full AI Assistant page"><?php echo nab_portal_icon( 'maximize-2', 17 ); ?></a>
      <?php endif; ?>
      <button type="button" class="nab-aiw-hbtn" data-aiw-close title="Close" aria-label="Close chat"><?php echo nab_portal_icon( 'x', 20 ); ?></button>
    </header>
    <div class="nab-aiw-body" id="nabAiwBody" aria-live="polite">
      <div class="nab-aiw-intro" id="nabAiwIntro">
        <p class="nab-aiw-hello"><?php echo esc_html( $s['first'] ? 'Hi ' . $s['first'] . ', I\'m Nabby!' : 'Hi, I\'m Nabby!' ); ?></p>
        <p class="nab-aiw-sub">How can I help? Ask about your credit score, disputes, cards, budgeting or anything in your portal.</p>
        <div class="nab-aiw-chips">
          <?php foreach ( $s['prompts'] as $p ) : ?>
          <button type="button" class="nab-aiw-chip" data-aiw-q="<?php echo esc_attr( $p['q'] ); ?>"><?php echo esc_html( $p['label'] ); ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="nab-aiw-msgs" id="nabAiwMsgs"></div>
    </div>
    <form class="nab-aiw-form" id="nabAiwForm">
      <textarea id="nabAiwInput" rows="1" maxlength="1000" placeholder="Type your question…" aria-label="Your question"></textarea>
      <button type="submit" class="nab-aiw-send" aria-label="Send"><?php echo nab_portal_icon( 'arrow-up', 18 ); ?></button>
    </form>
    <p class="nab-aiw-foot">General education only, not financial advice.<?php if ( $s['support'] !== '#' ) : ?> <a href="<?php echo esc_url( $s['support'] ); ?>">Talk to a person</a><?php endif; ?></p>
  </section>
</div>
<?php nab_portal_ai_message_css(); ?>
<style>
.nab-aiw{--aiw-blue:#0D5C9B;--aiw-ink:#0F1B2D;--aiw-muted:#6B7685;--aiw-line:#E8EBF0;font-family:inherit;color:var(--aiw-ink)}
.nab-aiw [hidden]{display:none!important}
.nab-aiw-launch{position:fixed;right:24px;bottom:24px;z-index:9990;display:flex;align-items:center;gap:10px;height:52px;padding:0 20px 0 10px;border:0;border-radius:999px;color:#fff;font:inherit;font-size:14.5px;font-weight:650;letter-spacing:.01em;cursor:pointer;
  background:linear-gradient(135deg,#163E70 0%,#0A1F3D 100%);box-shadow:inset 0 0 0 1px rgba(236,200,112,.55),inset 0 1px 0 rgba(255,255,255,.14),0 10px 26px -8px rgba(8,26,51,.65);transition:transform .15s,box-shadow .15s}
.nab-aiw-launch:hover{transform:translateY(-2px);box-shadow:inset 0 0 0 1px rgba(236,200,112,.8),inset 0 1px 0 rgba(255,255,255,.16),0 14px 30px -8px rgba(8,26,51,.7)}
.nab-aiw-launch .nab-nabby{filter:drop-shadow(0 2px 4px rgba(0,0,0,.35))}
.nab-aiw-launch:focus-visible{outline:3px solid rgba(13,92,155,.4);outline-offset:2px}
.nab-aiw[data-state=open] .nab-aiw-launch{display:none}
.nab-aiw-panel{position:fixed;right:24px;bottom:24px;z-index:9991;width:380px;height:min(600px,calc(100vh - 48px));display:flex;flex-direction:column;background:#fff;border:1px solid var(--aiw-line);border-radius:16px;box-shadow:0 18px 50px rgba(15,27,45,.22);overflow:hidden;animation:nabAiwIn .18s ease-out}
@keyframes nabAiwIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
.nab-aiw-head{display:flex;align-items:center;gap:10px;padding:12px 10px 12px 14px;border-bottom:1px solid var(--aiw-line)}
.nab-aiw-title{flex:1;min-width:0;line-height:1.25}
.nab-aiw-title strong{display:block;font-size:15px;font-weight:750}
.nab-aiw-title strong span{font-size:12.5px;font-weight:600;color:var(--aiw-muted)}
.nab-aiw-title small{display:block;font-size:12px;color:var(--aiw-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nab-aiw-hbtn{width:34px;height:34px;border:0;border-radius:9px;background:none;color:var(--aiw-muted);display:flex;align-items:center;justify-content:center;cursor:pointer;flex:none;text-decoration:none}
.nab-aiw-hbtn:hover{background:#F1F3F6;color:var(--aiw-ink)}
.nab-aiw-body{flex:1;overflow-y:auto;padding:16px 14px;overscroll-behavior:contain;background:#FAFBFC}
.nab-aiw-hello{font-size:17px;font-weight:700;margin:4px 0 4px}
.nab-aiw-sub{font-size:13px;color:var(--aiw-muted);margin:0 0 14px;line-height:1.5}
.nab-aiw-chips{display:flex;flex-direction:column;align-items:flex-start;gap:8px}
.nab-aiw-chip{font:inherit;font-size:13px;font-weight:600;color:var(--aiw-blue);background:#fff;border:1px solid #CFE0EF;border-radius:999px;padding:8px 13px;cursor:pointer;text-align:left;line-height:1.3}
.nab-aiw-chip:hover{background:#EEF5FB}
.nab-aiw-form{display:flex;align-items:flex-end;gap:8px;padding:10px 12px;border-top:1px solid var(--aiw-line);background:#fff}
.nab-aiw-form textarea{flex:1;min-height:42px;max-height:120px;resize:none;border:1px solid #D9DEE6;border-radius:12px;padding:10px 12px;font:inherit;font-size:14px;line-height:1.45;color:var(--aiw-ink);outline:none;box-sizing:border-box}
.nab-aiw-form textarea:focus{border-color:var(--aiw-blue);box-shadow:0 0 0 3px rgba(13,92,155,.12)}
.nab-aiw-send{width:42px;height:42px;border:0;border-radius:12px;background:var(--aiw-blue);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;flex:none}
.nab-aiw-send[disabled]{background:#C3CAD5;cursor:default}
.nab-aiw-foot{margin:0;padding:0 14px 10px;font-size:11.5px;color:#9AA4B2;background:#fff}
.nab-aiw-foot a{color:var(--aiw-blue);font-weight:600;text-decoration:none}
@media(max-width:640px){
  .nab-aiw-launch{right:16px;bottom:16px;width:58px;height:58px;padding:0;justify-content:center;border-radius:18px;background:none;box-shadow:0 10px 24px -8px rgba(8,26,51,.7)}
  .nab-aiw-launch .nab-nabby-word{display:none}
  .nab-aiw-launch .nab-nabby{--nabby:58px!important}
  body.nab-has-bottomnav .nab-aiw-launch{display:none} /* dashboard: the bottom bar's Ask AI button opens the chat */
  .nab-aiw-panel{inset:0;width:auto;height:100%;max-height:none;border-radius:0;border:0;animation:none}
  .nab-aiw-hide-sm{display:none}
  .nab-aiw-form textarea{font-size:16px} /* stops iOS zooming into the field */
  .nab-aiw-foot{padding-bottom:calc(10px + env(safe-area-inset-bottom))}
}
@media print{.nab-aiw{display:none}}
</style>
    <?php
}, 5 );
