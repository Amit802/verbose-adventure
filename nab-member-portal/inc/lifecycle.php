<?php
/**
 * Activation / deactivation / uninstall callbacks.
 * Kept out of the main plugin file so that file declares no named
 * functions — that lets its conflict check run before anything can clash.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function nab_plugin_activate() {
    if ( ! wp_next_scheduled( 'nab_mp_daily_check' ) ) {
        wp_schedule_event( time(), 'daily', 'nab_mp_daily_check' );
    }
    // Flush rewrite rules in case page templates need it
    flush_rewrite_rules();
}

function nab_plugin_deactivate() {
    $ts = wp_next_scheduled( 'nab_mp_daily_check' );
    if ( $ts ) wp_unschedule_event( $ts, 'nab_mp_daily_check' );
    wp_clear_scheduled_hook( 'nab_access_weekly_report' );
    flush_rewrite_rules();
}

function nab_plugin_uninstall() {
    // Delete plugin options only — NOT user data
    delete_option( 'nab_portal_version' );
    delete_option( 'nab_function_index' );
    delete_option( 'nab_access_report_to' );
    // Uncomment below to also wipe all member data on uninstall:
    // global $wpdb;
    // $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'nab_%'");
}
