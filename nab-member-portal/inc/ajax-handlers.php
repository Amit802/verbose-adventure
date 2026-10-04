<?php
/**
 * NAB Member Portal — AJAX Handlers
 *
 * ALL wp_ajax_ actions live here — never inside template files.
 * Template files only output HTML/JS. All data processing is here.
 *
 * Actions registered:
 *   nab_save_credit_score        — Save member's self-reported score
 *   nab_save_utilization_data    — Save up to 5 credit card utilization entries
 *   nab_save_simulation          — Save a score simulation result + history
 *   nab_get_post_content         — AJAX load blog/DIY post in dashboard reader
 *   nab_loanconnect_search       — Server-side call to LoanConnect API v1.4
 *   nab_dismiss_notification     — Handled in notifications.php
 *   nab_dismiss_auto_notification — Dismiss auto-notification from user meta
 *   nab_mp_pause                 — Pause MemberPress subscription (in memberpress.php)
 *   nab_mp_cancel                — Cancel MemberPress subscription (in memberpress.php)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════
   ENQUEUE nabPortal JS object on all portal pages
   Provides ajax URL + nonce to all frontend scripts.
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_enqueue_scripts', 'nab_enqueue_portal_js' );
function nab_enqueue_portal_js() {
    if ( ! is_page() ) return;

    $slug   = get_post_meta( get_the_ID(), '_wp_page_template', true );
    $portal = [
        'nab-dashboard', 'nab-simulator', 'nab-utilization',
        'nab-credit-report', 'nab-dispute-center',
        'nab-loan', 'nab-card-match', 'nab-pad',
        'nab-learning', 'nab-support', 'nab-chatbot',
        // Added — these 3 templates exist in template-loader.php but were
        // missing here, so nabPortal (ajax url + nonce) never loaded on
        // them, causing "Session error. Please refresh the page." alerts.
        'nab-emergency-fund', 'nab-lfp', 'nab-plans',
        'nab-roadmap',
    ];

    if ( ! in_array( $slug, $portal, true ) ) return;

    wp_enqueue_script( 'jquery' );
    // Enqueue dashboard JS as proper file - bypasses Elementor Ember optimizer
    if ( in_array( $slug, ['nab-dashboard'], true ) ) {
        wp_enqueue_script(
            'nab-dashboard',
            NAB_URL . 'assets/js/nab-dashboard.js',
            ['jquery'],
            NAB_VERSION,
            true // load in footer
        );
        // v1.9.0 visual dashboard: Chart.js (bundled locally, no CDN) + charts/forms
        wp_enqueue_script( 'nab-chartjs', NAB_URL . 'assets/js/vendor/chart.umd.min.js', [], '4.5.1', true );
        wp_enqueue_script( 'nab-dashboard-charts', NAB_URL . 'assets/js/nab-dashboard-charts.js', [ 'nab-chartjs', 'nab-dashboard' ], NAB_VERSION, true );
    }
    wp_add_inline_script(
        'jquery',
        'var nabPortal=' . wp_json_encode( [
            'ajax'     => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'nab_portal_nonce' ),
            'siteUrl'  => home_url(),
        ] ) . ';',
        'before'
    );
}

/* ═══════════════════════════════════════════════════════════
   SAVE CREDIT SCORE
   v1.7.8: fixed a mismatched AJAX action name — the dashboard's
   Check-score button was calling 'nab_save_score' (unregistered),
   so nab_credit_score user meta was never actually written. Score
   looked saved (client-side gauge update) but reset on refresh,
   and the Financial Roadmap's "Add Your Credit Score" step could
   never check off as a result. Pre-existing bug, not introduced
   by the Roadmap feature — just surfaced by it.
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_save_credit_score', function () {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );

    $uid   = get_current_user_id();
    $score = intval( $_POST['score'] ?? 0 ); // phpcs:ignore

    if ( ! $uid )                           wp_send_json_error( [ 'message' => 'Not logged in.' ] );
    if ( $score < 300 || $score > 900 )     wp_send_json_error( [ 'message' => 'Score must be 300–900.' ] );

    update_user_meta( $uid, 'nab_credit_score',  $score );
    update_user_meta( $uid, 'nab_score_source',  'self' );
    update_user_meta( $uid, 'nab_score_updated', current_time( 'mysql' ) );
    nab_record_score_history( $uid, $score ); // v1.9.0: feeds the dashboard score chart

    wp_send_json_success( [ 'score' => $score, 'dash' => nab_dash_get_chart_data( $uid ) ] );
} );

/* ═══════════════════════════════════════════════════════════
   SAVE UTILIZATION DATA (up to 5 cards)
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_save_utilization_data', function () {
    if ( ! wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ),
        'nab_save_utilization'
    ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
    }

    $uid = get_current_user_id();
    if ( ! $uid ) wp_send_json_error( [ 'message' => 'Not logged in.' ] );

    for ( $i = 1; $i <= 5; $i++ ) {
        update_user_meta( $uid, "nab_card_{$i}_name",    sanitize_text_field( wp_unslash( $_POST[ "card_name_{$i}" ]    ?? '' ) ) ); // phpcs:ignore
        update_user_meta( $uid, "nab_card_{$i}_balance", abs( floatval( $_POST[ "card_balance_{$i}" ] ?? 0 ) ) ); // phpcs:ignore
        update_user_meta( $uid, "nab_card_{$i}_limit",   abs( floatval( $_POST[ "card_limit_{$i}" ]   ?? 0 ) ) ); // phpcs:ignore
    }

    if ( isset( $_POST['overall_pct'] ) ) { // phpcs:ignore
        update_user_meta( $uid, 'nab_last_utilization_pct', floatval( $_POST['overall_pct'] ) ); // phpcs:ignore
    }

    update_user_meta( $uid, 'nab_utilization_last_saved', current_time( 'mysql' ) );
    wp_send_json_success( [ 'message' => 'Saved successfully.' ] );
} );

add_action( 'wp_ajax_nopriv_nab_save_utilization_data', function () {
    wp_send_json_error( [ 'message' => 'You must be logged in.' ], 401 );
} );

/* ═══════════════════════════════════════════════════════════
   SAVE SIMULATION
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_save_simulation', function () {
    check_ajax_referer( 'nab_sim_nonce', 'nonce' );

    $uid      = get_current_user_id();
    $score    = intval( $_POST['score']    ?? 0 ); // phpcs:ignore
    $result   = intval( $_POST['result']   ?? 0 ); // phpcs:ignore
    $scenario = sanitize_text_field( wp_unslash( $_POST['scenario'] ?? '' ) ); // phpcs:ignore
    $impact   = intval( $_POST['impact']   ?? 0 ); // phpcs:ignore

    if ( $score < 300 || $score > 900 ) wp_send_json_error( [ 'message' => 'Invalid score range.' ] );

    update_user_meta( $uid, 'nab_credit_score',   $score );
    update_user_meta( $uid, 'nab_last_sim_score',  $result );
    nab_record_score_history( $uid, $score ); // v1.9.0: keep dashboard chart in step

    $history = json_decode( get_user_meta( $uid, 'nab_sim_history', true ) ?: '[]', true );
    array_unshift( $history, [
        'score'    => $score,
        'result'   => $result,
        'scenario' => $scenario,
        'impact'   => $impact,
        'date'     => current_time( 'Y-m-d H:i' ),
    ] );
    update_user_meta( $uid, 'nab_sim_history', wp_json_encode( array_slice( $history, 0, 10 ) ) );

    // Fire auto-notification
    nab_add_auto_notification(
        $uid,
        'score',
        'Simulation Result Ready',
        'Your credit score simulation is complete. View your results in the Score Simulator tool.'
    );

    wp_send_json_success( [ 'message' => 'Simulation saved.' ] );
} );

/* ═══════════════════════════════════════════════════════════
   GET POST CONTENT — in-dashboard blog/DIY reader
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_get_post_content', function () {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );

    $post_id = intval( $_POST['post_id'] ?? 0 ); // phpcs:ignore
    if ( ! $post_id ) wp_send_json_error( [ 'message' => 'Invalid post ID.' ] );

    $post = get_post( $post_id );
    if ( ! $post || $post->post_status !== 'publish' || $post->post_type !== 'post' ) {
        wp_send_json_error( [ 'message' => 'Post not found.' ] );
    }

    $thumb   = get_the_post_thumbnail_url( $post_id, 'large' );
    $cats    = get_the_category( $post_id );
    $cat_lbl = ! empty( $cats ) ? esc_html( $cats[0]->name ) : 'Blog';
    $date    = get_the_date( 'F j, Y', $post );
    $author  = get_the_author_meta( 'display_name', $post->post_author );
    $content = apply_filters( 'the_content', $post->post_content );

    // Strip the featured image if it appears at the top of the content
    // Avoids the image showing twice (once in nab-article-thumb, once in content)
    if ( $thumb ) {
        $filename = basename( wp_parse_url( $thumb, PHP_URL_PATH ) );
        $content  = preg_replace( '/<figure[^>]*>.*?' . preg_quote( $filename, '/' ) . '.*?<\/figure>/is', '', $content, 1 );
        $content  = preg_replace( '/<p[^>]*>\s*<img[^>]*' . preg_quote( $filename, '/' ) . '[^>]*>\s*<\/p>/is', '', $content, 1 );
        $content  = preg_replace( '/<img[^>]*' . preg_quote( $filename, '/' ) . '[^>]*>/is', '', $content, 1 );
    }

    $content = wp_kses_post( $content );

    $html  = '<div class="nab-article-cat">' . $cat_lbl . '</div>';
    $html .= '<div class="nab-article-title">' . esc_html( get_the_title( $post ) ) . '</div>';
    $html .= '<div class="nab-article-meta">';
    $html .= '<span>' . esc_html( $date ) . '</span>';
    if ( $author ) {
        $html .= '<span>' . esc_html( $author ) . '</span>';
    }
    $html .= '</div>';
    if ( $thumb ) {
        $html .= '<img class="nab-article-thumb" src="' . esc_url( $thumb ) . '" alt="' . esc_attr( get_the_title( $post ) ) . '" loading="lazy">';
    }
    $html .= '<div class="nab-article-body">' . $content . '</div>';

    wp_send_json_success( [ 'html' => $html ] );
} );

/* ═══════════════════════════════════════════════════════════
   LOANCONNECT AUTO LOAN SEARCH
   API: POST https://loanconnect.ca/api/submit-application (v1.4)
   Loan type hardcoded = 8 (Buy a Car) per client requirement.
   Credentials stored in ACF on Dashboard page — never in code.
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_loanconnect_search', function () {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );

    $uid = get_current_user_id();
    if ( ! $uid ) wp_send_json_error( [ 'message' => 'Not logged in.' ] );

    // ── Sanitize — field names match LoanConnect API v1.4 spec exactly ──
    $firstname          = sanitize_text_field( wp_unslash( $_POST['firstname']          ?? '' ) ); // phpcs:ignore
    $lastname           = sanitize_text_field( wp_unslash( $_POST['lastname']           ?? '' ) ); // phpcs:ignore
    $email              = sanitize_email( wp_unslash( $_POST['email']                   ?? '' ) ); // phpcs:ignore
    $phone_raw          = sanitize_text_field( wp_unslash( $_POST['phone']              ?? '' ) ); // phpcs:ignore
    $dob                = sanitize_text_field( wp_unslash( $_POST['dob']                ?? '' ) ); // phpcs:ignore  YYYY-MM-DD
    $citizenship_status = sanitize_text_field( wp_unslash( $_POST['citizenship_status'] ?? '1' ) ); // phpcs:ignore
    $address            = sanitize_text_field( wp_unslash( $_POST['address']            ?? '' ) ); // phpcs:ignore
    $city               = sanitize_text_field( wp_unslash( $_POST['city']               ?? '' ) ); // phpcs:ignore
    $province           = sanitize_text_field( wp_unslash( $_POST['province']           ?? '' ) ); // phpcs:ignore  2-letter
    $pc                 = strtoupper( preg_replace( '/\s+/', '', wp_unslash( $_POST['pc'] ?? '' ) ) ); // phpcs:ignore
    $housing_status     = sanitize_text_field( wp_unslash( $_POST['housing_status']     ?? '2' ) ); // phpcs:ignore
    $rent_payment       = abs( intval( $_POST['rent_payment']      ?? 0 ) ); // phpcs:ignore
    $employment_status  = sanitize_text_field( wp_unslash( $_POST['employment_status']  ?? '1' ) ); // phpcs:ignore
    $income             = abs( intval( $_POST['income']            ?? 0 ) ); // phpcs:ignore  ANNUAL
    $monthly_payment    = abs( intval( $_POST['monthly_payment']   ?? 0 ) ); // phpcs:ignore
    $credit_score       = sanitize_text_field( wp_unslash( $_POST['credit_score']       ?? '3' ) ); // phpcs:ignore  1-4 scale
    $amount             = abs( intval( $_POST['amount']            ?? 0 ) ); // phpcs:ignore
    $p_and_c            = sanitize_text_field( wp_unslash( $_POST['p_and_c']            ?? '0' ) ); // phpcs:ignore

    // ── Validation ────────────────────────────────────────
    if ( ! $firstname || ! $lastname || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Please complete all required fields.' ] );
    }

    $phone_digits = preg_replace( '/\D/', '', $phone_raw );
    if ( strlen( $phone_digits ) < 10 ) {
        wp_send_json_error( [ 'message' => 'Please enter a valid 10-digit phone number.' ] );
    }
    // Format as 555-555-5555 per LoanConnect spec
    $phone = substr( $phone_digits, 0, 3 ) . '-' . substr( $phone_digits, 3, 3 ) . '-' . substr( $phone_digits, 6, 4 );

    if ( $amount < 1000 || $amount > 75000 ) {
        wp_send_json_error( [ 'message' => 'Loan amount must be between $1,000 and $75,000.' ] );
    }

    // ── Load credentials from ACF on Dashboard page ───────
    $dash_pages  = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dashboard', 'number' => 1 ] );
    $did         = ! empty( $dash_pages ) ? $dash_pages[0]->ID : null;
    $lc_affid    = $did ? ( get_field( 'nab_lc_affid', $did ) ?: '' ) : '';
    $lc_key      = $did ? ( get_field( 'nab_lc_key',   $did ) ?: '' ) : '';
    $decline_url = $did ? nab_resolve_url( get_field( 'nab_link_booking', $did ) ) : '#';

    // wp-config.php constants as fallback
    if ( ! $lc_affid && defined( 'NAB_LC_AFFID' ) ) $lc_affid = NAB_LC_AFFID;
    if ( ! $lc_key   && defined( 'NAB_LC_KEY' )   ) $lc_key   = NAB_LC_KEY;

    if ( ! $lc_affid || ! $lc_key ) {
        wp_send_json_error( [ 'message' => 'The Auto Loan Tool is not yet configured. Please contact support.' ] );
    }

    // ── Sandbox vs Live endpoint ──────────────────────────
    $sandbox  = ( defined( 'NAB_LC_SANDBOX' ) && NAB_LC_SANDBOX );
    $base_url = $sandbox
        ? 'https://sandbox.loanconnect.ca/api/submit-application'
        : 'https://loanconnect.ca/api/submit-application';

    // ── Build payload — exact field names from API v1.4 spec ─
    $payload = [
        'affid'              => $lc_affid,
        'key'                => $lc_key,
        'type'               => 8,           // HARDCODED: Buy a Car
        'terms'              => 1,           // Consent captured via checkbox in form
        'subscriber'         => 0,           // Do not subscribe member to LC marketing
        'newsletter'         => 0,
        'ext_pid'            => 'nabuid_' . $uid, // Track lead in LC reporting
        'firstname'          => $firstname,
        'lastname'           => $lastname,
        'email'              => $email,
        'phone'              => $phone,
        'dob'                => $dob,        // YYYY-MM-DD accepted by v1.4
        'citizenship_status' => $citizenship_status,
        'address'            => $address,
        'city'               => $city,
        'province'           => $province,
        'pc'                 => $pc,
        'housing_status'     => $housing_status,
        'rent_payment'       => $rent_payment,
        'employment_status'  => $employment_status,
        'income'             => $income,     // ANNUAL income as per spec
        'monthly_payment'    => $monthly_payment,
        'credit_score'       => $credit_score, // 1=Excellent 2=Good 3=Fair 4=Poor
        'amount'             => $amount,
        'p_and_c'            => $p_and_c,
    ];

    // ── POST to LoanConnect API ───────────────────────────
    $response = wp_remote_post( $base_url, [
        'timeout'     => 30,
        'headers'     => [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept'       => 'application/json',
        ],
        'body'        => $payload,  // wp_remote_post with array = form-encoded
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( 'NAB LoanConnect WP_Error: ' . $response->get_error_message() );
        wp_send_json_error( [ 'message' => 'Could not reach LoanConnect. Please try again in a moment.' ] );
    }

    $http_code   = wp_remote_retrieve_response_code( $response );
    $body        = wp_remote_retrieve_body( $response );
    $data        = json_decode( $body, true );
    $offers      = [];
    $api_message = '';

    // ── Parse response per API v1.4 spec ─────────────────
    // Success: { "status": true, "result": [...], "client_id": "..." }
    // Failure: { "status": false, "messages": "error text", "result": [] }
    if ( is_array( $data ) ) {
        if ( ! empty( $data['result'] ) && is_array( $data['result'] ) ) {
            $offers = $data['result'];
        }
        if ( ! empty( $data['messages'] ) ) {
            $api_message = sanitize_text_field( $data['messages'] );
        }
        if ( isset( $data['status'] ) && false === $data['status'] ) {
            error_log( 'NAB LoanConnect status=false. HTTP ' . $http_code . '. Message: ' . $api_message );
        }
    }

    // ── Audit log (no PII stored) ─────────────────────────
    $log     = [
        'ts'           => current_time( 'mysql' ),
        'amount'       => $amount,
        'province'     => $province,
        'offers_count' => count( $offers ),
        'http'         => $http_code,
        'sandbox'      => $sandbox,
    ];
    $history = json_decode( get_user_meta( $uid, 'nab_loan_history', true ) ?: '[]', true );
    array_unshift( $history, $log );
    update_user_meta( $uid, 'nab_loan_history', wp_json_encode( array_slice( $history, 0, 10 ) ) );

    // ── Auto-notification ─────────────────────────────────
    $notif_msg = count( $offers ) > 0
        ? 'Your loan search found ' . count( $offers ) . ' offer(s). View your results in the Auto Loan Tool.'
        : 'Your loan search is complete. No offers matched right now — book a specialist session for personalised help.';
    // Only add loan notification if not already added in last hour
    nab_add_auto_notification( $uid, 'loan', 'Auto Loan Results Ready', $notif_msg, 3600 );

    wp_send_json_success( [
        'offers'      => $offers,
        'decline_url' => $decline_url,
        'api_message' => $api_message,
    ] );
} );

/* ═══════════════════════════════════════════════════════════
   AUTO-NOTIFICATION CREATOR — used by all portal events
   Stores up to 20 notifications per member in user meta.
   Deduplicates by title within 24 hours to prevent flooding.
   ═══════════════════════════════════════════════════════════ */
if ( ! function_exists( 'nab_add_auto_notification' ) ) {
    function nab_add_auto_notification( $user_id, $type, $title, $message, $dedup_seconds = DAY_IN_SECONDS ) {
        if ( ! $user_id ) return;

        $meta   = 'nab_auto_notifications';
        $notifs = json_decode( get_user_meta( $user_id, $meta, true ) ?: '[]', true );

        // Deduplicate: skip if same title was added in the last 24 hours
        $cutoff = time() - $dedup_seconds;
        foreach ( $notifs as $n ) {
            if (
                isset( $n['title'], $n['ts'] ) &&
                $n['title'] === $title &&
                $n['ts'] > $cutoff
            ) {
                return; // already have this notification — skip
            }
        }

        array_unshift( $notifs, [
            'id'      => 'auto_' . wp_generate_uuid4(),
            'type'    => sanitize_key( $type ),
            'title'   => sanitize_text_field( $title ),
            'message' => sanitize_text_field( $message ),
            'ts'      => time(),
        ] );

        update_user_meta(
            $user_id,
            $meta,
            wp_json_encode( array_slice( array_values( $notifs ), 0, 20 ) )
        );
    }
}

/* ═══════════════════════════════════════════════════════════
   AUTO-ASSIGN MEMBER ID ON WP REGISTRATION (non-MemberPress)
   MemberPress signup handled separately in memberpress.php.
   ═══════════════════════════════════════════════════════════ */
add_action( 'user_register', function ( $uid ) {
    if ( ! get_user_meta( $uid, 'nab_member_id', true ) ) {
        update_user_meta( $uid, 'nab_member_id', 'NAB-' . str_pad( $uid, 4, '0', STR_PAD_LEFT ) );
    }
    // Welcome notification for non-MemberPress registrations
    nab_add_auto_notification(
        $uid,
        'info',
        'Welcome to NAB Solutions!',
        'Your member portal is ready. Enter your credit score on the dashboard and explore your tools to get started.'
    );
} );

/* ═══════════════════════════════════════════════════════════════════
   AI CHATBOT — nab_ai_chat
   Handles chat messages from the AI Assistant page and Card Finder.
   Uses OpenAI key + model from ACF on Dashboard page.
   ═══════════════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_ai_chat', function() {
    check_ajax_referer( 'nab_ai_chat', 'nonce' );

    $message = sanitize_text_field( $_POST['message'] ?? '' );
    $history = json_decode( stripslashes( $_POST['history'] ?? '[]' ), true );
    if ( ! is_array( $history ) ) $history = [];

    $dash_pages = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dashboard', 'number' => 1 ] );
    $dash_id    = ! empty( $dash_pages ) ? $dash_pages[0]->ID : 0;
    $key        = $dash_id ? get_field( 'nab_openai_key',   $dash_id ) : '';
    $model      = $dash_id ? ( get_field( 'nab_ai_model',   $dash_id ) ?: 'gpt-4o-mini' ) : 'gpt-4o-mini';
    $enabled    = $dash_id ? get_field( 'nab_ai_enabled',   $dash_id ) : true;

    if ( ! $message || ! $key || ! $enabled ) {
        wp_send_json_error( [ 'reply' => '' ] );
        return;
    }

    $uid   = get_current_user_id();
    $score = (int) get_user_meta( $uid, 'nab_credit_score', true );
    $goal  = get_user_meta( $uid, 'nab_credit_goal', true );
    $track = get_user_meta( $uid, 'nab_track', true );
    $tier  = function_exists( 'nab_get_member_tier' ) ? nab_get_member_tier( $uid ) : '';

    $system = "You are the NAB Credit Assistant, an expert AI credit advisor inside the NAB Solutions member portal for Canadian members.\n\n" .
        ( function_exists( 'nab_get_ai_knowledge_base' ) ? nab_get_ai_knowledge_base() . "\n\n" : "" ) .
        "MEMBER CONTEXT: " .
        ( $score ? "This member's credit score is {$score}. " : "" ) .
        ( $goal  ? "Their credit goal: {$goal}. "             : "" ) .
        ( $track ? "Their education track: {$track}. "        : "" ) .
        ( $tier  ? "They are on the {$tier} plan. "           : "" ) .
        "\n\nKeep responses helpful, clear, and under 150 words unless detail is specifically requested. Be encouraging. Only discuss credit, finance, and NAB portal topics. Always recommend verifying important decisions with a licensed professional.";

    $messages_arr = [ [ 'role' => 'system', 'content' => $system ] ];
    foreach ( array_slice( $history, -8 ) as $h ) {
        if ( in_array( $h['role'] ?? '', [ 'user', 'assistant' ] ) && ! empty( $h['content'] ) ) {
            $messages_arr[] = [ 'role' => $h['role'], 'content' => sanitize_text_field( $h['content'] ) ];
        }
    }
    $messages_arr[] = [ 'role' => 'user', 'content' => $message ];

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 25,
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode( [
            'model'       => $model,
            'max_tokens'  => 350,
            'temperature' => 0.7,
            'messages'    => $messages_arr,
        ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'reply' => '' ] );
        return;
    }

    $http  = wp_remote_retrieve_response_code( $response );
    $body  = json_decode( wp_remote_retrieve_body( $response ), true );
    $reply = $body['choices'][0]['message']['content'] ?? '';

    // If OpenAI returned an error (expired key, quota etc)
    if ( empty( $reply ) ) {
        $err      = $body['error']['message'] ?? 'Unknown error';
        $err_code = $body['error']['code']    ?? '';
        error_log( 'NAB AI Chat OpenAI error (HTTP '.$http.'): ' . $err );
        wp_send_json_error( [ 'reply' => '', 'error' => $err, 'code' => $err_code ] );
        return;
    }

    wp_send_json_success( [ 'reply' => wp_kses_post( $reply ) ] );
} );

/* ═══════════════════════════════════════════════════════════════════
   CREDIT CARD ASSISTANT — nab_card_assistant
   Used by the Credit Card Finder results page chatbox.
   ═══════════════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_card_assistant', function() {
    check_ajax_referer( 'nab_card_assistant', 'nonce' );

    $q   = sanitize_text_field( $_POST['question'] ?? '' );
    $ctx = sanitize_textarea_field( $_POST['context'] ?? '' );

    $dash_pages = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dashboard', 'number' => 1 ] );
    $dash_id    = ! empty( $dash_pages ) ? $dash_pages[0]->ID : 0;
    $key        = $dash_id ? get_field( 'nab_openai_key', $dash_id ) : '';
    $model      = $dash_id ? ( get_field( 'nab_ai_model', $dash_id ) ?: 'gpt-4o-mini' ) : 'gpt-4o-mini';

    if ( ! $q || ! $key ) {
        wp_send_json_error( [ 'reply' => '' ] );
        return;
    }

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode( [
            'model'      => $model,
            'max_tokens' => 250,
            'messages'   => [
                [ 'role' => 'system', 'content' => 'You are the NAB Credit Assistant, a helpful Canadian credit card advisor inside the NAB Solutions member portal. ' . $ctx . ' Keep answers under 100 words. Be specific and practical. Only discuss Canadian credit cards and credit topics.' ],
                [ 'role' => 'user',   'content' => $q ],
            ],
        ] ),
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'reply' => '' ] );
        return;
    }

    $http  = wp_remote_retrieve_response_code( $response );
    $body  = json_decode( wp_remote_retrieve_body( $response ), true );
    $reply = $body['choices'][0]['message']['content'] ?? '';

    // If OpenAI returned an error (expired key, quota etc)
    if ( empty( $reply ) ) {
        $err      = $body['error']['message'] ?? 'Unknown error';
        $err_code = $body['error']['code']    ?? '';
        error_log( 'NAB AI Chat OpenAI error (HTTP '.$http.'): ' . $err );
        wp_send_json_error( [ 'reply' => '', 'error' => $err, 'code' => $err_code ] );
        return;
    }

    wp_send_json_success( [ 'reply' => wp_kses_post( $reply ) ] );
} );

/* ═══════════════════════════════════════════════════════════════════
   FRESHDESK — SUBMIT TICKET
   ═══════════════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_freshdesk_submit', function() {
    // Prevent any caching of this AJAX response
    nocache_headers();
    $nonce = $_POST['nonce'] ?? '';
    if ( ! wp_verify_nonce( $nonce, 'nab_freshdesk_action' ) && ! wp_verify_nonce( $nonce, 'nab_portal_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed. Please refresh the page and try again.' ] );
        return;
    }

    $dash_pages = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dashboard', 'number' => 1 ] );
    $dash_id    = ! empty( $dash_pages ) ? $dash_pages[0]->ID : 0;
    $fd_key     = $dash_id ? get_field( 'nab_freshdesk_key',    $dash_id ) : '';
    $fd_domain  = $dash_id ? get_field( 'nab_freshdesk_domain', $dash_id ) : '';

    // Fallback to hardcoded credentials if ACF not configured yet
    if ( ! $fd_key )    $fd_key    = '0D70ZUpg0Ka68uSDsDk_';
    if ( ! $fd_domain ) $fd_domain = 'nabsolutions.freshdesk.com';

    $first    = sanitize_text_field( $_POST['first']    ?? '' );
    $last     = sanitize_text_field( $_POST['last']     ?? '' );
    $email    = sanitize_email(      $_POST['email']    ?? '' );
    $category = sanitize_text_field( $_POST['category'] ?? '' );
    $subject  = sanitize_text_field( $_POST['subject']  ?? '' );
    $message  = sanitize_textarea_field( $_POST['message'] ?? '' );

    if ( ! $first || ! $last || ! $email || ! $category || ! $subject || ! $message ) {
        wp_send_json_error( [ 'message' => 'Please fill in all fields.' ] );
        return;
    }

    $uid  = get_current_user_id();
    $body = [
        'name'        => trim( "$first $last" ),
        'email'       => $email,
        'subject'     => "[$category] $subject",
        'description' => nl2br( esc_html( $message ) ),
        'status'      => 2, // Open
        'priority'    => 1, // Low
        'tags'        => [ 'nab-portal', strtolower( str_replace( ' ', '-', $category ) ) ],
    ];

    $response = wp_remote_post( 'https://' . $fd_domain . '/api/v2/tickets', [
        'timeout' => 20,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Basic ' . base64_encode( $fd_key . ':X' ),
        ],
        'body' => json_encode( $body ),
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => 'Could not connect to support system. Please try again.' ] );
        return;
    }

    $http   = wp_remote_retrieve_response_code( $response );
    $result = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $http === 201 && ! empty( $result['id'] ) ) {
        // Add portal notification
        if ( function_exists( 'nab_add_auto_notification' ) ) {
            nab_add_auto_notification( $uid, 'support', '🎫 Ticket Submitted', "Your ticket #{$result['id']} has been submitted. We'll respond within 1-2 business days." );
        }
        wp_send_json_success( [ 'ticket_id' => $result['id'] ] );
    } else {
        $err = ! empty( $result['description'] ) ? $result['description'] : ( ! empty( $result['message'] ) ? $result['message'] : 'Failed to submit ticket. Please try again. (Code: ' . $http . ')' );
        wp_send_json_error( [ 'message' => $err ] );
    }
} );

/* ═══════════════════════════════════════════════════════════════════
   FRESHDESK — GET MEMBER TICKETS
   ═══════════════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_freshdesk_tickets', function() {
    if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'nab_freshdesk_action' ) && ! wp_verify_nonce( $_POST['nonce'] ?? '', 'nab_portal_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
        return;
    }

    $dash_pages = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dashboard', 'number' => 1 ] );
    $dash_id    = ! empty( $dash_pages ) ? $dash_pages[0]->ID : 0;
    $fd_key     = $dash_id ? get_field( 'nab_freshdesk_key',    $dash_id ) : '';
    $fd_domain  = $dash_id ? get_field( 'nab_freshdesk_domain', $dash_id ) : '';

    // Fallback to hardcoded credentials if ACF not configured yet
    if ( ! $fd_key )    $fd_key    = '0D70ZUpg0Ka68uSDsDk_';
    if ( ! $fd_domain ) $fd_domain = 'nabsolutions.freshdesk.com';

    $email    = get_userdata( get_current_user_id() )->user_email;
    $response = wp_remote_get( 'https://' . $fd_domain . '/api/v2/tickets?email=' . urlencode( $email ) . '&per_page=20&order_by=created_at&order_type=desc', [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode( $fd_key . ':X' ),
            'Content-Type'  => 'application/json',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [ 'message' => 'Could not load tickets.' ] );
        return;
    }

    $http    = wp_remote_retrieve_response_code( $response );
    $tickets = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $http === 200 ) {
        wp_send_json_success( [ 'tickets' => is_array( $tickets ) ? $tickets : [] ] );
    } else {
        wp_send_json_error( [ 'message' => 'Could not load tickets. Please try again.' ] );
    }
} );

/* ═══════════════════════════════════════════════════════════
   NONCE REFRESH — nab_get_nonce
   Provides fresh nonces for cached pages
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_get_nonce', function() {
    nocache_headers();
    $type  = sanitize_text_field( $_GET['type'] ?? 'nab_freshdesk_action' );
    $allowed = [ 'nab_freshdesk_action', 'nab_ai_chat', 'nab_card_assistant' ];
    if ( ! in_array( $type, $allowed, true ) ) {
        wp_send_json_error();
        return;
    }
    wp_send_json_success( [ 'nonce' => wp_create_nonce( $type ) ] );
} );
add_action( 'wp_ajax_nopriv_nab_get_nonce', function() {
    nocache_headers();
    wp_send_json_success( [ 'nonce' => wp_create_nonce( sanitize_text_field( $_GET['type'] ?? '' ) ) ] );
} );

/* ═══════════════════════════════════════════════════════════
   EDUCATION — COMPLETE MODULE
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_complete_module', function() {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );
    $uid = get_current_user_id();
    if ( ! $uid ) { wp_send_json_error(); return; }

    $module_id = (int) ( $_POST['module_id'] ?? 0 );
    if ( ! $module_id || $module_id < 1 || $module_id > 7 ) {
        wp_send_json_error( [ 'message' => 'Invalid module.' ] );
        return;
    }

    // Get existing completed modules
    $completed = json_decode( get_user_meta( $uid, 'nab_modules_completed', true ) ?: '[]', true );
    if ( ! is_array( $completed ) ) $completed = [];

    if ( ! in_array( $module_id, $completed ) ) {
        $completed[] = $module_id;
        update_user_meta( $uid, 'nab_modules_completed', json_encode( $completed ) );

        // Award points
        $points = (int) get_user_meta( $uid, 'nab_points', true );
        update_user_meta( $uid, 'nab_points', $points + 10 );

        // Update streak
        $last_activity = get_user_meta( $uid, 'nab_last_module_date', true );
        $today         = date( 'Y-m-d' );
        $yesterday     = date( 'Y-m-d', strtotime( '-1 day' ) );
        $streak        = (int) get_user_meta( $uid, 'nab_streak', true );
        if ( $last_activity === $yesterday ) {
            update_user_meta( $uid, 'nab_streak', $streak + 1 );
        } elseif ( $last_activity !== $today ) {
            update_user_meta( $uid, 'nab_streak', 1 );
        }
        update_user_meta( $uid, 'nab_last_module_date', $today );

        // Level up check
        $total_completed = count( $completed );
        $level = $total_completed >= 7 ? 'gold' : ( $total_completed >= 4 ? 'silver' : 'bronze' );
        update_user_meta( $uid, 'nab_level', $level );

        // Add notification
        if ( function_exists( 'nab_add_auto_notification' ) ) {
            nab_add_auto_notification( $uid, 'education', '🎓 Module Complete!', "You completed module #{$module_id} and earned 10 points. Keep going!" );
        }
    }

    wp_send_json_success( [ 'completed' => count($completed), 'total' => 7, 'points' => (int)get_user_meta($uid,'nab_points',true) ] );
} );

/* ═══════════════════════════════════════════════════════════
   REST API: Create Member from Gravity Forms signup
   Called by nabsolutions.ca after form submission
   ═══════════════════════════════════════════════════════════ */
add_action( 'rest_api_init', function() {
    register_rest_route( 'nab/v1', '/create-member', [
        'methods'             => 'POST',
        'callback'            => 'nab_rest_create_member',
        'permission_callback' => 'nab_rest_verify_secret',
    ]);
});

function nab_rest_verify_secret( $request ) {
    $secret = $request->get_header( 'X-NAB-Secret' );
    return $secret === 'nab_portal_secret_2024';
}

function nab_rest_create_member( $request ) {
    $params     = $request->get_json_params();
    $email      = sanitize_email( $params['email'] ?? '' );
    $username   = sanitize_user( $params['username'] ?? '' );
    $password   = $params['password'] ?? wp_generate_password( 10, false );
    $first_name = sanitize_text_field( $params['first_name'] ?? '' );
    $last_name  = sanitize_text_field( $params['last_name'] ?? '' );
    $phone      = sanitize_text_field( $params['phone'] ?? '' );
    $dob        = sanitize_text_field( $params['dob'] ?? '' );

    if ( empty( $email ) ) {
        return new WP_REST_Response( [ 'success' => false, 'message' => 'Email required' ], 400 );
    }

    // Check if user already exists
    $existing = get_user_by( 'email', $email );
    if ( $existing ) {
        return new WP_REST_Response( [
            'success'  => true,
            'message'  => 'User already exists',
            'user_id'  => $existing->ID,
            'username' => $existing->user_login,
            'existing' => true,
        ], 200 );
    }

    // Ensure unique username — keep trying until unique
    $base_username = $username;
    while ( username_exists( $username ) ) {
        $username = $base_username . rand( 10, 99 );
    }

    // Create the user
    $user_id = wp_create_user( $username, $password, $email );

    if ( is_wp_error( $user_id ) ) {
        return new WP_REST_Response( [
            'success' => false,
            'message' => $user_id->get_error_message(),
        ], 400 );
    }

    // Set user meta
    wp_update_user([
        'ID'         => $user_id,
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'role'       => 'subscriber',
    ]);

    $address     = sanitize_text_field( $params['address']     ?? '' );
    $bank_name   = sanitize_text_field( $params['bank_name']   ?? '' );
    $institution = sanitize_text_field( $params['institution'] ?? '' );
    $transit     = sanitize_text_field( $params['transit']     ?? '' );
    $account     = sanitize_text_field( $params['account']     ?? '' );

    update_user_meta( $user_id, 'nab_phone',        $phone );
    update_user_meta( $user_id, 'nab_dob',          $dob );
    update_user_meta( $user_id, 'nab_address',      $address );
    update_user_meta( $user_id, 'nab_bank_name',    $bank_name );
    update_user_meta( $user_id, 'nab_institution',  $institution );
    update_user_meta( $user_id, 'nab_transit',      $transit );
    update_user_meta( $user_id, 'nab_account',      $account );
    update_user_meta( $user_id, 'nab_member_since', date( 'd M Y' ) );
    update_user_meta( $user_id, 'nab_points',       0 );
    update_user_meta( $user_id, 'nab_temp_password', $password );

    // Auto-generate member ID
    $member_id = 'NAB-' . str_pad( $user_id, 4, '0', STR_PAD_LEFT );
    update_user_meta( $user_id, 'nab_member_id', $member_id );

    return new WP_REST_Response( [
        'success'   => true,
        'message'   => 'Member created',
        'user_id'   => $user_id,
        'member_id' => $member_id,
        'username'  => $username,
    ], 201 );
}

/* ═══════════════════════════════════════════════════════════════════
   EMERGENCY FUND PLANNER — save member inputs (monthly amount, goal,
   current saved) to user meta. Client spec (Matt, v1.7.4): member
   enters monthly savings + goal, tool shows progress bar + milestone
   messages at 25/50/75/100%, plus a static savings tips section.
   ═══════════════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_save_emergency_fund', function() {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );

    $uid     = get_current_user_id();
    $monthly = max( 0, (float) ( $_POST['monthly'] ?? 0 ) );
    $goal    = max( 0, (float) ( $_POST['goal']    ?? 0 ) );
    $saved   = max( 0, (float) ( $_POST['saved']   ?? 0 ) );

    update_user_meta( $uid, 'nab_ef_monthly', $monthly );
    update_user_meta( $uid, 'nab_ef_goal',    $goal );
    update_user_meta( $uid, 'nab_ef_saved',   $saved );

    $percent = $goal > 0 ? min( 100, round( ( $saved / $goal ) * 100 ) ) : 0;
    $months  = ( $monthly > 0 && $goal > $saved ) ? ceil( ( $goal - $saved ) / $monthly ) : 0;

    wp_send_json_success( [
        'percent' => $percent,
        'months'  => $months,
    ] );
} );
