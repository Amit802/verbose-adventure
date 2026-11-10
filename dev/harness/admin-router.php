<?php
/**
 * Local Users > Member Access screen with several fake members, for browser tests.
 *   php -S 127.0.0.1:8093 dev/harness/admin-router.php
 *   GET  /admin            the Member Access page (query args as in WordPress)
 *   POST /admin-post.php   the page's forms (real file uploads work)
 *   GET  /reset            fresh members
 * State lives in admin-state.json between requests.
 */
require __DIR__ . '/member-access-stubs.php';
$P = dirname( __DIR__, 2 ) . '/nab-member-portal/inc/';
foreach ( [ 'memberpress.php', 'member-access.php' ] as $f ) {
    $src = str_replace( "if ( ! defined( 'ABSPATH' ) ) exit;", '', file_get_contents( $P . $f ) );
    eval( '?>' . preg_replace( '/\bexit;/', 'throw new StopRequest;', $src ) );
}
// Real requests: nonces come from the page, uploads are real.
function admin_url_local( $u ) { return str_replace( 'https://members.example/wp-admin/', '/', $u ); }

$state = __DIR__ . '/admin-state.json';
$uri   = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( $uri === '/reset' || ! file_exists( $state ) ) {
    mk_user( 1, 'admin@nab.ca', 'Ada', 'Admin', true );
    $people = [ [ 'mark@mail.com', 'Mark', 'Active' ], [ 'pia@mail.com', 'Pia', 'Paused' ], [ 'eve@mail.com', 'Eve', 'Ending' ], [ 'john@mail.com', 'John', 'Smith' ], [ 'olga@mail.com', 'Olga', 'Old' ] ];
    foreach ( $people as $i => $p ) mk_user( 10 + $i, $p[0], $p[1], $p[2] );
    update_user_meta( 11, 'nab_membership_status', 'paused' );
    nab_access_cancel( 12, nab_access_next_billing_day(), '', 'portal' );
    update_user_meta( 14, 'nab_membership_status', 'cancelled' ); update_user_meta( 14, 'nab_cancel_date', '2026-10-14 10:00:00' );
    for ( $i = 0; $i < 30; $i++ ) mk_user( 100 + $i, "member$i@mail.com", 'Member', (string) $i );
} else {
    $s = json_decode( file_get_contents( $state ), true );
    $GLOBALS['USERS'] = $s['users']; $GLOBALS['META'] = $s['meta']; $GLOBALS['TRANS'] = $s['trans'];
}
$GLOBALS['CUR'] = 1;
$save = function () use ( $state ) { file_put_contents( $state, json_encode( [ 'users' => $GLOBALS['USERS'], 'meta' => $GLOBALS['META'], 'trans' => $GLOBALS['TRANS'] ] ) ); };

if ( $uri === '/reset' ) { $save(); header( 'Location: /admin' ); return true; }

if ( $uri === '/admin-post.php' ) {
    $action = $_REQUEST['action'] ?? '';
    $out = run( function () use ( $action ) { do_action( 'admin_post_' . $action ); } );
    $save();
    if ( isset( $out['redirect'] ) ) { header( 'Location: ' . str_replace( 'https://members.example/wp-admin/users.php', '/admin', $out['redirect'] ) ); return true; }
    if ( isset( $out['die'] ) ) { http_response_code( 403 ); echo esc_html( $out['die'] ); return true; }
    return true; // file downloads already echoed
}

// The page itself
ob_start();
$GLOBALS['SCREEN'] = 'users';
do_action( 'admin_notices' );
nab_access_admin_page();
$html = ob_get_clean();
$html = str_replace( [ 'https://members.example/wp-admin/admin-post.php', 'https://members.example/wp-admin/users.php' ], [ '/admin-post.php', '/admin' ], $html );
$save();
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Member Access</title><style>
body{margin:0;background:#f0f0f1;font:13px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;color:#1d2327}.wrap{margin:10px 20px}h1{font-size:23px;font-weight:400}
.button{display:inline-flex;align-items:center;padding:0 10px;min-height:30px;border:1px solid #2271b1;border-radius:3px;background:#f6f7f7;color:#2271b1;cursor:pointer;font-size:13px;text-decoration:none}.button-primary{background:#2271b1;color:#fff}
.button-link{background:none;border:0;color:#2271b1;text-decoration:underline;cursor:pointer}.widefat{width:100%;border-collapse:collapse;border:1px solid #c3c4c7;background:#fff}.widefat td,.widefat th{padding:8px 10px;text-align:left}.striped>tbody>:nth-child(odd){background:#f6f7f7}
.notice{background:#fff;border-left:4px solid #00a32a;padding:1px 12px;margin:5px 0 15px}.notice-warning{border-left-color:#dba617}.notice-error{border-left-color:#d63638}.description{color:#646970}input[type=text],input[type=search],input[type=date],select{padding:0 8px;min-height:30px;border:1px solid #8c8f94;border-radius:4px}.check-column{width:2.2em}
</style></head><body><?php echo $html; ?></body></html>
