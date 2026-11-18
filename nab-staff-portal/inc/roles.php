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

/** Staff (not admins) are sent to the staff portal after logging in. */
add_filter( 'login_redirect', function ( $redirect, $requested, $user ) {
    if ( $user instanceof WP_User && array_intersect( [ 'nab_staff', 'nab_staff_manager' ], (array) $user->roles ) ) {
        return nab_staff_url();
    }
    return $redirect;
}, 99, 3 );
