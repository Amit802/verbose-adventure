<?php
/**
 * Plugin Name:  NAB Member Portal
 * Plugin URI:   https://nabsolutions.ca
 * Description:  Full member portal for NAB Solutions — Dashboard, Credit Tools, Auto Loan (LoanConnect v1.4), Credit Card Matcher, PAD Agreement, MemberPress integration, Notification system.
 * Version:      1.8.2
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

define( 'NAB_VERSION', '1.8.2' );
define( 'NAB_DIR',     plugin_dir_path( __FILE__ ) );
define( 'NAB_URL',     plugin_dir_url( __FILE__ ) );

/* ── Load all modules ─────────────────────────────────── */
require_once NAB_DIR . 'inc/helpers.php';
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
function nab_plugin_activate() {
    if ( ! wp_next_scheduled( 'nab_mp_daily_check' ) ) {
        wp_schedule_event( time(), 'daily', 'nab_mp_daily_check' );
    }
    // Flush rewrite rules in case page templates need it
    flush_rewrite_rules();
}

/* ── Deactivation: clear scheduled cron ──────────────── */
register_deactivation_hook( __FILE__, 'nab_plugin_deactivate' );
function nab_plugin_deactivate() {
    $ts = wp_next_scheduled( 'nab_mp_daily_check' );
    if ( $ts ) wp_unschedule_event( $ts, 'nab_mp_daily_check' );
    flush_rewrite_rules();
}

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
function nab_plugin_uninstall() {
    // Delete plugin options only — NOT user data
    delete_option( 'nab_portal_version' );
    // Uncomment below to also wipe all member data on uninstall:
    // global $wpdb;
    // $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'nab_%'");
}

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
