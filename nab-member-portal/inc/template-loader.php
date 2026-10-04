<?php
/**
 * Page templates — driven by the tool registry (inc/tools.php).
 * Add new tools there, not here.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'theme_page_templates', function( $templates ) {
    foreach ( nab_tools() as $slug => $tool ) {
        $templates[ $slug ] = $tool['name'];
    }
    return $templates;
} );

add_filter( 'template_include', function( $template ) {
    if ( ! is_page() ) return $template;
    $slug  = get_post_meta( get_the_ID(), '_wp_page_template', true );
    $tools = nab_tools();
    if ( isset( $tools[ $slug ] ) ) {
        $file = NAB_DIR . 'templates/' . $tools[ $slug ]['file'];
        if ( file_exists( $file ) ) return $file;
    }
    return $template;
} );
