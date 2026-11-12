<?php
/**
 * Stand-in WordPress functions so the plugin's own templates can be rendered
 * and screenshotted locally. Member data lives in meta.json next to this file
 * (copied from seed-meta.json on first run; delete meta.json to reset).
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 ); define( 'WEEK_IN_SECONDS', 604800 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 );
define( 'NAB_VERSION', 'dev' );
define( 'NAB_DIR', dirname( __DIR__, 2 ) . '/nab-member-portal/' );
define( 'NAB_URL', '/plugin/' );

$GLOBALS['W'] = [ 'actions' => [], 'filters' => [], 'scripts' => [], 'inline' => [], 'styles' => [], 'tpl' => $GLOBALS['NAB_TPL'] ?? 'nab-dashboard' ];
if ( ! file_exists( __DIR__ . '/meta.json' ) ) copy( __DIR__ . '/seed-meta.json', __DIR__ . '/meta.json' );
$GLOBALS['M'] = file_exists( __DIR__ . '/meta.json' ) ? json_decode( file_get_contents( __DIR__ . '/meta.json' ), true ) : [];

class WP_User { public $ID = 7; public $first_name = 'Jane'; public $last_name = 'Doe'; public $display_name = 'Jane Doe'; public $user_email = 'jane@example.com'; public $user_login = 'jane'; public $user_registered = '2025-03-14 10:00:00'; public $roles = [ 'subscriber' ]; function exists() { return true; } }
class WP_Error { function __construct( $c = '', $m = '' ) { $this->m = $m; } function get_error_message() { return $this->m; } }
class WP_REST_Response { function __construct( $d = null, $s = 200 ) {} }

function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['W']['actions'][ $h ][ $p ][] = $cb; return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['W']['filters'][ $h ][ $p ][] = [ $cb, $n ]; return true; }
function do_action( $h, ...$a ) { $list = $GLOBALS['W']['actions'][ $h ] ?? []; ksort( $list ); foreach ( $list as $cbs ) foreach ( $cbs as $cb ) $cb( ...$a ); }
function did_action( $h ) { return 0; }
function apply_filters( $h, $v, ...$a ) { $list = $GLOBALS['W']['filters'][ $h ] ?? []; ksort( $list ); foreach ( $list as $cbs ) foreach ( $cbs as $c ) $v = $c[0]( $v, ...array_slice( $a, 0, max( 0, $c[1] - 1 ) ) ); return $v; }
function add_shortcode() {} function do_shortcode( $s ) { return $s; } function register_post_type() {} function register_rest_route() {}
function register_activation_hook() {} function register_deactivation_hook() {} function register_uninstall_hook() {} function add_management_page() {}
function acf_add_local_field_group() {}
function __( $s ) { return $s; }

function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $s ) { return (string) $s; } function esc_js( $s ) { return addslashes( (string) $s ); }
function wp_kses_post( $s ) { return $s; } function wpautop( $s ) { return '<p>' . $s . '</p>'; } function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return $s; } function sanitize_user( $s ) { return $s; } function is_email( $s ) { return true; }
function wp_unslash( $s ) { return $s; } function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_parse_args( $a, $d = [] ) { return array_merge( $d, (array) $a ); } function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_list_pluck( $a, $k ) { return array_column( $a, $k ); }

function is_user_logged_in() { return true; } function get_current_user_id() { return 7; } function wp_get_current_user() { return new WP_User(); }
function get_userdata( $id ) { return new WP_User(); } function get_user_by() { return new WP_User(); } function get_users() { return []; }
function current_user_can() { return false; } function user_can() { return false; } function is_admin() { return false; }
function get_avatar() { return ''; } function get_the_author_meta() { return ''; } function get_edit_user_link() { return '#'; }
function get_user_meta( $u, $k = '', $s = false ) { return $GLOBALS['M'][ $k ] ?? ''; }
function update_user_meta( $u, $k, $v ) { $GLOBALS['M'][ $k ] = $v; file_put_contents( __DIR__ . '/meta.json', json_encode( $GLOBALS['M'] ) ); return true; }
function delete_user_meta( $u, $k ) { unset( $GLOBALS['M'][ $k ] ); file_put_contents( __DIR__ . '/meta.json', json_encode( $GLOBALS['M'] ) ); return true; }
function get_option( $k, $d = false ) { return [ 'blogname' => 'NAB Solutions', 'timezone_string' => 'America/Edmonton', 'date_format' => 'F j, Y' ][ $k ] ?? $d; }
function update_option() { return true; } function delete_option() { return true; }
function get_transient() { return false; } function set_transient() { return true; }

function is_page() { return true; } function get_the_ID() { return 1; } function get_queried_object_id() { return 1; }
function get_post_meta( $id, $k = '', $s = false ) { return $k === '_wp_page_template' ? $GLOBALS['W']['tpl'] : ''; }
function get_post( $id = 0 ) { return (object) [ 'ID' => 1, 'post_title' => 'Page', 'post_content' => '' ]; }
function get_post_field() { return ''; } function get_the_title() { return 'Page'; } function get_posts() { return []; } function get_pages( $a = [] ) {
    $t = $a['meta_value'] ?? ''; return $t ? [ (object) [ 'ID' => crc32( $t ) ] ] : []; }
function get_page_by_path() { return (object) [ 'ID' => 2 ]; }
function get_permalink( $id = 0 ) { return '/page/' . $GLOBALS['W']['tpl']; } function get_page_link( $id = 0 ) { return '#'; }
function get_category_by_slug() { return null; } function get_the_category() { return []; } function get_the_date() { return ''; } function get_the_post_thumbnail_url() { return ''; }
function get_field( $k, $id = 0 ) { return null; } function get_header() {} function wp_get_attachment_url() { return ''; }

function home_url( $p = '' ) { return 'http://127.0.0.1:8092' . $p; } function site_url( $p = '' ) { return home_url( $p ); }
function admin_url( $p = '' ) { return '/' . $p; } function plugin_dir_path( $f ) { return dirname( $f ) . '/'; } function plugin_dir_url( $f ) { return '/plugin/'; } function plugins_url( $p = '' ) { return '/plugin/' . $p; }
function wp_login_url( $r = '' ) { return '/login'; } function wp_logout_url( $r = '' ) { return '/logout'; } function wp_lostpassword_url() { return '/lost'; } function mepr_login_url() { return '/login'; }
function add_query_arg( $a, $u = '' ) { return $u . ( strpos( $u, '?' ) === false ? '?' : '&' ) . http_build_query( (array) $a ); }
function wp_redirect() {} function wp_safe_redirect() {} function show_admin_bar() {} function nocache_headers() {}
function bloginfo( $k ) { echo $k === 'charset' ? 'UTF-8' : 'NAB Solutions'; } function get_bloginfo( $k = '' ) { return 'NAB Solutions'; }
function language_attributes() { echo 'lang="en-CA"'; } function body_class( $c = '' ) { echo 'class="' . esc_attr( is_array( $c ) ? implode( ' ', $c ) : $c ) . '"'; }
function wp_body_open() { do_action( 'wp_body_open' ); }

function wp_enqueue_script( $h, $src = '', $deps = [], $ver = false, $footer = false ) { if ( $src ) $GLOBALS['W']['scripts'][ $h ] = [ $src, $deps ]; }
function wp_enqueue_style( $h, $src = '' ) { if ( $src ) $GLOBALS['W']['styles'][ $h ] = $src; }
function wp_dequeue_script() {} function wp_deregister_script() {} function wp_print_scripts() {}
function wp_add_inline_script( $h, $js, $pos = 'after' ) { $GLOBALS['W']['inline'][ $h ][ $pos ][] = $js; }
function wp_head() {
    do_action( 'wp_enqueue_scripts' );
    foreach ( $GLOBALS['W']['styles'] as $s ) echo '<link rel="stylesheet" href="' . esc_attr( $s ) . '">';
    do_action( 'wp_head' );
}
function wp_footer() {
    $done = [];
    $print = function( $h ) use ( &$print, &$done ) {
        if ( isset( $done[ $h ] ) ) return; $done[ $h ] = 1;
        if ( $h === 'jquery' ) { foreach ( $GLOBALS['W']['inline']['jquery']['before'] ?? [] as $js ) echo "<script>$js</script>"; echo '<script src="/vendor/jquery.js"></script>'; return; }
        if ( ! isset( $GLOBALS['W']['scripts'][ $h ] ) ) return;
        foreach ( $GLOBALS['W']['scripts'][ $h ][1] as $d ) $print( $d );
        foreach ( $GLOBALS['W']['inline'][ $h ]['before'] ?? [] as $js ) echo "<script>$js</script>";
        echo '<script src="' . esc_attr( $GLOBALS['W']['scripts'][ $h ][0] ) . '"></script>';
        foreach ( $GLOBALS['W']['inline'][ $h ]['after'] ?? [] as $js ) echo "<script>$js</script>";
    };
    $print( 'jquery' );
    foreach ( array_keys( $GLOBALS['W']['scripts'] ) as $h ) $print( $h );
    do_action( 'wp_footer' );
}

function wp_create_nonce() { return 'testnonce'; } function wp_verify_nonce( $n ) { return $n === 'testnonce'; } function wp_nonce_field() {}
function check_ajax_referer( $a, $k = false, $die = true ) { $ok = ( $_REQUEST[ $k ?: '_ajax_nonce' ] ?? '' ) === 'testnonce'; if ( ! $ok && $die ) { echo '-1'; exit; } return $ok; }
function wp_send_json_success( $d = null ) { header( 'Content-Type: application/json' ); echo json_encode( [ 'success' => true, 'data' => $d ] ); exit; }
function wp_send_json_error( $d = null, $c = 200 ) { header( 'Content-Type: application/json' ); echo json_encode( [ 'success' => false, 'data' => $d ] ); exit; }
function wp_die( $m = '' ) { echo $m; exit; } function is_wp_error( $x ) { return $x instanceof WP_Error; }
function current_time( $f ) { return $f === 'timestamp' ? time() : date( $f === 'mysql' ? 'Y-m-d H:i:s' : $f ); } function wp_date( $f, $t = null ) { return date( $f, $t ?? time() ); } function date_i18n( $f, $t = null ) { return date( $f, $t ?? time() ); }
function wp_timezone() { return new DateTimeZone( 'America/Edmonton' ); }
function wp_generate_password( $n = 12, $s = true ) { return substr( bin2hex( random_bytes( 16 ) ), 0, $n ); } function wp_generate_uuid4() { return bin2hex( random_bytes( 16 ) ); }
function wp_next_scheduled() { return true; } function wp_schedule_event() {} function wp_schedule_single_event() {} function wp_unschedule_event() {} function wp_clear_scheduled_hook() {}
function wp_remote_get() { return new WP_Error( 'x', 'offline' ); } function wp_remote_post() { return new WP_Error( 'x', 'offline' ); }
function wp_remote_retrieve_body() { return ''; } function wp_remote_retrieve_response_code() { return 0; }
function wp_mail() { return true; } function flush_rewrite_rules() {} function wp_login_form() {} function wp_logout() {}
function wp_create_user() { return 8; } function wp_update_user() {} function wp_set_password() {} function username_exists() { return false; } function get_password_reset_key() { return 'k'; }
function selected( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' selected="selected"' : ''; if ( $echo ) echo $r; return $r; }
function checked( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' checked="checked"' : ''; if ( $echo ) echo $r; return $r; }
function disabled( $a, $b = true, $echo = true ) { $r = (string) $a === (string) $b ? ' disabled="disabled"' : ''; if ( $echo ) echo $r; return $r; }
if ( ! defined( 'MB_IN_BYTES' ) ) define( 'MB_IN_BYTES', 1048576 );
function wp_upload_dir( $t = null, $c = true ) { return [ 'basedir' => __DIR__ . '/uploads', 'baseurl' => '/uploads' ]; }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0755, true ); }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', $n ); }
function wp_doing_ajax(){return false;} function add_users_page(){}
function remove_action( $h, $cb, $p = 10 ) { return true; } function remove_filter( $h, $cb, $p = 10 ) { return true; }
function mysql2date( $f, $d ) { return date( $f, strtotime( $d ) ); }
