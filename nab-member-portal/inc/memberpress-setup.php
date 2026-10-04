<?php
/**
 * NAB Member Portal — MemberPress Extended Setup (v1.6.2)
 *
 * Plan IDs managed from WP Admin → Dashboard page ACF → "Membership Plans" tab.
 * No hardcoding needed. Matthew can update plan IDs himself anytime.
 *
 * @package NAB_Member_Portal
 * @since   1.6.2
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Get dashboard page ID (cached) ────────────────────────
function nab_get_dash_page_id() {
    static $did = null;
    if ( $did !== null ) return $did;
    $pages = get_pages( [ 'meta_key' => '_wp_page_template', 'meta_value' => 'nab-dashboard', 'number' => 1 ] );
    $did   = ! empty( $pages ) ? (int) $pages[0]->ID : 0;
    return $did;
}

// ── Get plan ID from ACF ──────────────────────────────────
function nab_mp_plan_id( $tier ) {
    $did = nab_get_dash_page_id();
    if ( ! $did || ! function_exists('get_field') ) return 0;
    $keys = [ 'basic' => 'nab_mp_plan_basic', 'standard' => 'nab_mp_plan_standard', 'premium' => 'nab_mp_plan_premium' ];
    return (int) ( get_field( $keys[$tier] ?? '', $did ) ?: 0 );
}

// ── Plans page URL ─────────────────────────────────────────
function nab_mp_plans_url() {
    $did = nab_get_dash_page_id();
    if ( $did && function_exists('get_field') ) {
        $url = get_field( 'nab_mp_plans_url', $did );
        if ( $url ) return esc_url( $url );
    }
    $page = get_page_by_path('membership-plans');
    return $page ? get_permalink($page->ID) : home_url('/membership-plans/');
}

// ── Price label from ACF ──────────────────────────────────
function nab_mp_price_label( $tier ) {
    $defaults = [ 'basic' => '$19/month', 'standard' => '$39/month', 'premium' => '$79/month' ];
    $did = nab_get_dash_page_id();
    if ( ! $did || ! function_exists('get_field') ) return $defaults[$tier] ?? '';
    $keys = [ 'basic' => 'nab_mp_basic_price', 'standard' => 'nab_mp_standard_price', 'premium' => 'nab_mp_premium_price' ];
    return get_field( $keys[$tier] ?? '', $did ) ?: ( $defaults[$tier] ?? '' );
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────
if ( ! function_exists('nab_get_member_plan') ) {
    function nab_get_member_plan( $user_id = null ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( ! $user_id || ! nab_mp_active() ) return false;
        $mepr_user = new MeprUser( $user_id );
        $subs = $mepr_user->active_product_subscriptions('products');
        if ( empty($subs) ) return false;
        $product = reset($subs);
        return [ 'id' => $product->ID, 'name' => $product->post_title, 'price' => $product->price, 'period' => $product->period_type ];
    }
}

if ( ! function_exists('nab_get_member_tier') ) {
    function nab_get_member_tier( $user_id = null ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( ! nab_mp_active() ) return false;
        // Use active_product_subscriptions which works across all MP versions
        $mepr_user = new MeprUser( $user_id );
        $active_ids = $mepr_user->active_product_subscriptions('ids');
        if ( empty($active_ids) ) return false;
        $prem = nab_mp_plan_id('premium');
        $std  = nab_mp_plan_id('standard');
        $bas  = nab_mp_plan_id('basic');
        if ( $prem && in_array($prem, $active_ids) ) return 'premium';
        if ( $std  && in_array($std,  $active_ids) ) return 'standard';
        if ( $bas  && in_array($bas,  $active_ids) ) return 'basic';
        return false;
    }
}

if ( ! function_exists('nab_is_active_member') ) {
    function nab_is_active_member( $user_id = null ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( ! $user_id || ! nab_mp_active() ) return false;
        $mepr_user = new MeprUser( $user_id );
        return ! empty( $mepr_user->active_product_subscriptions('ids') );
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// GET ACTIVE SUBSCRIPTION OBJECT — safe across all MP versions
// ─────────────────────────────────────────────────────────────────────────────
function nab_mp_get_active_sub( $user_id = null ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    if ( ! nab_mp_active() ) return false;
    $mepr_user = new MeprUser( $user_id );
    // get all subscriptions and find first active one
    if ( method_exists($mepr_user, 'subscriptions') ) {
        foreach ( $mepr_user->subscriptions() as $sub ) {
            if ( in_array($sub->status, ['active', 'suspended'], true) ) return $sub;
        }
    }
    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// REDIRECTS
// ─────────────────────────────────────────────────────────────────────────────
add_filter( 'mepr-login-redirect-url', function( $url, $user ) {
    $pages = get_pages(['meta_key'=>'_wp_page_template','meta_value'=>'nab-dashboard','number'=>1]);
    return ! empty($pages) ? get_permalink($pages[0]->ID) : $url;
}, 10, 2 );

add_filter( 'mepr-signup-redirect', function( $txn ) {
    $pages = get_pages(['meta_key'=>'_wp_page_template','meta_value'=>'nab-dashboard','number'=>1]);
    return ! empty($pages) ? get_permalink($pages[0]->ID) : home_url('/');
} );

add_filter( 'mepr-logout-redirect-url', function( $url ) {
    return home_url('/');
} );

// ─────────────────────────────────────────────────────────────────────────────
// SIGNUP FIELDS
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'mepr-signup-form-fields', function( $product ) {
    $v = [ 'nab_first_name'=>sanitize_text_field($_POST['nab_first_name']??''), 'nab_last_name'=>sanitize_text_field($_POST['nab_last_name']??''), 'nab_phone'=>sanitize_text_field($_POST['nab_phone']??''), 'nab_credit_goal'=>sanitize_text_field($_POST['nab_credit_goal']??''), 'nab_track'=>sanitize_text_field($_POST['nab_track']??'') ];
    ?>
    <div class="mepr-form-row"><label>First Name <span style="color:#e53e3e">*</span></label><input type="text" name="nab_first_name" class="mepr-form-input" required value="<?php echo esc_attr($v['nab_first_name']); ?>"></div>
    <div class="mepr-form-row"><label>Last Name <span style="color:#e53e3e">*</span></label><input type="text" name="nab_last_name" class="mepr-form-input" required value="<?php echo esc_attr($v['nab_last_name']); ?>"></div>
    <div class="mepr-form-row"><label>Phone Number</label><input type="tel" name="nab_phone" class="mepr-form-input" value="<?php echo esc_attr($v['nab_phone']); ?>"></div>
    <div class="mepr-form-row"><label>Credit Goal</label>
    <select name="nab_credit_goal" class="mepr-form-input"><option value="">Select goal</option>
    <?php foreach(['buy_home'=>'Buy a Home','buy_car'=>'Buy a Car','credit_card'=>'Get a Credit Card','improve'=>'Improve Credit Score','dispute'=>'Dispute Errors'] as $val=>$lbl): ?><option value="<?php echo esc_attr($val); ?>"<?php selected($v['nab_credit_goal'],$val);?>><?php echo esc_html($lbl);?></option><?php endforeach;?></select></div>
    <div class="mepr-form-row"><label>Education Track</label>
    <select name="nab_track" class="mepr-form-input"><option value="">Choose track</option>
    <?php foreach(['renter'=>'🏠 Renter','student'=>'🎓 Student','immigrant'=>'✈️ New to Canada'] as $val=>$lbl): ?><option value="<?php echo esc_attr($val); ?>"<?php selected($v['nab_track'],$val);?>><?php echo esc_html($lbl);?></option><?php endforeach;?></select></div>
    <?php
} );

add_action( 'mepr-signup-form-fields-save', function( $user ) {
    foreach(['nab_first_name','nab_last_name','nab_phone','nab_credit_goal','nab_track'] as $f) {
        if(!empty($_POST[$f])) update_user_meta($user->ID,$f,sanitize_text_field($_POST[$f]));
    }
    if(!empty($_POST['nab_first_name'])) wp_update_user(['ID'=>$user->ID,'first_name'=>sanitize_text_field($_POST['nab_first_name'])]);
    if(!empty($_POST['nab_last_name']))  wp_update_user(['ID'=>$user->ID,'last_name'=>sanitize_text_field($_POST['nab_last_name'])]);
}, 10, 1 );

add_filter( 'mepr-validate-signup', function($errors) {
    if(empty($_POST['nab_first_name'])) $errors[]='First Name is required.';
    if(empty($_POST['nab_last_name']))  $errors[]='Last Name is required.';
    return $errors;
} );

// ─────────────────────────────────────────────────────────────────────────────
// ONBOARDING — first transaction only
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'mepr-txn-status-complete', function($txn) {
    if(!($txn instanceof MeprTransaction)) return;
    $uid=(int)$txn->user_id;
    if(get_user_meta($uid,'nab_onboarded',true)) return;
    update_user_meta($uid,'nab_onboarded',1);
    update_user_meta($uid,'nab_joined_date',current_time('mysql'));
    update_user_meta($uid,'nab_points',10);
    update_user_meta($uid,'nab_streak',0);
    update_user_meta($uid,'nab_level','bronze');
}, 30, 1 );

// ─────────────────────────────────────────────────────────────────────────────
// WELCOME EMAIL WITH SET-PASSWORD LINK
// Client requirement: users created by NAB admin get email with link to
// set their own password — no double registration needed.
// ─────────────────────────────────────────────────────────────────────────────
function nab_dispatch_welcome_email( $user_id ) {
    if( get_user_meta($user_id,'nab_welcome_sent',true) ) return;
    update_user_meta($user_id,'nab_welcome_sent',1);
    $user  = get_userdata($user_id);
    if(!$user) return;
    $first = get_user_meta($user_id,'nab_first_name',true) ?: $user->first_name ?: 'Member';
    $key   = get_password_reset_key($user);
    if(is_wp_error($key)) return;
    $link  = add_query_arg(['action'=>'rp','key'=>$key,'login'=>rawurlencode($user->user_login)],wp_login_url());
    $pages = get_pages(['meta_key'=>'_wp_page_template','meta_value'=>'nab-dashboard','number'=>1]);
    $dash  = !empty($pages)?get_permalink($pages[0]->ID):home_url('/');
    $host  = parse_url(home_url(),PHP_URL_HOST);
    $year  = date('Y');
    $subj  = 'Welcome to NAB Solutions, your portal is ready';
    $body  = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:40px 20px;">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 20px rgba(0,0,0,.08);">
<tr><td style="background:linear-gradient(135deg,#0D5C9B,#0a4a7c);padding:28px 40px;text-align:center;"><h1 style="color:#fff;margin:0;font-size:20px;font-weight:700;">NAB Solutions — Member Portal</h1></td></tr>
<tr><td style="padding:36px 40px;">
<p style="color:#424242;line-height:1.7;margin:0 0 16px;">Hi '.esc_html($first).',</p>
<h2 style="color:#0D5C9B;margin:0 0 14px;">Welcome to NAB Solutions!</h2>
<p style="color:#424242;line-height:1.7;margin:0 0 16px;">Your Member Portal is ready to go. It&rsquo;s your one-stop spot for your NAB resources, tools, and support.</p>
<p style="color:#424242;line-height:1.7;margin:0 0 20px;">Here&rsquo;s how to get started. Click the button below to set your password and get into your portal.</p>
<a href="'.esc_url($link).'" style="display:inline-block;background:#0D5C9B;color:#fff;padding:16px 36px;border-radius:8px;text-decoration:none;font-weight:700;font-size:16px;margin-bottom:24px;">Set My Password &amp; Access My Portal &#x2192;</a>
<p style="color:#64748b;font-size:13px;margin:0 0 24px;">This link expires in <strong>24 hours</strong>. If it expires, no problem, just use the Forgot Password option on the login page to set a new one.</p>
<p style="color:#424242;line-height:1.7;margin:0 0 10px;">Once you&rsquo;re in, your portal gives you access to:</p>
<ul style="color:#424242;line-height:1.8;margin:0 0 20px;padding-left:20px;">
<li>Credit and dispute resources</li>
<li>Loan tools, including help finding the right lender for you</li>
<li>Financial tools and calculators to manage your money</li>
<li>Educational resources to help you build financial knowledge</li>
<li>Your personalized financial roadmap</li>
<li>Member support whenever you need it</li>
</ul>
<p style="color:#64748b;font-size:13px;margin:0 0 16px;">Keep your login info secure and don&rsquo;t share your password with anyone.</p>
<div style="background:#f8fafc;border-radius:8px;padding:14px 18px;margin-bottom:20px;">
<p style="margin:0;font-size:12px;color:#64748b;line-height:1.6;">Or copy this link:<br><span style="color:#0D5C9B;word-break:break-all;">'.esc_url($link).'</span></p></div>
<p style="color:#94a3b8;font-size:12px;margin:0 0 20px;">If you weren&rsquo;t expecting this email, you can safely ignore it.</p>
<p style="color:#424242;line-height:1.7;margin:0 0 4px;">Welcome aboard. We&rsquo;re glad to help you take the next step toward better financial management.</p>
<p style="color:#424242;line-height:1.7;margin:0;">NAB Solutions Team</p>
</td></tr>
<tr><td style="background:#f4f6f9;padding:14px 40px;text-align:center;font-size:12px;color:#9e9e9e;">&copy; '.$year.' NAB Solutions &nbsp;&bull;&nbsp;<a href="'.esc_url($dash).'" style="color:#0D5C9B;">Go to Dashboard</a></td></tr>
</table></td></tr></table></body></html>';
    wp_mail($user->user_email,$subj,$body,['Content-Type: text/html; charset=UTF-8',"From: NAB Solutions <noreply@{$host}>"]);
}

// Suppress WordPress default "new user" notification email
// Our branded welcome email replaces it completely
add_filter('wp_send_new_user_notification_to_user', '__return_false');
add_filter('wp_send_new_user_notification_to_admin', '__return_false');

// Fires when admin manually creates a user (NAB's main flow)
// Sent at the end of the request, not inside user_register: the REST
// create-member endpoint saves first/last name AFTER the user is created,
// so sending immediately greeted those members as "Hi Member".
add_action('user_register', function($uid) {
    if(did_action('mepr-signup')) return; // MP checkout handles its own
    add_action('shutdown', function() use ($uid) {
        nab_dispatch_welcome_email($uid);
    });
}, 20, 1);

// Also fires on first MP transaction
add_action('mepr-txn-status-complete', function($txn) {
    if(!($txn instanceof MeprTransaction)) return;
    nab_dispatch_welcome_email((int)$txn->user_id);
}, 40, 1);

// ─────────────────────────────────────────────────────────────────────────────
// FORGOT PASSWORD LINK on MemberPress login form
// ─────────────────────────────────────────────────────────────────────────────
add_action('mepr-login-form-before-submit', function() {
    echo '<div style="text-align:right;margin-bottom:10px;"><a href="'.esc_url(wp_lostpassword_url()).'" style="font-size:13px;color:#0D5C9B;text-decoration:none;">Forgot Password?</a></div>';
});

// ─────────────────────────────────────────────────────────────────────────────
// RESEND WELCOME EMAIL — button on Users > Edit User in WP Admin
// ─────────────────────────────────────────────────────────────────────────────
add_action('edit_user_profile','nab_admin_resend_btn');
add_action('show_user_profile','nab_admin_resend_btn');
function nab_admin_resend_btn($user) {
    if(!current_user_can('manage_options')) return;
    $url = add_query_arg(['nab_resend_welcome'=>$user->ID,'_wpnonce'=>wp_create_nonce('nab_resend_'.$user->ID)]);
    echo '<h2>NAB Portal</h2><table class="form-table"><tr><th>Welcome Email</th><td>
    <a href="'.esc_url($url).'" class="button">&#x1F4E7; Resend Set-Password Email</a>
    <p class="description">Sends fresh set-password link to '.esc_html($user->user_email).'</p>
    </td></tr></table>';
}
add_action('admin_init', function() {
    if(empty($_GET['nab_resend_welcome'])||!current_user_can('manage_options')) return;
    $uid=(int)$_GET['nab_resend_welcome'];
    if(!wp_verify_nonce($_GET['_wpnonce']??'','nab_resend_'.$uid)) wp_die('Invalid nonce');
    delete_user_meta($uid,'nab_welcome_sent');
    nab_dispatch_welcome_email($uid);
    wp_redirect(add_query_arg('nab_resent','1',get_edit_user_link($uid))); exit;
});
add_action('admin_notices', function() {
    if(isset($_GET['nab_resent'])) echo '<div class="notice notice-success is-dismissible"><p>&#x2705; Welcome email resent.</p></div>';
});

// Elementor optimization handled via external JS file



// ─────────────────────────────────────────────────────────────────────────────
// KILL ELEMENTOR JS (not CSS) on NAB portal pages
// Elementor's frontend.min.js crashes with "elementorFrontendConfig is not
// defined" on custom PHP templates — this crash causes continuous page reloads.
// We only kill the JS — the CSS is needed by Hello Elementor theme for layout.
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_page() ) return;
    $tpl = get_post_meta( get_queried_object_id(), '_wp_page_template', true );
    if ( strpos( $tpl, 'nab-' ) === false ) return;

    // Kill Elementor JS only — CSS must stay for Hello theme layout
    $kill = [
        'elementor-frontend',
        'elementor-frontend-modules',
        'elementor-webpack-runtime',
        'elementor-common',
        'elementor-app-loader',
        'elementor-dev-tools',
        'elementor-common-modules',
        'elementor-web-cli',
        'elementor-dialog',
        'elementor-api-request',
        'elementor-backbone-marionette',
        'elementor-backbone-radio',
        'elementor-hooks',
        'elementor-i18n',
        'elementor-pro-frontend',
        'elementor-pro-webpack-runtime',
        // WP overhead
        'heartbeat',
        'autosave',
        'wp-auth-check',
    ];
    foreach ( $kill as $s ) {
        wp_dequeue_script( $s );
        wp_deregister_script( $s );
    }
}, 999 );

// Belt-and-suspenders: also kill at wp_print_scripts (catches late enqueues)
add_action( 'wp_print_scripts', function() {
    if ( ! is_page() ) return;
    $tpl = get_post_meta( get_queried_object_id(), '_wp_page_template', true );
    if ( strpos( $tpl, 'nab-' ) === false ) return;
    $kill = [
        'elementor-frontend', 'elementor-frontend-modules',
        'elementor-webpack-runtime', 'elementor-common',
        'elementor-app-loader', 'elementor-dev-tools',
        'elementor-pro-frontend', 'elementor-pro-webpack-runtime',
        'heartbeat', 'autosave', 'wp-auth-check',
    ];
    foreach ( $kill as $s ) {
        wp_dequeue_script( $s );
        wp_deregister_script( $s );
    }
}, 999 );

// Belt and suspenders - also use heartbeat_settings
add_filter( 'heartbeat_settings', function( $settings ) {
    if ( ! is_page() ) return $settings;
    $tpl = get_post_meta( get_queried_object_id(), '_wp_page_template', true );
    if ( strpos( $tpl, 'nab-' ) !== false ) {
        $settings['interval'] = 3600; // 1 hour effectively disables it
    }
    return $settings;
} );

// And via Elementor's heartbeat
add_filter( 'elementor/editor/heartbeat_options', function( $settings ) {
    return $settings;
} );

// ─────────────────────────────────────────────────────────────────────────────
// LITESPEED CACHE — exclude all portal pages from caching
// Portal pages contain nonces and user-specific data — must never be cached
// ─────────────────────────────────────────────────────────────────────────────
add_filter( 'litespeed_is_not_cacheable', function( $not_cacheable ) {
    if ( ! is_user_logged_in() ) return $not_cacheable;
    // Exclude every registered portal tool (inc/tools.php) unless it opts in
    // with 'cache' => true. v1.9.1: now also covers LFP, Learning Center and
    // Emergency Fund, which show member-specific data but were missing here.
    $tool = nab_portal_current_tool();
    if ( $tool && empty( nab_portal_tools()[ $tool ]['cache'] ) ) return true;
    return $not_cacheable;
} );

// ─────────────────────────────────────────────────────────────────────────────
// CSS
// ─────────────────────────────────────────────────────────────────────────────
add_action('wp_enqueue_scripts', function() {
    if(!is_page()) return;
    $slug = get_post_field('post_name',get_queried_object_id());
    if(in_array($slug,['account','membership-plans','welcome'],true))
        wp_enqueue_style('nab-memberpress',NAB_URL.'assets/css/nab-memberpress.css',[],NAB_VERSION);
});

// ─────────────────────────────────────────────────────────────────────────────
// [nab_login] SHORTCODE
// ─────────────────────────────────────────────────────────────────────────────
add_shortcode('nab_login', function($atts) {
    if(is_user_logged_in()) {
        $pages=get_pages(['meta_key'=>'_wp_page_template','meta_value'=>'nab-dashboard','number'=>1]);
        $url=!empty($pages)?get_permalink($pages[0]->ID):home_url('/');
        return '<p>You are logged in. <a href="'.esc_url($url).'">Go to your dashboard &rarr;</a></p>';
    }
    return nab_mp_active()?do_shortcode('[mepr-login-form]'):wp_login_form(['echo'=>false]);
});
