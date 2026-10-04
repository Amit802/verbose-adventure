<?php
/**
 * Template Name: NAB Credit Score Impact Simulator
 *
 * ✅ Compatible with: Hello Elementor theme
 * ✅ Place this file in: /wp-content/themes/hello-elementor/
 * ✅ Filename MUST be: page-nab-simulator.php
 *
 * Requires: Advanced Custom Fields FREE
 */

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

$current_user  = wp_get_current_user();
$user_id       = get_current_user_id();
$first_name    = $current_user->first_name ?: $current_user->display_name;
$last_name     = $current_user->last_name  ?: '';
$initials      = strtoupper( substr( $first_name, 0, 1 ) . substr( $last_name, 0, 1 ) ) ?: 'M';

$member_status  = get_field( 'nab_member_status' )     ?: 'Active';
$member_since   = nab_get_member_since( $user_id );
$payment_status = get_field( 'nab_payment_status' )     ?: 'Secured';
$suspension_msg = get_field( 'nab_suspension_message' ) ?: '';
$sim_disclaimer = get_field( 'nab_sim_disclaimer' )
    ?: 'Estimates are for educational purposes only and are based on simplified scenarios. Actual score changes will vary based on your full credit profile and activity across all your accounts.
          <p style="font-size:11px;color:#94a3b8;margin-top:8px;line-height:1.5;">NAB Solutions does not guarantee the accuracy, completeness, or timeliness of simulator outputs and is not liable for any decisions or actions taken based on these estimates.</p>';

$member_id = get_field( 'nab_member_id' );
if ( ! $member_id ) {
    $member_id = '#NAB-' . str_pad( $user_id, 4, '0', STR_PAD_LEFT );
}

$dashboard_page = get_page_by_path( 'dashboard' );
$dash_id        = $dashboard_page ? $dashboard_page->ID : 0;

function sim_get_dash_url( $field, $dash_id ) {
    if ( ! function_exists( 'get_field' ) || ! $dash_id ) return '#';
    $val = get_field( $field, $dash_id );
    if ( is_array( $val ) && isset( $val['url'] ) ) return esc_url( $val['url'] );
    if ( is_string( $val ) && ! empty( $val ) )     return esc_url( $val );
    return '#';
}

$dashboard_url = $dash_id ? esc_url( get_permalink( $dash_id ) ) : esc_url( home_url( '/dashboard/' ) );
$url_report    = sim_get_dash_url( 'nab_link_report',      $dash_id );
$url_util      = sim_get_dash_url( 'nab_link_utilization', $dash_id );
$url_sim       = esc_url( get_permalink() );
$url_dispute   = sim_get_dash_url( 'nab_link_dispute',     $dash_id );
$url_booking   = sim_get_dash_url( 'nab_link_booking',     $dash_id );
$url_chatbot   = sim_get_dash_url( 'nab_link_chatbot',     $dash_id );
$url_blog      = sim_get_dash_url( 'nab_link_blog',        $dash_id );
$url_diy       = sim_get_dash_url( 'nab_link_diy',         $dash_id );
$url_learning  = sim_get_dash_url( 'nab_link_learning', $dash_id );
$url_support   = sim_get_dash_url( 'nab_link_support',  $dash_id );
$url_loan      = sim_get_dash_url( 'nab_link_loan',        $dash_id );
$url_card      = sim_get_dash_url( 'nab_link_card_match',        $dash_id );

$sim_history = get_user_meta( $user_id, 'nab_sim_history', true );
$sim_history = $sim_history ? json_decode( $sim_history, true ) : [];

$is_suspended = ( $member_status === 'Suspended' );
$hour         = (int) current_time( 'G' );
$greeting     = $hour < 12 ? 'Morning' : ( $hour < 17 ? 'Afternoon' : 'Evening' );

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Credit Score Impact Simulator — NAB Member Portal</title>
<?php wp_head(); ?>
<style>
.site-header,.site-footer,.elementor-location-header,
.elementor-location-footer,#masthead,#colophon { display:none !important; }
body { margin:0 !important; padding:0 !important; background:#F0F4FA !important; }
.elementor-page .elementor-section-wrap,
.e-page-settings .elementor-section-wrap { padding:0 !important; }

*,*::before,*::after { box-sizing:border-box; }
.nab-portal-wrap { display:flex; min-height:100vh; font-family:'Inter',system-ui,sans-serif; }

.nab-sidebar {
    width:260px; min-width:260px;
    background:linear-gradient(160deg,#0D5C9B 0%,#0a4a7c 100%);
    display:flex; flex-direction:column;
    position:sticky; top:0; height:100vh; overflow-y:auto; z-index:100;
}
.nab-sidebar-logo { display:flex; align-items:center; gap:10px; padding:24px 20px 20px; border-bottom:1px solid rgba(255,255,255,.1); }
.nab-logo-icon { width:36px; height:36px; border-radius:10px; background:#F5A623; color:#fff; font-weight:800; font-size:1.1rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.nab-logo-text { color:#fff; font-weight:700; font-size:.95rem; line-height:1.2; }
.nab-logo-text span  { color:#F5A623; }
.nab-logo-text small { display:block; font-size:.7rem; font-weight:400; opacity:.6; }
.nab-sidebar-nav { flex:1; padding:16px 12px; display:flex; flex-direction:column; gap:2px; }
.nab-nav-label { font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:rgba(255,255,255,.4); padding:12px 8px 4px; }
.nab-nav-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; color:rgba(255,255,255,.75); font-size:.88rem; font-weight:500; text-decoration:none; transition:background .2s,color .2s; }
.nab-nav-item:hover  { background:rgba(255,255,255,.1); color:#fff; }
.nab-nav-item.nab-active { background:rgba(255,255,255,.15); color:#fff; font-weight:600; }
.nab-sidebar-bottom { padding:16px 12px; border-top:1px solid rgba(255,255,255,.1); }
.nab-member-chip { display:flex; align-items:center; gap:10px; background:rgba(255,255,255,.08); border-radius:12px; padding:10px 12px; }
.nab-avatar { width:34px; height:34px; border-radius:50%; background:#F5A623; color:#fff; font-weight:700; font-size:.85rem; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.nab-member-info { flex:1; min-width:0; }
.nab-member-name { color:#fff; font-size:.82rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.nab-member-role { color:rgba(255,255,255,.5); font-size:.72rem; }
.nab-logout-btn  { color:rgba(255,255,255,.5); font-size:1.1rem; text-decoration:none; transition:color .2s; }
.nab-logout-btn:hover { color:#F5A623; }

.nab-hamburger { display:none; position:fixed; top:14px; left:14px; z-index:200; background:#0D5C9B; color:#fff; border:none; width:40px; height:40px; border-radius:10px; font-size:1.2rem; cursor:pointer; align-items:center; justify-content:center; }
.nab-sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:99; }
@media (max-width:768px) {
    .nab-hamburger { display:flex; }
    .nab-sidebar { position:fixed; top:0; left:-270px; height:100vh; transition:left .3s ease; z-index:150; }
    .nab-sidebar.nab-open { left:0; }
    .nab-sidebar-overlay.nab-open { display:block; }
    .nab-topbar { padding:0 16px 0 60px !important; }
    .nab-content { padding:16px !important; }
}

.nab-main { flex:1; min-width:0; display:flex; flex-direction:column; }
.nab-topbar { display:flex; align-items:center; justify-content:space-between; padding:0 28px; height:60px; background:#fff; border-bottom:1px solid #e8edf5; position:sticky; top:0; z-index:50; }
.nab-topbar-title { font-weight:700; font-size:1rem; color:#1a202c; }
.nab-topbar-right { display:flex; align-items:center; gap:12px; }
.nab-status-badge { font-size:.75rem; font-weight:600; padding:4px 12px; border-radius:50px; }
.nab-status-active    { background:#d1fae5; color:#065f46; }
.nab-status-suspended { background:#fee2e2; color:#991b1b; }
.nab-content { padding:24px 28px 60px; }

.sim-hero { background:linear-gradient(135deg,#1a3a8f 0%,#2563eb 55%,#1d4ed8 100%); border-radius:16px; padding:28px 32px; color:#fff; margin-bottom:24px; display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:20px; position:relative; overflow:hidden; }
.sim-hero::before { content:''; position:absolute; right:-60px; top:-80px; width:300px; height:300px; background:rgba(255,255,255,.04); border-radius:50%; pointer-events:none; }
.sim-hero-greeting { font-size:.82rem; color:rgba(255,255,255,.7); margin-bottom:3px; }
.sim-hero-name     { font-size:1.5rem; font-weight:700; margin-bottom:8px; }
.sim-hero-desc     { font-size:.85rem; color:rgba(255,255,255,.75); max-width:520px; line-height:1.6; }
.sim-hero-pills    { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
.sim-hero-pill { background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.2); border-radius:20px; padding:4px 12px; font-size:.75rem; font-weight:500; }
.sim-hero-actions { display:flex; gap:10px; flex-shrink:0; position:relative; z-index:1; }
.btn-outline-white { border:1.5px solid rgba(255,255,255,.5); color:#fff; background:transparent; border-radius:8px; padding:8px 18px; font-size:.83rem; font-weight:500; cursor:pointer; text-decoration:none; transition:background .15s; }
.btn-outline-white:hover { background:rgba(255,255,255,.1); color:#fff; }
.btn-orange { background:#F5A623; color:#fff; border:none; border-radius:8px; padding:8px 18px; font-size:.83rem; font-weight:600; cursor:pointer; text-decoration:none; transition:background .15s; }
.btn-orange:hover { background:#e09415; }

.sim-grid { display:grid; grid-template-columns:1fr 540px; gap:20px; align-items:start; }
@media (max-width:960px) { .sim-grid { grid-template-columns:1fr; } }

.sim-card { background:#fff; border-radius:14px; border:1px solid #e8edf5; overflow:hidden; }
.sim-card-head { padding:20px 24px 16px; border-bottom:1px solid #f0f4fa; }
.sim-card-title    { font-size:.95rem; font-weight:700; color:#1a202c; margin-bottom:3px; }
.sim-card-subtitle { font-size:.78rem; color:#718096; }
.sim-card-body     { padding:20px 24px 24px; }

.sim-form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px; }
@media (max-width:600px) { .sim-form-row { grid-template-columns:1fr; } }

.sim-field label { display:block; font-size:.72rem; font-weight:600; color:#718096; text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
.sim-field input,
.sim-field select { width:100%; border:1.5px solid #e2e8f0; border-radius:8px; padding:10px 13px; font-size:.88rem; font-family:inherit; color:#1a202c; background:#fff; outline:none; transition:border-color .15s,box-shadow .15s; }
.sim-field input:focus, .sim-field select:focus { border-color:#0D5C9B; box-shadow:0 0 0 3px rgba(13,92,155,.1); }
.sim-field input::placeholder { color:#c0ccd8; }
.sim-field-error { border-color:#ef4444 !important; box-shadow:0 0 0 3px rgba(239,68,68,.1) !important; }
.sim-error-msg   { font-size:.72rem; color:#b91c1c; margin-top:4px; display:none; }

.sim-run-btn { width:100%; background:#0D5C9B; color:#fff; border:none; border-radius:10px; padding:13px 20px; font-size:.92rem; font-weight:700; font-family:inherit; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:filter .15s,transform .1s; }
.sim-run-btn:hover  { filter:brightness(1.1); }
.sim-run-btn:active { transform:scale(.99); }

.sim-result-panel { display:none; margin-top:24px; background:#f8fafc; border-radius:12px; border:1.5px solid #e2e8f0; padding:24px; }
@keyframes simFadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

.sim-scenario-tag { display:inline-flex; align-items:center; gap:6px; background:#eff6ff; color:#0D5C9B; border-radius:20px; padding:5px 14px; font-size:.78rem; font-weight:700; margin-bottom:20px; }

.sim-dual-gauge { display:flex; gap:24px; justify-content:center; align-items:flex-end; margin-bottom:20px; flex-wrap:wrap; }
.sim-gauge-wrap { text-align:center; }
.sim-gauge-wrap svg { overflow:visible; display:block; margin:0 auto; }
.sim-gauge-num { font-size:1.6rem; font-weight:800; margin-top:6px; }
.sim-gauge-lbl { font-size:.72rem; color:#718096; font-weight:600; margin-top:2px; }
.sim-gauge-arrow { font-size:2rem; color:#94a3b8; padding-bottom:28px; }

.sim-ba-grid { display:grid; grid-template-columns:1fr auto 1fr; gap:12px; align-items:center; margin-bottom:20px; }
@media (max-width:520px) { .sim-ba-grid { grid-template-columns:1fr; text-align:center; } .sim-ba-arrow-cell { transform:rotate(90deg); } }
.sim-ba-box { background:#fff; border-radius:10px; padding:16px; border:1.5px solid #e2e8f0; text-align:center; }
.sim-ba-label  { font-size:.68rem; font-weight:700; color:#718096; text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px; }
.sim-ba-score  { font-size:2.2rem; font-weight:900; line-height:1; }
.sim-ba-rating { font-size:.75rem; color:#718096; margin-top:4px; font-weight:600; }

.sim-impact-pill { display:inline-block; padding:3px 14px; border-radius:20px; font-size:.78rem; font-weight:800; margin-top:8px; }
.sim-impact-pos { background:#dcfce7; color:#15803d; }
.sim-impact-neg { background:#fee2e2; color:#b91c1c; }

.sim-tip-box  { background:#eff6ff; border:1.5px solid #bfdbfe; border-radius:10px; padding:14px 18px; display:flex; gap:12px; align-items:flex-start; margin-top:16px; }
.sim-tip-icon  { font-size:1.3rem; flex-shrink:0; }
.sim-tip-title { font-size:.72rem; font-weight:700; color:#0D5C9B; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px; }
.sim-tip-text  { font-size:.8rem; color:#1e40af; line-height:1.55; }

.sim-disclaimer { background:#fefce8; border:1px solid #fde68a; border-radius:8px; padding:10px 14px; font-size:.73rem; color:#92400e; margin-top:14px; line-height:1.5; display:flex; gap:8px; }

.sim-save-btn { width:100%; background:#065f46; color:#fff; border:none; border-radius:10px; padding:12px 20px; font-size:.88rem; font-weight:700; font-family:inherit; cursor:pointer; margin-top:16px; display:flex; align-items:center; justify-content:center; gap:8px; transition:filter .15s,transform .1s; }
.sim-save-btn:hover    { filter:brightness(1.1); }
.sim-save-btn:active   { transform:scale(.99); }
.sim-save-btn:disabled { opacity:.6; cursor:not-allowed; }

.sim-right { position:sticky; top:76px; }

.sim-history-empty { text-align:center; padding:28px 16px; color:#718096; font-size:.83rem; }
#simHistoryCardBody { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.sim-history-table { width:100%; border-collapse:collapse; font-size:.78rem; }
@media (max-width:480px) { .sim-history-table { min-width:480px; } }
.sim-history-table th { text-align:left; font-size:.68rem; font-weight:700; color:#718096; text-transform:uppercase; letter-spacing:.06em; padding:0 10px 10px; border-bottom:1.5px solid #e8edf5; }
.sim-history-table td { padding:10px; border-bottom:1px solid #f1f5f9; color:#1a202c; }
.sim-history-table tr:last-child td { border-bottom:none; }
.sim-history-table tr:hover td { background:#f8fafc; }
.hist-pos { color:#15803d; font-weight:700; }
.hist-neg { color:#b91c1c; font-weight:700; }
.sim-hist-new td { animation:simFadeIn .4s ease; }

.sim-tips { background:linear-gradient(135deg,#0D5C9B,#1d4ed8); border-radius:14px; padding:18px 20px; color:#fff; margin-top:16px; }
.sim-tips-title { font-size:.83rem; font-weight:700; margin-bottom:12px; opacity:.9; }
.sim-tip-row { display:flex; gap:10px; margin-bottom:10px; font-size:.78rem; color:rgba(255,255,255,.85); line-height:1.5; }
.sim-tip-num { width:18px; height:18px; background:rgba(255,255,255,.15); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:700; flex-shrink:0; }

.sim-toast { position:fixed; bottom:24px; right:24px; background:#1e293b; color:#fff; padding:11px 20px; border-radius:10px; font-size:.83rem; font-weight:600; opacity:0; transform:translateY(8px); transition:all .3s; z-index:9999; pointer-events:none; }
.sim-toast.show { opacity:1; transform:translateY(0); }

.sim-suspension-screen { min-height:100vh; display:grid; place-items:center; background:#F0F4FA; padding:24px; }
.sim-suspension-box { background:#fff; border-radius:14px; border:1px solid #e8edf5; padding:48px 40px; max-width:480px; text-align:center; }
.sim-susp-icon { font-size:3.5rem; margin-bottom:16px; }
.sim-suspension-box h2 { color:#1a202c; margin:0 0 12px; font-size:1.3rem; }
.sim-suspension-box p  { color:#718096; line-height:1.6; margin:0 0 24px; }
</style>
</head>
<body <?php body_class( 'nab-portal-body' ); ?>>
<?php wp_body_open(); ?>

<?php if ( $is_suspended ) : ?>
<div class="sim-suspension-screen">
  <div class="sim-suspension-box">
    <div class="sim-susp-icon">🔒</div>
    <h2>Account Temporarily Suspended</h2>
    <p><?php echo wp_kses_post( $suspension_msg ?: 'Please settle your payment to regain access.' ); ?></p>
    <?php $nab_se=nab_get_global_setting('nab_support_email','admin@nabsolutions.ca'); ?>
    <a href="mailto:<?php echo esc_attr($nab_se); ?>" class="btn-orange" style="display:inline-block;border-radius:8px;padding:10px 24px;text-decoration:none;">Contact Support</a>
  </div>
</div>
<?php else : ?>

<div class="nab-portal-wrap">

  <!-- SIDEBAR — now rendered via shared nab_render_sidebar() so all
       templates stay in sync automatically (was hardcoded before) -->
  <?php nab_render_sidebar( 'sim' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title">📈 Credit Score Impact Simulator</div>
      <div class="nab-topbar-right">
        <span class="nab-status-badge nab-status-<?php echo esc_attr( strtolower( $member_status ) ); ?>">
          ● <?php echo esc_html( $member_status ); ?>
        </span>
      </div>
    </header>

    <div class="nab-content">

      <div class="sim-hero">
        <div>
          <div class="sim-hero-greeting">👋 Good <?php echo esc_html( $greeting ); ?>,</div>
          <div class="sim-hero-name"><?php echo esc_html( $first_name ); ?></div>
          <div class="sim-hero-desc">Enter your current score, choose a financial action, and instantly see the estimated impact on your credit score.</div>
          <div class="sim-hero-pills">
            <span class="sim-hero-pill">✅ <?php echo esc_html( $member_status ); ?> Member</span>
            <?php if ( $member_since ) : ?><span class="sim-hero-pill">📅 Since <?php echo esc_html( $member_since ); ?></span><?php endif; ?>
            <span class="sim-hero-pill">🔒 <?php echo esc_html( $payment_status ); ?></span>
            <span class="sim-hero-pill">ID: <?php echo esc_html( $member_id ); ?></span>
          </div>
        </div>
        <div class="sim-hero-actions">
          <a href="<?php echo $dashboard_url; ?>" class="btn-outline-white">Dashboard</a>
          <a href="<?php echo $url_booking; ?>"   class="btn-orange">Book Specialist</a>
        </div>
      </div>

      <div class="sim-grid">

        <!-- LEFT -->
        <div>
          <div class="sim-card">
            <div class="sim-card-head">
              <div class="sim-card-title"><p style="font-size:12px;color:#64748b;margin-bottom:10px;">For personalized guidance, consider speaking with our qualified credit expert.</p>
          ⚡ Run a Simulation</div>
              <div class="sim-card-subtitle">Enter your score and pick a scenario, results are instant</div>
            </div>
            <div class="sim-card-body">

              <div class="sim-form-row">
                <div class="sim-field">
                  <label for="simScore">Your Current Credit Score</label>
                  <input type="number" id="simScore" placeholder="e.g. 650" min="300" max="900"
                         value="<?php echo esc_attr( get_user_meta( $user_id, 'nab_credit_score', true ) ); ?>">
                  <div class="sim-error-msg" id="simScoreErr">Please enter a score between 300 and 900.</div>
                </div>
                <div class="sim-field">
                  <label for="simScenario">What are you planning to do?</label>
                  <select id="simScenario">
                    <option value="">— Select a scenario —</option>
                    <option value="payoff">💳 Pay off a credit card</option>
                    <option value="open_card">🆕 Open a new credit card</option>
                    <option value="miss_payment">⚠️ Miss a payment</option>
                    <option value="increase_limit">⬆️ Increase credit limit</option>
                    <option value="close_card">✂️ Close a credit card</option>
                    <option value="collections">🚨 Account goes to collections</option>
                    <option value="bankruptcy">💥 File for bankruptcy</option>
                    <option value="consumer_proposal">📋 Consumer proposal filed</option>
                    <option value="authorized_user">👥 Added as authorized user</option>
                    <option value="credit_builder">🏗️ Open a credit-builder loan</option>
                  </select>
                  <div class="sim-error-msg" id="simScenarioErr">Please select a scenario.</div>
                </div>
              </div>

              <button class="sim-run-btn" onclick="simRun()">⚡ Run Simulation</button>

              <div class="sim-result-panel" id="simResultPanel">
                <div class="sim-scenario-tag" id="simScenarioTag">📋 Scenario</div>

                <div class="sim-dual-gauge">
                  <div class="sim-gauge-wrap">
                    <svg viewBox="0 0 120 70" width="120" height="70">
                      <defs><linearGradient id="gGradB" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#ef4444"/><stop offset="50%" stop-color="#f97316"/><stop offset="100%" stop-color="#22c55e"/>
                      </linearGradient></defs>
                      <path d="M10 62 A50 50 0 0 1 110 62" fill="none" stroke="#e2e8f0" stroke-width="10" stroke-linecap="round"/>
                      <path id="gaugeArcB" d="M10 62 A50 50 0 0 1 110 62" fill="none" stroke="url(#gGradB)" stroke-width="10" stroke-linecap="round" stroke-dasharray="157" stroke-dashoffset="157" style="transition:stroke-dashoffset 1s ease"/>
                    </svg>
                    <div class="sim-gauge-num" id="gaugeBeforeNum" style="color:#1a202c">—</div>
                    <div class="sim-gauge-lbl">Current Score</div>
                  </div>
                  <div class="sim-gauge-arrow">→</div>
                  <div class="sim-gauge-wrap">
                    <svg viewBox="0 0 120 70" width="120" height="70">
                      <defs><linearGradient id="gGradA" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#ef4444"/><stop offset="50%" stop-color="#f97316"/><stop offset="100%" stop-color="#22c55e"/>
                      </linearGradient></defs>
                      <path d="M10 62 A50 50 0 0 1 110 62" fill="none" stroke="#e2e8f0" stroke-width="10" stroke-linecap="round"/>
                      <path id="gaugeArcA" d="M10 62 A50 50 0 0 1 110 62" fill="none" stroke="url(#gGradA)" stroke-width="10" stroke-linecap="round" stroke-dasharray="157" stroke-dashoffset="157" style="transition:stroke-dashoffset 1s ease"/>
                    </svg>
                    <div class="sim-gauge-num" id="gaugeAfterNum" style="color:#059669">—</div>
                    <div class="sim-gauge-lbl">Estimated After</div>
                  </div>
                </div>

                <div style="text-align:center;margin-bottom:20px">
                  <span class="sim-impact-pill" id="simImpactPill1"></span>
                </div>

                <div class="sim-ba-grid">
                  <div class="sim-ba-box">
                    <div class="sim-ba-label">Current Score</div>
                    <div class="sim-ba-score" id="baCurrentScore" style="color:#1a202c">—</div>
                    <div class="sim-ba-rating" id="baCurrentRating"></div>
                  </div>
                  <div class="sim-ba-arrow-cell" style="text-align:center;font-size:1.6rem;" id="baArrow">→</div>
                  <div class="sim-ba-box">
                    <div class="sim-ba-label">Estimated After</div>
                    <div class="sim-ba-score" id="baResultScore">—</div>
                    <div class="sim-ba-rating" id="baResultRating"></div>
                    <div><span class="sim-impact-pill" id="simImpactPill2"></span></div>
                  </div>
                </div>

                <div class="sim-tip-box">
                  <div class="sim-tip-icon" id="simTipIcon">💡</div>
                  <div>
                    <div class="sim-tip-title">Credit Expert Tip</div>
                    <div class="sim-tip-text" id="simTipText"></div>
                  </div>
                </div>

                <div class="sim-disclaimer">⚠️ <?php echo esc_html( $sim_disclaimer ); ?></div>
                <button class="sim-save-btn" id="simSaveBtn">💾 Save This Simulation</button>
              </div>

            </div>
          </div>
        </div>

        <!-- RIGHT -->
        <div class="sim-right">
          <div class="sim-card">
            <div class="sim-card-head">
              <div class="sim-card-title">🕐 Simulation History</div>
              <div class="sim-card-subtitle">Your last 10 saved simulations</div>
            </div>
            <div class="sim-card-body" id="simHistoryCardBody" style="padding-top:12px">
              <?php if ( empty( $sim_history ) ) : ?>
                <div class="sim-history-empty" id="simHistoryEmpty">
                  📊 No simulations saved yet.<br>Run your first simulation to see results here.
                </div>
              <?php else : ?>
                <table class="sim-history-table">
                  <thead><tr><th>Date</th><th>Scenario</th><th>Before</th><th>After</th><th>Impact</th></tr></thead>
                  <tbody id="simHistoryBody">
                    <?php foreach ( $sim_history as $h ) :
                      $imp  = intval( $h['impact'] );
                      $sign = $imp >= 0 ? '+' : '';
                      $cls  = $imp >= 0 ? 'hist-pos' : 'hist-neg';
                      // Strip emoji from scenario for display
                      $display = preg_replace('/^\X{1,2}\s*/u', '', $h['scenario'] );
                    ?>
                    <tr>
                      <td><?php echo esc_html( $h['date'] ); ?></td>
                      <td style="max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                          title="<?php echo esc_attr( $h['scenario'] ); ?>">
                        <?php echo esc_html( $display ); ?>
                      </td>
                      <td><?php echo esc_html( $h['score'] ); ?></td>
                      <td><?php echo esc_html( $h['result'] ); ?></td>
                      <td class="<?php echo $cls; ?>"><?php echo $sign . esc_html( $imp ); ?> pts</td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>

          <div class="sim-tips">
            <div class="sim-tips-title">💡 How Credit Score Factors Work</div>
            <div class="sim-tip-row"><span class="sim-tip-num">1</span>Payment history (35%) — biggest factor. Never miss a payment.</div>
            <div class="sim-tip-row"><span class="sim-tip-num">2</span>Credit utilization (30%) — keep below 30%, ideally under 10%.</div>
            <div class="sim-tip-row"><span class="sim-tip-num">3</span>Credit history length (15%) — older accounts help your score.</div>
            <div class="sim-tip-row"><span class="sim-tip-num">4</span>New credit (10%) — hard inquiries cause a short-term dip.</div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<?php endif; ?>

<div class="sim-toast" id="simToast"></div>

<script>
var nabSimAjax = {
  url:   '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>',
  nonce: '<?php echo esc_js( wp_create_nonce( "nab_sim_nonce" ) ); ?>'
};
</script>

<script>
(function () {
  'use strict';

  /* ── Sidebar ── */
  var ham = document.getElementById('nabHamburger');
  var sb  = document.getElementById('nabSidebar');
  var ovl = document.getElementById('nabOverlay');
  if (ham) {
    ham.addEventListener('click', function () { sb.classList.toggle('nab-open'); ovl.classList.toggle('nab-open'); });
    ovl.addEventListener('click', function () { sb.classList.remove('nab-open'); ovl.classList.remove('nab-open'); });
  }

  /* ── Scenario data ── */
  var SC = {
    payoff:            { label:'Pay off a credit card',       min:20,  max:45,  pos:true,  icon:'💳', tip:'Paying off a card reduces your utilization ratio — one of the biggest factors (30%) in your score. If utilization drops below 30% you could see gains quickly. Keep the card open to preserve your available credit limit.' },
    open_card:         { label:'Open a new credit card',      min:2,   max:8,   pos:false, icon:'🆕', tip:'Opening a new card triggers a hard inquiry (avg -5 pts) and temporarily lowers your average account age. However it increases total available credit which can improve utilization long-term. The dip usually recovers within 3-6 months.' },
    miss_payment:      { label:'Miss a payment',              min:60,  max:110, pos:false, icon:'⚠️', tip:'Payment history is the #1 credit factor (35%). A single missed payment can stay on your Canadian credit report for 6 years. Act immediately — pay as soon as possible and ask the lender to note "late" rather than "missed".' },
    increase_limit:    { label:'Increase credit limit',       min:5,   max:20,  pos:true,  icon:'⬆️', tip:'Increasing your limit lowers utilization without changing your balance. If you carry $1,500 on a $3,000 limit (50%) and increase to $6,000, utilization drops to 25% — a meaningful improvement with no extra spending.' },
    close_card:        { label:'Close a credit card',         min:15,  max:35,  pos:false, icon:'✂️', tip:'Closing a card removes its available credit, raising your utilization ratio, and can shorten your credit history. Avoid closing your oldest or highest-limit card. Pay down other balances first if you must close one.' },
    collections:       { label:'Account goes to collections', min:80,  max:130, pos:false, icon:'🚨', tip:'A collection account stays on your Canadian credit report for 6 years from first delinquency. Act immediately — negotiate a "pay for delete" with the collector, or settle the debt and dispute the remaining mark.' },
    bankruptcy:        { label:'File for bankruptcy',         min:150, max:200, pos:false, icon:'💥', tip:'Bankruptcy stays on Equifax for 6 years (14 for a 2nd) and TransUnion for 6-7 years. Many members rebuild to 650+ within 2 years using secured cards and credit-builder loans while making every payment on time.' },
    consumer_proposal: { label:'Consumer proposal filed',     min:100, max:150, pos:false, icon:'📋', tip:'A consumer proposal is reported for 3 years after completion (or 6 years from filing, whichever comes first). Start rebuilding immediately with secured credit cards and consistent on-time payments.' },
    authorized_user:   { label:'Added as authorized user',    min:10,  max:40,  pos:true,  icon:'👥', tip:'Being added to an account with a long history, low utilization, and no missed payments can meaningfully boost your score in Canada. Make sure the primary cardholder has excellent credit habits before agreeing.' },
    credit_builder:    { label:'Open a credit-builder loan',  min:15,  max:35,  pos:true,  icon:'🏗️', tip:'Credit-builder loans (KOHO, Refresh Financial, credit unions) report monthly payments to the bureaus without requiring good credit upfront. Over 12 months of on-time payments they can significantly improve your score.' },
  };

  function el(id)  { return document.getElementById(id); }

  function rating(s) {
    if (s >= 800) return { label:'Excellent', color:'#16a34a' };
    if (s >= 740) return { label:'Very Good', color:'#22c55e' };
    if (s >= 670) return { label:'Good',      color:'#3b82f6' };
    if (s >= 580) return { label:'Fair',      color:'#f97316' };
    return               { label:'Poor',      color:'#ef4444' };
  }

  function animGauge(id, score) {
    var arc = el(id);
    if (!arc) return;
    var pct = Math.max(0, Math.min(1, (score - 300) / 600));
    arc.style.strokeDashoffset = 157 - (pct * 157);
  }

  function toast(msg, err) {
    var t = el('simToast');
    t.textContent      = msg;
    t.style.background = err ? '#b91c1c' : '#1e293b';
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(function () { t.classList.remove('show'); }, 3500);
  }

  /* ── Safely set text content — handles emoji fine, no encoding issues ── */
  function setText(id, text) {
    var node = el(id);
    if (node) node.textContent = text;
  }

  /* ── Add row to history table immediately after save ── */
  function addHistoryRow(entry) {
    var cardBody = el('simHistoryCardBody');
    if (!cardBody) return;

    var tbody = el('simHistoryBody');

    /* First-ever save: build the table from scratch */
    if (!tbody) {
      cardBody.innerHTML =
        '<table class="sim-history-table">' +
          '<thead><tr>' +
            '<th>Date</th><th>Scenario</th><th>Before</th><th>After</th><th>Impact</th>' +
          '</tr></thead>' +
          '<tbody id="simHistoryBody"></tbody>' +
        '</table>';
      tbody = el('simHistoryBody');
    }

    /* Hide the "no simulations yet" placeholder */
    var empty = el('simHistoryEmpty');
    if (empty) empty.style.display = 'none';

    /* Build row cells with textContent (safe — no HTML injection) */
    var sign = entry.impact >= 0 ? '+' : '';
    var cls  = entry.impact >= 0 ? 'hist-pos' : 'hist-neg';

    var tr    = document.createElement('tr');
    tr.className = 'sim-hist-new';

    var tdDate     = document.createElement('td');
    var tdScenario = document.createElement('td');
    var tdBefore   = document.createElement('td');
    var tdAfter    = document.createElement('td');
    var tdImpact   = document.createElement('td');

    tdDate.textContent     = entry.date;
    tdScenario.textContent = entry.label;   /* plain text label, no emoji prefix */
    tdScenario.style.cssText = 'max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
    tdScenario.title       = entry.label;
    tdBefore.textContent   = entry.score;
    tdAfter.textContent    = entry.result;
    tdImpact.textContent   = sign + entry.impact + ' pts';
    tdImpact.className     = cls;

    tr.appendChild(tdDate);
    tr.appendChild(tdScenario);
    tr.appendChild(tdBefore);
    tr.appendChild(tdAfter);
    tr.appendChild(tdImpact);

    /* Newest row at top */
    tbody.insertBefore(tr, tbody.firstChild);

    /* Keep max 10 rows */
    while (tbody.rows.length > 10) tbody.deleteRow(tbody.rows.length - 1);
  }

  var lastResult = null;

  /* ── Run simulation ── */
  window.simRun = function () {
    var scoreEl = el('simScore');
    var scEl    = el('simScenario');
    var score   = parseInt(scoreEl.value, 10);
    var key     = scEl.value;
    var valid   = true;

    if (!score || score < 300 || score > 900) {
      scoreEl.classList.add('sim-field-error');
      el('simScoreErr').style.display = 'block';
      valid = false;
    } else {
      scoreEl.classList.remove('sim-field-error');
      el('simScoreErr').style.display = 'none';
    }
    if (!key) {
      scEl.classList.add('sim-field-error');
      el('simScenarioErr').style.display = 'block';
      valid = false;
    } else {
      scEl.classList.remove('sim-field-error');
      el('simScenarioErr').style.display = 'none';
    }
    if (!valid) return;

    var sc     = SC[key];
    var raw    = sc.min + Math.round(Math.random() * (sc.max - sc.min));
    var impact = sc.pos ? raw : -raw;
    var result = Math.min(900, Math.max(300, score + impact));

    lastResult = { score:score, result:result, label:sc.label, impact:impact };

    setText('simScenarioTag', sc.icon + ' ' + sc.label);

    setTimeout(function () { animGauge('gaugeArcB', score);  }, 80);
    setTimeout(function () { animGauge('gaugeArcA', result); }, 280);

    var bR = rating(score);
    var aR = rating(result);
    var ic = sc.pos ? '#059669' : '#b91c1c';

    var gBefore = el('gaugeBeforeNum');
    var gAfter  = el('gaugeAfterNum');
    if (gBefore) { gBefore.textContent = score;  gBefore.style.color = bR.color; }
    if (gAfter)  { gAfter.textContent  = result; gAfter.style.color  = ic; }

    setText('baCurrentScore',  score);
    setText('baCurrentRating', bR.label);
    setText('baResultScore',   result);
    var brs = el('baResultScore'); if (brs) brs.style.color = ic;
    setText('baResultRating',  aR.label);
    var ba  = el('baArrow'); if (ba) ba.style.color = ic;

    var sign = impact >= 0 ? '+' : '';
    var pc   = impact >= 0 ? 'sim-impact-pos' : 'sim-impact-neg';
    var txt  = sign + impact + ' pts estimated';
    ['simImpactPill1','simImpactPill2'].forEach(function (id) {
      var p = el(id);
      if (p) { p.textContent = txt; p.className = 'sim-impact-pill ' + pc; }
    });

    setText('simTipIcon', sc.icon);
    setText('simTipText', sc.tip);

    var panel = el('simResultPanel');
    panel.style.display = 'block';
    setTimeout(function () { panel.scrollIntoView({ behavior:'smooth', block:'nearest' }); }, 50);
  };

  /* ── Save simulation ── */
  var saveBtn = el('simSaveBtn');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      if (!lastResult) return;
      var btn = this;
      btn.disabled    = true;
      btn.textContent = 'Saving...';

      /* Build date string client-side for instant display */
      var now = new Date();
      var pad = function (n) { return String(n).padStart(2, '0'); };
      var dateStr = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) +
                    ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());

      var fd = new FormData();
      fd.append('action',   'nab_save_simulation');
      fd.append('nonce',    nabSimAjax.nonce);
      fd.append('score',    lastResult.score);
      fd.append('result',   lastResult.result);
      fd.append('scenario', lastResult.label);   /* plain text, no emoji */
      fd.append('impact',   lastResult.impact);

      fetch(nabSimAjax.url, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.success) {
            toast('Simulation saved to your profile!');
            btn.textContent = 'Saved!';
            /* Inject row immediately — no page reload needed */
            addHistoryRow({
              date:   dateStr,
              label:  lastResult.label,
              score:  lastResult.score,
              result: lastResult.result,
              impact: lastResult.impact,
            });
            setTimeout(function () {
              btn.disabled = false;
              btn.textContent = 'Save This Simulation';
            }, 3000);
          } else {
            toast((d.data && d.data.message) || 'Could not save.', true);
            btn.disabled    = false;
            btn.textContent = 'Save This Simulation';
          }
        })
        .catch(function () {
          toast('Network error. Please try again.', true);
          btn.disabled    = false;
          btn.textContent = 'Save This Simulation';
        });
    });
  }

})();
</script>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>