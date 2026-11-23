<?php
/**
 * One AJAX entry point for the staff portal: action=nab_staff, do=<task>.
 * Every task checks the nonce and the person's permission before doing anything.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** task => [ permission, handler ]. Handlers return an array; 'error' means failure. */
function nab_staff_ajax_tasks() {
    return [
        'reply'          => [ 'use',    'nab_staff_ai_reply' ],
        'retone'         => [ 'use',    'nab_staff_ai_retone' ],
        'member_search'  => [ 'use',    function () { return [ 'rows' => nab_staff_member_search( sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) ) ) ]; } ],
        'member_profile' => [ 'use',    function () { $p = nab_staff_member_profile( absint( $_POST['id'] ?? 0 ) ); return $p ? [ 'member' => $p ] : [ 'error' => 'Member not found.' ]; } ],
        'member_tickets' => [ 'use',    function () { $u = get_userdata( absint( $_POST['id'] ?? 0 ) ); return nab_staff_is_member( $u ) ? nab_staff_member_tickets( $u->user_email ) : [ 'error' => 'Member not found.' ]; } ],
        'member_brief'   => [ 'use',    function () { $b = nab_staff_member_brief( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) ); return [ 'member' => $b ? $b['card'] : null ]; } ],
        'member_resend'  => [ 'use',    function () { return nab_staff_member_resend( absint( $_POST['id'] ?? 0 ) ); } ],
        'doc_upload'     => [ 'manage', 'nab_staff_doc_upload' ],
        'doc_text'       => [ 'use',    function () { return nab_staff_doc_get_text( absint( $_POST['id'] ?? 0 ) ); } ],
        'doc_save'       => [ 'manage', function () { return nab_staff_doc_save_text( absint( $_POST['id'] ?? 0 ) ); } ],
        'doc_toggle'     => [ 'manage', function () { return nab_staff_doc_toggle( absint( $_POST['id'] ?? 0 ) ); } ],
        'doc_delete'     => [ 'manage', function () { return nab_staff_doc_delete( absint( $_POST['id'] ?? 0 ) ); } ],
        'topic_create'   => [ 'use',    'nab_staff_topic_create' ],
        'topic_vote'     => [ 'use',    function () { return nab_staff_topic_vote( absint( $_POST['id'] ?? 0 ) ); } ],
        'topic_comment'  => [ 'use',    function () { return nab_staff_topic_comment( absint( $_POST['id'] ?? 0 ) ); } ],
        'topic_status'   => [ 'use',    function () { return nab_staff_topic_status( absint( $_POST['id'] ?? 0 ) ); } ],
        'topic_delete'   => [ 'use',    function () { return nab_staff_topic_delete( absint( $_POST['id'] ?? 0 ) ); } ],
        'team_add'       => [ 'manage', 'nab_staff_team_add' ],
        'team_role'      => [ 'manage', 'nab_staff_team_role' ],
        'team_remove'    => [ 'manage', 'nab_staff_team_remove' ],
        'team_resend'    => [ 'manage', 'nab_staff_team_resend' ],
    ];
}

add_action( 'wp_ajax_nab_staff', function () {
    if ( ! check_ajax_referer( 'nab_staff', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Your session has expired. Please refresh the page.' ], 403 );
    }
    $tasks = nab_staff_ajax_tasks();
    $do    = sanitize_key( $_POST['do'] ?? '' );
    if ( ! isset( $tasks[ $do ] ) ) wp_send_json_error( [ 'message' => 'Unknown request.' ], 400 );
    if ( ! nab_staff_can( $tasks[ $do ][0] ) ) wp_send_json_error( [ 'message' => 'You don\'t have permission to do this.' ], 403 );
    $out = call_user_func( $tasks[ $do ][1] );
    if ( isset( $out['error'] ) ) wp_send_json_error( [ 'message' => $out['error'] ] );
    wp_send_json_success( $out );
} );

// Document download (a normal link, so it uses GET with its own nonce).
add_action( 'wp_ajax_nab_staff_doc_file', function () {
    if ( ! nab_staff_can( 'use' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'nab_staff_doc_file' ) ) wp_die( 'You don\'t have permission to open this file.', 403 );
    nab_staff_doc_download( absint( $_GET['id'] ?? 0 ) );
} );

function nab_staff_doc_file_url( $id ) {
    return wp_nonce_url( add_query_arg( [ 'action' => 'nab_staff_doc_file', 'id' => (int) $id ], admin_url( 'admin-ajax.php' ) ), 'nab_staff_doc_file' );
}
