<?php
/**
 * NAB Member Portal — UM → MemberPress Migration Tool (v1.6.2)
 *
 * Admin page: WP Admin → Tools → NAB Migration
 * Run DRY RUN first. Migration only COPIES data — never deletes UM meta.
 * All existing dashboard features keep working after migration.
 *
 * @package NAB_Member_Portal
 * @since   1.6.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'nab_migration_menu' );
function nab_migration_menu() {
    add_management_page( 'NAB Migration', 'NAB Migration', 'manage_options', 'nab-migrate', 'nab_migration_page' );
}

function nab_migration_page() {
    if ( ! current_user_can('manage_options') ) wp_die('Access denied.');
    $result = null;
    if ( isset($_POST['nab_run_migration']) && wp_verify_nonce($_POST['_wpnonce'],'nab_migrate') ) {
        $result = nab_run_migration( (bool)($_POST['dry_run']??false) );
    }
    ?>
    <div class="wrap">
        <h1>NAB: Ultimate Member → MemberPress Migration</h1>
        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:14px 18px;margin:20px 0;border-radius:4px;">
            <strong>⚠️ Run a Dry Run first.</strong> This migration COPIES data only — it never deletes UM meta.
            All existing dashboard features keep working after migration.
        </div>
        <?php if ($result): ?>
        <div style="background:#d4edda;border-left:4px solid #28a745;padding:14px 18px;margin:20px 0;border-radius:4px;">
            <h3 style="margin:0 0 8px;"><?php echo $result['dry_run'] ? '🔍 Dry Run Complete' : '✅ Migration Complete'; ?></h3>
            <p style="margin:4px 0;">Processed: <strong><?php echo $result['processed']; ?></strong> &nbsp;|&nbsp;
            Migrated: <strong><?php echo $result['migrated']; ?></strong> &nbsp;|&nbsp;
            Skipped: <strong><?php echo $result['skipped']; ?></strong> &nbsp;|&nbsp;
            Errors: <strong><?php echo $result['errors']; ?></strong></p>
            <?php if (!empty($result['log'])): ?>
            <details style="margin-top:12px;"><summary>View Log</summary>
            <pre style="background:#f8f9fa;padding:12px;max-height:400px;overflow:auto;font-size:12px;margin-top:8px;"><?php echo esc_html(implode("\n",$result['log'])); ?></pre>
            </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field('nab_migrate'); ?>
            <table class="form-table"><tr>
                <th>Mode</th>
                <td><label><input type="checkbox" name="dry_run" value="1" checked> Dry Run (simulate, no changes)</label></td>
            </tr></table>
            <p class="submit"><button name="nab_run_migration" value="1" class="button button-primary">Run Migration</button></p>
        </form>
    </div>
    <?php
}

function nab_run_migration( $dry_run = true ) {
    $r = [ 'dry_run'=>$dry_run, 'processed'=>0, 'migrated'=>0, 'skipped'=>0, 'errors'=>0, 'log'=>[] ];
    if ( ! nab_mp_active() ) { $r['log'][] = 'ERROR: MemberPress not active.'; $r['errors']++; return $r; }

    // Map WP roles → MP plan IDs  (edit these to match your real plan IDs)
    $plan_map = [
        'subscriber'  => nab_mp_plan_id('basic'),
        'um_member'   => nab_mp_plan_id('basic'),
        'um_basic'    => nab_mp_plan_id('basic'),
        'um_standard' => nab_mp_plan_id('standard'),
        'um_premium'  => nab_mp_plan_id('premium'),
    ];

    $users = get_users( [ 'number' => -1, 'role__in' => array_keys($plan_map) ] );

    foreach ( $users as $user ) {
        $r['processed']++;
        $uid = $user->ID;
        $log = "[#{$uid} {$user->user_email}]";
        if ( user_can($uid,'manage_options') ) { $r['log'][] = "$log Skipped (admin)"; continue; }

        $mepr_user = new MeprUser($uid);
        if ( ! empty($mepr_user->active_product_subscriptions('ids')) ) {
            $r['log'][] = "$log Skipped (already has MP subscription)";
            $r['skipped']++;
            continue;
        }

        $target = 0;
        foreach ( (array)$user->roles as $role ) {
            if ( isset($plan_map[$role]) && $plan_map[$role] ) { $target = (int)$plan_map[$role]; break; }
        }
        if ( ! $target ) { $r['log'][] = "$log No matching plan for roles: ".implode(',',$user->roles); $r['errors']++; continue; }

        // Copy UM profile fields → our meta keys (won't overwrite if already set)
        $field_map = [ 'first_name'=>'nab_first_name', 'last_name'=>'nab_last_name' ];
        foreach ( $field_map as $src=>$dst ) {
            $v = get_user_meta($uid,$src,true);
            if ( $v && ! get_user_meta($uid,$dst,true) && ! $dry_run ) update_user_meta($uid,$dst,$v);
        }
        if ( ! get_user_meta($uid,'nab_onboarded',true) && ! $dry_run ) {
            update_user_meta($uid,'nab_onboarded',1);
            update_user_meta($uid,'nab_joined_date',$user->user_registered);
        }

        if ( ! $dry_run ) {
            try {
                $product = new MeprProduct($target);
                if ( ! $product->ID ) throw new Exception("Plan {$target} not found");
                $txn = new MeprTransaction();
                $txn->user_id    = $uid;
                $txn->product_id = $target;
                $txn->coupon_id  = 0;
                $txn->status     = MeprTransaction::$complete_str;
                $txn->txn_type   = MeprTransaction::$payment_str;
                $txn->gateway    = 'manual';
                $txn->amount     = 0.00; $txn->total = 0.00;
                $txn->tax_amount = 0.00; $txn->tax_rate = 0.00;
                $txn->tax_desc   = ''; $txn->tax_class = 'standard';
                $txn->trans_num  = 'nab-migration-'.$uid.'-'.time();
                $txn->created_at = MeprUtils::ts_to_mysql_date(time());
                $txn->expires_at = MeprUtils::ts_to_mysql_date(strtotime('+1 year'));
                $txn->store();
                $r['log'][] = "$log ✅ Migrated to plan #{$target} ({$product->post_title})";
                $r['migrated']++;
            } catch (Exception $e) {
                $r['log'][] = "$log ERROR: ".$e->getMessage();
                $r['errors']++;
            }
        } else {
            $product = new MeprProduct($target);
            $r['log'][] = "$log [DRY RUN] Would migrate to: ".($product->ID ? $product->post_title : "Plan #{$target}");
            $r['migrated']++;
        }
    }
    $r['log'][] = "─── Done. Processed:{$r['processed']} Migrated:{$r['migrated']} Skipped:{$r['skipped']} Errors:{$r['errors']} ───";
    return $r;
}
