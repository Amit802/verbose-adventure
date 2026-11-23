<?php
$uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$P   = dirname( __DIR__, 3 ) . '/nab-staff-portal';
if ( strpos( $uri, '/plug/' ) === 0 ) { $f = $P . substr( $uri, 5 ); $t = [ 'css' => 'text/css', 'js' => 'text/javascript', 'woff2' => 'font/woff2' ]; if ( is_file( $f ) ) { header( 'Content-Type: ' . ( $t[ pathinfo( $f, PATHINFO_EXTENSION ) ] ?? 'text/plain' ) ); readfile( $f ); } return true; }
if ( $uri === '/login' ) { setcookie( 'as', (string) (int) ( $_GET['as'] ?? 0 ), 0, '/' ); header( 'Location: /staff/' . ( $_GET['to'] ?? '' ) ); return true; }
if ( $uri === '/reset' ) { @unlink( __DIR__ . '/state.json' ); array_map( 'unlink', glob( __DIR__ . '/uploads/nab-staff-docs/*' ) ?: [] ); echo 'ok'; return true; }
require __DIR__ . '/wp.php';
if ( ! $GLOBALS['S'] ) { require __DIR__ . '/seed.php'; save_state(); }
require $P . '/nab-staff-portal.php';
do_action( 'init' );
try {
  if ( $uri === '/wp-admin/admin-ajax.php' ) { do_action( 'wp_ajax_' . ( $_REQUEST['action'] ?? '' ) ); exit; }
  $u = trim( $uri, '/' );
  if ( $u === 'staff' ) $GLOBALS['QV'] = 'home'; elseif ( preg_match( '#^staff/([a-z0-9-]+)$#', $u, $m ) ) $GLOBALS['QV'] = $m[1];
  do_action( 'template_redirect' );
  echo 'not a staff page';
} catch ( Stop $e ) {}
