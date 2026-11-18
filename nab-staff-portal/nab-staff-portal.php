<?php
/**
 * Plugin Name:  NAB Staff Portal
 * Description:  Staff-only area for the Customer Care team at /staff/. Adds the "NAB Staff" role. Version 0.1 is a design preview with sample data; the reply assistant, member lookup and documents are not connected yet.
 * Version:      0.1.0
 * Author:       NAB Solutions
 * License:      Private — All Rights Reserved
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * How to use
 * 1. Upload and activate. This creates the "NAB Staff" role and the /staff/ page.
 * 2. Users > Add New (or edit a user) and choose the role "NAB Staff".
 * 3. Staff log in on the normal login page and land on /staff/. Admins can open it
 *    from the "Staff Portal" link in the admin menu.
 * Members never see it: anyone without the staff permission is sent away.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NAB_STAFF_VERSION', '0.1.0' );
define( 'NAB_STAFF_CAP', 'nab_staff_portal' );

/* ── Role and permission ─────────────────────────────────── */
function nab_staff_add_role() {
    add_role( 'nab_staff', 'NAB Staff', [ 'read' => true, NAB_STAFF_CAP => true ] );
    $role = get_role( 'nab_staff' );
    if ( $role && ! $role->has_cap( NAB_STAFF_CAP ) ) $role->add_cap( NAB_STAFF_CAP );
    $admin = get_role( 'administrator' );
    if ( $admin ) $admin->add_cap( NAB_STAFF_CAP );
}

register_activation_hook( __FILE__, function () {
    nab_staff_add_role();
    nab_staff_rewrite();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules(); // the role stays, so staff accounts keep their role if it's turned back on
} );

// Re-create the role if it was removed, without needing to reactivate.
add_action( 'init', function () {
    if ( ! get_role( 'nab_staff' ) ) nab_staff_add_role();
} );

/* ── The /staff/ address ─────────────────────────────────── */
function nab_staff_rewrite() {
    add_rewrite_rule( '^staff/?$', 'index.php?nab_staff=1', 'top' );
}
add_action( 'init', 'nab_staff_rewrite' );
add_filter( 'query_vars', function ( $vars ) { $vars[] = 'nab_staff'; return $vars; } );

add_action( 'template_redirect', function () {
    if ( ! get_query_var( 'nab_staff' ) ) return;

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( home_url( '/staff/' ) ) );
        exit;
    }
    if ( ! current_user_can( NAB_STAFF_CAP ) ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'Staff portal is private' );
    if ( ! headers_sent() ) header( 'X-Robots-Tag: noindex, nofollow', true );

    $user  = wp_get_current_user();
    $name  = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
    $parts = preg_split( '/\s+/', $name );
    $init  = strtoupper( substr( $parts[0] ?? '', 0, 1 ) . substr( $parts[1] ?? '', 0, 1 ) ) ?: 'NS';

    $html = file_get_contents( __DIR__ . '/preview.html' );
    echo str_replace(
        [ '__FONT_URL__', '__NAME__', '__INIT__', '__LOGOUT__' ],
        [ esc_url( plugins_url( 'font.woff2', __FILE__ ) ), esc_html( $name ), esc_html( $init ), esc_url( wp_logout_url( home_url( '/' ) ) ) ],
        $html
    );
    exit;
} );

/* ── Staff land on /staff/ after logging in ──────────────── */
add_filter( 'login_redirect', function ( $redirect, $requested, $user ) {
    if ( $user instanceof WP_User && in_array( 'nab_staff', (array) $user->roles, true ) ) {
        return home_url( '/staff/' );
    }
    return $redirect;
}, 99, 3 );

/* ── Admin menu link ─────────────────────────────────────── */
add_action( 'admin_menu', function () {
    add_menu_page( 'Staff Portal', 'Staff Portal', NAB_STAFF_CAP, 'nab-staff-portal-link', '__return_null', 'dashicons-groups', 3 );
} );
add_action( 'admin_init', function () {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'nab-staff-portal-link' ) {
        wp_safe_redirect( home_url( '/staff/' ) );
        exit;
    }
}, 1 );
