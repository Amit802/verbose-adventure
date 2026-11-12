<?php
/**
 * Collection Assistance (v1.22.0). Roadmap item 7.
 *
 * Members tell us about a debt that is with a collection agency. Each request:
 * - is saved on the member's profile (user meta nab_collection_requests), so it
 *   always shows on the page even if Freshdesk is down;
 * - opens a Freshdesk ticket for Customer Care ("[Collection Assistance] ...",
 *   tags nab-portal + collection-assistance, priority Medium);
 * - if Freshdesk can't be reached, admin@ gets the request by email instead.
 * The page shows each request with its Freshdesk status.
 *
 * @package NAB_Member_Portal
 * @since   1.22.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Choices shown on the form. key => label */
function nab_collection_options() {
    return [
        'contact' => [
            'calls'  => 'Phone calls',
            'letter' => 'Letters',
            'texts'  => 'Emails or texts',
            'report' => 'It shows on my credit report',
        ],
        'mine' => [
            'yes'    => 'Yes, I owe it',
            'unsure' => "I'm not sure",
            'no'     => "No, it's not mine",
        ],
        'help' => [
            'validate' => 'Check that the debt is valid',
            'plan'     => 'Plan a payment I can afford',
            'settle'   => 'Understand my settlement options',
            'report'   => 'Fix how it shows on my credit report',
            'calls'    => 'Deal with the calls',
            'advice'   => "I'm not sure, I need advice",
        ],
        'time' => [
            'any'       => 'Any time',
            'morning'   => 'Morning',
            'afternoon' => 'Afternoon',
        ],
    ];
}

/** Freshdesk status number → plain words. */
function nab_collection_status_label( $status ) {
    $map = [ 2 => 'Received', 3 => 'Waiting on you', 4 => 'Resolved', 5 => 'Closed', 6 => 'Waiting on you', 7 => 'In progress' ];
    return $map[ (int) $status ] ?? 'In progress';
}

function nab_collection_get( $uid ) {
    $list = json_decode( (string) get_user_meta( $uid, 'nab_collection_requests', true ), true );
    return is_array( $list ) ? $list : [];
}

function nab_collection_save( $uid, $list ) {
    update_user_meta( $uid, 'nab_collection_requests', wp_json_encode( array_values( $list ) ) );
}

/** The request as an HTML table for the ticket or email. */
function nab_collection_summary_html( $r, $user ) {
    $o    = nab_collection_options();
    $pick = function ( $group, $keys ) use ( $o ) {
        return implode( ', ', array_map( function ( $k ) use ( $o, $group ) { return $o[ $group ][ $k ] ?? $k; }, (array) $keys ) );
    };
    $rows = [
        'Member'            => trim( $r['name'] ) . ' (' . $user->user_email . ')',
        'Member ID'         => get_user_meta( $user->ID, 'nab_member_id', true ),
        'Phone'             => $r['phone'],
        'Best time to call' => $o['time'][ $r['time'] ] ?? '',
        'Collection agency' => $r['agency'],
        'Original creditor' => $r['creditor'],
        'Amount they say is owed' => $r['amount'] > 0 ? '$' . number_format( $r['amount'], 2 ) : '',
        'Account / reference' => $r['account'],
        'How they contact the member' => $pick( 'contact', $r['contact'] ),
        'Is the debt theirs?' => $o['mine'][ $r['mine'] ] ?? '',
        'Help wanted'       => $pick( 'help', $r['help'] ),
        'Notes'             => $r['notes'],
    ];
    $html = '<table cellpadding="6" style="border-collapse:collapse">';
    foreach ( $rows as $k => $v ) {
        if ( $v === '' || $v === null ) continue;
        $html .= '<tr><td style="color:#555;vertical-align:top"><strong>' . esc_html( $k ) . '</strong></td><td>' . nl2br( esc_html( (string) $v ) ) . '</td></tr>';
    }
    return $html . '</table><p>Sent from the Collection Assistance page in the member portal.</p>';
}

/* ═══════════════════════════════════════════════════════════
   AJAX: send a request
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_collection_submit', function () {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );
    $uid  = get_current_user_id();
    $user = get_userdata( $uid );
    if ( ! $user ) wp_send_json_error( [ 'message' => 'Please log in again.' ], 401 );

    $o     = nab_collection_options();
    $in    = wp_unslash( $_POST );
    $clean = function ( $v, $max = 120 ) { return mb_substr( sanitize_text_field( (string) $v ), 0, $max ); };
    $keys  = function ( $v, $group ) use ( $o ) { return array_values( array_intersect( array_map( 'sanitize_key', (array) $v ), array_keys( $o[ $group ] ) ) ); };

    $r = [
        'id'       => 'c' . wp_generate_password( 8, false, false ),
        'created'  => current_time( 'mysql' ),
        'name'     => trim( $clean( $in['name'] ?? '' ) ) ?: trim( $user->first_name . ' ' . $user->last_name ),
        'phone'    => $clean( $in['phone'] ?? '', 30 ),
        'time'     => array_key_exists( $in['time'] ?? '', $o['time'] ) ? $in['time'] : 'any',
        'agency'   => $clean( $in['agency'] ?? '' ),
        'creditor' => $clean( $in['creditor'] ?? '' ),
        'amount'   => max( 0, round( (float) preg_replace( '/[^0-9.]/', '', (string) ( $in['amount'] ?? '' ) ), 2 ) ),
        'account'  => $clean( $in['account'] ?? '', 40 ),
        'contact'  => $keys( $in['contact'] ?? [], 'contact' ),
        'mine'     => array_key_exists( $in['mine'] ?? '', $o['mine'] ) ? $in['mine'] : '',
        'help'     => $keys( $in['help'] ?? [], 'help' ),
        'notes'    => mb_substr( sanitize_textarea_field( (string) ( $in['notes'] ?? '' ) ), 0, 2000 ),
        'ticket'   => 0,
        'status'   => 2,
    ];

    if ( $r['agency'] === '' )  wp_send_json_error( [ 'message' => 'Please enter the name of the collection agency.', 'field' => 'agency' ] );
    if ( $r['mine'] === '' )    wp_send_json_error( [ 'message' => 'Please tell us if this debt is yours.', 'field' => 'mine' ] );
    if ( ! $r['help'] )         wp_send_json_error( [ 'message' => 'Please pick at least one thing we can help with.', 'field' => 'help' ] );
    if ( empty( $in['consent'] ) ) wp_send_json_error( [ 'message' => 'Please tick the box to confirm you understand how we can help.', 'field' => 'consent' ] );

    // A few requests an hour is plenty; stops double clicks and abuse.
    $list   = nab_collection_get( $uid );
    $recent = array_filter( $list, function ( $x ) { return strtotime( $x['created'] ) > current_time( 'timestamp' ) - HOUR_IN_SECONDS; } );
    if ( count( $recent ) >= 5 ) wp_send_json_error( [ 'message' => "You've sent several requests in the last hour. Our team will be in touch soon." ] );

    // Freshdesk ticket for Customer Care
    list( $fd_key, $fd_domain ) = nab_freshdesk_config();
    $response = wp_remote_post( 'https://' . $fd_domain . '/api/v2/tickets', [
        'timeout' => 20,
        'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Basic ' . base64_encode( $fd_key . ':X' ) ],
        'body'    => wp_json_encode( [
            'name'        => $r['name'],
            'email'       => $user->user_email,
            'phone'       => $r['phone'] ?: null,
            'subject'     => '[Collection Assistance] ' . $r['agency'] . ( $r['amount'] > 0 ? ' ($' . number_format( $r['amount'], 2 ) . ')' : '' ),
            'description' => nab_collection_summary_html( $r, $user ),
            'status'      => 2, // Open
            'priority'    => 2, // Medium
            'tags'        => [ 'nab-portal', 'collection-assistance' ],
        ] ),
    ] );
    $result = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) === 201 && ! empty( $result['id'] ) ) {
        $r['ticket'] = (int) $result['id'];
    } else {
        // Freshdesk unavailable: email the request so it's never lost.
        if ( function_exists( 'nab_send_membership_email' ) ) {
            nab_send_membership_email( 'admin@nabsolutions.ca', 'Collection Assistance request, ' . $r['name'], "Freshdesk could not be reached, so this request came by email instead.\n\n" . nab_collection_summary_html( $r, $user ) );
        }
    }

    array_unshift( $list, $r );
    nab_collection_save( $uid, array_slice( $list, 0, 30 ) );

    if ( function_exists( 'nab_add_auto_notification' ) ) {
        nab_add_auto_notification( $uid, 'support', 'Collection request received', 'We got your request about ' . $r['agency'] . '. Our team will contact you within 1 to 2 business days.' );
    }
    wp_send_json_success( [
        'message'  => $r['ticket'] ? 'Request sent. Your reference is #' . $r['ticket'] . '.' : 'Request sent. Our team will contact you soon.',
        'requests' => nab_collection_public( nab_collection_get( $uid ) ),
    ] );
} );

/* ═══════════════════════════════════════════════════════════
   AJAX: the member's requests, with the latest Freshdesk status
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_collection_list', function () {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );
    $uid  = get_current_user_id();
    $list = nab_collection_get( $uid );
    $user = get_userdata( $uid );
    if ( $list && $user && array_filter( array_column( $list, 'ticket' ) ) ) {
        list( $fd_key, $fd_domain ) = nab_freshdesk_config();
        $response = wp_remote_get( 'https://' . $fd_domain . '/api/v2/tickets?email=' . rawurlencode( $user->user_email ) . '&per_page=50&order_by=created_at&order_type=desc', [
            'timeout' => 15,
            'headers' => [ 'Authorization' => 'Basic ' . base64_encode( $fd_key . ':X' ) ],
        ] );
        if ( ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) === 200 ) {
            $status = [];
            foreach ( (array) json_decode( wp_remote_retrieve_body( $response ), true ) as $t ) {
                if ( isset( $t['id'], $t['status'] ) ) $status[ (int) $t['id'] ] = (int) $t['status'];
            }
            $changed = false;
            foreach ( $list as &$r ) {
                if ( $r['ticket'] && isset( $status[ $r['ticket'] ] ) && $status[ $r['ticket'] ] !== (int) $r['status'] ) {
                    $r['status'] = $status[ $r['ticket'] ];
                    $changed     = true;
                }
            }
            unset( $r );
            if ( $changed ) nab_collection_save( $uid, $list );
        }
    }
    wp_send_json_success( [ 'requests' => nab_collection_public( $list ) ] );
} );

/** What the page needs to show a request. */
function nab_collection_public( $list ) {
    $o = nab_collection_options();
    return array_map( function ( $r ) use ( $o ) {
        return [
            'agency'  => $r['agency'],
            'amount'  => (float) $r['amount'],
            'date'    => mysql2date( 'M j, Y', $r['created'] ),
            'help'    => array_map( function ( $k ) use ( $o ) { return $o['help'][ $k ] ?? $k; }, (array) $r['help'] ),
            'ticket'  => (int) $r['ticket'],
            'status'  => $r['ticket'] ? nab_collection_status_label( $r['status'] ) : 'Sent to our team',
            'done'    => in_array( (int) $r['status'], [ 4, 5 ], true ),
        ];
    }, $list );
}
