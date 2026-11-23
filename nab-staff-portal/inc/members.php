<?php
/**
 * Member lookup. Reads the NAB Member Portal's own data, never changes it,
 * except "Resend login email". Staff and admin accounts are never shown.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Is this account a member (not staff, not an admin)? */
function nab_staff_is_member( $user ) {
    if ( ! $user || user_can( $user, 'manage_options' ) || user_can( $user, 'nab_staff_portal' ) ) return false;
    return true;
}

/** Search by email, Member ID or name. Returns up to 10 short rows. */
function nab_staff_member_search( $q ) {
    $q = trim( (string) $q );
    if ( mb_strlen( $q ) < 2 ) return [];
    $ids = [];
    if ( is_email( $q ) && ( $u = get_user_by( 'email', $q ) ) ) $ids[] = $u->ID;
    if ( preg_match( '/^nab-?\d+$/i', $q ) ) {
        $ids = array_merge( $ids, get_users( [ 'fields' => 'ID', 'number' => 5, 'meta_key' => 'nab_member_id', 'meta_value' => strtoupper( str_replace( 'nab', 'NAB-', str_replace( '-', '', strtolower( $q ) ) ) ) ] ) );
    }
    $ids = array_merge( $ids, get_users( [ 'fields' => 'ID', 'number' => 20, 'search' => '*' . $q . '*', 'search_columns' => [ 'user_email', 'user_login', 'display_name' ] ] ) );
    $words = preg_split( '/\s+/', $q );
    $meta  = [ 'relation' => 'OR' ];
    foreach ( [ 'first_name', 'last_name', 'nab_first_name', 'nab_last_name', 'nab_member_id' ] as $k ) $meta[] = [ 'key' => $k, 'value' => $words[0], 'compare' => 'LIKE' ];
    $ids = array_merge( $ids, get_users( [ 'fields' => 'ID', 'number' => 20, 'meta_query' => $meta ] ) );

    $rows = [];
    foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
        $u = get_userdata( $id );
        if ( ! nab_staff_is_member( $u ) ) continue;
        $name = nab_staff_member_name( $u );
        if ( count( $words ) > 1 && stripos( $name . ' ' . $u->user_email, $words[1] ) === false ) continue; // "jane doe" must match both words
        $rows[] = [ 'id' => $id, 'name' => $name, 'email' => $u->user_email, 'memberId' => (string) get_user_meta( $id, 'nab_member_id', true ) ];
        if ( count( $rows ) >= 10 ) break;
    }
    return $rows;
}

function nab_staff_member_name( $u ) {
    $first = get_user_meta( $u->ID, 'nab_first_name', true ) ?: $u->first_name;
    $last  = get_user_meta( $u->ID, 'nab_last_name', true ) ?: $u->last_name;
    return trim( $first . ' ' . $last ) ?: $u->display_name;
}

/** Everything the lookup screen shows about one member. */
function nab_staff_member_profile( $id ) {
    $u = get_userdata( (int) $id );
    if ( ! nab_staff_is_member( $u ) ) return null;

    // Membership and portal access (inc/member-access.php in the member portal).
    $status = get_user_meta( $id, 'nab_membership_status', true ) ?: 'active';
    $state  = function_exists( 'nab_access_state' ) ? nab_access_state( $id ) : $status;
    $tones  = [ 'active' => 'good', 'paused' => 'warn', 'ending' => 'warn', 'ended' => 'bad', 'no_date' => 'warn' ];
    $labels = [ 'active' => 'Active', 'paused' => 'Paused', 'ending' => 'Cancelled', 'ended' => 'Access closed', 'no_date' => 'Cancelled' ];
    $ends   = function_exists( 'nab_access_end_date' ) ? nab_access_end_date( $id ) : '';
    $access = 'Open';
    if ( $state === 'ending' ) $access = 'Open until ' . wp_date( 'M j, Y', strtotime( $ends . ' 12:00' ) );
    if ( $state === 'ended' ) $access = 'Closed on ' . wp_date( 'M j, Y', strtotime( $ends . ' 12:00 +1 day' ) );
    if ( $state === 'paused' ) {
        $resume = get_user_meta( $id, 'nab_mp_resume_date', true );
        $access = $resume ? 'Paused until ' . wp_date( 'M j, Y', strtotime( $resume . ' 12:00' ) ) : 'Paused';
    }

    // Credit score and change since the first entry.
    $score = ''; $change = '';
    $hist  = function_exists( 'nab_dash_get_score_history' ) ? nab_dash_get_score_history( $id ) : [];
    if ( $hist ) {
        $last  = end( $hist ); $first = reset( $hist );
        $score = (int) $last['s'];
        $diff  = $score - (int) $first['s'];
        if ( count( $hist ) > 1 && $diff ) $change = ( $diff > 0 ? 'up ' : 'down ' ) . abs( $diff );
    } elseif ( ( $s = (int) get_user_meta( $id, 'nab_credit_score', true ) ) >= 300 ) {
        $score = $s;
    }

    $last_login = get_user_meta( $id, 'nab_last_login', true );

    // Dispute letters
    $disputes = [];
    if ( function_exists( 'nab_dispute_get_letters' ) ) {
        $types    = function_exists( 'nab_dispute_types' ) ? nab_dispute_types() : [];
        $statuses = function_exists( 'nab_dispute_statuses' ) ? nab_dispute_statuses() : [];
        $bureaus  = function_exists( 'nab_dispute_bureaus' ) ? nab_dispute_bureaus() : [];
        foreach ( array_slice( array_reverse( nab_dispute_get_letters( $id ) ), 0, 8 ) as $l ) {
            $st = $l['status'];
            $disputes[] = [
                'title'  => trim( ( $types[ $l['type'] ]['label'] ?? ucfirst( $l['type'] ) ) . ( $l['bureau'] ? ', ' . str_replace( ' Canada', '', $bureaus[ $l['bureau'] ]['name'] ?? ucfirst( $l['bureau'] ) ) : '' ) ),
                'detail' => ( $l['sent'] ? 'Sent ' . wp_date( 'M j', strtotime( $l['sent'] ) ) : ( $l['created'] ? 'Created ' . wp_date( 'M j', strtotime( $l['created'] ) ) : '' ) ) . ( ! empty( $l['f']['items'] ) ? ' · ' . count( $l['f']['items'] ) . ( count( $l['f']['items'] ) === 1 ? ' item' : ' items' ) : '' ),
                'status' => $statuses[ $st ]['label'] ?? ucfirst( $st ),
                'tone'   => in_array( $st, [ 'resolved' ], true ) ? 'good' : ( in_array( $st, [ 'sent', 'response' ], true ) ? 'blue' : ( $st === 'unresolved' ? 'bad' : 'warn' ) ),
            ];
        }
    }

    // Collection Assistance requests
    $collections = [];
    if ( function_exists( 'nab_collection_get' ) && function_exists( 'nab_collection_public' ) ) {
        foreach ( array_slice( nab_collection_public( nab_collection_get( $id ) ), 0, 5 ) as $c ) {
            $collections[] = [
                'title'  => $c['agency'] . ( $c['amount'] > 0 ? ' · $' . number_format( $c['amount'], 2 ) : '' ),
                'detail' => 'Sent ' . $c['date'] . ( $c['ticket'] ? ' · Reference #' . $c['ticket'] : '' ),
                'status' => $c['status'],
                'tone'   => $c['done'] ? 'good' : 'warn',
            ];
        }
    }

    return [
        'id'          => (int) $id,
        'name'        => nab_staff_member_name( $u ),
        'email'       => $u->user_email,
        'memberId'    => (string) get_user_meta( $id, 'nab_member_id', true ),
        'phone'       => (string) get_user_meta( $id, 'nab_phone', true ),
        'since'       => function_exists( 'nab_get_member_since' ) ? nab_get_member_since( $id ) : wp_date( 'M j, Y', strtotime( $u->user_registered . ' UTC' ) ),
        'membership'  => $labels[ $state ] ?? ucfirst( $status ),
        'tone'        => $tones[ $state ] ?? 'good',
        'access'      => $access,
        'lastLogin'   => $last_login ? nab_staff_when( get_gmt_from_date( $last_login ) ) : 'Never',
        'score'       => $score ? $score . ( $change ? ' (' . $change . ')' : '' ) : 'Not added',
        'disputes'    => $disputes,
        'collections' => $collections,
        'accessUrl'   => nab_staff_can( 'manage' ) ? add_query_arg( [ 'page' => 'nab-member-access', 's' => $u->user_email ], admin_url( 'users.php' ) ) : '',
    ];
}

/** The member's Freshdesk tickets (cached for 5 minutes). */
function nab_staff_member_tickets( $email ) {
    if ( ! function_exists( 'nab_freshdesk_config' ) || ! is_email( $email ) ) return [ 'tickets' => [], 'note' => 'Freshdesk is not connected.' ];
    $key = 'nab_staff_fd_' . md5( strtolower( $email ) );
    $hit = get_transient( $key );
    if ( is_array( $hit ) ) return $hit;
    list( $fd_key, $fd_domain ) = nab_freshdesk_config();
    $res = wp_remote_get( 'https://' . $fd_domain . '/api/v2/tickets?email=' . rawurlencode( $email ) . '&per_page=10&order_by=created_at&order_type=desc', [
        'timeout' => 15,
        'headers' => [ 'Authorization' => 'Basic ' . base64_encode( $fd_key . ':X' ) ],
    ] );
    if ( is_wp_error( $res ) || (int) wp_remote_retrieve_response_code( $res ) !== 200 ) {
        return [ 'tickets' => [], 'note' => 'Could not load tickets from Freshdesk right now.' ];
    }
    $map  = [ 2 => [ 'Open', 'warn' ], 3 => [ 'Pending', 'blue' ], 4 => [ 'Resolved', 'good' ], 5 => [ 'Closed', 'good' ] ];
    $list = [];
    foreach ( (array) json_decode( wp_remote_retrieve_body( $res ), true ) as $t ) {
        if ( ! isset( $t['id'] ) ) continue;
        $st     = $map[ (int) ( $t['status'] ?? 2 ) ] ?? [ 'Open', 'warn' ];
        $list[] = [
            'title'  => (string) ( $t['subject'] ?? 'Ticket' ),
            'detail' => '#' . (int) $t['id'] . ' · ' . wp_date( 'M j', strtotime( (string) ( $t['created_at'] ?? 'now' ) ) ),
            'status' => $st[0],
            'tone'   => $st[1],
            'url'    => 'https://' . $fd_domain . '/a/tickets/' . (int) $t['id'],
        ];
    }
    $out = [ 'tickets' => $list, 'note' => $list ? '' : 'No Freshdesk tickets for this email.' ];
    set_transient( $key, $out, 5 * MINUTE_IN_SECONDS );
    return $out;
}

/** Resend the "set your password" email. At most once every 10 minutes per member. */
function nab_staff_member_resend( $id ) {
    $u = get_userdata( (int) $id );
    if ( ! nab_staff_is_member( $u ) ) return [ 'error' => 'Member not found.' ];
    if ( ! function_exists( 'nab_dispatch_welcome_email' ) ) return [ 'error' => 'The member portal plugin is needed to send this email.' ];
    if ( get_transient( 'nab_staff_resend_' . $id ) ) return [ 'error' => 'A login email was sent to this member in the last 10 minutes. Please ask them to check their inbox and spam folder.' ];
    delete_user_meta( $id, 'nab_welcome_sent' );
    nab_dispatch_welcome_email( $id );
    set_transient( 'nab_staff_resend_' . $id, 1, 10 * MINUTE_IN_SECONDS );
    return [ 'message' => 'Login email sent to ' . $u->user_email . '. It has a link to set a new password.' ];
}

/** Short member details for the Reply Assistant (no contact or bank details). */
function nab_staff_member_brief( $email ) {
    $u = is_email( $email ) ? get_user_by( 'email', $email ) : null;
    if ( ! nab_staff_is_member( $u ) ) return null;
    $p = nab_staff_member_profile( $u->ID );
    $first = get_user_meta( $u->ID, 'nab_first_name', true ) ?: $u->first_name;
    return [
        'card' => [ 'name' => $p['name'], 'memberId' => $p['memberId'], 'since' => $p['since'], 'lastLogin' => $p['lastLogin'], 'membership' => $p['membership'], 'tone' => $p['tone'] ],
        'ai'   => "First name: {$first}\nMembership: {$p['membership']} ({$p['access']})\nMember since: {$p['since']}\nLast login to the portal: {$p['lastLogin']}\n"
                . ( $p['disputes'] ? 'Dispute letters: ' . implode( '; ', array_map( function ( $d ) { return $d['title'] . ' (' . $d['status'] . ')'; }, $p['disputes'] ) ) . "\n" : '' )
                . ( $p['collections'] ? 'Collection Assistance requests: ' . implode( '; ', array_map( function ( $c ) { return $c['title'] . ' (' . $c['status'] . ')'; }, $p['collections'] ) ) . "\n" : '' ),
    ];
}
