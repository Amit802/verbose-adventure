<?php
/**
 * Where the staff portal keeps its data.
 *
 * nab_staff_doc    a team document (title, uploaded file, the text the assistant
 *                  reads, on/off for the assistant). Files live in a private folder.
 * nab_staff_topic  a team discussion topic (type, status, votes, comments).
 *
 * Both are private post types with no admin screens of their own: everything is
 * managed from /staff/.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function () {
    $args = [ 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'query_var' => false, 'supports' => [ 'title', 'editor', 'author' ] ];
    register_post_type( 'nab_staff_doc', $args + [ 'label' => 'Staff documents' ] );
    register_post_type( 'nab_staff_topic', $args + [ 'label' => 'Staff topics' ] );
} );

/** Private folder for uploaded documents, closed to the web. */
function nab_staff_docs_dir() {
    $up  = wp_upload_dir( null, false );
    $dir = trailingslashit( $up['basedir'] ) . 'nab-staff-docs';
    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
    }
    if ( ! file_exists( $dir . '/.htaccess' ) ) {
        @file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
        @file_put_contents( $dir . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><authorization><deny users="*"/></authorization></system.webServer></configuration>' );
        @file_put_contents( $dir . '/index.php', "<?php // Silence.\n" );
    }
    return $dir;
}

/** "2 hours ago", "Yesterday", "Nov 12". */
function nab_staff_when( $mysql_gmt ) {
    $ts = strtotime( $mysql_gmt . ' UTC' );
    if ( ! $ts ) return '';
    $diff = time() - $ts;
    if ( $diff < 60 ) return 'Just now';
    if ( $diff < HOUR_IN_SECONDS ) return floor( $diff / 60 ) . 'm ago';
    if ( $diff < DAY_IN_SECONDS ) return floor( $diff / HOUR_IN_SECONDS ) . 'h ago';
    if ( $diff < 2 * DAY_IN_SECONDS ) return 'Yesterday';
    return wp_date( 'M j', $ts );
}

/** A person's display name and initials for the team views. */
function nab_staff_person( $user_id ) {
    $u = get_userdata( (int) $user_id );
    if ( ! $u ) return [ 'name' => 'Former team member', 'init' => '?' ];
    $name  = trim( $u->first_name . ' ' . ( $u->last_name ? substr( $u->last_name, 0, 1 ) . '.' : '' ) ) ?: $u->display_name;
    $parts = preg_split( '/\s+/', trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name );
    $init  = strtoupper( substr( $parts[0] ?? '', 0, 1 ) . substr( $parts[1] ?? '', 0, 1 ) ) ?: '?';
    return [ 'name' => $name, 'init' => $init ];
}

/** The member portal's Dashboard page, where the ACF settings live (same as Nabby's OpenAI key). */
function nab_staff_dashboard_page_id() {
    $pages = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dashboard', 'number' => 1 ] );
    return ! empty( $pages ) ? (int) $pages[0]->ID : 0;
}

/**
 * The logo in the staff portal sidebar. Returns [ 'url', 'wide', 'source' ]:
 *   1. "Staff Portal logo" (ACF, Pages > Dashboard > Staff Portal box)
 *   2. the website Logo (Appearance > Customize > Site Identity)
 *   3. the Site Icon (square)
 *   empty url = the "NAB" mark.
 */
function nab_staff_logo() {
    $page = function_exists( 'get_field' ) ? nab_staff_dashboard_page_id() : 0;
    if ( $page ) {
        $f   = get_field( 'nab_staff_logo', $page );
        $url = is_array( $f ) ? ( $f['sizes']['medium'] ?? $f['url'] ?? '' ) : ( is_numeric( $f ) ? wp_get_attachment_image_url( (int) $f, 'medium' ) : (string) $f );
        if ( $url ) return [ 'url' => $url, 'wide' => true, 'source' => 'acf' ];
    }
    $id = (int) get_theme_mod( 'custom_logo' );
    if ( $id ) {
        $url = wp_get_attachment_image_url( $id, 'medium' ) ?: wp_get_attachment_image_url( $id, 'full' );
        if ( $url ) return [ 'url' => $url, 'wide' => true, 'source' => 'site' ];
    }
    $icon = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 96 ) : '';
    return [ 'url' => $icon ?: '', 'wide' => false, 'source' => $icon ? 'icon' : 'none' ];
}

/**
 * ACF box "Staff Portal" on the Dashboard page (next to the member portal's
 * settings). Registered in code, so nothing to set up in ACF.
 */
add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;
    acf_add_local_field_group( [
        'key'      => 'group_nab_staff_portal',
        'title'    => 'Staff Portal',
        'fields'   => [ [
            'key'           => 'field_nab_staff_logo',
            'label'         => 'Staff Portal logo',
            'name'          => 'nab_staff_logo',
            'type'          => 'image',
            'instructions'  => 'Shown at the top left of the staff portal (/staff/). Leave empty to use the website logo from Appearance > Customize > Site Identity. A wide PNG with a transparent background, at least 300 pixels wide, looks best.',
            'return_format' => 'id',
            'preview_size'  => 'medium',
            'library'       => 'all',
            'mime_types'    => 'png,jpg,jpeg,webp',
        ] ],
        'location' => [ [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'nab-dashboard' ] ] ],
        'position' => 'normal',
        'menu_order' => 90,
    ] );
} );

// v1.3.0 had a logo upload in the portal itself; remove its file and setting once.
add_action( 'init', function () {
    $old = get_option( 'nab_staff_logo' );
    if ( ! $old ) return;
    if ( is_array( $old ) && ! empty( $old['file'] ) ) {
        $up = wp_upload_dir( null, false );
        @unlink( trailingslashit( $up['basedir'] ) . 'nab-staff-brand/' . basename( $old['file'] ) );
    }
    delete_option( 'nab_staff_logo' );
} );
