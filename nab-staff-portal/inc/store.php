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

/**
 * The site's logo from Appearance > Customize > Site Identity.
 * Returns [ 'url', 'wide', 'source' ]: a logo uploaded in Portal Settings first, then the site Logo (usually a wide
 * wordmark), then the Site Icon (square). Empty url = show the "NAB" mark.
 */
function nab_staff_logo() {
    if ( function_exists( 'nab_staff_own_logo' ) && ( $own = nab_staff_own_logo() ) ) return [ 'url' => $own['url'], 'wide' => true, 'source' => 'portal' ];
    $id = (int) get_theme_mod( 'custom_logo' );
    if ( $id ) {
        $url = wp_get_attachment_image_url( $id, 'medium' ) ?: wp_get_attachment_image_url( $id, 'full' );
        if ( $url ) return [ 'url' => $url, 'wide' => true, 'source' => 'site' ];
    }
    $icon = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 96 ) : '';
    return [ 'url' => $icon ?: '', 'wide' => false, 'source' => $icon ? 'icon' : 'none' ];
}
