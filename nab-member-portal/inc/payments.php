<?php
/**
 * NAB Member Portal — Payment Calendar data (v1.11.0)
 *
 * Members list their bills (rent, card payments, loans, subscriptions…)
 * with how often they repeat. The calendar shows every due date, members
 * tick payments off as paid, and the notification bell reminds them about
 * payments due within 3 days or overdue.
 *
 * User meta:
 *   nab_bills     — JSON [ { id, name, amount, freq, start, cat, autopay, added } ]
 *                   freq: monthly | biweekly | weekly | yearly | once
 *                   start: first due date (Y-m-d); later dates repeat from it
 *                   added: day the bill was added (Y-m-d). Repeats before
 *                   that day are not tracked, so adding an old bill doesn't
 *                   fill the calendar with "overdue" payments.
 *   nab_bill_paid — JSON { "<id>@<Y-m-d>": 1 } one key per paid occurrence
 *   nab_bills_notified — Y-m-d of the last reminder check (once a day)
 *
 * The same date rules live in assets/js/nab-payments.js (nabPayDates) —
 * keep the two in step.
 *
 * AJAX (nab_portal_nonce, logged in):
 *   nab_cal_bill_save / nab_cal_bill_delete / nab_cal_toggle_paid
 *
 * @package NAB_Member_Portal
 * @since   1.11.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function nab_cal_frequencies() {
    return [
        'monthly'  => 'Every month',
        'biweekly' => 'Every 2 weeks',
        'weekly'   => 'Every week',
        'yearly'   => 'Every year',
        'once'     => 'One time',
    ];
}

function nab_cal_get_bills( $uid ) {
    return array_values( nab_dash_json_meta( $uid, 'nab_bills' ) );
}
function nab_cal_get_paid( $uid ) {
    return nab_dash_json_meta( $uid, 'nab_bill_paid' );
}

/**
 * Due dates of one bill between $from and $to (Y-m-d, inclusive).
 * Monthly/yearly bills keep the start day, moved to the last day of
 * shorter months (a bill due on the 31st is due Feb 28/29).
 * The start date itself always counts; other dates only from `added` on.
 */
function nab_cal_bill_dates( $bill, $from, $to ) {
    $start = $bill['start'] ?? '';
    if ( ! nab_cal_valid_date( $start ) ) return [];
    $added = $bill['added'] ?? '';
    $dates = nab_cal_repeat_dates( $bill, $start, $from, $to );
    if ( ! nab_cal_valid_date( $added ) || $added <= $start ) return $dates;
    return array_values( array_filter( $dates, function( $d ) use ( $start, $added ) { return $d === $start || $d >= $added; } ) );
}

function nab_cal_repeat_dates( $bill, $start, $from, $to ) {
    $out  = [];
    $freq = $bill['freq'] ?? 'monthly';

    if ( $freq === 'once' ) {
        if ( $start >= $from && $start <= $to ) $out[] = $start;
        return $out;
    }
    if ( $freq === 'weekly' || $freq === 'biweekly' ) {
        $tz   = wp_timezone();
        $step = $freq === 'weekly' ? 7 : 14;
        $d    = new DateTime( $start, $tz );
        $end  = new DateTime( $to, $tz );
        // jump close to the window instead of walking from a far-away start
        if ( $start < $from ) {
            $gap = (int) floor( $d->diff( new DateTime( $from, $tz ) )->days / $step );
            $d->modify( '+' . ( $gap * $step ) . ' days' );
        }
        for ( $i = 0; $i < 400 && $d <= $end; $i++, $d->modify( "+{$step} days" ) ) {
            $ds = $d->format( 'Y-m-d' );
            if ( $ds >= $from ) $out[] = $ds;
        }
        return $out;
    }
    // monthly / yearly
    list( $y, $m, $day ) = array_map( 'intval', explode( '-', $start ) );
    $stepM = $freq === 'yearly' ? 12 : 1;
    $gap   = ( (int) substr( $from, 0, 4 ) - $y ) * 12 + ( (int) substr( $from, 5, 2 ) - $m );
    if ( $gap > 0 ) {
        $m += intdiv( $gap, $stepM ) * $stepM;
        while ( $m > 12 ) { $m -= 12; $y++; }
    }
    for ( $i = 0; $i < 400; $i++ ) {
        $last = (int) gmdate( 't', gmmktime( 0, 0, 0, $m, 1, $y ) );
        $ds   = sprintf( '%04d-%02d-%02d', $y, $m, min( $day, $last ) );
        if ( $ds > $to ) break;
        if ( $ds >= $from && $ds >= $start ) $out[] = $ds;
        $m += $stepM;
        while ( $m > 12 ) { $m -= 12; $y++; }
    }
    return $out;
}

function nab_cal_valid_date( $d ) {
    if ( ! is_string( $d ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) return false;
    list( $y, $m, $day ) = array_map( 'intval', explode( '-', $d ) );
    return checkdate( $m, $day, $y ) && $y >= 2000 && $y <= 2100;
}

/** Everything the calendar page needs. */
function nab_cal_page_data( $uid ) {
    return [
        'today' => current_time( 'Y-m-d' ),
        'bills' => nab_cal_get_bills( $uid ),
        'paid'  => (object) nab_cal_get_paid( $uid ),
        'freqs' => nab_cal_frequencies(),
        'cats'  => nab_dash_expense_categories(),
    ];
}

/* ═══════════════════════════════════════════════════════════
   REMINDERS — once a day, when the member opens any portal page,
   add a bell notification for each unpaid bill that is overdue
   (last 7 days) or due in the next 3 days. Autopay bills are skipped.
   ═══════════════════════════════════════════════════════════ */
function nab_cal_send_reminders( $uid ) {
    $today = current_time( 'Y-m-d' );
    if ( get_user_meta( $uid, 'nab_bills_notified', true ) === $today ) return;
    if ( ! function_exists( 'nab_add_auto_notification' ) ) return;
    $bills = nab_cal_get_bills( $uid );
    if ( ! $bills ) return;
    update_user_meta( $uid, 'nab_bills_notified', $today );
    $paid = nab_cal_get_paid( $uid );
    $from = wp_date( 'Y-m-d', strtotime( $today . ' -7 days' ) );
    $to   = wp_date( 'Y-m-d', strtotime( $today . ' +3 days' ) );
    foreach ( $bills as $b ) {
        if ( ! empty( $b['autopay'] ) ) continue;
        foreach ( nab_cal_bill_dates( $b, $from, $to ) as $d ) {
            if ( ! empty( $paid[ $b['id'] . '@' . $d ] ) ) continue;
            $when   = wp_date( 'M j', strtotime( $d . ' 12:00' ) );
            $amount = '$' . number_format( (float) $b['amount'], 2 );
            if ( $d < $today ) {
                nab_add_auto_notification( $uid, 'billing', 'Overdue: ' . $b['name'] . ' (' . $when . ')',
                    $b['name'] . ' (' . $amount . ') was due on ' . $when . '. Mark it as paid in your Payment Calendar once it\'s done.', WEEK_IN_SECONDS );
            } else {
                nab_add_auto_notification( $uid, 'billing', 'Due soon: ' . $b['name'] . ' (' . $when . ')',
                    $b['name'] . ' (' . $amount . ') is due ' . ( $d === $today ? 'today' : 'on ' . $when ) . '.', WEEK_IN_SECONDS );
            }
        }
    }
}
add_action( 'template_redirect', function() {
    if ( ! is_user_logged_in() || ! function_exists( 'nab_portal_current_tool' ) || ! nab_portal_current_tool() ) return;
    nab_cal_send_reminders( get_current_user_id() );
}, 5 );

/* ═══════════════════════════════════════════════════════════
   AJAX
   ═══════════════════════════════════════════════════════════ */
function nab_cal_ajax_uid() {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );
    $uid = get_current_user_id();
    if ( ! $uid ) wp_send_json_error( [ 'message' => 'Please log in again.' ], 401 );
    return $uid;
}
function nab_cal_ajax_done( $uid, $message ) {
    wp_send_json_success( [ 'message' => $message, 'data' => nab_cal_page_data( $uid ) ] );
}

add_action( 'wp_ajax_nab_cal_bill_save', function() {
    $uid   = nab_cal_ajax_uid();
    $id    = sanitize_key( $_POST['id'] ?? '' );
    $name  = mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 0, 60 );
    $freq  = sanitize_key( $_POST['freq'] ?? '' );
    $start = sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) );
    $cat   = sanitize_key( $_POST['cat'] ?? '' );
    if ( $name === '' ) wp_send_json_error( [ 'message' => 'Please give the payment a name.' ] );
    if ( ! isset( nab_cal_frequencies()[ $freq ] ) ) wp_send_json_error( [ 'message' => 'Please choose how often it repeats.' ] );
    if ( ! nab_cal_valid_date( $start ) ) wp_send_json_error( [ 'message' => 'Please choose a valid due date.' ] );
    if ( ! isset( nab_dash_expense_categories()[ $cat ] ) ) $cat = '';

    $row = [
        'id'      => $id ?: strtolower( wp_generate_password( 10, false ) ),
        'name'    => $name,
        'amount'  => nab_dash_money( $_POST['amount'] ?? 0 ),
        'freq'    => $freq,
        'start'   => $start,
        'cat'     => $cat,
        'autopay' => ! empty( $_POST['autopay'] ),
        'added'   => current_time( 'Y-m-d' ),
    ];
    $bills = nab_cal_get_bills( $uid );
    $found = false;
    foreach ( $bills as $i => $b ) {
        if ( $id && $b['id'] === $id ) {
            $row['added'] = $b['added'] ?? ''; // keep the original tracking start
            $bills[ $i ]  = $row;
            $found        = true;
            break;
        }
    }
    if ( ! $found ) {
        if ( count( $bills ) >= 50 ) wp_send_json_error( [ 'message' => 'You can track up to 50 payments.' ] );
        $bills[] = $row;
    }
    update_user_meta( $uid, 'nab_bills', wp_json_encode( array_values( $bills ) ) );
    delete_user_meta( $uid, 'nab_bills_notified' ); // re-check reminders on the next page load
    nab_cal_ajax_done( $uid, 'Payment saved.' );
} );

add_action( 'wp_ajax_nab_cal_bill_delete', function() {
    $uid = nab_cal_ajax_uid();
    $id  = sanitize_key( $_POST['id'] ?? '' );
    $bills = array_values( array_filter( nab_cal_get_bills( $uid ), function( $b ) use ( $id ) { return $b['id'] !== $id; } ) );
    update_user_meta( $uid, 'nab_bills', wp_json_encode( $bills ) );
    $paid = array_filter( nab_cal_get_paid( $uid ), function( $k ) use ( $id ) { return strpos( $k, $id . '@' ) !== 0; }, ARRAY_FILTER_USE_KEY );
    update_user_meta( $uid, 'nab_bill_paid', wp_json_encode( (object) $paid ) );
    nab_cal_ajax_done( $uid, 'Payment removed.' );
} );

add_action( 'wp_ajax_nab_cal_toggle_paid', function() {
    $uid  = nab_cal_ajax_uid();
    $id   = sanitize_key( $_POST['id'] ?? '' );
    $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
    if ( ! nab_cal_valid_date( $date ) ) wp_send_json_error( [ 'message' => 'Invalid date.' ] );
    $bill = null;
    foreach ( nab_cal_get_bills( $uid ) as $b ) if ( $b['id'] === $id ) $bill = $b;
    if ( ! $bill || ! in_array( $date, nab_cal_bill_dates( $bill, $date, $date ), true ) ) {
        wp_send_json_error( [ 'message' => 'That payment could not be found.' ] );
    }
    $paid = nab_cal_get_paid( $uid );
    $key  = $id . '@' . $date;
    if ( ! empty( $_POST['paid'] ) ) $paid[ $key ] = 1; else unset( $paid[ $key ] );
    // keep about a year of history
    $cut = wp_date( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -400 days' ) );
    $paid = array_filter( $paid, function( $k ) use ( $cut ) { return substr( $k, -10 ) >= $cut; }, ARRAY_FILTER_USE_KEY );
    update_user_meta( $uid, 'nab_bill_paid', wp_json_encode( (object) $paid ) );
    nab_cal_ajax_done( $uid, ! empty( $_POST['paid'] ) ? 'Marked as paid.' : 'Marked as not paid.' );
} );
