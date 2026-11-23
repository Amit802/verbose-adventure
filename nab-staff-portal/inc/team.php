<?php
/**
 * Team: managers add people to the staff portal, change their role, resend
 * the invite or remove access. Admin accounts and your own account can't be
 * changed here (so nobody locks themselves or the site owner out).
 *
 * Staff accounts are normal WordPress users with the NAB Staff or NAB Staff
 * Manager role. They get a staff invite email with a set-password link, never
 * the member welcome email. Removing someone takes away their role and logs
 * them out; adding the same email later gives access back.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Roles a manager can give, role => label. */
function nab_staff_team_roles() {
    return [ 'nab_staff' => 'Customer Care', 'nab_staff_manager' => 'Care Manager' ];
}

/** Everyone with access to the staff portal, admins first, then by name. */
function nab_staff_team() {
    $ids = get_users( [ 'role__in' => array_merge( [ 'administrator' ], array_keys( nab_staff_team_roles() ) ), 'fields' => 'ID', 'number' => 200 ] );
    $out = [];
    foreach ( $ids as $id ) {
        $v = nab_staff_team_view( (int) $id );
        if ( $v ) $out[] = $v;
    }
    usort( $out, function ( $a, $b ) { return [ ! $a['admin'], strtolower( $a['name'] ) ] <=> [ ! $b['admin'], strtolower( $b['name'] ) ]; } );
    return $out;
}

function nab_staff_team_view( $id ) {
    $u = get_userdata( $id );
    if ( ! $u ) return null;
    $admin = user_can( $u, 'manage_options' );
    $role  = '';
    foreach ( array_keys( nab_staff_team_roles() ) as $r ) if ( in_array( $r, (array) $u->roles, true ) ) $role = $r;
    if ( ! $admin && ! $role ) return null;
    $last  = get_user_meta( $id, 'nab_last_login', true );
    $name  = trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name;
    $parts = preg_split( '/\s+/', $name );
    return [
        'id'        => $id,
        'name'      => $name,
        'init'      => strtoupper( substr( $parts[0] ?? '', 0, 1 ) . substr( $parts[1] ?? '', 0, 1 ) ) ?: '?',
        'email'     => $u->user_email,
        'role'      => $admin ? 'admin' : $role,
        'roleLabel' => $admin ? 'Site admin' : nab_staff_team_roles()[ $role ],
        'admin'     => $admin,
        'me'        => $id === get_current_user_id(),
        'lastLogin' => $last ? wp_date( 'M j, Y', strtotime( $last ) ) : '',
        'invited'   => ! $last && get_user_meta( $id, 'nab_staff_invited', true ) ? wp_date( 'M j', (int) get_user_meta( $id, 'nab_staff_invited', true ) ) : '',
    ];
}

/** Can the current person change this account? */
function nab_staff_team_editable( $u ) {
    if ( ! $u || $u->ID === get_current_user_id() ) return 'You can\'t change your own account here.';
    if ( user_can( $u, 'manage_options' ) ) return 'Site admin accounts can only be changed in WordPress.';
    if ( ! array_intersect( array_keys( nab_staff_team_roles() ), (array) $u->roles ) ) return 'This person isn\'t on the team.';
    return '';
}

function nab_staff_team_add() {
    $first = sanitize_text_field( wp_unslash( $_POST['first'] ?? '' ) );
    $last  = sanitize_text_field( wp_unslash( $_POST['last'] ?? '' ) );
    $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    $role  = sanitize_key( $_POST['role'] ?? '' );
    if ( $first === '' ) return [ 'error' => 'Please add their first name.' ];
    if ( ! is_email( $email ) ) return [ 'error' => 'Please add a valid email address.' ];
    if ( ! isset( nab_staff_team_roles()[ $role ] ) ) return [ 'error' => 'Please choose a role.' ];

    $u = get_user_by( 'email', $email );
    if ( $u ) {
        if ( user_can( $u, 'manage_options' ) ) return [ 'error' => 'This email is a site admin account, it already has access.' ];
        if ( array_intersect( array_keys( nab_staff_team_roles() ), (array) $u->roles ) ) return [ 'error' => 'This person is already on the team.' ];
        if ( ! get_user_meta( $u->ID, 'nab_staff_removed', true ) ) {
            return [ 'error' => 'This email belongs to a member account. Please use a different email (for example their work email) for the staff portal.' ];
        }
        // A former team member coming back.
        $u->set_role( $role );
        delete_user_meta( $u->ID, 'nab_staff_removed' );
        $id = $u->ID;
    } else {
        $login = sanitize_user( strtolower( strstr( $email, '@', true ) ), true ) ?: 'staff';
        $base  = $login;
        for ( $i = 2; username_exists( $login ); $i++ ) $login = $base . $i;
        // Stop the member portal's member welcome email for this account (it checks this flag).
        add_action( 'user_register', function ( $uid ) { update_user_meta( $uid, 'nab_welcome_sent', 1 ); }, 1 );
        $id = wp_insert_user( [
            'user_login'   => $login,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password( 24, true, true ),
            'first_name'   => $first,
            'last_name'    => $last,
            'display_name' => trim( $first . ' ' . $last ),
            'role'         => $role,
        ] );
        if ( is_wp_error( $id ) ) return [ 'error' => 'The account couldn\'t be created: ' . $id->get_error_message() ];
        update_user_meta( $id, 'nab_welcome_sent', 1 );
    }
    $sent = nab_staff_team_send_invite( $id );
    return [
        'person'  => nab_staff_team_view( $id ),
        'message' => $sent ? trim( $first ) . ' has been added. They\'ll get an email to set their password.' : trim( $first ) . ' has been added, but the email didn\'t send. Try "Resend invite".',
    ];
}

function nab_staff_team_role() {
    $u    = get_userdata( absint( $_POST['id'] ?? 0 ) );
    $role = sanitize_key( $_POST['role'] ?? '' );
    if ( $msg = nab_staff_team_editable( $u ) ) return [ 'error' => $msg ];
    if ( ! isset( nab_staff_team_roles()[ $role ] ) ) return [ 'error' => 'Please choose a role.' ];
    $u->set_role( $role );
    return [ 'person' => nab_staff_team_view( $u->ID ) ];
}

function nab_staff_team_remove() {
    $u = get_userdata( absint( $_POST['id'] ?? 0 ) );
    if ( $msg = nab_staff_team_editable( $u ) ) return [ 'error' => $msg ];
    $u->set_role( '' );
    update_user_meta( $u->ID, 'nab_staff_removed', time() );
    if ( class_exists( 'WP_Session_Tokens' ) ) WP_Session_Tokens::get_instance( $u->ID )->destroy_all();
    return [ 'removed' => $u->ID ];
}

function nab_staff_team_resend() {
    $u = get_userdata( absint( $_POST['id'] ?? 0 ) );
    if ( $msg = nab_staff_team_editable( $u ) ) return [ 'error' => $msg ];
    $lock = 'nab_staff_invite_' . $u->ID;
    if ( get_transient( $lock ) ) return [ 'error' => 'An invite was sent a few minutes ago. Please wait 10 minutes before sending another.' ];
    if ( ! nab_staff_team_send_invite( $u->ID ) ) return [ 'error' => 'The email didn\'t send. Please try again later.' ];
    return [ 'message' => 'Invite sent to ' . $u->user_email . '.', 'person' => nab_staff_team_view( $u->ID ) ];
}

/** Staff invite with a set-password link (WordPress reset key, valid 24 hours). */
function nab_staff_team_send_invite( $id ) {
    $u = get_userdata( $id );
    if ( ! $u ) return false;
    $key = get_password_reset_key( $u );
    if ( is_wp_error( $key ) ) return false;
    $link  = add_query_arg( [ 'action' => 'rp', 'key' => $key, 'login' => rawurlencode( $u->user_login ) ], wp_login_url() );
    $first = $u->first_name ?: 'there';
    $by    = wp_get_current_user();
    $by    = trim( $by->first_name ) ?: 'Your manager';
    $host  = wp_parse_url( home_url(), PHP_URL_HOST );
    $p     = 'style="color:#424242;line-height:1.7;margin:0 0 16px;"';
    $body  = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">'
        . '<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:40px 20px;">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:#0D5C9B;padding:24px 40px;text-align:center;"><h1 style="color:#fff;margin:0;font-size:19px;">NAB Solutions Staff Portal</h1></td></tr>'
        . '<tr><td style="padding:34px 40px;">'
        . '<p ' . $p . '>Hi ' . esc_html( $first ) . ',</p>'
        . '<p ' . $p . '>' . esc_html( $by ) . ' has added you to the NAB Staff Portal. It\'s where the Customer Care team answers member emails, looks up members and shares our policies.</p>'
        . '<p ' . $p . '>Click below to set your password. After that you can log in any time at ' . esc_html( $host ) . ' and you\'ll go straight to the staff portal.</p>'
        . '<a href="' . esc_url( $link ) . '" style="display:inline-block;background:#0D5C9B;color:#fff;padding:14px 30px;border-radius:8px;text-decoration:none;font-weight:700;margin-bottom:22px;">Set my password</a>'
        . '<p style="color:#64748b;font-size:13px;margin:0 0 18px;">This link works for 24 hours. If it runs out, use "Forgot password" on the login page, or ask your manager to send a new invite.</p>'
        . '<p style="color:#64748b;font-size:12px;margin:0 0 18px;word-break:break-all;">Or copy this link: ' . esc_url( $link ) . '</p>'
        . '<p style="color:#424242;margin:0;">NAB Solutions</p>'
        . '</td></tr></table></td></tr></table></body></html>';
    $ok = wp_mail( $u->user_email, 'You\'ve been added to the NAB Staff Portal', $body, [ 'Content-Type: text/html; charset=UTF-8', 'From: NAB Solutions <noreply@' . $host . '>' ] );
    if ( $ok ) {
        update_user_meta( $id, 'nab_staff_invited', time() );
        set_transient( 'nab_staff_invite_' . $id, 1, 10 * MINUTE_IN_SECONDS );
    }
    return (bool) $ok;
}

/**
 * People made staff in WordPress (Users > Add New with a staff role) also skip
 * the member welcome email. Runs before the member portal sends it at shutdown.
 */
add_action( 'user_register', function ( $uid ) {
    $u = get_userdata( $uid );
    if ( $u && array_intersect( array_keys( nab_staff_team_roles() ), (array) $u->roles ) ) update_user_meta( $uid, 'nab_welcome_sent', 1 );
}, 20 );

/** Someone removed from the team can't log in any more (until they're added back). */
add_filter( 'authenticate', function ( $user ) {
    if ( $user instanceof WP_User && get_user_meta( $user->ID, 'nab_staff_removed', true ) && ! array_filter( (array) $user->roles ) ) {
        return new WP_Error( 'nab_staff_removed', 'Your access to the staff portal has been removed. Please speak to your manager.' );
    }
    return $user;
}, 99 );
