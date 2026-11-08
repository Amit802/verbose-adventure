<?php
/**
 * Member access: closes portal access for cancelled members automatically (v1.19.0).
 *
 * How it works
 * - A cancelled member keeps access until their "access ends on" date
 *   (user meta nab_access_ends, Y-m-d, the last day they can still log in).
 * - The day after that date, login is refused and any open session is
 *   logged out on its next page load or AJAX call.
 * - Cancelling in the portal sets the date to the next billing day (Friday).
 * - The team cancels members who ask by email or phone in
 *   Users → Member Access, and can fix older cancelled members in one go.
 * - A weekly email lists whose access is ending soon or has just ended.
 *
 * Statuses (nab_membership_status) stay as before: active / paused / cancelled.
 *
 * @package NAB_Member_Portal
 * @since   1.19.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════
   HELPERS
   ═══════════════════════════════════════════════════════════ */

/** Who can use the Member Access screen. */
function nab_access_cap() {
    return apply_filters( 'nab_member_access_capability', 'edit_users' );
}

/** Today in the site's time zone (Y-m-d). */
function nab_access_today() {
    return wp_date( 'Y-m-d' );
}

/** Valid Y-m-d string or ''. */
function nab_access_clean_date( $value ) {
    $value = trim( (string) $value );
    if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) return '';
    return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : '';
}

/** Day after $from (Y-m-d, default today) that is the weekly billing day. */
function nab_access_next_billing_day( $from = '' ) {
    $weekday = apply_filters( 'nab_billing_weekday', 'friday' );
    try {
        $d = new DateTime( nab_access_clean_date( $from ) ?: nab_access_today(), wp_timezone() );
        $d->modify( 'next ' . $weekday );
        return $d->format( 'Y-m-d' );
    } catch ( Exception $e ) {
        return nab_access_shift( 7 );
    }
}

/** Today plus $days in the site's time zone (Y-m-d). */
function nab_access_shift( $days ) {
    $d = new DateTime( nab_access_today(), wp_timezone() );
    $d->modify( ( $days < 0 ? '-' : '+' ) . abs( (int) $days ) . ' days' );
    return $d->format( 'Y-m-d' );
}

/** Yesterday (Y-m-d): used to end access right away. */
function nab_access_yesterday() {
    return nab_access_shift( -1 );
}

/** Last day of access, or ''. */
function nab_access_end_date( $user_id ) {
    return nab_access_clean_date( get_user_meta( $user_id, 'nab_access_ends', true ) );
}

/** Human date, e.g. "Fri, Nov 13, 2026". */
function nab_access_fmt( $ymd, $format = 'D, M j, Y' ) {
    $ymd = nab_access_clean_date( $ymd );
    if ( ! $ymd ) return '';
    $d = DateTime::createFromFormat( 'Y-m-d H:i:s', $ymd . ' 12:00:00', wp_timezone() );
    return $d ? wp_date( $format, $d->getTimestamp() ) : $ymd;
}

/**
 * active | paused | ending (cancelled, still has access)
 * | ended (cancelled, access closed) | no_date (cancelled before v1.19, no end date yet)
 */
function nab_access_state( $user_id ) {
    $status = get_user_meta( $user_id, 'nab_membership_status', true ) ?: 'active';
    if ( $status === 'paused' ) return 'paused';
    if ( $status !== 'cancelled' ) return 'active';
    $ends = nab_access_end_date( $user_id );
    if ( ! $ends ) return 'no_date';
    return nab_access_today() > $ends ? 'ended' : 'ending';
}

/** True when this member must not use the portal. Admins are never locked out. */
function nab_access_is_blocked( $user_id ) {
    if ( ! $user_id || user_can( $user_id, 'manage_options' ) ) return false;
    return nab_access_state( $user_id ) === 'ended';
}

/** Short label for admin tables. */
function nab_access_label( $user_id ) {
    $ends = nab_access_end_date( $user_id );
    switch ( nab_access_state( $user_id ) ) {
        case 'paused':  return 'Paused';
        case 'ending':  return 'Cancelled, access ends ' . nab_access_fmt( $ends, 'M j, Y' );
        case 'ended':   return 'Access ended ' . nab_access_fmt( $ends, 'M j, Y' );
        case 'no_date': return 'Cancelled, no end date';
    }
    return 'Active';
}

/**
 * Cancel a member and set the last day of access.
 * $source: portal | team
 */
function nab_access_cancel( $user_id, $ends, $reason = '', $source = 'team' ) {
    $ends = nab_access_clean_date( $ends );
    if ( ! $user_id || ! $ends ) return false;

    update_user_meta( $user_id, 'nab_membership_status', 'cancelled' );
    update_user_meta( $user_id, 'nab_access_ends', $ends );
    update_user_meta( $user_id, 'nab_cancel_source', $source );
    if ( ! get_user_meta( $user_id, 'nab_cancel_date', true ) ) {
        update_user_meta( $user_id, 'nab_cancel_date', current_time( 'mysql' ) );
    }
    if ( $reason !== '' ) update_user_meta( $user_id, 'nab_cancel_reason', $reason );
    if ( $source === 'team' ) update_user_meta( $user_id, 'nab_cancel_by', get_current_user_id() );

    // A cancelled member has no pause to resume from.
    wp_clear_scheduled_hook( 'nab_mp_auto_resume', [ (int) $user_id ] );
    delete_user_meta( $user_id, 'nab_mp_resume_date' );
    delete_user_meta( $user_id, 'nab_pause_weeks' );

    if ( nab_access_today() > $ends ) nab_access_end_sessions( $user_id );
    return true;
}

/** Give a cancelled member full access again. */
function nab_access_reactivate( $user_id ) {
    if ( ! $user_id ) return false;
    update_user_meta( $user_id, 'nab_membership_status', 'active' );
    foreach ( [ 'nab_access_ends', 'nab_cancel_source', 'nab_cancel_by', 'nab_cancel_date', 'nab_cancel_reason' ] as $key ) {
        delete_user_meta( $user_id, $key );
    }
    return true;
}

/** Log the member out everywhere. */
function nab_access_end_sessions( $user_id ) {
    if ( class_exists( 'WP_Session_Tokens' ) ) {
        WP_Session_Tokens::get_instance( (int) $user_id )->destroy_all();
    }
}

/** All cancelled members (IDs). */
function nab_access_cancelled_ids() {
    return array_map( 'intval', get_users( [
        'fields'     => 'ID',
        'number'     => -1,
        'meta_key'   => 'nab_membership_status',
        'meta_value' => 'cancelled',
    ] ) );
}

/** Cancelled members grouped by state: [ 'ending' => [...], 'ended' => [...], 'no_date' => [...] ]. */
function nab_access_groups() {
    $out = [ 'ending' => [], 'ended' => [], 'no_date' => [] ];
    foreach ( nab_access_cancelled_ids() as $id ) {
        $state = nab_access_state( $id );
        if ( isset( $out[ $state ] ) ) $out[ $state ][] = $id;
    }
    $by_date = function ( $a, $b ) { return strcmp( nab_access_end_date( $a ), nab_access_end_date( $b ) ); };
    usort( $out['ending'], $by_date );
    usort( $out['ended'], function ( $a, $b ) use ( $by_date ) { return $by_date( $b, $a ); } );
    return $out;
}

/** Suggested last day for a member cancelled before v1.19: the billing day after they cancelled. */
function nab_access_suggested_end( $user_id ) {
    $cancelled = get_user_meta( $user_id, 'nab_cancel_date', true );
    $from      = $cancelled ? substr( (string) $cancelled, 0, 10 ) : '';
    return nab_access_clean_date( $from ) ? nab_access_next_billing_day( $from ) : nab_access_yesterday();
}

/** Display name for admin lists and emails. */
function nab_access_name( $user_id ) {
    $user  = get_userdata( $user_id );
    $first = get_user_meta( $user_id, 'nab_first_name', true ) ?: ( $user ? $user->first_name : '' );
    $last  = get_user_meta( $user_id, 'nab_last_name', true ) ?: ( $user ? $user->last_name : '' );
    $name  = trim( $first . ' ' . $last );
    return $name ?: ( $user ? $user->display_name : 'Member #' . $user_id );
}

/* ═══════════════════════════════════════════════════════════
   BLOCKING
   ═══════════════════════════════════════════════════════════ */

/** Message shown when a member whose access ended tries to sign in. */
function nab_access_ended_message() {
    return apply_filters(
        'nab_access_ended_message',
        'Your NAB Solutions membership has ended, so your member portal access is closed. To come back, call us at 1-855-542-6078 (daily, 9:00 AM to 3:00 PM Mountain Time) or email billing@nabsolutions.ca.'
    );
}

// Refuse the login.
add_filter( 'wp_authenticate_user', function ( $user ) {
    if ( is_wp_error( $user ) || ! $user ) return $user;
    if ( nab_access_is_blocked( $user->ID ) ) {
        return new WP_Error( 'nab_membership_ended', nab_access_ended_message() );
    }
    return $user;
}, 21 );

// Log out a session that was open when the access ended (pages and AJAX).
add_action( 'init', function () {
    if ( ! is_user_logged_in() ) return;
    $uid = get_current_user_id();
    if ( ! nab_access_is_blocked( $uid ) ) return;

    nab_access_end_sessions( $uid );
    wp_logout();

    if ( wp_doing_ajax() ) {
        wp_send_json_error( [ 'message' => nab_access_ended_message(), 'access' => 'ended' ], 403 );
    }
    wp_safe_redirect( home_url( '/?membership=ended' ) );
    exit;
}, 20 );

// Short note on the page a member lands on after being logged out.
add_action( 'wp_footer', function () {
    $why = isset( $_GET['membership'] ) ? sanitize_key( wp_unslash( $_GET['membership'] ) ) : '';
    if ( is_user_logged_in() || ! in_array( $why, [ 'ended', 'paused' ], true ) ) return;
    $text = $why === 'ended'
        ? nab_access_ended_message()
        : 'Your membership is paused, so your member portal is on hold until it resumes. Questions? Email billing@nabsolutions.ca.';
    echo '<div role="status" style="position:fixed;left:16px;right:16px;bottom:16px;z-index:99999;max-width:560px;margin:0 auto;padding:14px 18px;border-radius:12px;background:#0f2742;color:#fff;font:500 14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;box-shadow:0 12px 30px rgba(15,39,66,.25)">'
        . esc_html( $text ) . '</div>';
} );

/* ═══════════════════════════════════════════════════════════
   WEEKLY EMAIL TO THE TEAM
   ═══════════════════════════════════════════════════════════ */

function nab_access_report_recipients() {
    $raw  = (string) get_option( 'nab_access_report_to', 'billing@nabsolutions.ca' );
    $list = array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', $raw ) ), 'is_email' );
    return array_values( array_unique( $list ) );
}

/** Next Monday 9:00 site time. */
function nab_access_next_report_time() {
    $d = new DateTime( 'today 09:00', wp_timezone() );
    $d->modify( 'next monday' );
    $d->setTime( 9, 0 );
    return $d->getTimestamp();
}

add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'nab_access_weekly_report' ) ) {
        wp_schedule_event( nab_access_next_report_time(), 'weekly', 'nab_access_weekly_report' );
    }
} );

add_action( 'nab_access_weekly_report', function () {
    nab_access_send_report( false );
} );

/**
 * Email the team. Skips an empty report unless $always.
 * Returns 'sent', 'empty', 'no_recipients' or 'failed'.
 */
function nab_access_send_report( $always = false ) {
    $to = nab_access_report_recipients();
    if ( ! $to ) return 'no_recipients';

    $today   = nab_access_today();
    $in7     = nab_access_shift( 7 );
    $ago7    = nab_access_shift( -7 );
    $groups  = nab_access_groups();
    $soon    = array_filter( $groups['ending'], function ( $id ) use ( $in7 ) { return nab_access_end_date( $id ) <= $in7; } );
    $ended   = array_filter( $groups['ended'], function ( $id ) use ( $ago7 ) { return nab_access_end_date( $id ) >= $ago7; } );
    $no_date = $groups['no_date'];

    if ( ! $always && ! $soon && ! $ended && ! $no_date ) return 'empty';

    $row = function ( $id, $label ) {
        $u = get_userdata( $id );
        return '<li><strong>' . esc_html( nab_access_name( $id ) ) . '</strong> (' . esc_html( $u ? $u->user_email : '' ) . '), ' . esc_html( $label ) . '</li>';
    };

    $body = "Hi team,\n\nHere's this week's update on member portal access.\n\n";

    $body .= '<strong>Access ending in the next 7 days (' . count( $soon ) . ')</strong>';
    $body .= $soon ? '<ul>' . implode( '', array_map( function ( $id ) use ( $row ) { return $row( $id, 'last day ' . nab_access_fmt( nab_access_end_date( $id ) ) ); }, $soon ) ) . '</ul>' : "\nNone.\n\n";

    $body .= '<strong>Access closed in the last 7 days (' . count( $ended ) . ')</strong>';
    $body .= $ended ? '<ul>' . implode( '', array_map( function ( $id ) use ( $row ) { return $row( $id, 'last day was ' . nab_access_fmt( nab_access_end_date( $id ) ) ); }, $ended ) ) . '</ul>' : "\nNone.\n\n";

    if ( $no_date ) {
        $body .= '<strong>Cancelled but still have access, no end date set (' . count( $no_date ) . ')</strong>'
            . "\nThese members cancelled before the new system. Set their end date in Users &rarr; Member Access.\n\n";
    }

    $body .= "If someone cancelled by email or phone and isn't listed, cancel them in Users &rarr; Member Access:\n"
        . '<a href="' . esc_url( admin_url( 'users.php?page=nab-member-access' ) ) . '">' . esc_html( admin_url( 'users.php?page=nab-member-access' ) ) . "</a>\n\nNAB Member Portal";

    $subject = 'Member portal access: ' . count( $soon ) . ' ending soon, ' . count( $ended ) . ' closed this week';
    $ok      = true;
    foreach ( $to as $email ) {
        $ok = nab_send_membership_email( $email, $subject, $body ) && $ok;
    }
    return $ok ? 'sent' : 'failed';
}

/* ═══════════════════════════════════════════════════════════
   ADMIN: Users → Member Access
   ═══════════════════════════════════════════════════════════ */

add_action( 'admin_menu', function () {
    add_users_page( 'Member Access', 'Member Access', nab_access_cap(), 'nab-member-access', 'nab_access_admin_page' );
} );

/** Redirect back to the screen with a notice. */
function nab_access_back( $msg, $type = 'success', $extra = [] ) {
    $args = array_merge( [ 'page' => 'nab-member-access', 'nab_msg' => rawurlencode( $msg ), 'nab_type' => $type ], $extra );
    wp_safe_redirect( add_query_arg( $args, admin_url( 'users.php' ) ) );
    exit;
}

function nab_access_check_request( $action ) {
    if ( ! current_user_can( nab_access_cap() ) ) wp_die( 'You are not allowed to do this.', 403 );
    check_admin_referer( $action );
}

// Cancel one member, or change their date.
add_action( 'admin_post_nab_access_cancel', function () {
    nab_access_check_request( 'nab_access_cancel' );
    $uid    = absint( $_POST['user_id'] ?? 0 );
    $user   = get_userdata( $uid );
    $when   = sanitize_key( $_POST['when'] ?? 'date' );
    $ends   = $when === 'now' ? nab_access_yesterday() : nab_access_clean_date( wp_unslash( $_POST['ends'] ?? '' ) );
    $reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
    $back   = [ 's' => sanitize_text_field( wp_unslash( $_POST['s'] ?? '' ) ) ];

    if ( ! $user ) nab_access_back( 'That member was not found.', 'error', $back );
    if ( user_can( $uid, 'manage_options' ) ) nab_access_back( 'Admin accounts cannot be cancelled here.', 'error', $back );
    if ( ! $ends ) nab_access_back( 'Please pick the last day of access.', 'error', $back );

    nab_access_cancel( $uid, $ends, $reason, 'team' );
    $name = nab_access_name( $uid );
    $msg  = nab_access_today() > $ends
        ? $name . "'s portal access is closed now."
        : $name . ' is cancelled. Their last day of access is ' . nab_access_fmt( $ends ) . '.';
    nab_access_back( $msg, 'success', $back );
} );

// Reactivate one member.
add_action( 'admin_post_nab_access_reactivate', function () {
    nab_access_check_request( 'nab_access_reactivate' );
    $uid  = absint( $_POST['user_id'] ?? 0 );
    $back = [ 's' => sanitize_text_field( wp_unslash( $_POST['s'] ?? '' ) ) ];
    if ( ! get_userdata( $uid ) ) nab_access_back( 'That member was not found.', 'error', $back );
    nab_access_reactivate( $uid );
    nab_access_back( nab_access_name( $uid ) . ' is active again and can use the portal.', 'success', $back );
} );

// Set end dates for older cancelled members in one go.
add_action( 'admin_post_nab_access_bulk', function () {
    nab_access_check_request( 'nab_access_bulk' );
    $picked = array_map( 'absint', (array) ( $_POST['users'] ?? [] ) );
    $dates  = (array) ( $_POST['ends'] ?? [] );
    $mode   = sanitize_key( $_POST['mode'] ?? 'dates' );
    $done   = 0;
    foreach ( $picked as $uid ) {
        if ( ! $uid || nab_access_state( $uid ) !== 'no_date' || user_can( $uid, 'manage_options' ) ) continue;
        $ends = $mode === 'now' ? nab_access_yesterday() : nab_access_clean_date( wp_unslash( $dates[ $uid ] ?? '' ) );
        if ( ! $ends ) continue;
        if ( nab_access_cancel( $uid, $ends, '', 'team' ) ) $done++;
    }
    if ( ! $done ) nab_access_back( 'No members were updated. Tick at least one member.', 'error' );
    nab_access_back( $done . ' member' . ( $done === 1 ? '' : 's' ) . ' updated. Anyone whose date has passed can no longer use the portal.' );
} );

// Weekly email settings and "send now".
add_action( 'admin_post_nab_access_report', function () {
    nab_access_check_request( 'nab_access_report' );
    $raw  = sanitize_text_field( wp_unslash( $_POST['report_to'] ?? '' ) );
    $list = array_filter( array_map( 'sanitize_email', preg_split( '/[\s,;]+/', $raw ) ), 'is_email' );
    update_option( 'nab_access_report_to', implode( ', ', array_unique( $list ) ), false );

    if ( isset( $_POST['send_now'] ) ) {
        $res = nab_access_send_report( true );
        $map = [
            'sent'          => [ 'Saved. The access report was emailed to ' . implode( ', ', nab_access_report_recipients() ) . '.', 'success' ],
            'no_recipients' => [ 'Add at least one email address first.', 'error' ],
            'failed'        => [ 'Saved, but the email could not be sent. Check the site email (SMTP) settings.', 'error' ],
        ];
        list( $m, $t ) = $map[ $res ] ?? [ 'Saved.', 'success' ];
        nab_access_back( $m, $t );
    }
    nab_access_back( $list ? 'Saved. The report goes out every Monday morning.' : 'Saved. No address is set, so the weekly report is off.' );
} );

/** Search members by email, name, username or Member ID. */
function nab_access_search( $term ) {
    $term = trim( $term );
    if ( $term === '' ) return [];
    $ids = get_users( [ 'search' => '*' . $term . '*', 'search_columns' => [ 'user_email', 'user_login', 'display_name', 'user_nicename' ], 'fields' => 'ID', 'number' => 30 ] );
    $by_meta = get_users( [
        'fields'     => 'ID',
        'number'     => 30,
        'meta_query' => [
            'relation' => 'OR',
            [ 'key' => 'nab_member_id', 'value' => $term, 'compare' => 'LIKE' ],
            [ 'key' => 'nab_first_name', 'value' => $term, 'compare' => 'LIKE' ],
            [ 'key' => 'nab_last_name', 'value' => $term, 'compare' => 'LIKE' ],
            [ 'key' => 'first_name', 'value' => $term, 'compare' => 'LIKE' ],
            [ 'key' => 'last_name', 'value' => $term, 'compare' => 'LIKE' ],
        ],
    ] );
    return array_slice( array_values( array_unique( array_map( 'intval', array_merge( $ids, $by_meta ) ) ) ), 0, 30 );
}

/** Small inline forms used in the tables. */
function nab_access_row_actions( $uid, $search = '' ) {
    $state = nab_access_state( $uid );
    $ends  = nab_access_end_date( $uid );
    $url   = esc_url( admin_url( 'admin-post.php' ) );
    $s     = '<input type="hidden" name="s" value="' . esc_attr( $search ) . '">';
    $out   = '';

    if ( user_can( $uid, 'manage_options' ) ) return '<span class="description">Admin account</span>';

    $out .= '<form method="post" action="' . $url . '" class="nab-acc-form">'
        . wp_nonce_field( 'nab_access_cancel', '_wpnonce', true, false )
        . '<input type="hidden" name="action" value="nab_access_cancel"><input type="hidden" name="user_id" value="' . (int) $uid . '">' . $s
        . '<label>Last day of access <input type="date" name="ends" required value="' . esc_attr( $ends ?: ( $state === 'no_date' ? nab_access_suggested_end( $uid ) : nab_access_next_billing_day() ) ) . '"></label>';
    if ( in_array( $state, [ 'active', 'paused' ], true ) ) {
        $out .= '<label>Note <input type="text" name="reason" placeholder="e.g. Asked by email" maxlength="200"></label>'
            . '<button class="button button-primary" name="when" value="date">Cancel member</button>'
            . '<button class="button nab-acc-danger" name="when" value="now" formnovalidate onclick="return confirm(\'Close portal access for this member right now?\')">Cancel and close access now</button>';
    } else {
        $out .= '<button class="button" name="when" value="date">' . ( $state === 'no_date' ? 'Set end date' : 'Change date' ) . '</button>';
        if ( $state !== 'ended' ) {
            $out .= '<button class="button nab-acc-danger" name="when" value="now" formnovalidate onclick="return confirm(\'Close portal access for this member right now?\')">Close access now</button>';
        }
    }
    $out .= '</form>';

    if ( $state !== 'active' && $state !== 'paused' ) {
        $out .= '<form method="post" action="' . $url . '" class="nab-acc-form">'
            . wp_nonce_field( 'nab_access_reactivate', '_wpnonce', true, false )
            . '<input type="hidden" name="action" value="nab_access_reactivate"><input type="hidden" name="user_id" value="' . (int) $uid . '">' . $s
            . '<button class="button-link" onclick="return confirm(\'Make this member active again?\')">Reactivate member</button></form>';
    }
    return $out;
}

function nab_access_member_cell( $uid ) {
    $u   = get_userdata( $uid );
    $mid = get_user_meta( $uid, 'nab_member_id', true );
    return '<strong>' . esc_html( nab_access_name( $uid ) ) . '</strong><br><span class="description">'
        . esc_html( $u ? $u->user_email : '' ) . ( $mid ? ' &middot; ' . esc_html( $mid ) : '' ) . '</span>';
}

function nab_access_badge( $uid ) {
    $state = nab_access_state( $uid );
    return '<span class="nab-acc-badge nab-acc-' . esc_attr( $state ) . '">' . esc_html( nab_access_label( $uid ) ) . '</span>';
}

function nab_access_admin_page() {
    if ( ! current_user_can( nab_access_cap() ) ) return;

    $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $msg    = isset( $_GET['nab_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['nab_msg'] ) ) ) : '';
    $type   = ( $_GET['nab_type'] ?? '' ) === 'error' ? 'error' : 'success';
    $groups = nab_access_groups();
    $found  = $search !== '' ? nab_access_search( $search ) : [];
    $today  = nab_access_today();
    $in14   = nab_access_shift( 14 );
    $ago30  = nab_access_shift( -30 );
    $soon   = array_filter( $groups['ending'], function ( $id ) use ( $in14 ) { return nab_access_end_date( $id ) <= $in14; } );
    $later  = array_diff( $groups['ending'], $soon );
    $recent = array_filter( $groups['ended'], function ( $id ) use ( $ago30 ) { return nab_access_end_date( $id ) >= $ago30; } );
    $post   = esc_url( admin_url( 'admin-post.php' ) );
    ?>
    <div class="wrap nab-acc">
        <style>
            .nab-acc .nab-acc-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 20px;margin:18px 0;max-width:1100px}
            .nab-acc h2{margin:0 0 6px;font-size:16px}
            .nab-acc .nab-acc-lead{margin:0 0 14px;color:#50575e}
            .nab-acc table.widefat td{vertical-align:middle}
            .nab-acc .nab-acc-form{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:4px 0}
            .nab-acc .nab-acc-form label{display:inline-flex;gap:6px;align-items:center;font-size:12px;color:#50575e}
            .nab-acc .nab-acc-danger{color:#b32d2e!important;border-color:#b32d2e!important}
            .nab-acc .nab-acc-badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:600;background:#edfaef;color:#1d6b2c}
            .nab-acc .nab-acc-paused{background:#fcf9e8;color:#7a5b00}
            .nab-acc .nab-acc-ending{background:#fff4e5;color:#8a4b00}
            .nab-acc .nab-acc-ended{background:#fcf0f1;color:#8a2424}
            .nab-acc .nab-acc-no_date{background:#fcf0f1;color:#8a2424}
            .nab-acc .nab-acc-stats{display:flex;flex-wrap:wrap;gap:12px;margin:14px 0 0}
            .nab-acc .nab-acc-stat{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:10px 16px;min-width:150px}
            .nab-acc .nab-acc-stat b{display:block;font-size:22px;line-height:1.2}
            .nab-acc .nab-acc-search{display:flex;gap:8px;flex-wrap:wrap}
            .nab-acc .nab-acc-search input[type=search]{min-width:280px}
            @media(max-width:782px){
                .nab-acc .nab-acc-search input[type=search]{min-width:0;width:100%}
                .nab-acc table.widefat,.nab-acc table.widefat tbody,.nab-acc table.widefat tr,.nab-acc table.widefat td,.nab-acc table.widefat tbody th{display:block;width:auto}
                .nab-acc table.widefat thead{display:none}
                .nab-acc table.widefat tr{border-bottom:1px solid #dcdcde;padding:6px 0}
                .nab-acc table.widefat tbody th.check-column{float:left;padding:10px 6px 0 10px}
                .nab-acc .nab-acc-card{padding:14px}
            }
        </style>

        <h1>Member Access</h1>
        <p class="nab-acc-lead">Cancel members who ask by email or phone, and see whose portal access is ending. When a member's last day passes, they can't log in and are signed out automatically.</p>

        <?php if ( $msg ) : ?>
            <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
        <?php endif; ?>

        <div class="nab-acc-stats">
            <div class="nab-acc-stat"><b><?php echo count( $soon ); ?></b>Ending in 14 days</div>
            <div class="nab-acc-stat"><b><?php echo count( $groups['ending'] ); ?></b>Cancelled, still have access</div>
            <div class="nab-acc-stat"><b><?php echo count( $groups['ended'] ); ?></b>Access closed</div>
            <?php if ( $groups['no_date'] ) : ?>
            <div class="nab-acc-stat" style="border-color:#d63638"><b style="color:#d63638"><?php echo count( $groups['no_date'] ); ?></b>Need an end date</div>
            <?php endif; ?>
        </div>

        <?php if ( $groups['no_date'] ) : ?>
        <div class="nab-acc-card" id="nab-acc-old" style="border-color:#d63638">
            <h2>Cancelled members who still have access</h2>
            <p class="nab-acc-lead">These members cancelled before this update, so no end date was saved and they can still use the portal. The suggested date is the billing day after they cancelled. Untick anyone who should keep access, then save.</p>
            <form method="post" action="<?php echo $post; ?>">
                <?php wp_nonce_field( 'nab_access_bulk' ); ?>
                <input type="hidden" name="action" value="nab_access_bulk">
                <table class="widefat striped">
                    <thead><tr><td class="check-column"><input type="checkbox" checked onclick="document.querySelectorAll('#nab-acc-old tbody input[type=checkbox]').forEach(c=>c.checked=this.checked)"></td><th>Member</th><th>Cancelled on</th><th>Last day of access</th></tr></thead>
                    <tbody>
                    <?php foreach ( $groups['no_date'] as $uid ) :
                        $cd = get_user_meta( $uid, 'nab_cancel_date', true ); ?>
                        <tr>
                            <th class="check-column"><input type="checkbox" name="users[]" value="<?php echo (int) $uid; ?>" checked></th>
                            <td><?php echo nab_access_member_cell( $uid ); ?></td>
                            <td><?php echo $cd ? esc_html( nab_access_fmt( substr( (string) $cd, 0, 10 ), 'M j, Y' ) ?: $cd ) : '<span class="description">Not recorded</span>'; ?></td>
                            <td><input type="date" name="ends[<?php echo (int) $uid; ?>]" value="<?php echo esc_attr( nab_access_suggested_end( $uid ) ); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="nab-acc-form" style="margin-top:12px">
                    <button class="button button-primary" name="mode" value="dates">Save these dates</button>
                    <button class="button nab-acc-danger" name="mode" value="now" onclick="return confirm('Close portal access right now for every ticked member?')">Close access now for all ticked</button>
                </p>
            </form>
        </div>
        <?php endif; ?>

        <div class="nab-acc-card">
            <h2>Cancel a member</h2>
            <p class="nab-acc-lead">Search by email, name or Member ID. The date is the last day they can still use the portal (it starts on the next billing Friday).</p>
            <form method="get" action="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="nab-acc-search">
                <input type="hidden" name="page" value="nab-member-access">
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="member@email.com" autofocus>
                <button class="button">Search</button>
            </form>
            <?php if ( $search !== '' ) : ?>
                <?php if ( ! $found ) : ?>
                    <p style="margin-top:14px">No member matches "<?php echo esc_html( $search ); ?>".</p>
                <?php else : ?>
                <table class="widefat striped" style="margin-top:14px">
                    <thead><tr><th>Member</th><th>Portal access</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ( $found as $uid ) : ?>
                        <tr><td><?php echo nab_access_member_cell( $uid ); ?></td><td><?php echo nab_access_badge( $uid ); ?></td><td><?php echo nab_access_row_actions( $uid, $search ); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="nab-acc-card">
            <h2>Access ending soon</h2>
            <p class="nab-acc-lead">Cancelled members whose access ends in the next 14 days. Today is <?php echo esc_html( nab_access_fmt( $today ) ); ?>.</p>
            <?php nab_access_table( $soon, 'Nobody is due to lose access in the next 14 days.' ); ?>
            <?php if ( $later ) : ?>
                <details style="margin-top:12px"><summary><?php echo count( $later ); ?> more ending later</summary><?php nab_access_table( $later, '' ); ?></details>
            <?php endif; ?>
        </div>

        <div class="nab-acc-card">
            <h2>Access closed recently</h2>
            <p class="nab-acc-lead">Members whose access ended in the last 30 days. Use Reactivate if someone comes back.</p>
            <?php nab_access_table( $recent, 'No member lost access in the last 30 days.' ); ?>
        </div>

        <div class="nab-acc-card">
            <h2>Weekly email</h2>
            <p class="nab-acc-lead">Every Monday morning this sends the list of members whose access ends that week, the ones closed the week before, and any cancelled members without an end date. Nothing is sent when there's nothing to report.</p>
            <form method="post" action="<?php echo $post; ?>" class="nab-acc-form">
                <?php wp_nonce_field( 'nab_access_report' ); ?>
                <input type="hidden" name="action" value="nab_access_report">
                <label>Send to <input type="text" name="report_to" class="regular-text" value="<?php echo esc_attr( implode( ', ', nab_access_report_recipients() ) ); ?>" placeholder="billing@nabsolutions.ca"></label>
                <button class="button button-primary">Save</button>
                <button class="button" name="send_now" value="1">Save and send it now</button>
            </form>
            <?php $next = wp_next_scheduled( 'nab_access_weekly_report' ); if ( $next ) : ?>
                <p class="description">Next email: <?php echo esc_html( wp_date( 'l, M j, Y \a\t g:i a', $next ) ); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function nab_access_table( $ids, $empty ) {
    if ( ! $ids ) {
        if ( $empty ) echo '<p>' . esc_html( $empty ) . '</p>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr><th>Member</th><th>Portal access</th><th>Action</th></tr></thead><tbody>';
    foreach ( $ids as $uid ) {
        echo '<tr><td>' . nab_access_member_cell( $uid ) . '</td><td>' . nab_access_badge( $uid ) . '</td><td>' . nab_access_row_actions( $uid ) . '</td></tr>'; // cells are escaped in their helpers
    }
    echo '</tbody></table>';
}

/* ── Reminder on the WordPress dashboard and Users screens ── */
add_action( 'admin_notices', function () {
    if ( ! current_user_can( nab_access_cap() ) ) return;
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'users' ], true ) ) return;
    $count = count( nab_access_groups()['no_date'] );
    if ( ! $count ) return;
    echo '<div class="notice notice-warning"><p><strong>' . (int) $count . ' cancelled member' . ( $count === 1 ? '' : 's' ) . ' still ' . ( $count === 1 ? 'has' : 'have' ) . ' portal access</strong> because no end date is set. '
        . '<a href="' . esc_url( admin_url( 'users.php?page=nab-member-access#nab-acc-old' ) ) . '">Fix them in one go</a></p></div>';
} );

/* ── Users list: "Portal access" column and a quick link ── */
add_filter( 'manage_users_columns', function ( $columns ) {
    $columns['nab_access'] = 'Portal access';
    return $columns;
} );

add_filter( 'manage_users_custom_column', function ( $value, $column, $user_id ) {
    if ( $column !== 'nab_access' ) return $value;
    $state  = nab_access_state( $user_id );
    $colors = [ 'active' => '#1d6b2c', 'paused' => '#7a5b00', 'ending' => '#8a4b00', 'ended' => '#8a2424', 'no_date' => '#8a2424' ];
    return '<span style="color:' . esc_attr( $colors[ $state ] ?? '#1d6b2c' ) . ';font-weight:600">' . esc_html( nab_access_label( $user_id ) ) . '</span>';
}, 10, 3 );

add_filter( 'user_row_actions', function ( $actions, $user ) {
    if ( current_user_can( nab_access_cap() ) && ! user_can( $user, 'manage_options' ) ) {
        $actions['nab_access'] = '<a href="' . esc_url( add_query_arg( [ 'page' => 'nab-member-access', 's' => $user->user_email ], admin_url( 'users.php' ) ) ) . '">Portal access</a>';
    }
    return $actions;
}, 10, 2 );
