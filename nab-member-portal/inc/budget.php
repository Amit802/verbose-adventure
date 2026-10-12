<?php
/**
 * NAB Member Portal — Monthly Budget Planner data (v1.10.0)
 *
 * The member sets one monthly budget per spending category. Actual
 * spending is NOT stored here: it comes from the dashboard's monthly
 * cash flow entries (nab_cashflow, see inc/dashboard-data.php), and the
 * planner saves spending through the same nab_dash_cashflow_save action.
 * So the budget page and the dashboard charts always show the same numbers.
 *
 * User meta:
 *   nab_budget — JSON { category: monthly amount }, categories from
 *                nab_dash_expense_categories()
 *
 * AJAX (nab_portal_nonce, logged in):
 *   nab_budget_save — replace the whole budget
 *
 * @package NAB_Member_Portal
 * @since   1.10.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** The member's budget: [ category => amount ] (only amounts above zero). */
function nab_budget_get( $uid ) {
    $cats   = nab_dash_expense_categories();
    $budget = [];
    foreach ( nab_dash_json_meta( $uid, 'nab_budget' ) as $k => $v ) {
        if ( isset( $cats[ $k ] ) && (float) $v > 0 ) $budget[ $k ] = round( (float) $v, 2 );
    }
    return $budget;
}

/** Everything the planner page needs, in one snapshot. */
function nab_budget_page_data( $uid ) {
    $dash = nab_dash_get_chart_data( $uid );
    return [
        'today'    => $dash['today'],
        'month'    => $dash['month'],
        'budget'   => nab_budget_get( $uid ),
        'cashflow' => $dash['cashflow'],
        'cats'     => $dash['cats'],
    ];
}

add_action( 'wp_ajax_nab_budget_save', function() {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );
    $uid = get_current_user_id();
    if ( ! $uid ) wp_send_json_error( [ 'message' => 'Please log in again.' ], 401 );

    $budget = [];
    foreach ( array_keys( nab_dash_expense_categories() ) as $c ) {
        $v = nab_dash_money( $_POST[ 'b_' . $c ] ?? 0 ); // phpcs:ignore
        if ( $v > 0 ) $budget[ $c ] = $v;
    }
    update_user_meta( $uid, 'nab_budget', wp_json_encode( $budget ) );

    wp_send_json_success( [
        'message' => $budget ? 'Budget saved.' : 'Budget cleared.',
        'data'    => nab_budget_page_data( $uid ),
    ] );
} );
