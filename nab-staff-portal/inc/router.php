<?php
/**
 * Page addresses and access.
 *   /staff/            first screen the person can use
 *   /staff/{slug}/     a screen from nab_staff_screens()
 * Logged out visitors go to the login page; members (no staff permission)
 * go to the home page. Staff pages are never cached or indexed.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function nab_staff_add_rewrites() {
    add_rewrite_rule( '^staff/?$', 'index.php?nab_staff=reply', 'top' );
    add_rewrite_rule( '^staff/([a-z0-9-]+)/?$', 'index.php?nab_staff=$matches[1]', 'top' );
}
add_action( 'init', 'nab_staff_add_rewrites' );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'nab_staff';
    return $vars;
} );

// If the address rules were never saved (plugin copied by FTP), save them once.
add_action( 'init', function () {
    if ( get_option( 'nab_staff_rewrites' ) !== NAB_STAFF_VERSION ) {
        flush_rewrite_rules( false );
        update_option( 'nab_staff_rewrites', NAB_STAFF_VERSION, false );
    }
}, 99 );

add_action( 'template_redirect', function () {
    $slug = get_query_var( 'nab_staff' );
    if ( ! $slug ) return;

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( nab_staff_url( $slug === 'reply' ? '' : $slug ) ) );
        exit;
    }
    if ( ! nab_staff_can( 'use' ) ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    $screens = nab_staff_screens();
    $screen  = $screens[ $slug ] ?? null;
    if ( ! $screen || ! empty( $screen['link'] ) || ! nab_staff_can( $screen['cap'] ?? 'use' ) ) {
        wp_safe_redirect( nab_staff_url() );
        exit;
    }

    nocache_headers();
    do_action( 'litespeed_control_set_nocache', 'Staff portal is private' );
    if ( ! headers_sent() ) header( 'X-Robots-Tag: noindex, nofollow', true );

    $GLOBALS['nab_staff_screen'] = $slug;
    include NAB_STAFF_DIR . 'templates/layout.php';
    exit;
} );

/* Admin menu link to the portal. */
add_action( 'admin_menu', function () {
    add_menu_page( 'Staff Portal', 'Staff Portal', nab_staff_caps()['use'], 'nab-staff-portal', '__return_null', 'dashicons-groups', 3 );
} );
add_action( 'admin_init', function () {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'nab-staff-portal' ) {
        wp_safe_redirect( nab_staff_url() );
        exit;
    }
}, 1 );
