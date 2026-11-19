<?php
/**
 * Roles and permissions.
 *
 * NAB Staff          uses the staff portal (reply assistant, member lookup, reads documents)
 * NAB Staff Manager  everything above, plus uploads and manages the team documents
 * Administrator      gets both permissions
 *
 * Screens check permissions, never role names, so a new role only needs the right caps.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Permission keys used across the plugin. */
function nab_staff_caps() {
    return [
        'use'    => 'nab_staff_portal',       // open the staff portal
        'manage' => 'nab_staff_manage_docs',  // upload and switch documents on/off
    ];
}

function nab_staff_setup_roles() {
    $c = nab_staff_caps();
    add_role( 'nab_staff', 'NAB Staff', [ 'read' => true, $c['use'] => true ] );
    add_role( 'nab_staff_manager', 'NAB Staff Manager', [ 'read' => true, $c['use'] => true, $c['manage'] => true ] );

    // Make sure existing roles have the caps (add_role does nothing if the role exists).
    foreach ( [ 'nab_staff' => [ 'use' ], 'nab_staff_manager' => [ 'use', 'manage' ], 'administrator' => [ 'use', 'manage' ] ] as $role => $keys ) {
        $r = get_role( $role );
        if ( ! $r ) continue;
        foreach ( $keys as $k ) {
            if ( ! $r->has_cap( $c[ $k ] ) ) $r->add_cap( $c[ $k ] );
        }
    }
    update_option( 'nab_staff_roles_version', NAB_STAFF_VERSION, false );
}

// Keep roles up to date after a plugin update, without needing to reactivate.
add_action( 'init', function () {
    if ( get_option( 'nab_staff_roles_version' ) !== NAB_STAFF_VERSION ) nab_staff_setup_roles();
} );

function nab_staff_can( $key = 'use', $user_id = 0 ) {
    $cap = nab_staff_caps()[ $key ] ?? $key;
    return $user_id ? user_can( $user_id, $cap ) : current_user_can( $cap );
}

/** True for people whose job is the staff portal: NAB Staff or NAB Staff Manager, and not an admin. */
function nab_staff_is_staff_only( $user = null ) {
    $user = $user ?: wp_get_current_user();
    if ( ! $user || ! $user->exists() || user_can( $user, 'manage_options' ) ) return false;
    return (bool) array_intersect( [ 'nab_staff', 'nab_staff_manager' ], (array) $user->roles );
}

/**
 * Staff land on the staff portal, wherever they log in from. The member
 * portal sends logged-in people from its home and login pages to the member
 * dashboard, so besides the login redirect below, staff opening the home page
 * or any member portal page are sent to /staff/. Admins are never redirected.
 */
add_action( 'template_redirect', function () {
    if ( get_query_var( 'nab_staff' ) || ! is_user_logged_in() || ! nab_staff_is_staff_only() ) return;
    $is_portal_page = false;
    if ( is_page() && function_exists( 'nab_portal_tools' ) ) {
        $tpl            = get_post_meta( get_queried_object_id(), '_wp_page_template', true );
        $is_portal_page = isset( nab_portal_tools()[ $tpl ] );
    }
    if ( $is_portal_page || is_front_page() || is_home() ) {
        wp_safe_redirect( nab_staff_url() );
        exit;
    }
}, 1 );

/** Staff (not admins) are sent to the staff portal after logging in. */
add_filter( 'login_redirect', function ( $redirect, $requested, $user ) {
    if ( $user instanceof WP_User && nab_staff_is_staff_only( $user ) ) {
        return nab_staff_url();
    }
    return $redirect;
}, 9999, 3 );
