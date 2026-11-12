<?php
/** Local renderer: /page/<template-key> shows that portal page; /admin-ajax.php runs plugin AJAX. */
$uri    = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$plugin = dirname( __DIR__, 2 ) . '/nab-member-portal';
$types  = [ 'js' => 'text/javascript', 'css' => 'text/css', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'pdf' => 'application/pdf', 'webp' => 'image/webp' ];
if ( strpos( $uri, '/plugin/' ) === 0 || strpos( $uri, '/vendor/' ) === 0 ) {
    $f = strpos( $uri, '/vendor/' ) === 0 ? __DIR__ . $uri : $plugin . substr( $uri, 7 );
    if ( is_file( $f ) ) { header( 'Content-Type: ' . ( $types[ pathinfo( $f, PATHINFO_EXTENSION ) ] ?? 'application/octet-stream' ) ); readfile( $f ); return true; }
    http_response_code( 404 ); return true;
}
if ( preg_match( '#^/page/([a-z0-9-]+)#', $uri, $m ) ) $GLOBALS['NAB_TPL'] = $m[1];
require __DIR__ . '/wp.php';
// Load the same modules, in the same order, as nab-member-portal.php
preg_match_all( "/require_once NAB_DIR \. 'inc\/([a-z0-9-]+)\.php';/", file_get_contents( $plugin . '/nab-member-portal.php' ), $nab_mods );
foreach ( $nab_mods[1] as $f ) {
    require $plugin . '/inc/' . $f . '.php';
}
do_action( 'plugins_loaded' ); do_action( 'init' );
if ( $uri === '/admin-ajax.php' ) { do_action( 'wp_ajax_' . ( $_REQUEST['action'] ?? '' ) ); exit; }
do_action( 'template_redirect' );
$tools = nab_portal_tools();
$tpl   = $GLOBALS['W']['tpl'];
if ( ! isset( $tools[ $tpl ] ) ) { echo 'unknown template'; exit; }
require $plugin . '/templates/' . $tools[ $tpl ]['file'];
