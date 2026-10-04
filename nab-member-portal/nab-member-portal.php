<?php
/**
 * Plugin Name:  NAB Member Portal
 * Plugin URI:   https://nabsolutions.ca
 * Description:  Full member portal for NAB Solutions — Dashboard, Credit Tools, Auto Loan (LoanConnect v1.4), Credit Card Matcher, PAD Agreement, MemberPress integration, Notification system.
 * Version:      1.9.3
 * Author:       NAB Solutions
 * Author URI:   https://nabsolutions.ca
 * License:      Private — All Rights Reserved
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain:  nab-portal
 *
 * SETUP GUIDE — read before activating:
 *
 * 1. Upload the nab-member-portal/ folder to /wp-content/plugins/
 * 2. Activate in WP Admin → Plugins
 * 3. Go to Pages → create one page per portal section:
 *      - NAB Member Dashboard          (template: NAB Member Dashboard)
 *      - Credit Report Access          (template: NAB Credit Report Access)
 *      - Score Simulator               (template: NAB Credit Score Simulator)
 *      - Utilization Checker           (template: NAB Utilization Checker)
 *      - Dispute Center                (template: NAB Credit Dispute Center)
 *      - Auto Loan Tool                (template: NAB Auto Loan Tool)
 *      - Credit Card Matcher           (template: NAB Credit Card Matcher)
 *      - PAD Agreement                 (template: NAB PAD Agreement)
 * 4. Edit your Dashboard page → fill in ALL ACF field tabs:
 *      Tab "Site Settings"   → support email, phone, company name
 *      Tab "Navigation Links" → link every page created above
 *      Tab "LoanConnect API"  → paste your affid and key
 *      Tab "Credit Providers" → confirm/update Equifax, TransUnion etc. URLs
 * 5. Restrict pages with Ultimate Member or MemberPress as needed
 * 6. When MemberPress is purchased and activated — pause/cancel features activate automatically
 *
 * LOANCONNECT CREDENTIALS:
 *   Stored in ACF on Dashboard page → "LoanConnect API" tab.
 *   Never put credentials in code. Alternatively define in wp-config.php:
 *   define('NAB_LC_AFFID', 'your-affid');
 *   define('NAB_LC_KEY',   'your-key');
 *   define('NAB_LC_SANDBOX', false); // true = use sandbox for testing
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Activation error reporter (v1.9.3) ────────────────────
   While WordPress test-loads the plugin to activate it, its own fatal
   error handler is switched off and the plugins screen only says
   "triggered a fatal error". If ANY PHP fatal happens while loading or
   activating this plugin, show the real message, file and line instead,
   so it can be fixed without digging through server logs. */
if ( defined( 'WP_SANDBOX_SCRAPING' ) ) {
    register_shutdown_function( function() {
        $e = error_get_last();
        if ( ! $e || ! in_array( $e['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ], true ) ) return;
        while ( ob_get_level() ) ob_end_clean();
        if ( ! headers_sent() ) {
            header_remove( 'Location' ); // WordPress has already queued a redirect to its vague error notice
            http_response_code( 200 );
            header( 'Content-Type: text/html; charset=utf-8' );
        }
        $root = defined( 'ABSPATH' ) ? ABSPATH : '';
        $msg  = htmlspecialchars( str_replace( $root, '', $e['message'] ), ENT_QUOTES, 'UTF-8' );
        $file = htmlspecialchars( str_replace( $root, '', $e['file'] ), ENT_QUOTES, 'UTF-8' );
        $back = function_exists( 'admin_url' ) ? admin_url( 'plugins.php' ) : '../wp-admin/plugins.php';
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>NAB Member Portal was not activated</title></head>'
           . '<body style="font:14px/1.6 -apple-system,Segoe UI,Roboto,sans-serif;background:#f0f0f1;margin:0;padding:40px 16px">'
           . '<div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #c3c4c7;padding:24px 28px">'
           . '<h1 style="font-size:20px;margin:0 0 12px">NAB Member Portal was not activated</h1>'
           . '<p>PHP stopped with this error while loading the plugin. Nothing on your site was changed.</p>'
           . '<pre style="white-space:pre-wrap;background:#f6f7f7;border:1px solid #dcdcde;padding:12px;font-size:13px">' . $msg . "\n\nFile: " . $file . ' (line ' . (int) $e['line'] . ')</pre>'
           . '<p>Please send a screenshot of this box to your developer.</p>'
           . '<p><a href="' . htmlspecialchars( $back, ENT_QUOTES, 'UTF-8' ) . '">&laquo; Back to Plugins</a></p></div></body></html>';
    } );
}

/* ── Safety check before loading (v1.9.2) ─────────────────
   If another copy of this plugin, or a theme/snippet/plugin, already
   defines one of our functions, PHP stops with "Cannot redeclare": the
   activation fails with only "triggered a fatal error", and if the clash
   appears while the portal is active the WHOLE SITE goes down.
   Instead: stop loading the portal and say exactly what clashes and where.
   Our function names are scanned once per release and cached in an
   option, so each page load only does a few hundred function_exists(). */
$nab_conflict = ( function() {
    if ( defined( 'NAB_VERSION' ) ) {
        return 'Another copy of NAB Member Portal (version ' . NAB_VERSION . ') is already running'
            . ( defined( 'NAB_DIR' ) ? ' from ' . str_replace( ABSPATH, '', NAB_DIR ) : '' ) . '. Deactivate and delete that copy in Plugins, then activate this one.';
    }
    $files = glob( __DIR__ . '/inc/*.php' ) ?: [];
    $key   = md5( implode( '|', array_map( function( $f ) { return basename( $f ) . ':' . @filemtime( $f ) . ':' . @filesize( $f ); }, $files ) ) );
    $index = get_option( 'nab_function_index' );
    if ( ! is_array( $index ) || ( $index['key'] ?? '' ) !== $key ) {
        if ( ! function_exists( 'token_get_all' ) ) return '';
        $names = [];
        foreach ( $files as $file ) {
            $tokens = token_get_all( (string) file_get_contents( $file ) );
            $depth  = 0;
            foreach ( $tokens as $i => $t ) {
                if ( $t === '{' || ( is_array( $t ) && in_array( $t[0], [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ], true ) ) ) { $depth++; continue; }
                if ( $t === '}' ) { $depth--; continue; }
                // Only top-level declarations: ones wrapped in if ( ! function_exists() ) can't clash.
                if ( $depth !== 0 || ! is_array( $t ) || $t[0] !== T_FUNCTION ) continue;
                for ( $j = $i + 1; isset( $tokens[ $j ] ) && is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_WHITESPACE; $j++ );
                if ( isset( $tokens[ $j ] ) && is_array( $tokens[ $j ] ) && $tokens[ $j ][0] === T_STRING ) $names[] = $tokens[ $j ][1];
            }
        }
        $index = [ 'key' => $key, 'names' => array_values( array_unique( $names ) ) ];
        update_option( 'nab_function_index', $index, true );
    }
    foreach ( $index['names'] as $name ) {
        if ( ! function_exists( $name ) ) continue;
        $ref   = new ReflectionFunction( $name );
        $where = $ref->getFileName() ? str_replace( ABSPATH, '', $ref->getFileName() ) . ' (line ' . $ref->getStartLine() . ')' : 'WordPress core or a PHP extension';
        return 'The function ' . $name . '() used by NAB Member Portal is already defined in ' . $where
            . '. Remove or rename it there (or deactivate the plugin/theme that contains it), then activate NAB Member Portal again.';
    }
    return '';
} )();
if ( $nab_conflict ) {
    if ( defined( 'WP_SANDBOX_SCRAPING' ) ) {
        // WordPress is test-loading us to activate: cancel activation and show
        // the reason on its own page. (WordPress's own "triggered a fatal error"
        // box no longer shows plugin details — its notice filter strips them.)
        // Status 200 so the browser shows this page instead of following the
        // redirect WordPress already queued, and hosts don't swap in a 500 page.
        wp_die(
            '<h1>NAB Member Portal was not activated</h1><p>' . esc_html( $nab_conflict ) . '</p><p>Nothing on your site was changed.</p>',
            'NAB Member Portal was not activated',
            [ 'response' => 200, 'back_link' => true ]
        );
    }
    // Already active: keep the rest of the site running and tell the admin.
    add_action( 'admin_notices', function() use ( $nab_conflict ) {
        if ( current_user_can( 'activate_plugins' ) ) {
            echo '<div class="notice notice-error"><p><strong>NAB Member Portal is switched off:</strong> ' . esc_html( $nab_conflict ) . '</p></div>';
        }
    } );
    return;
}
unset( $nab_conflict );

define( 'NAB_VERSION', '1.9.3' );
define( 'NAB_DIR',     plugin_dir_path( __FILE__ ) );
define( 'NAB_URL',     plugin_dir_url( __FILE__ ) );

/* ── Load all modules ─────────────────────────────────── */
require_once NAB_DIR . 'inc/lifecycle.php';
require_once NAB_DIR . 'inc/helpers.php';
require_once NAB_DIR . 'inc/icons.php';
require_once NAB_DIR . 'inc/tools.php';
require_once NAB_DIR . 'inc/dashboard-data.php';
require_once NAB_DIR . 'inc/acf-fields.php';
require_once NAB_DIR . 'inc/notifications.php';
require_once NAB_DIR . 'inc/ajax-handlers.php';
require_once NAB_DIR . 'inc/memberpress.php';
require_once NAB_DIR . 'inc/memberpress-setup.php';
require_once NAB_DIR . 'inc/memberpress-account.php';
require_once NAB_DIR . 'inc/memberpress-migration.php';
require_once NAB_DIR . 'inc/template-loader.php';

/* ── Activation: schedule cron + set defaults ─────────── */
register_activation_hook( __FILE__, 'nab_plugin_activate' );

/* ── Deactivation: clear scheduled cron ──────────────── */
register_deactivation_hook( __FILE__, 'nab_plugin_deactivate' );

/* ── Auto-purge on file update ─────────────────────────
   v1.8.0: Runs on every request (one cheap get_option compare).
   Files are usually updated on this plugin by overwriting them
   directly on the server (FTP/file manager), NOT through the WP
   plugin updater — so register_activation_hook() above never
   fires for a routine update, and LiteSpeed keeps serving old
   cached pages/assets until someone remembers to click Purge All.
   This checks NAB_VERSION against the last-seen version stored
   in the DB; the moment they differ (i.e. new files just landed
   on the server), it fires LiteSpeed's official purge-all hook
   automatically, then records the new version so it only fires
   once per deploy — not on every page load.
   NOTE: browser-side JS/CSS caching is separately handled by
   NAB_VERSION already being passed as the wp_enqueue_script()
   version param (see inc/ajax-handlers.php) — that part was
   already working. This adds the missing server-side cache half.
   ── */
add_action( 'init', function() {
    $stored = get_option( 'nab_portal_version' );
    if ( $stored === NAB_VERSION ) return;

    do_action( 'litespeed_purge_all' ); // safe no-op if LiteSpeed Cache isn't active

    update_option( 'nab_portal_version', NAB_VERSION );
} );

/* ── Uninstall: remove plugin data (only on full delete) ─
   NOTE: This does NOT delete member data (scores, histories)
   by default — uncomment the block below only if you want
   full data wipe on plugin delete. ── */
register_uninstall_hook( __FILE__, 'nab_plugin_uninstall' );

// ─── LAST LOGIN TRACKING ──────────────────────────────────────────────────────
// Track last login time
add_action( 'wp_login', function( $user_login, $user ) {
    update_user_meta( $user->ID, 'nab_last_login', current_time( 'mysql' ) );
}, 10, 2 );

// Add Last Login column to Users list in WP Admin
add_filter( 'manage_users_columns', function( $columns ) {
    $columns['nab_last_login'] = 'Last Login';
    $columns['nab_member_id']  = 'Member ID';
    return $columns;
});

add_filter( 'manage_users_custom_column', function( $value, $column_name, $user_id ) {
    if ( $column_name === 'nab_last_login' ) {
        $last = get_user_meta( $user_id, 'nab_last_login', true );
        return $last ? '<span style="color:#0D5C9B;">' . date( 'd M Y H:i', strtotime( $last ) ) . '</span>' : '<span style="color:#94a3b8;">Never</span>';
    }
    if ( $column_name === 'nab_member_id' ) {
        $mid = get_user_meta( $user_id, 'nab_member_id', true );
        return $mid ? '<strong>' . esc_html( $mid ) . '</strong>' : '—';
    }
    return $value;
}, 10, 3 );

// Make Last Login column sortable
add_filter( 'manage_users_sortable_columns', function( $columns ) {
    $columns['nab_last_login'] = 'nab_last_login';
    return $columns;
});

// ─── ONE TIME FIX: Set Member ID for existing users ─────────────────────────
add_action( 'admin_init', function() {
    if ( get_option( 'nab_member_id_fixed_v1' ) ) return;
    $users = get_users( [ 'fields' => [ 'ID' ] ] );
    foreach ( $users as $u ) {
        $existing_mid = get_user_meta( $u->ID, 'nab_member_id', true );
        if ( empty( $existing_mid ) ) {
            $mid = 'NAB-' . str_pad( $u->ID, 4, '0', STR_PAD_LEFT );
            update_user_meta( $u->ID, 'nab_member_id', $mid );
        }
    }
    update_option( 'nab_member_id_fixed_v1', true );
});

// ─── ONE TIME FIX: Reset wrong member_since dates ────────────────────────────
// Runs once to fix existing users with incorrect 2024 dates
add_action( 'admin_init', function() {
    if ( get_option( 'nab_member_since_fixed_v2' ) ) return;
    $users = get_users( [ 'fields' => [ 'ID', 'user_registered' ] ] );
    foreach ( $users as $u ) {
        $saved = get_user_meta( $u->ID, 'nab_member_since', true );
        // If saved date contains 2024 but user registered after, reset it
        $reg_date = date( 'd M Y', strtotime( $u->user_registered ) );
        $reg_year = date( 'Y', strtotime( $u->user_registered ) );
        if ( $saved && strpos( $saved, '2024' ) !== false && $reg_year != '2024' ) {
            update_user_meta( $u->ID, 'nab_member_since', $reg_date );
        }
        // If no date saved at all, set from registration
        if ( empty( $saved ) ) {
            update_user_meta( $u->ID, 'nab_member_since', $reg_date );
        }
    }
    update_option( 'nab_member_since_fixed_v2', true );
});

// ─── BLOCK WP-ADMIN FOR NON-ADMIN USERS ──────────────────────────────────────
add_action( 'admin_init', function() {
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'editor' ) ) {
        wp_redirect( home_url( '/dashboard/' ) );
        exit;
    }
});

// Also block admin bar for subscribers
add_action( 'after_setup_theme', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        show_admin_bar( false );
    }
});
