<?php
/**
 * NAB Member Portal — Account Page (v1.6.2)
 * Shortcode: [nab_account_page]
 * Place on your /account/ page.
 *
 * Tabs: Membership (pause/cancel/upgrade) | Profile | Billing | Security
 * All actions use AJAX-style POST with nonces — no page reload flicker.
 *
 * @package NAB_Member_Portal
 * @since   1.6.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'nab_account_page', 'nab_mp_account_shortcode' );
function nab_mp_account_shortcode() {
    if ( ! is_user_logged_in() ) {
        $login = nab_mp_active() ? mepr_login_url() : wp_login_url( get_permalink() );
        return '<p><a href="' . esc_url( $login ) . '">' . __( 'Please log in to view your account.', 'nab-portal' ) . '</a></p>';
    }

    $user_id = get_current_user_id();
    $user    = get_userdata( $user_id );
    $mp_data = function_exists( 'nab_mp_get_member_data' ) ? nab_mp_get_member_data( $user_id ) : [];
    $tier    = nab_get_member_tier( $user_id );
    $paused  = (bool) get_user_meta( $user_id, 'nab_mp_resume_date', true );
    $resume  = get_user_meta( $user_id, 'nab_mp_resume_date', true );
    $joined  = get_user_meta( $user_id, 'nab_joined_date', true );
    $points  = (int) get_user_meta( $user_id, 'nab_points', true );
    $level   = get_user_meta( $user_id, 'nab_level', true ) ?: 'bronze';
    $streak  = (int) get_user_meta( $user_id, 'nab_streak', true );
    $first   = get_user_meta( $user_id, 'nab_first_name', true ) ?: $user->first_name;
    $last    = get_user_meta( $user_id, 'nab_last_name', true )  ?: $user->last_name;
    $phone   = get_user_meta( $user_id, 'nab_phone', true );
    $goal    = get_user_meta( $user_id, 'nab_credit_goal', true );
    $track   = get_user_meta( $user_id, 'nab_track', true );

    // Process form submissions
    $msg = nab_mp_process_account_form( $user_id );

    ob_start();
    ?>
    <div class="nab-account-wrap">

    <?php if ( $msg['success'] ): ?>
        <div class="nab-alert nab-alert--success"><?php echo esc_html( $msg['success'] ); ?></div>
    <?php endif; ?>
    <?php if ( $msg['error'] ): ?>
        <div class="nab-alert nab-alert--error"><?php echo esc_html( $msg['error'] ); ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div class="nab-account-header">
        <div class="nab-account-avatar"><?php echo get_avatar( $user_id, 72, '', '', [ 'class' => 'nab-avatar-img' ] ); ?></div>
        <div class="nab-account-identity">
            <h2><?php echo esc_html( trim("$first $last") ?: $user->display_name ); ?></h2>
            <p class="nab-account-email"><?php echo esc_html( $user->user_email ); ?></p>
            <?php if ( $tier ): ?>
                <span class="nab-badge nab-badge--<?php echo esc_attr($tier); ?>"><?php echo esc_html( ucfirst($tier) ); ?> Member</span>
            <?php endif; ?>
            <?php if ( $paused ): ?>
                <span class="nab-badge nab-badge--paused">⏸ Paused until <?php echo esc_html( date('M j, Y', strtotime($resume)) ); ?></span>
            <?php endif; ?>
        </div>
        <div class="nab-account-stats">
            <div class="nab-stat"><span class="nab-stat__num"><?php echo esc_html($points); ?></span><span class="nab-stat__lbl">Points</span></div>
            <div class="nab-stat"><span class="nab-stat__num"><?php echo esc_html(ucfirst($level)); ?></span><span class="nab-stat__lbl">Level</span></div>
            <div class="nab-stat"><span class="nab-stat__num"><?php echo esc_html($streak); ?>wk</span><span class="nab-stat__lbl">Streak</span></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nab-tabs">
        <button class="nab-tab nab-tab--active" data-tab="membership">Membership</button>
        <button class="nab-tab" data-tab="profile">Profile</button>
        <button class="nab-tab" data-tab="billing">Billing</button>
        <button class="nab-tab" data-tab="security">Security</button>
    </div>

    <!-- MEMBERSHIP TAB -->
    <div class="nab-tab-pane nab-tab-pane--active" id="tab-membership">
        <?php if ( ! empty( $mp_data['has_subscription'] ) && $mp_data['has_subscription'] ): ?>
        <div class="nab-card">
            <h3 class="nab-card__title">Current Plan</h3>
            <div class="nab-plan-summary">
                <div class="nab-plan-name"><?php echo esc_html( $mp_data['plan_name'] ?? '' ); ?></div>
                <div class="nab-plan-price"><?php echo esc_html( $mp_data['plan_price'] ?? '' ); ?> <span>/ <?php echo esc_html( $mp_data['billing_cycle'] ?? '' ); ?></span></div>
                <?php if ( $joined ): ?><div class="nab-plan-since">Member since <?php echo esc_html( date('F j, Y', strtotime($joined)) ); ?></div><?php endif; ?>
                <?php if ( ! empty( $mp_data['next_billing_date'] ) ): ?><div class="nab-plan-since">Next billing: <?php echo esc_html( $mp_data['next_billing_date'] ); ?></div><?php endif; ?>
            </div>

            <!-- Pause -->
            <?php if ( ! $paused ): ?>
            <div class="nab-action-section">
                <h4>⏸ Pause Membership</h4>
                <p>Need a break? Pause billing for 2 or 4 weeks. Your data stays safe and billing restarts automatically.</p>
                <form method="post" class="nab-inline-form">
                    <?php wp_nonce_field( 'nab_pause_membership', 'nab_account_nonce' ); ?>
                    <input type="hidden" name="nab_action" value="pause_membership">
                    <label><input type="radio" name="pause_duration" value="2" checked> 2 weeks</label>
                    <label><input type="radio" name="pause_duration" value="4"> 4 weeks</label>
                    <button type="submit" class="nab-btn nab-btn--secondary nab-btn--sm">Pause Membership</button>
                </form>
            </div>
            <?php else: ?>
            <div class="nab-action-section nab-action-section--warning">
                <h4>⏸ Membership Paused</h4>
                <p>Resumes automatically on <strong><?php echo esc_html( date('F j, Y', strtotime($resume)) ); ?></strong>.</p>
                <form method="post">
                    <?php wp_nonce_field( 'nab_resume_membership', 'nab_account_nonce' ); ?>
                    <input type="hidden" name="nab_action" value="resume_membership">
                    <button type="submit" class="nab-btn nab-btn--primary nab-btn--sm">Resume Now</button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Upgrade -->
            <?php if ( $tier && $tier !== 'premium' ): ?>
            <div class="nab-action-section">
                <h4>⬆️ Upgrade Plan</h4>
                <p>Unlock AI tools, priority support and more.</p>
                <a href="<?php echo esc_url( home_url('/membership-plans/') ); ?>" class="nab-btn nab-btn--accent nab-btn--sm">View Plans →</a>
            </div>
            <?php endif; ?>

            <!-- Cancel -->
            <div class="nab-action-section">
                <h4>❌ Cancel Membership</h4>
                <p>You'll keep access until the end of your billing period.</p>
                <button type="button" class="nab-btn nab-btn--outline-red nab-btn--sm" id="nab-cancel-toggle">Cancel Membership</button>
                <form method="post" id="nab-cancel-form" class="nab-cancel-form nab-hidden" style="margin-top:16px;background:#fff5f5;border-radius:8px;padding:20px;">
                    <?php wp_nonce_field( 'nab_cancel_membership', 'nab_account_nonce' ); ?>
                    <input type="hidden" name="nab_action" value="cancel_membership">
                    <div class="nab-form-row" style="margin-bottom:12px;">
                        <label style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;">What's the reason? (optional)</label>
                        <textarea name="cancel_reason" rows="3" class="nab-form-input" style="width:100%;box-sizing:border-box;" placeholder="Help us improve…"></textarea>
                    </div>
                    <p style="color:#c62828;font-size:13px;margin:0 0 12px;">⚠️ This will cancel your subscription immediately.</p>
                    <button type="submit" class="nab-btn nab-btn--red nab-btn--sm">Confirm Cancellation</button>
                    <button type="button" class="nab-btn nab-btn--ghost nab-btn--sm" id="nab-cancel-dismiss" style="margin-left:8px;">Never mind</button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="nab-card" style="text-align:center;padding:48px;">
            <h3>No active membership</h3>
            <p style="color:#64748b;">Join NAB to access your full credit repair dashboard.</p>
            <a href="<?php echo esc_url( home_url('/membership-plans/') ); ?>" class="nab-btn nab-btn--primary">View Plans</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- PROFILE TAB -->
    <div class="nab-tab-pane" id="tab-profile">
        <div class="nab-card">
            <h3 class="nab-card__title">Edit Profile</h3>
            <form method="post">
                <?php wp_nonce_field( 'nab_update_profile', 'nab_account_nonce' ); ?>
                <input type="hidden" name="nab_action" value="update_profile">
                <div class="nab-form-grid">
                    <div class="nab-form-row">
                        <label>First Name</label>
                        <input type="text" name="nab_first_name" value="<?php echo esc_attr($first); ?>" class="nab-form-input" required>
                    </div>
                    <div class="nab-form-row">
                        <label>Last Name</label>
                        <input type="text" name="nab_last_name" value="<?php echo esc_attr($last); ?>" class="nab-form-input" required>
                    </div>
                    <div class="nab-form-row">
                        <label>Phone</label>
                        <input type="tel" name="nab_phone" value="<?php echo esc_attr($phone); ?>" class="nab-form-input">
                    </div>
                    <div class="nab-form-row">
                        <label>Credit Goal</label>
                        <select name="nab_credit_goal" class="nab-form-input">
                            <option value="">Select</option>
                            <?php foreach ( [ 'buy_home'=>'Buy a Home','buy_car'=>'Buy a Car','credit_card'=>'Get a Credit Card','improve'=>'Improve Score','dispute'=>'Dispute Errors' ] as $v=>$l ) printf('<option value="%s"%s>%s</option>',esc_attr($v),selected($goal,$v,false),esc_html($l)); ?>
                        </select>
                    </div>
                    <div class="nab-form-row">
                        <label>Education Track</label>
                        <select name="nab_track" class="nab-form-input">
                            <option value="">Select</option>
                            <?php foreach ( [ 'renter'=>'🏠 Renter','student'=>'🎓 Student','immigrant'=>'✈️ New to Canada' ] as $v=>$l ) printf('<option value="%s"%s>%s</option>',esc_attr($v),selected($track,$v,false),esc_html($l)); ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="nab-btn nab-btn--primary">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- BILLING TAB -->
    <div class="nab-tab-pane" id="tab-billing">
        <div class="nab-card">
            <h3 class="nab-card__title">Billing History</h3>
            <?php nab_mp_render_billing_history( $user_id ); ?>
        </div>
        <?php
        // PAD page link
        $pad_pages = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-pad', 'number' => 1 ] );
        if ( ! empty($pad_pages) ):
        ?>
        <div class="nab-card">
            <h3 class="nab-card__title">PAD Agreement</h3>
            <p style="color:#64748b;font-size:14px;">View and download your Pre-Authorised Debit agreement.</p>
            <a href="<?php echo esc_url( get_permalink($pad_pages[0]->ID) ); ?>" class="nab-btn nab-btn--secondary">View PAD Agreement →</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- SECURITY TAB -->
    <div class="nab-tab-pane" id="tab-security">
        <div class="nab-card">
            <h3 class="nab-card__title">Change Password</h3>
            <?php if ( nab_mp_active() ): ?>
                <?php echo do_shortcode( '[mepr-account-form]' ); ?>
            <?php else: ?>
                <p style="color:#64748b;font-size:14px;">Use the <a href="<?php echo esc_url(wp_lostpassword_url()); ?>">reset password</a> link to change your password.</p>
            <?php endif; ?>
        </div>
    </div>

    </div><!-- .nab-account-wrap -->

    <script>
    (function(){
        // Tab switching
        document.querySelectorAll('.nab-tab').forEach(function(btn){
            btn.addEventListener('click',function(){
                document.querySelectorAll('.nab-tab').forEach(function(b){b.classList.remove('nab-tab--active');});
                document.querySelectorAll('.nab-tab-pane').forEach(function(p){p.classList.remove('nab-tab-pane--active');});
                btn.classList.add('nab-tab--active');
                var pane = document.getElementById('tab-'+btn.dataset.tab);
                if(pane) pane.classList.add('nab-tab-pane--active');
            });
        });
        // Cancel toggle
        var ct=document.getElementById('nab-cancel-toggle'),
            cf=document.getElementById('nab-cancel-form'),
            cd=document.getElementById('nab-cancel-dismiss');
        if(ct&&cf){ ct.addEventListener('click',function(){ cf.classList.remove('nab-hidden'); }); }
        if(cd&&cf){ cd.addEventListener('click',function(){ cf.classList.add('nab-hidden'); }); }
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// PROCESS FORM ACTIONS
// ─────────────────────────────────────────────────────────────────────────────
function nab_mp_process_account_form( $user_id ) {
    $out = [ 'success' => '', 'error' => '' ];
    if ( empty( $_POST['nab_action'] ) ) return $out;

    $action = sanitize_key( $_POST['nab_action'] );
    $nonce  = $_POST['nab_account_nonce'] ?? '';

    switch ( $action ) {

        case 'pause_membership':
            if ( ! wp_verify_nonce( $nonce, 'nab_pause_membership' ) ) { $out['error'] = 'Security check failed.'; break; }
            if ( ! nab_mp_active() ) { $out['error'] = 'Membership system not active.'; break; }
            $weeks = in_array( (int)($_POST['pause_duration']??2), [2,4], true ) ? (int)$_POST['pause_duration'] : 2;
            $mepr_user = new MeprUser( $user_id );
            $sub = nab_mp_get_active_sub( $user_id );
            if ( method_exists($sub, 'suspend') ) $sub->suspend();
            $resume_date = date( 'Y-m-d', strtotime("+{$weeks} weeks") );
            update_user_meta( $user_id, 'nab_mp_resume_date', $resume_date );
            wp_schedule_single_event( strtotime($resume_date.' 09:00:00'), 'nab_mp_auto_resume', [ $user_id, $sub->id ] );
            if ( function_exists('nab_add_auto_notification') ) nab_add_auto_notification( $user_id, 'billing', '⏸ Membership Paused', "Paused {$weeks} week(s). Resumes ".date('M j, Y',strtotime($resume_date))."." );
            $out['success'] = "Membership paused for {$weeks} weeks. Billing resumes on ".date('F j, Y',strtotime($resume_date)).".";
            break;

        case 'resume_membership':
            if ( ! wp_verify_nonce( $nonce, 'nab_resume_membership' ) ) { $out['error'] = 'Security check failed.'; break; }
            if ( ! nab_mp_active() ) { $out['error'] = 'Membership system not active.'; break; }
            $mepr_user = new MeprUser( $user_id );
            $subs = $mepr_user->subscriptions();
            foreach ( $subs as $sub ) {
                if ( $sub->status === MeprSubscription::$suspended_str && method_exists($sub,'resume') ) { $sub->resume(); break; }
            }
            delete_user_meta( $user_id, 'nab_mp_resume_date' );
            if ( function_exists('nab_add_auto_notification') ) nab_add_auto_notification( $user_id, 'billing', '▶️ Membership Resumed', 'Welcome back! Your membership is active again.' );
            $out['success'] = 'Membership resumed. Welcome back!';
            break;

        case 'cancel_membership':
            if ( ! wp_verify_nonce( $nonce, 'nab_cancel_membership' ) ) { $out['error'] = 'Security check failed.'; break; }
            if ( ! nab_mp_active() ) { $out['error'] = 'Membership system not active.'; break; }
            $reason = sanitize_textarea_field( $_POST['cancel_reason'] ?? '' );
            $mepr_user = new MeprUser( $user_id );
            $sub = nab_mp_get_active_sub( $user_id );
            if ( ! $sub ) { $out['error'] = 'No active subscription found.'; break; }
            if ( method_exists($sub,'cancel') ) $sub->cancel();
            update_user_meta( $user_id, 'nab_cancel_reason', $reason );
            update_user_meta( $user_id, 'nab_cancel_date', current_time('mysql') );
            if ( function_exists('nab_add_auto_notification') ) nab_add_auto_notification( $user_id, 'billing', '❌ Membership Cancelled', 'Your membership has been cancelled. Access continues until end of billing period.' );
            $out['success'] = 'Membership cancelled. You retain access until your billing period ends.';
            break;

        case 'update_profile':
            if ( ! wp_verify_nonce( $nonce, 'nab_update_profile' ) ) { $out['error'] = 'Security check failed.'; break; }
            foreach ( [ 'nab_first_name','nab_last_name','nab_phone','nab_credit_goal','nab_track' ] as $f ) {
                if ( isset($_POST[$f]) ) update_user_meta( $user_id, $f, sanitize_text_field($_POST[$f]) );
            }
            wp_update_user( [ 'ID' => $user_id, 'first_name' => sanitize_text_field($_POST['nab_first_name']??''), 'last_name' => sanitize_text_field($_POST['nab_last_name']??'') ] );
            $out['success'] = 'Profile updated successfully.';
            break;
    }
    return $out;
}

// ─────────────────────────────────────────────────────────────────────────────
// BILLING HISTORY TABLE
// ─────────────────────────────────────────────────────────────────────────────
function nab_mp_render_billing_history( $user_id ) {
    if ( ! nab_mp_active() || ! class_exists('MeprTransaction') ) {
        echo '<p style="color:#94a3b8;font-size:14px;">Billing history will appear here once MemberPress is configured.</p>';
        return;
    }
    $txns = MeprTransaction::get_all_by_user_id( $user_id );
    if ( empty($txns) ) { echo '<p style="color:#94a3b8;font-size:14px;">No billing history yet.</p>'; return; }
    echo '<table class="nab-table nab-table--billing">';
    echo '<thead><tr><th>Date</th><th>Plan</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead><tbody>';
    foreach ( $txns as $txn ) {
        $product = new MeprProduct( $txn->product_id );
        $status  = $txn->status === MeprTransaction::$complete_str
            ? '<span class="nab-status nab-status--success">Paid</span>'
            : '<span class="nab-status nab-status--pending">'.esc_html(ucfirst($txn->status)).'</span>';
        echo '<tr>';
        echo '<td>'.esc_html(date('M j, Y',strtotime($txn->created_at))).'</td>';
        echo '<td>'.esc_html($product->post_title).'</td>';
        echo '<td>$'.esc_html(number_format($txn->amount,2)).'</td>';
        echo '<td>'.$status.'</td>';
        echo '<td><a href="'.esc_url($txn->receipt_url()).'" target="_blank" class="nab-link">View</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

// ─────────────────────────────────────────────────────────────────────────────
// WELCOME EMAIL on first signup
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'mepr-txn-status-complete', 'nab_mp_send_welcome_email', 40, 1 );
function nab_mp_send_welcome_email( $txn ) {
    if ( ! ($txn instanceof MeprTransaction) ) return;
    $user_id = (int) $txn->user_id;
    if ( get_user_meta( $user_id, 'nab_welcome_email_sent', true ) ) return;
    update_user_meta( $user_id, 'nab_welcome_email_sent', 1 );

    $user  = get_userdata( $user_id );
    $first = get_user_meta( $user_id, 'nab_first_name', true ) ?: $user->first_name ?: 'Member';
    $plan  = nab_get_member_plan( $user_id );
    $pname = $plan ? $plan['name'] : 'NAB Solutions';

    $pages = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dashboard', 'number' => 1 ] );
    $dash  = ! empty($pages) ? get_permalink($pages[0]->ID) : home_url('/');
    $year  = date('Y');
    $host  = parse_url( home_url(), PHP_URL_HOST );

    $subject = "🎉 Welcome to NAB, {$first}! Your credit journey starts now.";
    $body = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:40px 20px;">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 20px rgba(0,0,0,.08);">
    <tr><td style="background:linear-gradient(135deg,#0D5C9B,#0a4a7c);padding:28px 40px;text-align:center;">
    <h1 style="color:#fff;margin:0;font-size:20px;font-weight:700;">NAB Solutions — Member Portal</h1></td></tr>
    <tr><td style="padding:36px 40px;">
    <h2 style="color:#0D5C9B;margin:0 0 16px;">Welcome, '.esc_html($first).'! 🎉</h2>
    <p style="color:#424242;line-height:1.7;">You\'re now a <strong>'.esc_html($pname).'</strong> member. Your credit repair journey starts today.</p>
    <table cellpadding="0" cellspacing="0" width="100%">
        <tr><td style="padding:10px 0;border-bottom:1px solid #f0f0f0;">✅ <strong>View your Credit Score</strong> — know where you stand</td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #f0f0f0;">📚 <strong>Start an Education Module</strong> — earn points</td></tr>
        <tr><td style="padding:10px 0;">🛡️ <strong>Check Dispute Templates</strong> — fix errors on your file</td></tr>
    </table><br>
    <a href="'.esc_url($dash).'" style="display:inline-block;background:#0D5C9B;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:700;">Go to Dashboard →</a>
    </td></tr>
    <tr><td style="background:#f4f6f9;padding:16px 40px;text-align:center;font-size:12px;color:#9e9e9e;">© '.$year.' NAB Solutions. <a href="'.home_url('/account/').'" style="color:#0D5C9B;">Manage Account</a></td></tr>
    </table></td></tr></table></body></html>';

    wp_mail( $user->user_email, $subject, $body, [
        'Content-Type: text/html; charset=UTF-8',
        "From: NAB Solutions <noreply@{$host}>",
    ] );
}
