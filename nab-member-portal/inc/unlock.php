<?php
/**
 * Progressive tool unlock (v1.23.0). Roadmap item 9.
 *
 * The money tools open one step at a time, so a new member is guided through
 * them in a sensible order instead of seeing everything at once:
 *
 *   1. Budget Planner         open from the start
 *   2. Payment Calendar       after a budget is saved,            or 1 lesson done
 *   3. Net Worth Calculator   after the first bill is added,      or 2 lessons done
 *   4. Loan Calculator        after the first account is added,   or 3 lessons done
 *
 * Every other tool (credit, disputes, support, LFP, learning...) is always open.
 * A tool is also open if the member already has data in it, and once a tool is
 * open it never locks again. Admins never see locks. The whole feature can be
 * switched off with the "Unlock tools step by step" switch in the Dashboard
 * page's Global Site Settings (ACF), or the 'nab_progressive_unlock' filter.
 *
 * Locked pages show templates/tool-locked.php (what to do next, with a link).
 * State: user meta nab_unlock_state = { tool key: 'locked' | 'open' }.
 *
 * @package NAB_Member_Portal
 * @since   1.23.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** The journey. Keyed by sidebar item key (inc/tools.php). */
function nab_unlock_steps() {
    return apply_filters( 'nab_unlock_steps', [
        'budget'    => [ 'step' => 1, 'label' => 'Budget Planner',       'task' => 'Set your monthly budget' ],
        'payments'  => [ 'step' => 2, 'label' => 'Payment Calendar',     'task' => 'Add your first bill',   'after' => 'budget',   'lessons' => 1 ],
        'networth'  => [ 'step' => 3, 'label' => 'Net Worth Calculator', 'task' => 'Add your first account', 'after' => 'payments', 'lessons' => 2 ],
        'loan-calc' => [ 'step' => 4, 'label' => 'Loan Calculator',      'task' => 'Work out a loan',       'after' => 'networth', 'lessons' => 3 ],
    ] );
}

/** Is the feature on? Default on. */
function nab_unlock_enabled() {
    $on = function_exists( 'nab_get_global_setting' ) ? nab_get_global_setting( 'nab_progressive_unlock', 1 ) : 1;
    return (bool) apply_filters( 'nab_progressive_unlock', ! in_array( $on, [ 0, '0', false ], true ) );
}

/** Has the member done the task of this step (has data in that tool)? */
function nab_unlock_step_done( $uid, $key ) {
    switch ( $key ) {
        case 'budget':   return function_exists( 'nab_budget_get' ) && nab_budget_get( $uid );
        case 'payments': return function_exists( 'nab_cal_get_bills' ) && nab_cal_get_bills( $uid );
        case 'networth': return function_exists( 'nab_dash_get_accounts' ) && nab_dash_get_accounts( $uid );
    }
    return false;
}

function nab_unlock_lessons_done( $uid ) {
    $done = json_decode( (string) get_user_meta( $uid, 'nab_modules_completed', true ), true );
    return is_array( $done ) ? count( $done ) : 0;
}

function nab_unlock_state( $uid ) {
    $s = json_decode( (string) get_user_meta( $uid, 'nab_unlock_state', true ), true );
    return is_array( $s ) ? $s : [];
}

/** Open right now? (ignores the feature switch and admins; see nab_tool_is_unlocked) */
function nab_unlock_check( $uid, $key, $state = null ) {
    $steps = nab_unlock_steps();
    if ( empty( $steps[ $key ]['after'] ) ) return true;           // not a locked tool
    $state = $state ?? nab_unlock_state( $uid );
    if ( ( $state[ $key ] ?? '' ) === 'open' ) return true;         // once open, always open
    if ( nab_unlock_step_done( $uid, $key ) ) return true;          // already uses it
    if ( nab_unlock_step_done( $uid, $steps[ $key ]['after'] ) ) return true;
    return nab_unlock_lessons_done( $uid ) >= (int) ( $steps[ $key ]['lessons'] ?? 99 );
}

/** Can this member open the tool? */
function nab_tool_is_unlocked( $uid, $key ) {
    if ( ! nab_unlock_enabled() || ! $uid || user_can( $uid, 'manage_options' ) ) return true;
    return nab_unlock_check( $uid, $key );
}

/**
 * Save which tools are open and send a bell notification for any tool that
 * just opened. Runs once per page load from the sidebar. Returns key => bool.
 */
function nab_unlock_sync( $uid ) {
    static $done = [];
    if ( isset( $done[ $uid ] ) ) return $done[ $uid ];
    $out = [];
    if ( ! nab_unlock_enabled() || ! $uid || user_can( $uid, 'manage_options' ) ) {
        foreach ( nab_unlock_steps() as $k => $s ) $out[ $k ] = true;
        return $done[ $uid ] = $out;
    }
    $state   = nab_unlock_state( $uid );
    $changed = false;
    foreach ( nab_unlock_steps() as $k => $s ) {
        if ( empty( $s['after'] ) ) { $out[ $k ] = true; continue; }
        $open    = nab_unlock_check( $uid, $k, $state );
        $out[ $k ] = $open;
        $now     = $open ? 'open' : 'locked';
        $before  = $state[ $k ] ?? '';
        if ( $before !== $now ) {
            // Only tell the member about tools they actually saw locked.
            if ( $before === 'locked' && $open && function_exists( 'nab_add_auto_notification' ) ) {
                nab_add_auto_notification( $uid, 'education', $s['label'] . ' unlocked', 'Nice work! You can now use the ' . $s['label'] . '. Find it under Money Management.' );
            }
            $state[ $k ] = $now;
            $changed     = true;
        }
    }
    if ( $changed ) update_user_meta( $uid, 'nab_unlock_state', wp_json_encode( $state ) );
    return $done[ $uid ] = $out;
}

/* ── Locked pages show what to do next ───────────────────── */
add_filter( 'template_include', function ( $template ) {
    if ( ! is_page() || ! is_user_logged_in() || ! function_exists( 'nab_portal_current_tool' ) ) return $template;
    $slug = nab_portal_current_tool();
    if ( ! $slug ) return $template;
    foreach ( nab_portal_nav_sections() as $section ) {
        foreach ( $section['items'] as $it ) {
            if ( ( $it['template'] ?? '' ) !== $slug ) continue;
            if ( ! nab_tool_is_unlocked( get_current_user_id(), $it['key'] ) ) {
                $GLOBALS['nab_locked_tool'] = $it;
                return NAB_DIR . 'templates/tool-locked.php';
            }
            return $template;
        }
    }
    return $template;
}, 30 );

/** The URL of a step's tool, or '#'. */
function nab_unlock_tool_url( $key ) {
    foreach ( nab_portal_nav_sections() as $section ) {
        foreach ( $section['items'] as $it ) {
            if ( $it['key'] === $key ) return nab_portal_nav_item_url( $it );
        }
    }
    return '#';
}
