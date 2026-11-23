<?php
/**
 * Portal settings (managers): the staff portal logo.
 * An uploaded logo is used first; "Use the site logo" goes back to
 * Appearance > Customize > Site Identity (see nab_staff_logo()).
 * Logos are PNG, JPG or WebP (no SVG, it can carry scripts), max 2 MB, kept
 * in uploads/nab-staff-brand/ (public, it is shown on every page).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NAB_STAFF_LOGO_MAX_BYTES', 2 * 1024 * 1024 );

function nab_staff_brand_dir() {
    $up = wp_upload_dir( null, false );
    return [ trailingslashit( $up['basedir'] ) . 'nab-staff-brand', trailingslashit( $up['baseurl'] ) . 'nab-staff-brand' ];
}

/** The uploaded staff portal logo, [ 'url', 'file' ] or null. */
function nab_staff_own_logo() {
    $o = get_option( 'nab_staff_logo' );
    if ( ! is_array( $o ) || empty( $o['file'] ) ) return null;
    list( $dir, $url ) = nab_staff_brand_dir();
    return is_file( $dir . '/' . basename( $o['file'] ) ) ? [ 'url' => $url . '/' . rawurlencode( basename( $o['file'] ) ), 'file' => basename( $o['file'] ) ] : null;
}

function nab_staff_logo_upload() {
    $f = $_FILES['logo'] ?? null;
    if ( ! $f || ! empty( $f['error'] ) || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) return [ 'error' => 'Please choose an image.' ];
    if ( (int) $f['size'] > NAB_STAFF_LOGO_MAX_BYTES ) return [ 'error' => 'The image is bigger than 2 MB.' ];
    $info  = @getimagesize( $f['tmp_name'] );
    $types = [ IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp' ];
    if ( ! $info || ! isset( $types[ $info[2] ] ) ) return [ 'error' => 'Please upload a PNG, JPG or WebP image. A PNG with a transparent background looks best.' ];
    if ( $info[0] < 60 || $info[1] < 20 ) return [ 'error' => 'The image is too small. Please use one at least 300 pixels wide.' ];
    list( $dir ) = nab_staff_brand_dir();
    if ( ! is_dir( $dir ) ) wp_mkdir_p( $dir );
    $name = 'logo-' . wp_generate_password( 10, false ) . '.' . $types[ $info[2] ];
    if ( ! @move_uploaded_file( $f['tmp_name'], $dir . '/' . $name ) ) return [ 'error' => 'The image could not be saved. Please try again.' ];
    nab_staff_logo_remove_file();
    update_option( 'nab_staff_logo', [ 'file' => $name, 'by' => get_current_user_id(), 'at' => time() ], false );
    return [ 'logo' => nab_staff_logo(), 'message' => 'Logo updated.' ];
}

function nab_staff_logo_reset() {
    nab_staff_logo_remove_file();
    delete_option( 'nab_staff_logo' );
    return [ 'logo' => nab_staff_logo(), 'message' => 'Now using the site logo.' ];
}

function nab_staff_logo_remove_file() {
    $own = nab_staff_own_logo();
    if ( $own ) @unlink( nab_staff_brand_dir()[0] . '/' . $own['file'] );
}
