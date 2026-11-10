<?php
// Minimal multi-user WordPress stand-in for testing inc/member-access.php from the CLI.
define( 'ABSPATH', __DIR__ . '/' );
date_default_timezone_set( 'UTC' );
$GLOBALS['NOW']   = strtotime( '2026-11-08 12:00:00 America/Edmonton' );
$GLOBALS['H']     = [];   // hooks
$GLOBALS['USERS'] = [];   // id => [email, login, display, admin]
$GLOBALS['META']  = [];   // id => key => value
$GLOBALS['OPT']   = [];
$GLOBALS['MAIL']  = [];
$GLOBALS['CUR']   = 0;
$GLOBALS['CRON']  = [];
$GLOBALS['OUT']   = null; // redirect / json result

class StopRequest extends Exception {}
class WP_Error { public $code, $msg; function __construct( $c = '', $m = '' ) { $this->code = $c; $this->msg = $m; } function get_error_message() { return $this->msg; } }
class WP_User { public $ID, $user_email, $user_login, $display_name, $first_name = '', $last_name = ''; }
class WP_Session_Tokens { public $id; static $destroyed = []; static function get_instance( $id ) { $o = new self; $o->id = $id; return $o; } function destroy_all() { self::$destroyed[] = $this->id; } }
class DummyScreen { public $id; }

function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['H'][ $h ][ $p ][] = [ $cb, $a ]; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { add_action( $h, $cb, $p, $a ); }
function apply_filters( $h, $v, ...$args ) {
    if ( empty( $GLOBALS['H'][ $h ] ) ) return $v;
    ksort( $GLOBALS['H'][ $h ] );
    foreach ( $GLOBALS['H'][ $h ] as $list ) foreach ( $list as [ $cb, $n ] ) $v = $cb( ...array_slice( array_merge( [ $v ], $args ), 0, $n ) );
    return $v;
}
function do_action( $h, ...$args ) {
    if ( empty( $GLOBALS['H'][ $h ] ) ) return;
    ksort( $GLOBALS['H'][ $h ] );
    foreach ( $GLOBALS['H'][ $h ] as $list ) foreach ( $list as [ $cb, $n ] ) $cb( ...array_slice( $args, 0, $n ) );
}
function add_shortcode() {}

function wp_timezone() { return new DateTimeZone( 'America/Edmonton' ); }
function wp_date( $f, $t = null ) { $d = new DateTime( '@' . ( $t ?? $GLOBALS['NOW'] ) ); $d->setTimezone( wp_timezone() ); return $d->format( $f ); }
function current_time( $f ) { return wp_date( $f === 'mysql' ? 'Y-m-d H:i:s' : $f ); }

function mk_user( $id, $email, $first, $last, $admin = false ) {
    $GLOBALS['USERS'][ $id ] = compact( 'email', 'admin' );
    $GLOBALS['META'][ $id ]  = [ 'nab_first_name' => $first, 'nab_last_name' => $last, 'nab_member_id' => 'NAB-' . str_pad( $id, 4, '0', STR_PAD_LEFT ) ];
}
function get_userdata( $id ) {
    if ( ! isset( $GLOBALS['USERS'][ $id ] ) ) return false;
    $u = new WP_User; $u->ID = $id; $u->user_email = $GLOBALS['USERS'][ $id ]['email']; $u->user_login = 'u' . $id;
    $u->display_name = 'User ' . $id; return $u;
}
function get_user_meta( $id, $k, $s = true ) { return $GLOBALS['META'][ $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['META'][ $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['META'][ $id ][ $k ] ); return true; }
function get_users( $a ) {
    $ids = array_keys( $GLOBALS['USERS'] );
    if ( isset( $a['meta_key'] ) ) $ids = array_filter( $ids, function ( $i ) use ( $a ) { return get_user_meta( $i, $a['meta_key'] ) === $a['meta_value']; } );
    if ( isset( $a['search'] ) ) { $t = trim( $a['search'], '*' ); $ids = array_filter( $ids, function ( $i ) use ( $t ) { return stripos( $GLOBALS['USERS'][ $i ]['email'], $t ) !== false; } ); }
    if ( isset( $a['meta_query'] ) ) { $t = $a['meta_query'][0]['value']; $ids = array_filter( $ids, function ( $i ) use ( $a ) {
        foreach ( array_slice( $a['meta_query'], 1 ) as $q ) if ( stripos( (string) get_user_meta( $i, $q['key'] ), $q['value'] ) !== false ) return true; return false; } ); }
    return array_values( $ids );
}
function user_can( $u, $cap ) { $id = is_object( $u ) ? $u->ID : $u; return ! empty( $GLOBALS['USERS'][ $id ]['admin'] ); }
function current_user_can( $cap ) { return user_can( $GLOBALS['CUR'], $cap ); }
function get_current_user_id() { return $GLOBALS['CUR']; }
function is_user_logged_in() { return $GLOBALS['CUR'] > 0; }
function wp_logout() { $GLOBALS['CUR'] = 0; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_doing_ajax() { return ! empty( $GLOBALS['AJAX'] ); }
function is_admin() { return false; }

function get_option( $k, $d = false ) { return $GLOBALS['OPT'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['OPT'][ $k ] = $v; return true; }
function wp_next_scheduled( $h ) { return $GLOBALS['CRON'][ $h ]['ts'] ?? false; }
function wp_schedule_event( $ts, $rec, $h ) { $GLOBALS['CRON'][ $h ] = [ 'ts' => $ts, 'rec' => $rec ]; }
function wp_clear_scheduled_hook( $h ) {} function wp_schedule_single_event() {}

function wp_mail( $to, $sub, $body ) { $GLOBALS['MAIL'][] = compact( 'to', 'sub', 'body' ); return true; }
function wpautop( $s ) { return nl2br( $s ); }
function get_bloginfo() { return 'NAB Solutions'; }

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return filter_var( trim( $s ), FILTER_SANITIZE_EMAIL ); }
function is_email( $s ) { return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function admin_url( $p = '' ) { return 'https://members.example/wp-admin/' . $p; }
function home_url( $p = '' ) { return 'https://members.example' . $p; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_field( $a, $n = '_wpnonce', $r = true, $e = true ) { $h = '<input type="hidden" name="' . $n . '" value="nonce-' . $a . '">'; if ( $e ) echo $h; return $h; }
function check_admin_referer( $a ) { if ( ( $_POST['_wpnonce'] ?? $_GET['_wpnonce'] ?? '' ) !== 'nonce-' . $a ) { $GLOBALS['OUT'] = [ 'die' => 'bad nonce' ]; throw new StopRequest; } }
function check_ajax_referer() { return true; }
function wp_die( $m ) { $GLOBALS['OUT'] = [ 'die' => $m ]; throw new StopRequest; }
function wp_safe_redirect( $u ) { $GLOBALS['OUT'] = [ 'redirect' => $u ]; }
function wp_send_json_success( $d ) { $GLOBALS['OUT'] = [ 'json' => true, 'data' => $d ]; throw new StopRequest; }
function wp_send_json_error( $d, $code = 200 ) { $GLOBALS['OUT'] = [ 'json' => false, 'data' => $d, 'code' => $code ]; throw new StopRequest; }
function add_users_page( ...$a ) { $GLOBALS['PAGE'] = $a; }
function get_current_screen() { $s = new DummyScreen; $s->id = $GLOBALS['SCREEN'] ?? 'dashboard'; return $s; }
function nab_add_auto_notification( $uid, $type, $title, $text ) { $GLOBALS['NOTE'][] = $text; }

// exit() in the plugin would end the test; the plugin calls exit after redirects,
// so run request handlers through this helper that catches the redirect first.
function run( callable $fn ) { $GLOBALS['OUT'] = null; try { $fn(); } catch ( StopRequest $e ) {} return $GLOBALS['OUT']; }

// v1.21.0 list upload
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['TRANS'] = [];
function get_user_by( $f, $v ) { foreach ( $GLOBALS['USERS'] as $id => $u ) if ( $f === 'email' && strcasecmp( $u['email'], $v ) === 0 ) return get_userdata( $id ); return false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['TRANS'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['TRANS'][ $k ] ?? false; }
function delete_transient( $k ) { unset( $GLOBALS['TRANS'][ $k ] ); return true; }
function wp_generate_password( $n = 12 ) { return substr( str_repeat( 'abc123XYZ', 5 ), 0, $n ); }
function nocache_headers() {}
function checked( $a, $b = true, $e = true ) { $r = $a == $b ? ' checked="checked"' : ''; if ( $e ) echo $r; return $r; }
function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=nonce-' . $a; }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', $n ); }
