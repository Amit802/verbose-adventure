<?php
/**
 * NAB Member Portal — Visual Dashboard data (v1.9.0)
 *
 * Member-entered data behind the dashboard charts, plus the single
 * source of truth for the "Member Since" date.
 *
 * User meta (all JSON, per member):
 *   nab_score_history     — [ { d: 'Y-m-d', s: 300–900 } ]  one entry per day, oldest first
 *   nab_cashflow          — { 'Y-m': { inc: float, exp: { category: float } } }
 *   nab_accounts          — [ { id, name, type, bal } ]
 *   nab_networth_history  — { 'Y-m': float }  snapshot taken whenever accounts change
 *
 * Read-only sources reused from existing tools (never written here):
 *   Utilization Checker → nab_card_{1-5}_name/_balance/_limit, nab_last_utilization_pct
 *   Emergency Fund      → nab_ef_goal, nab_ef_saved, nab_ef_monthly
 *
 * AJAX actions (all require nab_portal_nonce + logged in):
 *   nab_dash_score_add / nab_dash_score_delete
 *   nab_dash_cashflow_save / nab_dash_cashflow_delete
 *   nab_dash_account_save / nab_dash_account_delete
 * Every action responds with the full chart data so the page re-renders
 * from one consistent snapshot.
 *
 * @package NAB_Member_Portal
 * @since   1.9.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════
   MEMBER SINCE — the member's WordPress registration date,
   shown in the site's timezone. Used by every page that shows
   a join date (Dashboard, Simulator, PAD, Account) so they can
   never disagree again.
   Replaces: get_the_date( 'Y-m-d', $user_id ) — that call treats
   the user ID as a POST ID and returned an unrelated page's date.
   ═══════════════════════════════════════════════════════════ */
if ( ! function_exists( 'nab_get_member_since' ) ) {
    function nab_get_member_since( $user_id, $format = 'M j, Y' ) {
        $user = get_userdata( (int) $user_id );
        if ( ! $user || empty( $user->user_registered ) || strpos( $user->user_registered, '0000' ) === 0 ) return '';
        $ts = strtotime( $user->user_registered . ' +0000' ); // stored in UTC
        return $ts ? wp_date( $format, $ts ) : '';
    }
}

/* ═══════════════════════════════════════════════════════════
   CATALOGUES
   ═══════════════════════════════════════════════════════════ */
function nab_dash_expense_categories() {
    return [
        'housing'   => [ 'label' => 'Housing & Rent',     'color' => '#0D5C9B' ],
        'transport' => [ 'label' => 'Transportation',     'color' => '#F97316' ],
        'food'      => [ 'label' => 'Food & Groceries',   'color' => '#22A06B' ],
        'utilities' => [ 'label' => 'Bills & Utilities',  'color' => '#8B5CF6' ],
        'debt'      => [ 'label' => 'Debt Payments',      'color' => '#E5484D' ],
        'shopping'  => [ 'label' => 'Shopping',           'color' => '#EAB308' ],
        'health'    => [ 'label' => 'Health & Insurance', 'color' => '#06B6D4' ],
        'other'     => [ 'label' => 'Other',              'color' => '#94A3B8' ],
    ];
}

function nab_dash_account_types() {
    return [
        'cash'        => [ 'label' => 'Cash & Bank',     'group' => 'asset' ],
        'investment'  => [ 'label' => 'Investments',     'group' => 'asset' ],
        'property'    => [ 'label' => 'Real Estate',     'group' => 'asset' ],
        'vehicle'     => [ 'label' => 'Vehicle',         'group' => 'asset' ],
        'other_asset' => [ 'label' => 'Other Asset',     'group' => 'asset' ],
        'credit_card' => [ 'label' => 'Credit Card',     'group' => 'debt'  ],
        'loan'        => [ 'label' => 'Loan',            'group' => 'debt'  ],
        'mortgage'    => [ 'label' => 'Mortgage',        'group' => 'debt'  ],
        'other_debt'  => [ 'label' => 'Other Debt',      'group' => 'debt'  ],
    ];
}

/* ── small helpers ─────────────────────────────────────── */
function nab_dash_json_meta( $uid, $key ) {
    $v = json_decode( (string) get_user_meta( $uid, $key, true ), true );
    return is_array( $v ) ? $v : [];
}
function nab_dash_money( $v ) {
    return round( min( 100000000, max( 0, (float) $v ) ), 2 );
}
function nab_dash_valid_date( $d ) {
    if ( ! is_string( $d ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) return false;
    list( $y, $m, $day ) = array_map( 'intval', explode( '-', $d ) );
    return checkdate( $m, $day, $y ) && $y >= 1990 && $d <= current_time( 'Y-m-d' );
}
function nab_dash_valid_month( $m ) {
    return is_string( $m ) && preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $m, $p )
        && (int) $p[1] >= 2000 && $m <= current_time( 'Y-m' );
}

/* ═══════════════════════════════════════════════════════════
   CREDIT SCORE HISTORY
   ═══════════════════════════════════════════════════════════ */
function nab_dash_get_score_history( $uid ) {
    $h = nab_dash_json_meta( $uid, 'nab_score_history' );
    // First visit after upgrade: seed the chart with the score the
    // member already saved, so nobody starts from an empty chart.
    if ( ! $h ) {
        $score = (int) get_user_meta( $uid, 'nab_credit_score', true );
        if ( $score >= 300 && $score <= 900 ) {
            $upd = get_user_meta( $uid, 'nab_score_updated', true );
            $d   = $upd ? substr( $upd, 0, 10 ) : current_time( 'Y-m-d' );
            if ( ! nab_dash_valid_date( $d ) ) $d = current_time( 'Y-m-d' );
            $h = [ [ 'd' => $d, 's' => $score ] ];
            update_user_meta( $uid, 'nab_score_history', wp_json_encode( $h ) );
        }
    }
    return $h;
}

/**
 * Add (or replace that day's) score. Keeps nab_credit_score pointing at
 * the most recent entry, so every other tool keeps reading the same key.
 */
function nab_record_score_history( $uid, $score, $date = null ) {
    $score = (int) $score;
    if ( $score < 300 || $score > 900 ) return false;
    $date = $date ?: current_time( 'Y-m-d' );
    $h = array_values( array_filter( nab_dash_get_score_history( $uid ), function( $e ) use ( $date ) {
        return isset( $e['d'] ) && $e['d'] !== $date;
    } ) );
    $h[] = [ 'd' => $date, 's' => $score ];
    usort( $h, function( $a, $b ) { return strcmp( $a['d'], $b['d'] ); } );
    $h = array_slice( $h, -120 );
    update_user_meta( $uid, 'nab_score_history', wp_json_encode( $h ) );
    nab_dash_sync_current_score( $uid, $h );
    return $h;
}

function nab_dash_sync_current_score( $uid, $h ) {
    if ( ! $h ) {
        delete_user_meta( $uid, 'nab_credit_score' );
        return;
    }
    $latest = end( $h );
    update_user_meta( $uid, 'nab_credit_score',  (int) $latest['s'] );
    update_user_meta( $uid, 'nab_score_source',  'self' );
    update_user_meta( $uid, 'nab_score_updated', current_time( 'mysql' ) );
}

/* ═══════════════════════════════════════════════════════════
   CASH FLOW + NET WORTH
   ═══════════════════════════════════════════════════════════ */
function nab_dash_get_cashflow( $uid ) {
    $cf = nab_dash_json_meta( $uid, 'nab_cashflow' );
    ksort( $cf );
    return $cf;
}

function nab_dash_get_accounts( $uid ) {
    return array_values( nab_dash_json_meta( $uid, 'nab_accounts' ) );
}

function nab_dash_networth_totals( $accounts ) {
    $types = nab_dash_account_types();
    $assets = $debts = 0;
    foreach ( $accounts as $a ) {
        $g = $types[ $a['type'] ?? '' ]['group'] ?? 'asset';
        if ( $g === 'debt' ) $debts += (float) $a['bal'];
        else                 $assets += (float) $a['bal'];
    }
    return [ 'assets' => round( $assets, 2 ), 'debts' => round( $debts, 2 ), 'net' => round( $assets - $debts, 2 ) ];
}

function nab_dash_snapshot_networth( $uid, $accounts ) {
    $hist = nab_dash_json_meta( $uid, 'nab_networth_history' );
    $hist[ current_time( 'Y-m' ) ] = nab_dash_networth_totals( $accounts )['net'];
    ksort( $hist );
    update_user_meta( $uid, 'nab_networth_history', wp_json_encode( array_slice( $hist, -60, null, true ) ) );
}

/* ═══════════════════════════════════════════════════════════
   EVERYTHING THE DASHBOARD CHARTS NEED — one snapshot
   ═══════════════════════════════════════════════════════════ */
function nab_dash_get_chart_data( $uid ) {
    $accounts = nab_dash_get_accounts( $uid );

    $cards = [];
    for ( $i = 1; $i <= 5; $i++ ) {
        $lim = (float) get_user_meta( $uid, "nab_card_{$i}_limit", true );
        if ( $lim <= 0 ) continue;
        $cards[] = [
            'name' => get_user_meta( $uid, "nab_card_{$i}_name", true ) ?: 'Card ' . $i,
            'bal'  => (float) get_user_meta( $uid, "nab_card_{$i}_balance", true ),
            'lim'  => $lim,
        ];
    }

    $nw_hist = [];
    foreach ( nab_dash_json_meta( $uid, 'nab_networth_history' ) as $m => $v ) $nw_hist[] = [ 'm' => $m, 'v' => (float) $v ];
    usort( $nw_hist, function( $a, $b ) { return strcmp( $a['m'], $b['m'] ); } );

    $cf = [];
    foreach ( nab_dash_get_cashflow( $uid ) as $m => $row ) {
        $cf[] = [ 'm' => $m, 'inc' => (float) ( $row['inc'] ?? 0 ), 'exp' => (array) ( $row['exp'] ?? [] ) ];
    }

    return [
        'today'    => current_time( 'Y-m-d' ),
        'month'    => current_time( 'Y-m' ),
        'score'    => nab_dash_get_score_history( $uid ),
        'cashflow' => $cf,
        'accounts' => $accounts,
        'networth' => array_merge( nab_dash_networth_totals( $accounts ), [ 'history' => $nw_hist ] ),
        'util'     => [ 'cards' => $cards ],
        'ef'       => [
            'goal'    => (float) get_user_meta( $uid, 'nab_ef_goal', true ),
            'saved'   => (float) get_user_meta( $uid, 'nab_ef_saved', true ),
            'monthly' => (float) get_user_meta( $uid, 'nab_ef_monthly', true ),
        ],
        'cats'     => nab_dash_expense_categories(),
        'types'    => nab_dash_account_types(),
    ];
}

/* ═══════════════════════════════════════════════════════════
   AJAX
   ═══════════════════════════════════════════════════════════ */
function nab_dash_ajax_uid() {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );
    $uid = get_current_user_id();
    if ( ! $uid ) wp_send_json_error( [ 'message' => 'Please log in again.' ], 401 );
    return $uid;
}
function nab_dash_ajax_done( $uid, $message ) {
    wp_send_json_success( [ 'message' => $message, 'dash' => nab_dash_get_chart_data( $uid ) ] );
}

/* Credit score — add / replace an entry for a date */
add_action( 'wp_ajax_nab_dash_score_add', function() {
    $uid   = nab_dash_ajax_uid();
    $score = (int) ( $_POST['score'] ?? 0 );
    $date  = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) ) ?: current_time( 'Y-m-d' );
    if ( $score < 300 || $score > 900 ) wp_send_json_error( [ 'message' => 'Score must be between 300 and 900.' ] );
    if ( ! nab_dash_valid_date( $date ) ) wp_send_json_error( [ 'message' => 'Please choose a valid date (not in the future).' ] );
    nab_record_score_history( $uid, $score, $date );
    nab_dash_ajax_done( $uid, 'Score saved.' );
} );

add_action( 'wp_ajax_nab_dash_score_delete', function() {
    $uid  = nab_dash_ajax_uid();
    $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
    $h = array_values( array_filter( nab_dash_get_score_history( $uid ), function( $e ) use ( $date ) {
        return $e['d'] !== $date;
    } ) );
    update_user_meta( $uid, 'nab_score_history', wp_json_encode( $h ) );
    nab_dash_sync_current_score( $uid, $h );
    nab_dash_ajax_done( $uid, 'Score removed.' );
} );

/* Cash flow — one row per month */
add_action( 'wp_ajax_nab_dash_cashflow_save', function() {
    $uid   = nab_dash_ajax_uid();
    $month = sanitize_text_field( wp_unslash( $_POST['month'] ?? '' ) );
    if ( ! nab_dash_valid_month( $month ) ) wp_send_json_error( [ 'message' => 'Please choose a valid month (not in the future).' ] );
    $exp = [];
    foreach ( array_keys( nab_dash_expense_categories() ) as $c ) {
        $v = nab_dash_money( $_POST[ 'exp_' . $c ] ?? 0 );
        if ( $v > 0 ) $exp[ $c ] = $v;
    }
    $inc = nab_dash_money( $_POST['income'] ?? 0 );
    if ( ! $inc && ! $exp ) wp_send_json_error( [ 'message' => 'Enter your income or at least one expense.' ] );
    $cf = nab_dash_get_cashflow( $uid );
    $cf[ $month ] = [ 'inc' => $inc, 'exp' => $exp ];
    ksort( $cf );
    update_user_meta( $uid, 'nab_cashflow', wp_json_encode( array_slice( $cf, -36, null, true ) ) );
    nab_dash_ajax_done( $uid, 'Month saved.' );
} );

add_action( 'wp_ajax_nab_dash_cashflow_delete', function() {
    $uid   = nab_dash_ajax_uid();
    $month = sanitize_text_field( wp_unslash( $_POST['month'] ?? '' ) );
    $cf = nab_dash_get_cashflow( $uid );
    unset( $cf[ $month ] );
    update_user_meta( $uid, 'nab_cashflow', wp_json_encode( $cf ) );
    nab_dash_ajax_done( $uid, 'Month removed.' );
} );

/* Accounts — add or edit (when id is sent) */
add_action( 'wp_ajax_nab_dash_account_save', function() {
    $uid  = nab_dash_ajax_uid();
    $id   = sanitize_key( $_POST['id'] ?? '' );
    $name = mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 0, 60 );
    $type = sanitize_key( $_POST['type'] ?? '' );
    if ( $name === '' ) wp_send_json_error( [ 'message' => 'Please give the account a name.' ] );
    if ( ! isset( nab_dash_account_types()[ $type ] ) ) wp_send_json_error( [ 'message' => 'Please choose an account type.' ] );
    $row = [ 'id' => $id ?: strtolower( wp_generate_password( 10, false ) ), 'name' => $name, 'type' => $type, 'bal' => nab_dash_money( $_POST['balance'] ?? 0 ) ];

    $accounts = nab_dash_get_accounts( $uid );
    $found = false;
    foreach ( $accounts as $i => $a ) {
        if ( $id && $a['id'] === $id ) { $accounts[ $i ] = $row; $found = true; break; }
    }
    if ( ! $found ) {
        if ( count( $accounts ) >= 30 ) wp_send_json_error( [ 'message' => 'You can track up to 30 accounts.' ] );
        $accounts[] = $row;
    }
    update_user_meta( $uid, 'nab_accounts', wp_json_encode( array_values( $accounts ) ) );
    nab_dash_snapshot_networth( $uid, $accounts );
    nab_dash_ajax_done( $uid, 'Account saved.' );
} );

add_action( 'wp_ajax_nab_dash_account_delete', function() {
    $uid = nab_dash_ajax_uid();
    $id  = sanitize_key( $_POST['id'] ?? '' );
    $accounts = array_values( array_filter( nab_dash_get_accounts( $uid ), function( $a ) use ( $id ) {
        return $a['id'] !== $id;
    } ) );
    update_user_meta( $uid, 'nab_accounts', wp_json_encode( $accounts ) );
    nab_dash_snapshot_networth( $uid, $accounts );
    nab_dash_ajax_done( $uid, 'Account removed.' );
} );
