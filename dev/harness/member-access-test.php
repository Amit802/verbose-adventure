<?php
require __DIR__ . '/member-access-stubs.php';
$P = dirname( __DIR__, 2 ) . '/nab-member-portal/inc/';
// Load plugin files with `exit;` turned into a catchable stop, so one process can run every scenario.
foreach ( [ 'memberpress.php', 'member-access.php' ] as $f ) {
    $src = file_get_contents( $P . $f );
    $src = str_replace( "if ( ! defined( 'ABSPATH' ) ) exit;", '', $src );
    $src = preg_replace( '/\bexit;/', 'throw new StopRequest;', $src );
    eval( '?>' . $src );
}
$fail = 0;
function ok( $cond, $label ) { global $fail; echo ( $cond ? "PASS " : "FAIL " ) . $label . "\n"; if ( ! $cond ) $fail++; }
function at( $when ) { $GLOBALS['NOW'] = strtotime( $when . ' America/Edmonton' ); }

mk_user( 1, 'admin@nab.ca', 'Ada', 'Admin', true );
mk_user( 2, 'sam@mail.com', 'Sam', 'Portal' );
mk_user( 3, 'emma@mail.com', 'Emma', 'Email' );
mk_user( 4, 'old@mail.com', 'Olga', 'Old' );
mk_user( 5, 'older@mail.com', 'Omar', 'Older' );
mk_user( 6, 'paused@mail.com', 'Pia', 'Paused' );
// Cancelled before v1.19 (no end date)
update_user_meta( 4, 'nab_membership_status', 'cancelled' ); update_user_meta( 4, 'nab_cancel_date', '2026-10-14 10:00:00' ); // a Wednesday
update_user_meta( 5, 'nab_membership_status', 'cancelled' );                                                              // no cancel date
update_user_meta( 6, 'nab_membership_status', 'paused' ); update_user_meta( 6, 'nab_mp_resume_date', '2026-11-20' );

do_action( 'init' ); // schedules the weekly email
ok( ( $GLOBALS['CRON']['nab_access_weekly_report']['rec'] ?? '' ) === 'weekly', 'weekly report scheduled' );
ok( wp_date( 'D H:i', $GLOBALS['CRON']['nab_access_weekly_report']['ts'] ) === 'Mon 09:00', 'report time is Monday 9:00 site time (' . wp_date( 'Y-m-d D H:i', $GLOBALS['CRON']['nab_access_weekly_report']['ts'] ) . ')' );

// ── Billing day maths (today is Sun Nov 8, 2026)
ok( nab_access_next_billing_day() === '2026-11-13', 'next Friday from Sunday = Nov 13' );
ok( nab_access_next_billing_day( '2026-11-13' ) === '2026-11-20', 'from a Friday = the following Friday' );
ok( nab_access_next_billing_day( '2026-11-12' ) === '2026-11-13', 'from Thursday = next day' );
ok( nab_access_clean_date( '2026-02-30' ) === '' && nab_access_clean_date( 'x' ) === '', 'bad dates rejected' );
ok( nab_access_suggested_end( 4 ) === '2026-10-16', 'old member suggested end = Friday after cancel date' );
ok( nab_access_suggested_end( 5 ) === '2026-11-07', 'no cancel date suggested = yesterday' );

// ── States
ok( nab_access_state( 2 ) === 'active' && nab_access_state( 6 ) === 'paused', 'active and paused states' );
ok( nab_access_state( 4 ) === 'no_date' && ! nab_access_is_blocked( 4 ), 'old cancelled: no_date and not blocked yet (team decides)' );

// ── Member cancels in the portal (Sun Nov 8)
$GLOBALS['CUR'] = 2; $_POST = [ 'reason' => 'Too expensive' ]; $GLOBALS['MAIL'] = [];
$r = run( function () { do_action( 'wp_ajax_nab_mp_cancel' ); } );
ok( $r['json'] === true && $r['data']['access_ends'] === '2026-11-13', 'portal cancel sets end date Nov 13' );
ok( strpos( $r['data']['message'], 'Friday, November 13' ) !== false, 'member sees: ' . $r['data']['message'] );
ok( get_user_meta( 2, 'nab_membership_status' ) === 'cancelled' && get_user_meta( 2, 'nab_cancel_source' ) === 'portal' && get_user_meta( 2, 'nab_cancel_reason' ) === 'Too expensive', 'status, source and reason saved' );
ok( count( $GLOBALS['MAIL'] ) === 2 && strpos( $GLOBALS['MAIL'][0]['body'], 'ends on <strong>Friday, November 13</strong>' ) !== false, 'member email names the real date' );
ok( strpos( $GLOBALS['MAIL'][1]['body'], 'Portal access ends after: <strong>Friday, November 13</strong>' ) !== false, 'billing email names the date' );
$GLOBALS['MAIL'] = [];
$r = run( function () { do_action( 'wp_ajax_nab_mp_cancel' ); } );
ok( $r['data']['access_ends'] === '2026-11-13' && ! $GLOBALS['MAIL'], 'second cancel keeps the date, no duplicate emails' );

// Still has access through Friday
at( '2026-11-13 23:30' );
ok( nab_access_state( 2 ) === 'ending' && ! nab_access_is_blocked( 2 ), 'Fri Nov 13 late evening: still has access' );
$u = get_userdata( 2 );
ok( apply_filters( 'wp_authenticate_user', $u, 'pw' ) === $u, 'login allowed on last day' );

// Saturday: blocked
at( '2026-11-14 00:05' );
ok( nab_access_state( 2 ) === 'ended' && nab_access_is_blocked( 2 ), 'Sat Nov 14: access ended' );
$res = apply_filters( 'wp_authenticate_user', $u, 'pw' );
ok( is_wp_error( $res ) && $res->code === 'nab_membership_ended', 'login refused: ' . ( is_wp_error( $res ) ? $res->msg : '' ) );
// Open session on a page
$GLOBALS['CUR'] = 2; WP_Session_Tokens::$destroyed = [];
$r = run( function () { do_action( 'init' ); } );
ok( ( $r['redirect'] ?? '' ) === 'https://members.example/?membership=ended' && $GLOBALS['CUR'] === 0 && WP_Session_Tokens::$destroyed === [ 2 ], 'open page session logged out and redirected' );
// Open session using AJAX
$GLOBALS['CUR'] = 2; $GLOBALS['AJAX'] = true;
$r = run( function () { do_action( 'init' ); } );
ok( $r['json'] === false && $r['code'] === 403, 'AJAX call refused with 403' );
$GLOBALS['AJAX'] = false;
// Logged-out landing note
$_GET = [ 'membership' => 'ended' ]; ob_start(); do_action( 'wp_footer' ); $note = ob_get_clean(); $_GET = [];
ok( strpos( $note, 'membership has ended' ) !== false, 'landing page shows the ended note' );
// Admins are never blocked
update_user_meta( 1, 'nab_membership_status', 'cancelled' ); update_user_meta( 1, 'nab_access_ends', '2026-01-01' );
ok( ! nab_access_is_blocked( 1 ), 'admin never blocked' );
delete_user_meta( 1, 'nab_membership_status' ); delete_user_meta( 1, 'nab_access_ends' );
// Other members untouched
$GLOBALS['CUR'] = 3; $r = run( function () { do_action( 'init' ); } );
ok( $r === null && $GLOBALS['CUR'] === 3, 'active member not affected' );

// ── Team screen (back to Sun Nov 8, logged in as admin)
at( '2026-11-08 12:00' ); $GLOBALS['CUR'] = 1;
// Non-admin cannot use the handlers
$GLOBALS['CUR'] = 3; $_POST = [ '_wpnonce' => 'nonce-nab_access_cancel', 'user_id' => 2, 'ends' => '2026-12-01' ];
$r = run( function () { do_action( 'admin_post_nab_access_cancel' ); } );
ok( isset( $r['die'] ), 'non-admin refused' );
$GLOBALS['CUR'] = 1;
// Bad nonce
$_POST = [ '_wpnonce' => 'wrong', 'user_id' => 3, 'ends' => '2026-11-20' ];
$r = run( function () { do_action( 'admin_post_nab_access_cancel' ); } );
ok( isset( $r['die'] ) && get_user_meta( 3, 'nab_membership_status' ) === '', 'bad nonce refused' );
// Emma emailed to cancel: team sets her date
$_POST = [ '_wpnonce' => 'nonce-nab_access_cancel', 'user_id' => 3, 'ends' => '2026-11-20', 'reason' => 'Asked by email', 'when' => 'date', 's' => 'emma' ];
$r = run( function () { do_action( 'admin_post_nab_access_cancel' ); } );
parse_str( parse_url( $r['redirect'], PHP_URL_QUERY ), $q );
ok( $q['nab_type'] === 'success' && nab_access_state( 3 ) === 'ending' && nab_access_end_date( 3 ) === '2026-11-20', 'team cancel: ' . rawurldecode( $q['nab_msg'] ) );
ok( get_user_meta( 3, 'nab_cancel_source' ) === 'team' && (int) get_user_meta( 3, 'nab_cancel_by' ) === 1 && get_user_meta( 3, 'nab_cancel_reason' ) === 'Asked by email', 'source, who and note saved' );
// Missing date
$_POST = [ '_wpnonce' => 'nonce-nab_access_cancel', 'user_id' => 6, 'ends' => '', 'when' => 'date' ];
$r = run( function () { do_action( 'admin_post_nab_access_cancel' ); } ); parse_str( parse_url( $r['redirect'], PHP_URL_QUERY ), $q );
ok( $q['nab_type'] === 'error' && nab_access_state( 6 ) === 'paused', 'missing date refused, paused member untouched' );
// Admin account cannot be cancelled
$_POST = [ '_wpnonce' => 'nonce-nab_access_cancel', 'user_id' => 1, 'ends' => '2026-11-20', 'when' => 'date' ];
$r = run( function () { do_action( 'admin_post_nab_access_cancel' ); } ); parse_str( parse_url( $r['redirect'], PHP_URL_QUERY ), $q );
ok( $q['nab_type'] === 'error', 'admin account cannot be cancelled' );
// Close access now (paused member)
$_POST = [ '_wpnonce' => 'nonce-nab_access_cancel', 'user_id' => 6, 'when' => 'now' ];
WP_Session_Tokens::$destroyed = [];
$r = run( function () { do_action( 'admin_post_nab_access_cancel' ); } ); parse_str( parse_url( $r['redirect'], PHP_URL_QUERY ), $q );
ok( nab_access_state( 6 ) === 'ended' && get_user_meta( 6, 'nab_mp_resume_date' ) === '' && WP_Session_Tokens::$destroyed === [ 6 ], 'close now: ended at once, pause cleared, sessions killed (' . rawurldecode( $q['nab_msg'] ) . ')' );
// Reactivate
$_POST = [ '_wpnonce' => 'nonce-nab_access_reactivate', 'user_id' => 6 ];
run( function () { do_action( 'admin_post_nab_access_reactivate' ); } );
ok( nab_access_state( 6 ) === 'active' && get_user_meta( 6, 'nab_access_ends' ) === '', 'reactivated' );

// Weekly email (before the bulk fix: includes the 2 old members)
$GLOBALS['MAIL'] = [];
ok( nab_access_send_report( false ) === 'sent', 'weekly report sent' );
$m = $GLOBALS['MAIL'][0] ?? [ 'to' => '', 'sub' => '', 'body' => '' ];
ok( $m['to'] === 'billing@nabsolutions.ca', 'to billing@' );
ok( strpos( $m['body'], 'Sam Portal' ) !== false && strpos( $m['body'], 'Emma Email' ) === false, 'ending in 7 days lists Sam (Nov 13), not Emma (Nov 20)' );
ok( strpos( $m['body'], 'no end date set (2)' ) !== false, 'mentions 2 old members without a date' );
echo "  subject: {$m['sub']}\n";

// Bulk: old members in one go
$GLOBALS['SCREEN'] = 'users'; ob_start(); do_action( 'admin_notices' ); $notice = ob_get_clean();
ok( strpos( $notice, '2 cancelled members still have portal access' ) !== false, 'reminder notice on Users screen' );
$_POST = [ '_wpnonce' => 'nonce-nab_access_bulk', 'mode' => 'dates', 'users' => [ 4, 5, 2 ], 'ends' => [ 4 => '2026-10-16', 5 => '2026-11-27', 2 => '2027-01-01' ] ];
$r = run( function () { do_action( 'admin_post_nab_access_bulk' ); } ); parse_str( parse_url( $r['redirect'], PHP_URL_QUERY ), $q );
ok( nab_access_state( 4 ) === 'ended' && nab_access_state( 5 ) === 'ending' && nab_access_end_date( 2 ) === '2026-11-13', 'bulk: Olga closed, Omar until Nov 27, Sam not touched (' . rawurldecode( $q['nab_msg'] ) . ')' );
ob_start(); do_action( 'admin_notices' ); ok( ob_get_clean() === '', 'notice gone once fixed' );

// Report: nothing to report → no email
$GLOBALS['USERS'] = array_intersect_key( $GLOBALS['USERS'], [ 1 => 1, 3 => 1 ] );
at( '2026-12-20 09:00' ); $GLOBALS['MAIL'] = [];
ok( nab_access_send_report( false ) === 'empty' && ! $GLOBALS['MAIL'], 'empty week: no email' );
ok( nab_access_send_report( true ) === 'sent', '"send now" always sends' );
// Recipients setting
$_POST = [ '_wpnonce' => 'nonce-nab_access_report', 'report_to' => 'a@x.com, bad, b@y.com' ];
run( function () { do_action( 'admin_post_nab_access_report' ); } );
ok( nab_access_report_recipients() === [ 'a@x.com', 'b@y.com' ], 'recipients saved, invalid dropped' );

// Users list column
ok( apply_filters( 'manage_users_custom_column', '', 'nab_access', 3 ) !== '' , 'users list column: ' . strip_tags( apply_filters( 'manage_users_custom_column', '', 'nab_access', 3 ) ) );


// ── v1.21.0: upload a list of members who cancelled by email or phone
at( '2026-11-10 10:00' ); $GLOBALS['CUR'] = 1;
$GLOBALS['USERS'] = []; $GLOBALS['META'] = [];
mk_user( 1, 'admin@nab.ca', 'Ada', 'Admin', true );
mk_user( 10, 'mark@mail.com', 'Mark', 'Active' );
mk_user( 11, 'pia@mail.com', 'Pia', 'Paused' ); update_user_meta( 11, 'nab_membership_status', 'paused' );
mk_user( 12, 'ed@mail.com', 'Ed', 'Ended' ); nab_access_cancel( 12, '2026-11-01', '', 'portal' );
mk_user( 13, 'eve@mail.com', 'Eve', 'Ending' ); nab_access_cancel( 13, '2026-11-13', '', 'portal' );
mk_user( 14, 'olga@mail.com', 'Olga', 'Old' ); update_user_meta( 14, 'nab_membership_status', 'cancelled' );
mk_user( 15, 'john@mail.com', 'John', 'Smith' );
mk_user( 16, 'bd@mail.com', 'Bad', 'Date' );
$csv = "\xEF\xBB\xBFFull Name,Email Address,Last Day\r\n"
     . "Mark Active,MARK@mail.com,\r\n"
     . "Pia Paused,pia@mail.com,2026-12-31\r\n"
     . "Mark Again,mark@mail.com,\r\n"
     . "Nobody,nobody@mail.com,\r\n"
     . "Bad,not-an-email,\r\n"
     . "Ada,admin@nab.ca,\r\n"
     . "Ed,ed@mail.com,\r\n"
     . "Eve,eve@mail.com,2026-11-30\r\n"
     . "Olga,olga@mail.com,13/11/2026\r\n"
     . "Bad Date,bd@mail.com,someday\r\n"
     . "\"Smith, John\",john@mail.com,11/20/2026\r\n"
     . ",,\r\n";
$pr = nab_access_import_parse( $csv );
ok( $pr['error'] === '' && count( $pr['rows'] ) === 11, 'parse: 11 rows (BOM, header, blank line ignored)' );
ok( $pr['rows'][10]['name'] === 'Smith, John' && $pr['rows'][0]['email'] === 'mark@mail.com' && $pr['rows'][0]['line'] === 2, 'quoted name with comma, email lower-cased, line numbers' );
$plan = nab_access_import_plan( $pr['rows'], nab_access_yesterday() );
$st = array_map( function ( $r ) { return $r['status'] . ':' . $r['note']; }, $plan );
$expect = [ 'cancel:', 'cancel:', 'skip:Same email as line 2', 'skip:No portal account with this email', 'skip:Not a valid email', 'skip:Admin account, skipped', 'skip:Access already closed (Nov 1, 2026)', 'update:', 'cancel:', 'skip:Last day "someday" is not a date', 'cancel:' ];
$okplan = true; foreach ( $expect as $i => $e ) { if ( strpos( $st[ $i ], $e ) !== 0 ) { $okplan = false; echo "   row $i: {$st[$i]} (expected $e)\n"; } }
ok( $okplan, 'plan: cancel / duplicate / not found / bad email / admin / already closed / already cancelled / bad date' );
ok( $plan[0]['ends'] === '2026-11-09' && $plan[1]['ends'] === '2026-12-31' && $plan[8]['ends'] === '2026-11-13' && $plan[10]['ends'] === '2026-11-20', 'dates: blank = close now, ISO, day-first 13/11, month-first 11/20' );
ok( $plan[0]['tick'] && ! $plan[7]['tick'], 'new cancels ticked, already-cancelled not ticked' );

// Other file shapes
$p2 = nab_access_import_parse( "john@mail.com;John Smith\nmark@mail.com;Mark" );
ok( $p2['error'] === '' && count( $p2['rows'] ) === 2 && $p2['rows'][0]['email'] === 'john@mail.com' && $p2['rows'][0]['name'] === 'John Smith', 'no header row, semicolons' );
$p3 = nab_access_import_parse( "First name\tLast name\tE-mail\nJohn\tSmith\tjohn@mail.com" );
ok( $p3['rows'][0]['name'] === 'John Smith' && $p3['rows'][0]['email'] === 'john@mail.com', 'tabs, first + last name columns, "E-mail" header' );
$p4 = nab_access_import_parse( "Name,Email\n" . "Ren\xE9e,renee@mail.com" );
ok( $p4['rows'][0]['name'] === 'Renée', 'old Excel (Windows-1252) text converted' );
ok( nab_access_import_parse( "Name,Phone\nJohn,555" )['error'] !== '', 'no email column: clear error' );
ok( nab_access_import_parse( "" )['error'] === 'The file is empty.', 'empty file: clear error' );
ok( nab_access_import_date( 'Nov 13, 2026' ) === '2026-11-13' && nab_access_import_date( '2026/2/30' ) === '' && nab_access_import_date( '1/2/26' ) === '2026-01-02', 'more date shapes' );

// Preview → confirm
set_transient( nab_access_import_key( 'tok1' ), [ 'by' => 1, 'file' => 'list.csv', 'default' => nab_access_yesterday(), 'rows' => $plan ], 3600 );
$GLOBALS['CUR'] = 3; ok( nab_access_import_get( 'tok1' ) === null, 'another user cannot use the preview' ); $GLOBALS['CUR'] = 1;
// ticks: Mark(0), Pia(1), Olga(8), John(10), plus Eve(7) to change her date, plus a skipped row (3) that must be ignored
$_POST = [ '_wpnonce' => 'nonce-nab_access_import_apply', 'token' => 'tok1', 'packed' => json_encode( [ 0, 1, 3, 7, 8, 10 ] ) ];
WP_Session_Tokens::$destroyed = [];
$r = run( function () { do_action( 'admin_post_nab_access_import_apply' ); } ); parse_str( parse_url( $r['redirect'], PHP_URL_QUERY ), $q );
echo '  message: ' . rawurldecode( $q['nab_msg'] ) . "\n";
ok( nab_access_state( 10 ) === 'ended', 'Mark: access closed now' );
ok( nab_access_state( 11 ) === 'ending' && nab_access_end_date( 11 ) === '2026-12-31', 'Pia: cancelled, last day Dec 31 (pause cleared)' );
ok( nab_access_end_date( 13 ) === '2026-11-30', 'Eve: date changed because it was ticked' );
ok( nab_access_end_date( 14 ) === '2026-11-13' && nab_access_state( 14 ) === 'ending', 'Olga: last day Nov 13' );
ok( nab_access_end_date( 15 ) === '2026-11-20', 'John: last day Nov 20' );
ok( nab_access_state( 16 ) === 'active' && nab_access_state( 1 ) === 'active', 'skipped rows and admin untouched' );
ok( strpos( get_user_meta( 10, 'nab_cancel_reason' ), 'uploaded list: list.csv' ) !== false && get_user_meta( 10, 'nab_cancel_source' ) === 'team', 'reason and source recorded' );
ok( get_transient( nab_access_import_key( 'tok1' ) ) === false, 'preview deleted after confirm' );
$r = run( function () { do_action( 'admin_post_nab_access_import_apply' ); } ); parse_str( parse_url( $r['redirect'], PHP_URL_QUERY ), $q );
ok( $q['nab_type'] === 'error' && strpos( rawurldecode( $q['nab_msg'] ), 'expired' ) !== false, 'confirming twice: "expired", nothing changes' );
$_POST['_wpnonce'] = 'bad'; $r = run( function () { do_action( 'admin_post_nab_access_import_apply' ); } );
ok( isset( $r['die'] ), 'bad nonce refused' );

// Preview card renders and escapes the file's text
set_transient( nab_access_import_key( 'tok2' ), [ 'by' => 1, 'file' => 'x.csv', 'default' => nab_access_yesterday(), 'rows' => nab_access_import_plan( nab_access_import_parse( "Name,Email\n<script>alert(1)</script>,zed@mail.com" )['rows'], nab_access_yesterday() ) ], 3600 );
$_GET = [ 'import' => 'tok2' ]; ob_start(); nab_access_import_card(); $html = ob_get_clean(); $_GET = [];
ok( strpos( $html, '<script>alert' ) === false && strpos( $html, 'alert(1)' ) !== false, 'tags in the file are stripped, text still shown' );
ob_start(); nab_access_import_card(); $html = ob_get_clean();
ok( strpos( $html, 'type="file"' ) !== false, 'upload form shows when there is no preview' );

// Bulk form sends the ticked rows packed into one field
update_user_meta( 16, 'nab_membership_status', 'cancelled' ); delete_user_meta( 16, 'nab_access_ends' );
$_POST = [ '_wpnonce' => 'nonce-nab_access_bulk', 'mode' => 'dates', 'packed' => json_encode( [ '16' => '2026-11-27' ] ) ];
run( function () { do_action( 'admin_post_nab_access_bulk' ); } );
ok( nab_access_end_date( 16 ) === '2026-11-27', 'bulk save works with the packed field' );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
