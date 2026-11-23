<?php
/**
 * Team discussions: anyone on the team can start a topic, comment and vote.
 * The author or a manager can change a topic's status or delete it.
 *
 * Meta: _type, _status, _votes (array of user IDs), _comments (array of
 * [ uid, text, time (GMT mysql) ]).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function nab_staff_topic_types() {
    return [ 'suggestion' => 'Suggestion', 'process' => 'Process', 'feedback' => 'Member feedback', 'question' => 'Question', 'team' => 'Team' ];
}
function nab_staff_topic_statuses() {
    return [ 'open' => [ 'Open', 'warn' ], 'discussing' => [ 'Discussing', 'warn' ], 'planned' => [ 'Planned', 'blue' ], 'done' => [ 'Done', 'good' ] ];
}

function nab_staff_topic_view( $p ) {
    $uid      = get_current_user_id();
    $votes    = array_map( 'intval', (array) get_post_meta( $p->ID, '_votes', true ) );
    $comments = (array) get_post_meta( $p->ID, '_comments', true );
    $type     = get_post_meta( $p->ID, '_type', true ) ?: 'suggestion';
    $status   = get_post_meta( $p->ID, '_status', true ) ?: 'open';
    $st       = nab_staff_topic_statuses()[ $status ] ?? [ 'Open', 'warn' ];
    $author   = nab_staff_person( $p->post_author );
    return [
        'id'       => (int) $p->ID,
        'title'    => get_the_title( $p ),
        'body'     => (string) $p->post_content,
        'type'     => $type,
        'typeLabel'=> nab_staff_topic_types()[ $type ] ?? 'Suggestion',
        'status'   => $status,
        'statusLabel' => $st[0],
        'statusTone'  => $st[1],
        'author'   => $author['name'],
        'init'     => $author['init'],
        'when'     => nab_staff_when( $p->post_date_gmt ),
        'votes'    => count( $votes ),
        'voted'    => in_array( $uid, $votes, true ),
        'canEdit'  => (int) $p->post_author === $uid || nab_staff_can( 'manage' ),
        'comments' => array_values( array_map( function ( $c ) {
            $who = nab_staff_person( $c['uid'] ?? 0 );
            return [ 'name' => $who['name'], 'init' => $who['init'], 'text' => (string) ( $c['text'] ?? '' ), 'when' => nab_staff_when( $c['time'] ?? '' ) ];
        }, array_filter( $comments, 'is_array' ) ) ),
    ];
}

/** Topics, most voted first, then newest. Done topics go last. */
function nab_staff_topics( $type = '' ) {
    $q = [ 'post_type' => 'nab_staff_topic', 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'date', 'order' => 'DESC' ];
    if ( $type && isset( nab_staff_topic_types()[ $type ] ) ) $q['meta_query'] = [ [ 'key' => '_type', 'value' => $type ] ];
    $list = array_map( 'nab_staff_topic_view', get_posts( $q ) );
    usort( $list, function ( $a, $b ) {
        $da = $a['status'] === 'done'; $db = $b['status'] === 'done';
        if ( $da !== $db ) return $da ? 1 : -1;
        return $b['votes'] <=> $a['votes'];
    } );
    return $list;
}

function nab_staff_topic_create() {
    $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
    $body  = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) );
    $type  = sanitize_key( $_POST['type'] ?? '' );
    if ( $title === '' ) return [ 'error' => 'Please give the topic a title.' ];
    if ( ! isset( nab_staff_topic_types()[ $type ] ) ) $type = 'suggestion';
    $id = wp_insert_post( [ 'post_type' => 'nab_staff_topic', 'post_status' => 'publish', 'post_title' => mb_substr( $title, 0, 150 ), 'post_content' => mb_substr( $body, 0, 5000 ), 'post_author' => get_current_user_id() ], true );
    if ( is_wp_error( $id ) ) return [ 'error' => 'The topic could not be saved.' ];
    update_post_meta( $id, '_type', $type );
    update_post_meta( $id, '_status', 'open' );
    update_post_meta( $id, '_votes', [ get_current_user_id() ] );
    update_post_meta( $id, '_comments', [] );
    return [ 'topic' => nab_staff_topic_view( get_post( $id ) ) ];
}

function nab_staff_topic_get( $id ) {
    $p = get_post( $id );
    return $p && $p->post_type === 'nab_staff_topic' && $p->post_status === 'publish' ? $p : null;
}

function nab_staff_topic_vote( $id ) {
    $p = nab_staff_topic_get( $id );
    if ( ! $p ) return [ 'error' => 'Topic not found.' ];
    $uid   = get_current_user_id();
    $votes = array_map( 'intval', (array) get_post_meta( $id, '_votes', true ) );
    $votes = in_array( $uid, $votes, true ) ? array_values( array_diff( $votes, [ $uid ] ) ) : array_merge( $votes, [ $uid ] );
    update_post_meta( $id, '_votes', $votes );
    return [ 'votes' => count( $votes ), 'voted' => in_array( $uid, $votes, true ) ];
}

function nab_staff_topic_comment( $id ) {
    $p = nab_staff_topic_get( $id );
    if ( ! $p ) return [ 'error' => 'Topic not found.' ];
    $text = sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) );
    if ( $text === '' ) return [ 'error' => 'Write a comment first.' ];
    $comments   = array_filter( (array) get_post_meta( $id, '_comments', true ), 'is_array' );
    $comments[] = [ 'uid' => get_current_user_id(), 'text' => mb_substr( $text, 0, 2000 ), 'time' => current_time( 'mysql', true ) ];
    update_post_meta( $id, '_comments', array_values( $comments ) );
    if ( ( get_post_meta( $id, '_status', true ) ?: 'open' ) === 'open' && (int) $p->post_author !== get_current_user_id() ) {
        update_post_meta( $id, '_status', 'discussing' );
    }
    return [ 'topic' => nab_staff_topic_view( get_post( $id ) ) ];
}

function nab_staff_topic_status( $id ) {
    $p = nab_staff_topic_get( $id );
    if ( ! $p ) return [ 'error' => 'Topic not found.' ];
    if ( (int) $p->post_author !== get_current_user_id() && ! nab_staff_can( 'manage' ) ) return [ 'error' => 'Only the person who started this topic or a manager can change it.' ];
    $status = sanitize_key( $_POST['status'] ?? '' );
    if ( ! isset( nab_staff_topic_statuses()[ $status ] ) ) return [ 'error' => 'Unknown status.' ];
    update_post_meta( $id, '_status', $status );
    return [ 'topic' => nab_staff_topic_view( get_post( $id ) ) ];
}

function nab_staff_topic_delete( $id ) {
    $p = nab_staff_topic_get( $id );
    if ( ! $p ) return [ 'error' => 'Topic not found.' ];
    if ( (int) $p->post_author !== get_current_user_id() && ! nab_staff_can( 'manage' ) ) return [ 'error' => 'Only the person who started this topic or a manager can delete it.' ];
    wp_delete_post( $id, true );
    return [ 'message' => 'Deleted.' ];
}
