<?php
/**
 * NAB Member Portal — Custom Membership Management
 * No MemberPress dependency. Uses WordPress user meta only.
 * Status stored in: nab_membership_status (active/paused/cancelled)
 *
 * @package NAB_Member_Portal
 * @since   1.6.6
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════
   HELPER: always returns false — MemberPress not used
   ═══════════════════════════════════════════════════════════ */
if ( ! function_exists( 'nab_mp_active' ) ) {
    function nab_mp_active() { return false; }
}

/* ═══════════════════════════════════════════════════════════
   GET MEMBER DATA — from user meta only
   ═══════════════════════════════════════════════════════════ */
if ( ! function_exists( 'nab_mp_get_member_data' ) ) {
    function nab_mp_get_member_data( $user_id = 0 ) {
        if ( ! $user_id ) $user_id = get_current_user_id();

        $status      = get_user_meta( $user_id, 'nab_membership_status', true ) ?: 'active';
        $resume_date = get_user_meta( $user_id, 'nab_mp_resume_date',    true ) ?: null;
        $joined      = get_user_meta( $user_id, 'nab_joined_date',       true ) ?: get_the_date( 'Y-m-d', $user_id );
        $plan        = get_user_meta( $user_id, 'nab_plan_name',         true ) ?: 'NAB Solutions Membership';
        $price       = get_user_meta( $user_id, 'nab_plan_price',        true ) ?: '$20.45 CAD/week';

        return [
            'has_subscription'  => true,
            'status'            => $status,
            'plan_name'         => $plan,
            'plan_price'        => $price,
            'billing_cycle'     => 'Weekly',
            'next_billing_date' => null,
            'started_at'        => $joined ? date( 'M j, Y', strtotime( $joined ) ) : null,
            'is_paused'         => ( $status === 'paused' ),
            'resume_date'       => $resume_date ? date( 'M j, Y', strtotime( $resume_date ) ) : null,
            'recent_txns'       => [],
            'sub_id'            => null,
        ];
    }
}

/* ═══════════════════════════════════════════════════════════
   SEND EMAIL — reusable wrapper using wp_mail
   ═══════════════════════════════════════════════════════════ */
function nab_send_membership_email( $to, $subject, $body ) {
    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
    $from    = get_bloginfo( 'name' ) . ' <noreply@nabsolutions.ca>';
    $headers[] = 'From: ' . $from;

    $html = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#1e293b;max-width:600px;margin:0 auto;padding:24px">
        <div style="background:#0D5C9B;padding:20px 24px;border-radius:8px 8px 0 0">
            <h2 style="color:#fff;margin:0">NAB Solutions</h2>
            <p style="color:rgba(255,255,255,.8);margin:4px 0 0;font-size:13px">Member Portal</p>
        </div>
        <div style="background:#fff;padding:24px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px">
            ' . wpautop( $body ) . '
            <hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0">
            <p style="font-size:12px;color:#94a3b8">NAB Solutions Member Portal &bull; members.nabsolutions.ca</p>
        </div>
    </body></html>';

    return wp_mail( $to, $subject, $html, $headers );
}

/* ═══════════════════════════════════════════════════════════
   AJAX: PAUSE MEMBERSHIP
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_mp_pause', function() {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );

    $uid   = get_current_user_id();
    $weeks = intval( $_POST['weeks'] ?? 2 );
    if ( ! in_array( $weeks, [2, 4], true ) ) $weeks = 2;

    $user        = get_userdata( $uid );
    $email       = $user->user_email;
    $name        = get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ?: 'Member';
    $resume_date = date( 'Y-m-d', strtotime( "+{$weeks} weeks" ) );
    $resume_fmt  = date( 'M j, Y', strtotime( $resume_date ) );

    // Update status
    update_user_meta( $uid, 'nab_membership_status', 'paused' );
    update_user_meta( $uid, 'nab_mp_resume_date', $resume_date );
    update_user_meta( $uid, 'nab_pause_weeks', $weeks );

    // Schedule auto-resume via WP Cron
    $ts = strtotime( $resume_date . ' 09:00:00' );
    wp_clear_scheduled_hook( 'nab_mp_auto_resume', [$uid] );
    wp_schedule_single_event( $ts, 'nab_mp_auto_resume', [$uid] );

    // Portal notification
    if ( function_exists( 'nab_add_auto_notification' ) ) {
        nab_add_auto_notification( $uid, 'billing',
            '⏸ Membership Paused',
            'Your membership is paused for ' . $weeks . ' week(s). It resumes automatically on ' . $resume_fmt . '.'
        );
    }

    // Email to member
    nab_send_membership_email(
        $email,
        'Your NAB Solutions Membership Has Been Paused',
        "Hi {$name},\n\nYour NAB Solutions membership has been paused for {$weeks} week(s). You won't be charged during this time, and your Member Dashboard and benefits will be on hold until it resumes.\n\nYour membership will automatically resume on <strong>{$resume_fmt}</strong>, and we'll send you a confirmation once it's active again.\n\nNeed to resume early or have questions? Contact us at 1-855-542-6078 (daily, 9:00 AM to 3:00 PM Mountain Time) or email billing@nabsolutions.ca.\n\nThank you,\nThe NAB Solutions Team"
    );

    // Email to admin
    nab_send_membership_email(
        'billing@nabsolutions.ca',
        'Member Paused Membership — ' . $name,
        "Member <strong>{$name}</strong> ({$email}) has paused their membership for {$weeks} week(s).\n\nResume date: <strong>{$resume_fmt}</strong>\n\nUser ID: {$uid}"
    );

    wp_send_json_success( [
        'message'     => 'Membership paused for ' . $weeks . ' weeks.',
        'resume_date' => $resume_fmt,
        'status'      => 'paused',
    ] );
} );

/* ═══════════════════════════════════════════════════════════
   WP CRON: AUTO-RESUME AFTER PAUSE
   ═══════════════════════════════════════════════════════════ */
add_action( 'nab_mp_auto_resume', function( $uid ) {
    $uid  = (int) $uid;
    $user = get_userdata( $uid );
    if ( ! $user ) return;

    $name  = get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ?: 'Member';
    $email = $user->user_email;

    update_user_meta( $uid, 'nab_membership_status', 'active' );
    delete_user_meta( $uid, 'nab_mp_resume_date' );
    delete_user_meta( $uid, 'nab_pause_weeks' );

    if ( function_exists( 'nab_add_auto_notification' ) ) {
        nab_add_auto_notification( $uid, 'billing',
            '▶️ Membership Resumed',
            'Your membership has been automatically resumed. Welcome back!'
        );
    }

    // Email to member
    nab_send_membership_email(
        $email,
        'Your NAB Solutions Membership Has Been Reactivated',
        "Hi {$name},\n\nYour NAB Solutions membership is now active again. You have full access to your Member Dashboard and all benefits, including credit monitoring, LFP, Auto Loan Matcher, Credit Card Matcher, and educational videos.\n\nYour weekly billing has resumed, and your next charge will be this Friday.\n\nQuestions or need help? Contact us at 1-855-542-6078 (daily, 9:00 AM to 3:00 PM Mountain Time) or email billing@nabsolutions.ca.\n\nThank you for staying with NAB Solutions,\nThe NAB Solutions Team"
    );

    // Email to admin
    nab_send_membership_email(
        'billing@nabsolutions.ca',
        'Member Membership Auto-Resumed — ' . $name,
        "Member <strong>{$name}</strong> ({$email}) membership has been automatically resumed.\n\nUser ID: {$uid}"
    );
}, 10, 1 );

/* ═══════════════════════════════════════════════════════════
   AJAX: CANCEL MEMBERSHIP
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_mp_cancel', function() {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );

    $uid    = get_current_user_id();
    $reason = sanitize_textarea_field( $_POST['reason'] ?? '' );
    $user   = get_userdata( $uid );
    $email  = $user->user_email;
    $name   = get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ?: 'Member';

    update_user_meta( $uid, 'nab_membership_status', 'cancelled' );
    update_user_meta( $uid, 'nab_cancel_reason', $reason );
    update_user_meta( $uid, 'nab_cancel_date', current_time( 'mysql' ) );

    // Clear any pending resume
    wp_clear_scheduled_hook( 'nab_mp_auto_resume', [$uid] );
    delete_user_meta( $uid, 'nab_mp_resume_date' );

    if ( function_exists( 'nab_add_auto_notification' ) ) {
        nab_add_auto_notification( $uid, 'billing',
            '❌ Membership Cancelled',
            'Your membership has been cancelled. You retain access until the end of your current billing period.'
        );
    }

    // Email to member
    nab_send_membership_email(
        $email,
        'Your NAB Solutions Membership Has Been Cancelled',
        "Hi {$name},\n\nWe're sorry to see you go.\n\nThis confirms that your NAB Solutions membership has been successfully cancelled. You'll continue to have full access to your Member Dashboard and all active benefits until your current billing period ends this Friday. After that, you'll lose access to:\n\n- Your NAB Solutions Member Dashboard\n- Credit monitoring and credit score updates\n- Personalized credit rebuilding guidance and support\n- Our self-service matching tools, including the Lending Finder Program (LFP), Auto Loan Matcher, and Credit Card Matcher — helping you find financing options based on your profile\n- Exclusive member resources, educational videos, and tools\n- Priority access to new products, services, and member-exclusive offers\n\n<strong>Changed your mind?</strong> It only takes a minute to reactivate.\n\nPrefer to talk it through first? Our team is happy to help you find the right fit  whether that's pausing, adjusting your plan, or just answering questions. We're here seven days a week, 9:00 AM–3:00 PM (Mountain Time):\n📞 1-855-542-6078\n✉️ billing@nabsolutions.ca\n\nThank you for choosing NAB Solutions. We appreciate the opportunity to have supported your financial journey, and we'd love the chance to keep doing so.\n\nSincerely,\nThe NAB Solutions Team"
    );

    // Email to admin
    nab_send_membership_email(
        'billing@nabsolutions.ca',
        'Member Cancelled Membership — ' . $name,
        "Member <strong>{$name}</strong> ({$email}) has cancelled their membership.\n\n" .
        ( $reason ? "Cancellation reason: <strong>{$reason}</strong>\n\n" : '' ) .
        "User ID: {$uid}\nDate: " . current_time( 'M j, Y' )
    );

    wp_send_json_success( [
        'message' => 'Your membership has been cancelled. You retain access until your billing period ends.',
        'status'  => 'cancelled',
    ] );
} );

/* ═══════════════════════════════════════════════════════════
   AJAX: RESUME MEMBERSHIP (manual resume before auto-date)
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_mp_resume', function() {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );

    $uid   = get_current_user_id();
    $user  = get_userdata( $uid );
    $email = $user->user_email;
    $name  = get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ?: 'Member';

    update_user_meta( $uid, 'nab_membership_status', 'active' );
    delete_user_meta( $uid, 'nab_mp_resume_date' );
    delete_user_meta( $uid, 'nab_pause_weeks' );
    wp_clear_scheduled_hook( 'nab_mp_auto_resume', [$uid] );

    if ( function_exists( 'nab_add_auto_notification' ) ) {
        nab_add_auto_notification( $uid, 'billing',
            '▶️ Membership Resumed',
            'Your membership has been resumed and is now active again.'
        );
    }

    // Email to member
    nab_send_membership_email(
        $email,
        'Your NAB Solutions Membership Has Been Reactivated',
        "Hi {$name},\n\nYour NAB Solutions membership is now active again. You have full access to your Member Dashboard and all benefits, including credit monitoring, LFP, Auto Loan Matcher, Credit Card Matcher, and educational videos.\n\nYour weekly billing has resumed, and your next charge will be this Friday.\n\nQuestions or need help? Contact us at 1-855-542-6078 (daily, 9:00 AM to 3:00 PM Mountain Time) or email billing@nabsolutions.ca.\n\nThank you for staying with NAB Solutions,\nThe NAB Solutions Team"
    );

    // Email to admin
    nab_send_membership_email(
        'billing@nabsolutions.ca',
        'Member Resumed Membership Manually — ' . $name,
        "Member <strong>{$name}</strong> ({$email}) has manually resumed their membership.\n\nUser ID: {$uid}"
    );

    wp_send_json_success( [
        'message' => 'Your membership has been resumed successfully.',
        'status'  => 'active',
    ] );
} );

/* ═══════════════════════════════════════════════════════════
   BLOCK LOGIN WHILE MEMBERSHIP IS PAUSED
   Prevents a paused member from logging in, and force-logs-out
   anyone whose status flips to "paused" mid-session.
   ═══════════════════════════════════════════════════════════ */
add_filter( 'wp_authenticate_user', function( $user, $password ) {

    if ( is_wp_error( $user ) ) return $user;

    $status = get_user_meta( $user->ID, 'nab_membership_status', true ) ?: 'active';

    if ( $status === 'paused' ) {
        $resume = get_user_meta( $user->ID, 'nab_mp_resume_date', true );
        $resume_txt = $resume ? date( 'F j, Y', strtotime( $resume ) ) : 'soon';

        return new WP_Error(
            'nab_membership_paused',
            "Your membership is currently paused and will resume automatically on <strong>{$resume_txt}</strong>. Please contact support@nabsolutions.ca if you'd like to reactivate early."
        );
    }

    // NOTE: cancelled members intentionally keep login access — the cancel
    // email/notification promises "access until the end of your current
    // billing period." Only "paused" blocks login. If a hard cutoff for
    // cancelled members is wanted later, that needs a real billing-period
    // end date to check against, not an immediate block.

    return $user;
}, 20, 2 );

add_action( 'template_redirect', function() {
    if ( ! is_user_logged_in() ) return;
    if ( current_user_can( 'manage_options' ) ) return; // never lock out admins

    $uid    = get_current_user_id();
    $status = get_user_meta( $uid, 'nab_membership_status', true ) ?: 'active';

    if ( $status === 'paused' ) {
        wp_logout();
        wp_safe_redirect( home_url( '/?membership=paused' ) );
        exit;
    }
} );
