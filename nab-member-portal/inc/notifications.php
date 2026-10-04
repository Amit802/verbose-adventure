<?php
/**
 * NAB Member Portal — Notification System
 *
 * Two notification sources:
 *   1. Admin-created: WP Admin → NAB Notifications (custom post type)
 *      - Target all members, or one specific user
 *      - Set type, expiry date
 *   2. Auto-triggered: fired by portal events (sim saved, loan results, welcome etc.)
 *      - Stored in user meta: nab_auto_notifications (JSON array, max 20)
 *      - Dismissed via AJAX — removed from the array
 *
 * All notifications merge and render via nab_get_user_notifications()
 * which is called by nab_render_notification_bell() in helpers.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════
   CUSTOM POST TYPE — Admin notification authoring
   ═══════════════════════════════════════════════════════════ */
add_action( 'init', 'nab_register_notification_cpt' );
function nab_register_notification_cpt() {
    register_post_type( 'nab_notification', [
        'label'           => 'NAB Notifications',
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => true,
        'menu_icon'       => 'dashicons-bell',
        'menu_position'   => 30,
        'supports'        => [ 'title', 'editor' ],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
        'rewrite'         => false,
        'query_var'       => false,
        'labels'          => [
            'name'          => 'NAB Notifications',
            'singular_name' => 'Notification',
            'add_new'       => 'Send New Notification',
            'add_new_item'  => 'Send New Notification',
            'edit_item'     => 'Edit Notification',
            'all_items'     => 'All Notifications',
            'menu_name'     => 'NAB Notifications',
        ],
    ] );
}

/* ── ACF fields for notification post type ── */
add_action( 'acf/init', 'nab_register_notification_acf_fields' );
function nab_register_notification_acf_fields() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'      => 'group_nab_notif_post',
        'title'    => 'Notification Settings',
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'nab_notification' ] ] ],
        'position' => 'normal',
        'fields'   => [
            [
                'key' => 'f_notif_type', 'label' => 'Notification Type', 'name' => 'notif_type',
                'type' => 'select', 'default_value' => 'info', 'wrapper' => [ 'width' => '33' ],
                'instructions' => 'Choose a category icon for this notification.',
                'choices' => [
                    'info'    => 'Announcement',
                    'dispute' => 'Dispute Update',
                    'score'   => 'Score Alert',
                    'billing' => 'Billing',
                    'loan'    => 'Loan Update',
                ],
            ],
            [
                'key' => 'f_notif_target', 'label' => 'Send To', 'name' => 'notif_target',
                'type' => 'select', 'default_value' => 'all', 'wrapper' => [ 'width' => '33' ],
                'choices' => [ 'all' => 'All Members', 'specific' => 'Specific User' ],
            ],
            [
                'key'               => 'f_notif_user_id', 'label' => 'User ID (if Specific)',
                'name'              => 'notif_user_id', 'type' => 'number',
                'wrapper'           => [ 'width' => '34' ],
                'instructions'      => 'Only used when "Send To" = Specific User. Find the User ID in WP Admin → Users.',
                'conditional_logic' => [ [ [ 'field' => 'f_notif_target', 'operator' => '==', 'value' => 'specific' ] ] ],
            ],
            [
                'key'          => 'f_notif_expires', 'label' => 'Expires On (optional)',
                'name'         => 'notif_expires', 'type' => 'date_picker',
                'display_format' => 'd M Y', 'return_format' => 'Y-m-d',
                'instructions' => 'Leave blank — notification never expires.',
                'wrapper'      => [ 'width' => '50' ],
            ],
        ],
    ] );
}

/* ═══════════════════════════════════════════════════════════
   nab_get_user_notifications()
   Returns merged list of admin-created + auto-event notifications.
   Called by nab_render_notification_bell() on every portal page.
   ═══════════════════════════════════════════════════════════ */
if ( ! function_exists( 'nab_get_user_notifications' ) ) {
    function nab_get_user_notifications( $user_id = 0 ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( ! $user_id ) return [];

        $dismissed = (array) get_user_meta( $user_id, 'nab_dismissed_notifications', true );
        $today     = wp_date( 'Y-m-d' );
        $results   = [];

        // ── 1. Admin CPT notifications ────────────────────
        $posts = get_posts( [
            'post_type'      => 'nab_notification',
            'post_status'    => 'publish',
            'posts_per_page' => 15,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );

        foreach ( $posts as $post ) {
            $nid = 'np_' . $post->ID;
            if ( in_array( $nid, $dismissed, true ) ) continue;

            $target  = get_field( 'notif_target', $post->ID ) ?: 'all';
            $expires = get_field( 'notif_expires', $post->ID );

            if ( $expires && $expires < $today ) continue;

            if ( $target === 'specific' ) {
                $target_uid = (int) get_field( 'notif_user_id', $post->ID );
                if ( $target_uid !== $user_id ) continue;
            }

            $results[] = [
                'id'      => $nid,
                'type'    => get_field( 'notif_type', $post->ID ) ?: 'info',
                'title'   => get_the_title( $post ),
                'message' => wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) ),
            ];
        }

        // ── 2. Auto-event notifications from user meta ────
        $auto = json_decode( get_user_meta( $user_id, 'nab_auto_notifications', true ) ?: '[]', true );
        foreach ( $auto as $n ) {
            if ( empty( $n['id'] ) ) continue;
            if ( in_array( $n['id'], $dismissed, true ) ) continue;
            $results[] = [
                'id'      => sanitize_key( $n['id'] ),
                'type'    => sanitize_key( $n['type'] ?? 'info' ),
                'title'   => sanitize_text_field( $n['title'] ?? '' ),
                'message' => sanitize_text_field( $n['message'] ?? '' ),
            ];
        }

        return $results;
    }
}

/* ═══════════════════════════════════════════════════════════
   AJAX: DISMISS NOTIFICATION
   Handles both CPT (np_X) and auto-event (auto_X) notifications.
   ═══════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_nab_dismiss_notification', function () {
    check_ajax_referer( 'nab_portal_nonce', 'nonce' );

    $nid = sanitize_key( $_POST['notification_id'] ?? '' ); // phpcs:ignore
    $uid = get_current_user_id();

    if ( ! $uid || ! $nid ) {
        wp_send_json_error( [ 'message' => 'Invalid request.' ] );
    }

    // Auto notifications live in the nab_auto_notifications JSON array
    if ( strpos( $nid, 'auto_' ) === 0 ) {
        $meta   = 'nab_auto_notifications';
        $notifs = json_decode( get_user_meta( $uid, $meta, true ) ?: '[]', true );
        $notifs = array_values( array_filter( $notifs, function ( $n ) use ( $nid ) {
            return ( $n['id'] ?? '' ) !== $nid;
        } ) );
        update_user_meta( $uid, $meta, wp_json_encode( $notifs ) );
    } else {
        // CPT notifications — add to dismissed list
        $dismissed   = (array) get_user_meta( $uid, 'nab_dismissed_notifications', true );
        $dismissed[] = $nid;
        update_user_meta( $uid, 'nab_dismissed_notifications', array_unique( $dismissed ) );
    }

    wp_send_json_success();
} );
