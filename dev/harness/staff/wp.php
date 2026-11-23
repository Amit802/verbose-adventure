<?php
/**
 * Stand-in WordPress for testing the NAB Staff Portal plugin with several users.
 * State (users, meta, posts, transients) lives in state.json between requests.
 * Mocks: OpenAI (echoes back what it received) and Freshdesk (two tickets).
 * Who is logged in: cookie "as" = user ID (set with /login?as=ID).
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 ); define( 'MINUTE_IN_SECONDS', 60 ); define( 'DAY_IN_SECONDS', 86400 );
date_default_timezone_set( 'UTC' );
class Stop extends Exception {}
$GLOBALS['S'] = file_exists( __DIR__ . '/state.json' ) ? json_decode( file_get_contents( __DIR__ . '/state.json' ), true ) : null;
function save_state() { file_put_contents( __DIR__ . '/state.json', json_encode( $GLOBALS['S'] ) ); }
function S( $k ) { return $GLOBALS['S'][ $k ] ?? []; }

/* hooks */
$GLOBALS['H'] = [];
function add_action( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['H'][ $h ][ $p ][] = [ $c, $a ]; }
function add_filter( $h, $c, $p = 10, $a = 1 ) { add_action( $h, $c, $p, $a ); }
function do_action( $h, ...$args ) { if ( empty( $GLOBALS['H'][ $h ] ) ) return; ksort( $GLOBALS['H'][ $h ] ); foreach ( $GLOBALS['H'][ $h ] as $l ) foreach ( $l as $x ) $x[0]( ...array_slice( $args, 0, $x[1] ) ); }
function apply_filters( $h, $v, ...$args ) { if ( empty( $GLOBALS['H'][ $h ] ) ) return $v; ksort( $GLOBALS['H'][ $h ] ); foreach ( $GLOBALS['H'][ $h ] as $l ) foreach ( $l as $x ) $v = $x[0]( ...array_slice( array_merge( [ $v ], $args ), 0, $x[1] ) ); return $v; }
function register_activation_hook() {} function register_deactivation_hook() {} function add_rewrite_rule() {} function flush_rewrite_rules() {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; } function plugin_dir_url( $f ) { return '/plug/'; }
function register_post_type() {}

/* options, transients */
function get_option( $k, $d = false ) { return S( 'opt' )[ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['S']['opt'][ $k ] = $v; save_state(); return true; }
function get_transient( $k ) { $t = S( 'tr' )[ $k ] ?? null; return $t && $t[1] > time() ? $t[0] : false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['S']['tr'][ $k ] = [ $v, time() + ( $e ?: 999999 ) ]; save_state(); return true; }
function delete_transient( $k ) { unset( $GLOBALS['S']['tr'][ $k ] ); save_state(); return true; }

/* users */
class WP_User { public $ID = 0, $roles = [], $user_email = '', $user_login = '', $display_name = '', $first_name = '', $last_name = '', $user_registered = '';
  function exists() { return $this->ID > 0; }
  function set_role( $r ) { $GLOBALS['S']['users'][ (string) $this->ID ]['roles'] = $r ? [ $r ] : []; $this->roles = $r ? [ $r ] : []; save_state(); } }
function username_exists( $l ) { foreach ( S( 'users' ) as $u ) if ( $u['user_login'] === $l ) return true; return false; }
function sanitize_user( $l, $strict = false ) { return preg_replace( '/[^a-z0-9_.\-@]/i', '', $l ); }
function wp_insert_user( $a ) { $GLOBALS['S']['seq'] = ( $GLOBALS['S']['seq'] ?? 100 ) + 1; $id = $GLOBALS['S']['seq'];
  $GLOBALS['S']['users'][ (string) $id ] = [ 'roles' => [ $a['role'] ], 'user_email' => $a['user_email'], 'user_login' => $a['user_login'], 'display_name' => $a['display_name'], 'first_name' => $a['first_name'], 'last_name' => $a['last_name'], 'user_registered' => gmdate( 'Y-m-d H:i:s' ) ];
  save_state(); do_action( 'user_register', $id ); return $id; }
function get_password_reset_key( $u ) { return 'key' . $u->ID; }
function wp_mail( $to, $subj, $body, $h = [] ) { $GLOBALS['S']['mail'][] = [ $to, $subj, $body ]; save_state(); return true; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u === '/' ? 'https://members.nabsolutions.ca/' : $u, $c ); }
function get_userdata( $id ) { $u = S( 'users' )[ (string) $id ] ?? null; if ( ! $u ) return false; $o = new WP_User; foreach ( $u as $k => $v ) $o->$k = $v; $o->ID = (int) $id; return $o; }
function get_user_by( $f, $v ) { foreach ( S( 'users' ) as $id => $u ) if ( $f === 'email' && strcasecmp( $u['user_email'], $v ) === 0 ) return get_userdata( $id ); return false; }
function get_current_user_id() { return (int) ( $_COOKIE['as'] ?? 0 ); }
function wp_get_current_user() { return get_userdata( get_current_user_id() ) ?: new WP_User; }
function is_user_logged_in() { return get_current_user_id() > 0 && get_userdata( get_current_user_id() ); }
function nab_test_caps( $u ) { $c = [ 'read' => 1 ]; foreach ( $u->roles as $r ) { if ( $r === 'administrator' ) $c += [ 'manage_options' => 1, 'nab_staff_portal' => 1, 'nab_staff_manage_docs' => 1, 'edit_users' => 1 ]; if ( $r === 'nab_staff' ) $c += [ 'nab_staff_portal' => 1 ]; if ( $r === 'nab_staff_manager' ) $c += [ 'nab_staff_portal' => 1, 'nab_staff_manage_docs' => 1 ]; } return $c; }
function user_can( $u, $cap ) { $u = is_object( $u ) ? $u : get_userdata( $u ); return $u && isset( nab_test_caps( $u )[ $cap ] ); }
function current_user_can( $cap ) { return user_can( wp_get_current_user(), $cap ); }
function add_role() {} function get_role() { return null; }
function get_user_meta( $id, $k = '', $s = false ) { return S( 'meta' )[ (string) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['S']['meta'][ (string) $id ][ $k ] = $v; save_state(); return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['S']['meta'][ (string) $id ][ $k ] ); save_state(); return true; }
function get_users( $a ) {
  $out = [];
  foreach ( S( 'users' ) as $id => $u ) {
    $ok = true;
    if ( isset( $a['role__in'] ) && ! array_intersect( $a['role__in'], $u['roles'] ) ) continue;
    if ( isset( $a['search'] ) ) { $t = trim( $a['search'], '*' ); $ok = stripos( $u['user_email'] . ' ' . $u['user_login'] . ' ' . $u['display_name'], $t ) !== false; }
    if ( isset( $a['meta_key'] ) ) $ok = $ok && ( get_user_meta( $id, $a['meta_key'] ) === $a['meta_value'] );
    if ( isset( $a['meta_query'] ) ) { $hit = false; foreach ( array_slice( $a['meta_query'], 1 ) as $q ) { $v = (string) ( $q['key'] === 'first_name' || $q['key'] === 'last_name' ? ( $u[ $q['key'] ] ?? '' ) : get_user_meta( $id, $q['key'] ) ); if ( $v !== '' && stripos( $v, $q['value'] ) !== false ) $hit = true; } $ok = $ok && $hit; }
    if ( $ok ) $out[] = (int) $id;
  }
  return $out;
}

/* posts */
class WP_Post { public $ID, $post_type, $post_title, $post_content, $post_author, $post_status, $post_date_gmt, $post_modified_gmt, $post_modified; }
function get_post( $id ) { $p = S( 'posts' )[ (string) $id ] ?? null; if ( ! $p ) return null; $o = new WP_Post; foreach ( $p as $k => $v ) $o->$k = $v; $o->ID = (int) $id; return $o; }
function wp_insert_post( $a, $err = false ) { $GLOBALS['S']['seq'] = ( $GLOBALS['S']['seq'] ?? 100 ) + 1; $id = $GLOBALS['S']['seq']; $now = gmdate( 'Y-m-d H:i:s' );
  $GLOBALS['S']['posts'][ (string) $id ] = $a + [ 'post_date_gmt' => $now, 'post_modified_gmt' => $now, 'post_modified' => $now ]; save_state(); return $id; }
function wp_update_post( $a ) { $id = (string) $a['ID']; unset( $a['ID'] ); $GLOBALS['S']['posts'][ $id ] = array_merge( $GLOBALS['S']['posts'][ $id ], $a, [ 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s' ), 'post_modified' => gmdate( 'Y-m-d H:i:s' ) ] ); save_state(); return (int) $id; }
function wp_delete_post( $id ) { unset( $GLOBALS['S']['posts'][ (string) $id ], $GLOBALS['S']['pmeta'][ (string) $id ] ); save_state(); return true; }
function get_post_meta( $id, $k = '', $s = false ) { return S( 'pmeta' )[ (string) $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['S']['pmeta'][ (string) $id ][ $k ] = $v; save_state(); return true; }
function get_the_title( $p ) { $p = is_object( $p ) ? $p : get_post( $p ); return $p ? $p->post_title : ''; }
function get_posts( $a ) {
  $out = [];
  foreach ( S( 'posts' ) as $id => $p ) {
    if ( $p['post_type'] !== $a['post_type'] || ( $p['post_status'] ?? 'publish' ) !== 'publish' ) continue;
    if ( isset( $a['meta_query'] ) ) { $q = $a['meta_query'][0]; if ( get_post_meta( $id, $q['key'] ) !== $q['value'] ) continue; }
    $out[] = get_post( $id );
  }
  usort( $out, function ( $x, $y ) use ( $a ) { $f = ( $a['orderby'] ?? 'date' ) === 'modified' ? 'post_modified_gmt' : 'post_date_gmt'; return strcmp( $y->$f, $x->$f ) ?: $y->ID - $x->ID; } );
  return array_slice( $out, 0, $a['numberposts'] ?? 5 );
}

/* misc WP */
function home_url( $p = '' ) { return $p ?: '/'; } function admin_url( $p = '' ) { return '/wp-admin/' . $p; }
function wp_login_url( $r = '' ) { return '/login'; } function wp_logout_url( $r = '' ) { return '/logout'; }
function wp_safe_redirect( $u ) { header( 'Location: ' . $u ); }
function nocache_headers() {} function get_query_var( $k ) { return $GLOBALS['QV'] ?? ''; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function sanitize_email( $s ) { return filter_var( trim( (string) $s ), FILTER_SANITIZE_EMAIL ); } function is_email( $s ) { return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ); }
function sanitize_file_name( $n ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', $n ); } function wp_unslash( $s ) { return $s; } function absint( $n ) { return abs( (int) $n ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); } function wp_generate_password( $n = 12 ) { return substr( bin2hex( random_bytes( 20 ) ), 0, $n ); }
function wp_date( $f, $t = null ) { return date( $f, $t ?? time() ); } function current_time( $t, $gmt = false ) { return $t === 'mysql' ? gmdate( 'Y-m-d H:i:s' ) : time(); }
function get_gmt_from_date( $d ) { return $d; } function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; } function wp_mkdir_p( $d ) { return @mkdir( $d, 0777, true ); }
function wp_upload_dir() { return [ 'basedir' => __DIR__ . '/uploads', 'baseurl' => '/up' ]; }
function delete_option( $k ) { unset( $GLOBALS['S']['opt'][ $k ] ); save_state(); return true; }
function add_query_arg( $a, $u ) { return $u . ( strpos( $u, '?' ) === false ? '?' : '&' ) . http_build_query( $a ); }
function wp_create_nonce( $a ) { return 'n-' . $a . '-' . get_current_user_id(); } function wp_verify_nonce( $n, $a ) { return $n === wp_create_nonce( $a ); }
function check_ajax_referer( $a, $k, $die = true ) { return wp_verify_nonce( $_REQUEST[ $k ] ?? '', $a ); }
function wp_nonce_url( $u, $a ) { return add_query_arg( [ '_wpnonce' => wp_create_nonce( $a ) ], $u ); }
function wp_send_json_success( $d = null ) { header( 'Content-Type: application/json' ); echo json_encode( [ 'success' => true, 'data' => $d ] ); throw new Stop; }
function wp_send_json_error( $d = null, $code = 200 ) { http_response_code( $code ); header( 'Content-Type: application/json' ); echo json_encode( [ 'success' => false, 'data' => $d ] ); throw new Stop; }
function wp_die( $m = '', $c = 500 ) { http_response_code( is_int( $c ) ? $c : 500 ); echo $m; throw new Stop; }
function is_page() { return false; } function is_front_page() { return false; } function is_home() { return false; }
function is_wp_error( $x ) { return $x instanceof WP_Error; } class WP_Error { function get_error_message() { return 'err'; } }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; } function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_get( $url, $a = [] ) { $GLOBALS['S']['calls'][] = [ 'GET', $url ]; save_state();
  return [ 'code' => 200, 'body' => json_encode( [ [ 'id' => 301, 'subject' => 'Can\'t log in to the portal', 'status' => 4, 'created_at' => '2026-10-28T10:00:00Z' ], [ 'id' => 322, 'subject' => 'Question about the Lending Finder Program', 'status' => 2, 'created_at' => '2026-11-09T10:00:00Z' ] ] ) ]; }
function wp_remote_post( $url, $a = [] ) {
  $req = json_decode( $a['body'], true ); $GLOBALS['S']['calls'][] = [ 'POST', $url, $req ]; save_state();
  if ( strpos( $url, 'openai' ) !== false ) {
    $sys = $req['messages'][0]['content']; $usr = $req['messages'][1]['content'] ?? '';
    if ( strpos( $sys, 'Rewrite this' ) === 0 ) $c = [ 'reply' => "[rewritten " . ( strpos( $sys, 'short' ) !== false ? 'short' : 'tone' ) . "] " . $usr ];
    else $c = [ 'summary' => 'Sarah wants to cancel and is asking for a refund for this week.', 'tags' => [ 'Cancellation', 'Refund request' ], 'mood' => 'unhappy', 'covered' => strpos( $usr, '30 days' ) !== false,
      'reply' => "Hi Sarah,\n\nThanks for reaching out — I've noted your request. 😊\n\nKind regards,\nJessica\nNAB Solutions Customer Care" ];
    return [ 'code' => 200, 'body' => json_encode( [ 'choices' => [ [ 'message' => [ 'content' => json_encode( $c ) ] ] ] ] ) ];
  }
  return [ 'code' => 500, 'body' => '' ];
}

/* member portal functions the staff plugin reads */
function nab_access_state( $id ) { return get_user_meta( $id, 'state' ) ?: 'active'; }
function nab_access_end_date( $id ) { return get_user_meta( $id, 'ends' ); }
function nab_get_member_since( $id ) { return 'Oct 2, 2026'; }
function nab_dash_get_score_history( $id ) { return get_user_meta( $id, 'hist' ) ?: []; }
function nab_dispute_get_letters( $id ) { return get_user_meta( $id, 'letters' ) ?: []; }
function nab_dispute_types() { return [ 'late' => [ 'label' => 'Late payment error' ], 'notmine' => [ 'label' => 'Not my account' ] ]; }
function nab_dispute_statuses() { return [ 'ready' => [ 'label' => 'Ready to send' ], 'sent' => [ 'label' => 'Sent' ] ]; }
function nab_dispute_bureaus() { return [ 'equifax' => [ 'name' => 'Equifax Canada' ], 'transunion' => [ 'name' => 'TransUnion Canada' ] ]; }
function nab_collection_get( $id ) { return get_user_meta( $id, 'coll' ) ?: []; }
function nab_collection_public( $l ) { return $l; }
function nab_freshdesk_config() { return [ 'key', 'nabsolutions.freshdesk.com' ]; }
function nab_portal_ai_config() { return [ 'key' => get_option( 'ai_key', 'sk-test' ), 'model' => 'gpt-4o-mini' ]; }
function nab_dispatch_welcome_email( $id ) { $GLOBALS['S']['mails'][] = $id; save_state(); }
function nab_portal_tools() { return []; }
function get_theme_mod( $k, $d = false ) { return get_option( 'theme_' . $k, $d ); }
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) { return $id ? '/fx/logo.svg' : false; }
function get_pages( $a ) { return [ (object) [ 'ID' => 900 ] ]; }
function get_field( $k, $id = 0 ) { return $id === 900 ? get_option( 'acf_' . $k, null ) : null; }
function get_site_icon_url( $size = 512 ) { return get_option( 'site_icon_url', '' ); }
