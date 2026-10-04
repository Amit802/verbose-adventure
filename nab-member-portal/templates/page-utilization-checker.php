<?php
/**
 * Template Name: Utilization Checker
 * Description: NAB Solutions — Credit Utilization Checker with ACF + user data saving
 *
 * Place in: /wp-content/themes/YOUR-THEME/page-utilization-checker.php
 *
 * Requires:
 *  - Advanced Custom Fields FREE
 *  - inc/functions.php included in functions.php
 */

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

// ── Current user ──────────────────────────────────────────────────────────────
$current_user  = wp_get_current_user();
$user_id       = get_current_user_id();
$first_name    = $current_user->first_name ?: $current_user->display_name;
$last_name     = $current_user->last_name  ?: '';
$initials      = strtoupper( substr( $first_name, 0, 1 ) . substr( $last_name, 0, 1 ) ) ?: 'M';
$member_id     = get_user_meta( $user_id, 'nab_member_id', true ) ?: 'NAB-' . str_pad( $user_id, 4, '0', STR_PAD_LEFT );
$member_status = get_user_meta( $user_id, 'nab_plan',      true ) ?: 'Active';

// ── Read sidebar URLs from Dashboard page ACF fields (same as Dispute Center) ─
$dashboard_page = get_page_by_path( 'dashboard' );
$dash_id        = $dashboard_page ? $dashboard_page->ID : 0;

function uc_get_dash_url( $field, $dash_id ) {
    if ( ! function_exists( 'get_field' ) || ! $dash_id ) return '#';
    $val = get_field( $field, $dash_id );
    if ( is_array( $val ) && isset( $val['url'] ) ) return esc_url( $val['url'] );
    if ( is_string( $val ) && ! empty( $val ) )     return esc_url( $val );
    return '#';
}

$dashboard_url = $dash_id ? esc_url( get_permalink( $dash_id ) ) : esc_url( home_url( '/dashboard/' ) );
$url_report    = uc_get_dash_url( 'nab_link_report',      $dash_id );
$url_util      = esc_url( get_permalink() ); // this page = active
$url_sim       = uc_get_dash_url( 'nab_link_simulator',   $dash_id );
$url_dispute   = uc_get_dash_url( 'nab_link_dispute',     $dash_id );
$url_booking   = uc_get_dash_url( 'nab_link_booking',     $dash_id );
$url_chatbot   = uc_get_dash_url( 'nab_link_chatbot',     $dash_id );
$url_learning = nab_resolve_url( get_field( 'nab_link_learning', nab_get_dash_page_id() ) );
$url_blog      = uc_get_dash_url( 'nab_link_blog',        $dash_id );
$url_diy       = uc_get_dash_url( 'nab_link_diy',         $dash_id );
$url_learning  = uc_get_dash_url( 'nab_link_learning', $dash_id );
$url_support   = uc_get_dash_url( 'nab_link_support',  $dash_id );
$url_loan      = uc_get_dash_url( 'nab_link_loan',        $dash_id );
$url_card      = uc_get_dash_url( 'nab_link_card_match',        $dash_id );

// ── Load saved card data ───────────────────────────────────────────────────────
$saved_cards = array();
for ( $i = 1; $i <= 5; $i++ ) {
    $saved_cards[ $i ] = array(
        'name'    => get_user_meta( $user_id, "nab_card_{$i}_name",    true ),
        'balance' => get_user_meta( $user_id, "nab_card_{$i}_balance", true ),
        'limit'   => get_user_meta( $user_id, "nab_card_{$i}_limit",   true ),
    );
}
$last_saved = get_user_meta( $user_id, 'nab_utilization_last_saved', true );
$last_util  = get_user_meta( $user_id, 'nab_last_utilization_pct',   true );

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Utilization Checker — <?php bloginfo( 'name' ); ?></title>
<?php wp_head(); ?>
<style>
/* ── Hide theme chrome (Hello Elementor / other themes) ─────────── */
.site-header,.site-footer,.elementor-location-header,
.elementor-location-footer,#masthead,#colophon { display:none !important; }
body { margin:0 !important; padding:0 !important; background:#F0F4FA !important; }
.elementor-page .elementor-section-wrap,
.e-page-settings .elementor-section-wrap { padding:0 !important; }

/* ══ RESET ══════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing:border-box; }

.nab-portal-wrap {
    display:flex; min-height:100vh;
    font-family:'Inter',system-ui,sans-serif;
}

/* ══════════════════════════════════════════════════════════════════
   SIDEBAR — copied exactly from Dispute Center
══════════════════════════════════════════════════════════════════ */
.nab-sidebar {
    width:260px; min-width:260px;
    background:linear-gradient(160deg,#0D5C9B 0%,#0a4a7c 100%);
    display:flex; flex-direction:column;
    position:sticky; top:0; height:100vh; overflow-y:auto; z-index:100;
}
.nab-sidebar-logo {
    display:flex; align-items:center; gap:10px;
    padding:24px 20px 20px;
    border-bottom:1px solid rgba(255,255,255,.1);
}
.nab-logo-icon {
    width:36px; height:36px; border-radius:10px;
    background:#F5A623; color:#fff;
    font-weight:800; font-size:1.1rem;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.nab-logo-text { color:#fff; font-weight:700; font-size:.95rem; line-height:1.2; }
.nab-logo-text span  { color:#F5A623; }
.nab-logo-text small { display:block; font-size:.7rem; font-weight:400; opacity:.6; }

.nab-sidebar-nav {
    flex:1; padding:16px 12px;
    display:flex; flex-direction:column; gap:2px;
}
.nab-nav-label {
    font-size:.68rem; font-weight:700; letter-spacing:.1em;
    text-transform:uppercase; color:rgba(255,255,255,.4);
    padding:12px 8px 4px;
}
.nab-nav-item {
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; border-radius:10px;
    color:rgba(255,255,255,.75); font-size:.88rem; font-weight:500;
    text-decoration:none; transition:background .2s,color .2s;
}
.nab-nav-item:hover  { background:rgba(255,255,255,.1); color:#fff; }
.nab-nav-item.nab-active { background:rgba(255,255,255,.15); color:#fff; font-weight:600; }

.nab-sidebar-bottom {
    padding:16px 12px;
    border-top:1px solid rgba(255,255,255,.1);
}
.nab-member-chip {
    display:flex; align-items:center; gap:10px;
    background:rgba(255,255,255,.08);
    border-radius:12px; padding:10px 12px;
}
.nab-avatar {
    width:34px; height:34px; border-radius:50%;
    background:#F5A623; color:#fff;
    font-weight:700; font-size:.85rem;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.nab-member-info { flex:1; min-width:0; }
.nab-member-name { color:#fff; font-size:.82rem; font-weight:600; }
.nab-member-role { color:rgba(255,255,255,.5); font-size:.72rem; }
.nab-logout-btn  {
    color:rgba(255,255,255,.5); font-size:1.1rem;
    text-decoration:none; transition:color .2s;
}
.nab-logout-btn:hover { color:#F5A623; }

/* ══ HAMBURGER + OVERLAY ════════════════════════════════════════════ */
.nab-hamburger {
    display:none; position:fixed; top:14px; left:14px; z-index:200;
    background:#0D5C9B; color:#fff; border:none;
    width:40px; height:40px; border-radius:10px;
    font-size:1.2rem; cursor:pointer;
    align-items:center; justify-content:center;
}
.nab-sidebar-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.5); z-index:99;
}
@media (max-width:768px) {
    .nab-hamburger { display:flex; }
    .nab-sidebar {
        position:fixed; top:0; left:-270px; height:100vh;
        transition:left .3s ease; z-index:150;
    }
    .nab-sidebar.nab-open   { left:0; }
    .nab-sidebar-overlay.nab-open { display:block; }
}

/* ══ MAIN ═══════════════════════════════════════════════════════════ */
.nab-main { flex:1; min-width:0; display:flex; flex-direction:column; }

.nab-topbar {
    display:flex; align-items:center; justify-content:space-between;
    padding:0 28px; height:60px; background:#fff;
    border-bottom:1px solid #e8edf5;
    position:sticky; top:0; z-index:50;
}
.nab-topbar-title { font-weight:700; font-size:1rem; color:#1a202c; }
.nab-topbar-right { display:flex; align-items:center; gap:12px; }
.nab-status-badge { font-size:.75rem; font-weight:600; padding:4px 12px; border-radius:50px; }
.nab-status-active    { background:#d1fae5; color:#065f46; }
.nab-status-pending   { background:#fef3c7; color:#92400e; }
.nab-status-suspended { background:#fee2e2; color:#991b1b; }

/* ══ SAVE NOTICE ════════════════════════════════════════════════════ */
.uc-notice {
    margin:16px 28px 0;
    padding:11px 18px; border-radius:10px;
    font-size:.85rem; font-weight:500;
    display:none; animation:ucFadeIn .3s ease;
}
.uc-notice.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.uc-notice.error   { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; }
@keyframes ucFadeIn { from{opacity:0;transform:translateY(-5px)} to{opacity:1;transform:translateY(0)} }

/* ══ PAGE CONTENT ═══════════════════════════════════════════════════ */
.uc-content { padding:24px 28px 60px; }

/* ── Hero ── */
.uc-hero {
    background:linear-gradient(135deg,#1a3a8f 0%,#2563eb 55%,#1d4ed8 100%);
    border-radius:16px; padding:28px 32px; color:#fff;
    margin-bottom:24px; display:flex;
    align-items:flex-end; justify-content:space-between;
    flex-wrap:wrap; gap:20px;
    position:relative; overflow:hidden;
}
.uc-hero::before {
    content:''; position:absolute; right:-60px; top:-80px;
    width:300px; height:300px; background:rgba(255,255,255,.04);
    border-radius:50%; pointer-events:none;
}
.uc-hero-greeting { font-size:.82rem; color:rgba(255,255,255,.7); margin-bottom:3px; }
.uc-hero-name     { font-size:1.5rem; font-weight:700; margin-bottom:8px; }
.uc-hero-desc     { font-size:.85rem; color:rgba(255,255,255,.75); max-width:520px; line-height:1.6; }
.uc-hero-pills    { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
.uc-hero-pill {
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.2);
    border-radius:20px; padding:4px 12px;
    font-size:.75rem; font-weight:500;
}
.uc-hero-actions { display:flex; gap:10px; flex-shrink:0; position:relative; z-index:1; }
.btn-outline-white {
    border:1.5px solid rgba(255,255,255,.5); color:#fff;
    background:transparent; border-radius:8px; padding:8px 18px;
    font-size:.83rem; font-weight:500; cursor:pointer;
    text-decoration:none; transition:background .15s;
}
.btn-outline-white:hover { background:rgba(255,255,255,.1); color:#fff; }
.btn-orange {
    background:#F5A623; color:#fff; border:none;
    border-radius:8px; padding:8px 18px;
    font-size:.83rem; font-weight:600; cursor:pointer;
    text-decoration:none; transition:background .15s;
}
.btn-orange:hover { background:#e09415; }

/* ── Main grid ── */
.uc-grid {
    display:grid; grid-template-columns:1fr 340px;
    gap:20px; align-items:start;
}
@media (max-width:960px) { .uc-grid { grid-template-columns:1fr; } }

/* ── Cards ── */
.uc-card {
    background:#fff; border-radius:14px;
    border:1px solid #e8edf5; overflow:hidden;
}
.uc-card-head {
    display:flex; align-items:flex-start;
    justify-content:space-between; gap:12px;
    padding:20px 24px 0;
}
.uc-card-title    { font-size:.95rem; font-weight:700; color:#1a202c; margin-bottom:3px; }
.uc-card-subtitle { font-size:.78rem; color:#718096; }
.uc-card-body     { padding:16px 24px 24px; }

.btn-clear {
    background:#fef2f2; color:#b91c1c;
    border:1px solid #fecaca; border-radius:8px;
    padding:6px 12px; font-size:.72rem; font-weight:600;
    cursor:pointer; white-space:nowrap; flex-shrink:0;
    transition:background .15s;
}
.btn-clear:hover { background:#fee2e2; }

/* ── Form card sections ── */
.uc-section { margin-bottom:14px; }
.uc-section-header {
    display:flex; align-items:center; gap:8px;
    padding-bottom:9px; border-bottom:1px solid #f0f4fa; margin-bottom:11px;
}
.uc-section-num {
    width:22px; height:22px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:.7rem; font-weight:700; color:#fff; flex-shrink:0;
}
.uc-section-title { font-size:.83rem; font-weight:600; color:#1a202c; }
.uc-optional-tag  { font-size:.68rem; color:#718096; background:#f1f5f9; padding:2px 7px; border-radius:4px; }
.uc-required-tag  { font-size:.68rem; color:#0D5C9B; background:#EEF3FA; padding:2px 7px; border-radius:4px; }
.uc-mini-badge    { font-size:.7rem; font-weight:700; padding:2px 9px; border-radius:20px; margin-left:auto; }

.uc-card-row {
    background:#f8fafc; border:1px solid #e8edf5;
    border-radius:10px; padding:12px 14px;
}
.uc-card-row-label {
    font-size:.78rem; font-weight:600;
    display:flex; align-items:center; gap:6px; margin-bottom:11px;
}
.uc-fields {
    display:grid; grid-template-columns:1.4fr 1fr 1fr; gap:10px;
}
@media (max-width:580px) {
    .uc-fields { grid-template-columns:1fr 1fr; }
    .uc-fields .uc-field:first-child { grid-column:1 / -1; }
}
.uc-field label {
    display:block; font-size:.72rem; font-weight:500;
    color:#718096; margin-bottom:5px;
}
.uc-field input {
    width:100%; border:1.5px solid #e2e8f0; border-radius:8px;
    padding:8px 11px; font-size:.85rem; font-family:inherit;
    color:#1a202c; background:#fff; outline:none;
    transition:border-color .15s, box-shadow .15s;
}
.uc-field input:focus {
    border-color:#0D5C9B;
    box-shadow:0 0 0 3px rgba(13,92,155,.1);
}
.uc-field input::placeholder { color:#c0ccd8; }

/* ── Buttons ── */
.uc-calc-btn {
    width:100%; background:#0D5C9B; color:#fff;
    border:none; border-radius:10px; padding:13px 20px;
    font-size:.9rem; font-weight:700; font-family:inherit;
    cursor:pointer; margin-top:16px;
    display:flex; align-items:center; justify-content:center; gap:8px;
    transition:filter .15s, transform .1s;
}
.uc-calc-btn:hover  { filter:brightness(1.1); }
.uc-calc-btn:active { transform:scale(.99); }

.uc-save-btn {
    width:100%; background:#065f46; color:#fff;
    border:none; border-radius:10px; padding:12px 20px;
    font-size:.85rem; font-weight:700; font-family:inherit;
    cursor:pointer; margin-top:14px;
    display:flex; align-items:center; justify-content:center; gap:8px;
    transition:filter .15s, transform .1s;
}
.uc-save-btn:hover    { filter:brightness(1.1); }
.uc-save-btn:active   { transform:scale(.99); }
.uc-save-btn:disabled { opacity:.6; cursor:not-allowed; }

.uc-last-saved { text-align:center; font-size:.72rem; color:#718096; margin-top:10px; }

/* ── Results card ── */
.uc-result-card { position:sticky; top:76px; }

.uc-placeholder { text-align:center; padding:28px 20px; color:#718096; }
.uc-placeholder-icon { font-size:2.5rem; margin-bottom:10px; }
.uc-placeholder-text { font-size:.83rem; line-height:1.6; }
.uc-last-hint {
    margin-top:14px; background:#EEF3FA;
    border:1px solid #bfdbfe; border-radius:8px;
    padding:10px 14px; font-size:.78rem; color:#0D5C9B;
}

/* Ring */
.uc-score-wrap { text-align:center; padding:16px 0 12px; }
.uc-ring-wrap  { position:relative; width:140px; height:140px; margin:0 auto 12px; }
.uc-ring-center {
    position:absolute; inset:0;
    display:flex; flex-direction:column;
    align-items:center; justify-content:center;
}
.uc-score-num  { font-size:1.9rem; font-weight:800; line-height:1; }
.uc-score-unit { font-size:.72rem; color:#718096; }
.uc-score-lbl  { font-size:.95rem; font-weight:700; margin-bottom:3px; }
.uc-score-sub  { font-size:.78rem; color:#718096; }

/* Meter */
.uc-meter { background:#f1f5f9; border-radius:8px; padding:12px 14px; margin:16px 0; }
.uc-meter-labels {
    display:flex; justify-content:space-between;
    font-size:.68rem; color:#94a3b8; margin-bottom:7px;
}
.uc-meter-track { height:10px; background:#e2e8f0; border-radius:10px; overflow:hidden; }
.uc-meter-fill  {
    height:100%; border-radius:10px; width:0%;
    transition:width .8s cubic-bezier(.4,0,.2,1), background .4s;
}
.uc-meter-ticks {
    display:flex; justify-content:space-between;
    font-size:.65rem; color:#94a3b8; margin-top:5px;
}

/* Status boxes */
.uc-status {
    border-radius:10px; padding:12px 14px;
    margin-top:14px; font-size:.78rem; line-height:1.6; display:none;
}
.uc-status-icon  { font-size:.95rem; margin-bottom:3px; display:block; }
.uc-status-title { font-weight:700; font-size:.83rem; margin-bottom:3px; }
.uc-status.green  { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
.uc-status.yellow { background:#fefce8; border:1px solid #fde68a; color:#a16207; }
.uc-status.red    { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; }

/* Breakdown */
.uc-breakdown-head {
    font-size:.72rem; font-weight:600; color:#718096;
    text-transform:uppercase; letter-spacing:.06em; margin:16px 0 8px;
}
.uc-bd-item {
    display:flex; align-items:center; justify-content:space-between;
    padding:7px 0; border-bottom:1px solid #f1f5f9; font-size:.78rem;
}
.uc-bd-item:last-child { border-bottom:none; }
.uc-bd-left  { display:flex; align-items:center; gap:8px; font-weight:500; color:#1a202c; }
.uc-bd-dot   { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.uc-bd-right { display:flex; align-items:center; gap:6px; }
.uc-bd-amt   { font-size:.72rem; color:#718096; }

.uc-badge { font-size:.72rem; font-weight:700; padding:2px 8px; border-radius:20px; }
.badge-green  { background:#dcfce7; color:#15803d; }
.badge-yellow { background:#fef9c3; color:#a16207; }
.badge-red    { background:#fee2e2; color:#b91c1c; }

/* Totals */
.uc-totals {
    display:flex; justify-content:space-between;
    background:#f8fafc; border:1px solid #e8edf5;
    border-radius:8px; padding:12px 14px;
    margin-top:10px; font-size:.78rem;
}
.uc-total-lbl { color:#718096; margin-bottom:2px; }
.uc-total-val { font-weight:700; font-size:.95rem; color:#1a202c; }

/* Tips card */
.uc-tips {
    background:linear-gradient(135deg,#0D5C9B,#1d4ed8);
    border-radius:14px; padding:18px 20px; color:#fff; margin-top:16px;
}
.uc-tips-title { font-size:.83rem; font-weight:700; margin-bottom:12px; opacity:.9; }
.uc-tip { display:flex; gap:10px; margin-bottom:10px; font-size:.78rem; color:rgba(255,255,255,.85); line-height:1.5; }
.uc-tip-num {
    width:18px; height:18px; background:rgba(255,255,255,.15); border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:.65rem; font-weight:700; flex-shrink:0;
}
</style>
</head>
<body <?php body_class( 'nab-portal-body' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">

<!-- ══════════════════════════════════════════════════════════════════
     SIDEBAR — now rendered via shared nab_render_sidebar() so all
     templates stay in sync automatically (was hardcoded before, and
     was missing the "Learning Center" link — now included)
══════════════════════════════════════════════════════════════════ -->
<?php nab_render_sidebar( 'util' ); ?>

<!-- ══════════════════════════════════════════════════════════════════
     MAIN
══════════════════════════════════════════════════════════════════ -->
<main class="nab-main">

    <header class="nab-topbar">
        <div class="nab-topbar-title">📊 Utilization Checker</div>
        <div class="nab-topbar-right">
            <span class="nab-status-badge nab-status-<?php echo esc_attr( strtolower( $member_status ) ); ?>">
                ● <?php echo esc_html( $member_status ); ?>
            </span>
        </div>
    </header>

    <!-- Save / error notice -->
    <div id="ucNotice" class="uc-notice"></div>

    <div class="uc-content">

        <!-- Hero Banner -->
        <div class="uc-hero">
            <div>
                <div class="uc-hero-greeting">👋 Good Evening,</div>
                <div class="uc-hero-name"><?php echo esc_html( $first_name ); ?></div>
                <div class="uc-hero-desc">Check your revolving credit utilization across all cards. Many scoring models consider under 30% as generally favorable, while under 10% is often associated with stronger score outcomes. Results are estimates, not guarantees of any specific credit score or approval.</div>
                <div class="uc-hero-pills">
                    <span class="uc-hero-pill">✅ <?php echo esc_html( $member_status ); ?> Member</span>
                    <span class="uc-hero-pill">🔒 Secured</span>
                    <span class="uc-hero-pill">Member ID: #<?php echo esc_html( $member_id ); ?></span>
                </div>
            </div>
            <div class="uc-hero-actions">
                <a href="<?php echo $dashboard_url; ?>" class="btn-outline-white">View Profile</a>
                <a href="<?php echo $url_booking; ?>"  class="btn-orange">Book Specialist</a>
            </div>
        </div>

        <!-- Grid -->
        <div class="uc-grid">

            <!-- ══ LEFT: FORM ══ -->
            <div>
                <div class="uc-card">
                    <div class="uc-card-head">
                        <div>
                            <div class="uc-card-title">Enter Your Card Details</div>
                            <div class="uc-card-subtitle">You can add up to 5 revolving credit cards. The information you enter is saved securely to your account and used only to calculate your utilization within this tool.</div>
                        </div>
                        <button class="btn-clear" onclick="ucClearAll()">🗑 Clear All</button>
                    </div>
                    <div class="uc-card-body">

                        <?php
                        $card_colors = array( '#0D5C9B', '#6366f1', '#0891b2', '#059669', '#d97706' );
                        $card_labels = array( 'Primary Card', 'Secondary Card', 'Card 3', 'Card 4', 'Card 5' );

                        for ( $i = 1; $i <= 5; $i++ ) :
                            $color = $card_colors[ $i - 1 ];
                            $label = $card_labels[ $i - 1 ];
                            $name  = esc_attr( $saved_cards[ $i ]['name'] );
                            $bal   = esc_attr( $saved_cards[ $i ]['balance'] );
                            $lim   = esc_attr( $saved_cards[ $i ]['limit'] );
                        ?>
                        <div class="uc-section">
                            <div class="uc-section-header">
                                <div class="uc-section-num" style="background:<?php echo $color; ?>"><?php echo $i; ?></div>
                                <div class="uc-section-title">Card <?php echo $i; ?></div>
                                <?php if ( $i === 1 ) : ?>
                                    <span class="uc-required-tag">required</span>
                                <?php else : ?>
                                    <span class="uc-optional-tag">optional</span>
                                <?php endif; ?>
                                <span id="mini<?php echo $i; ?>" class="uc-mini-badge" style="display:none"></span>
                            </div>
                            <div class="uc-card-row">
                                <div class="uc-card-row-label" style="color:<?php echo $color; ?>">
                                    💳 <?php echo esc_html( $label ); ?>
                                </div>
                                <div class="uc-fields">
                                    <div class="uc-field">
                                        <label>Card Nickname</label>
                                        <input type="text" id="name<?php echo $i; ?>"
                                               value="<?php echo $name; ?>"
                                               placeholder="e.g. Chase Sapphire"
                                               maxlength="40">
                                    </div>
                                    <div class="uc-field">
                                        <label>Current Balance ($)</label>
                                        <input type="number" id="b<?php echo $i; ?>"
                                               value="<?php echo $bal; ?>"
                                               placeholder="e.g. 850"
                                               min="0" step="1"
                                               oninput="ucMini(<?php echo $i; ?>)">
                                    </div>
                                    <div class="uc-field">
                                        <label>Credit Limit ($)</label>
                                        <input type="number" id="l<?php echo $i; ?>"
                                               value="<?php echo $lim; ?>"
                                               placeholder="e.g. 5000"
                                               min="0" step="1"
                                               oninput="ucMini(<?php echo $i; ?>)">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>

                        <button class="uc-calc-btn" onclick="ucCalculate()">
                            📊 Calculate My Utilization
                        </button>

                        <?php if ( $last_saved ) : ?>
                        <div class="uc-last-saved">
                            🕐 Last saved: <?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $last_saved ) ) ); ?>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <!-- ══ RIGHT: RESULTS ══ -->
            <div>
                <div class="uc-card uc-result-card">
                    <div class="uc-card-head">
                        <div>
                            <div class="uc-card-title">Your Results</div>
                            <div class="uc-card-subtitle">Live utilization breakdown</div>
                        </div>
                    </div>
                    <div class="uc-card-body">

                        <div id="ucPlaceholder" class="uc-placeholder">
                            <div class="uc-placeholder-icon">📊</div>
                            <div class="uc-placeholder-text">Enter your card balances and limits, then click <strong>Calculate</strong> to see your score.</div>
                            <?php if ( $last_util !== '' ) : ?>
                            <div class="uc-last-hint">
                                Last saved utilization: <strong><?php echo esc_html( number_format( (float) $last_util, 1 ) ); ?>%</strong>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div id="ucResults" style="display:none">

                            <div class="uc-score-wrap">
                                <div class="uc-ring-wrap">
                                    <svg width="140" height="140" viewBox="0 0 140 140">
                                        <circle cx="70" cy="70" r="56" fill="none" stroke="#f1f5f9" stroke-width="12"/>
                                        <circle id="ucRing" cx="70" cy="70" r="56" fill="none" stroke="#22c55e"
                                            stroke-width="12" stroke-dasharray="351.86" stroke-dashoffset="351.86"
                                            stroke-linecap="round"
                                            style="transition:stroke-dashoffset .9s cubic-bezier(.4,0,.2,1),stroke .4s;transform:rotate(-90deg);transform-origin:center"/>
                                    </svg>
                                    <div class="uc-ring-center">
                                        <div id="ucScoreNum" class="uc-score-num">0%</div>
                                        <div class="uc-score-unit">utilization</div>
                                    </div>
                                </div>
                                <div id="ucScoreLbl" class="uc-score-lbl">—</div>
                                <div id="ucScoreSub" class="uc-score-sub">—</div>
                            </div>

                            <div class="uc-meter">
                                <div class="uc-meter-labels">
                                    <span>0%</span><span>30%</span><span>75%</span><span>100%</span>
                                </div>
                                <div class="uc-meter-track">
                                    <div id="ucMeterFill" class="uc-meter-fill"></div>
                                </div>
                                <div class="uc-meter-ticks">
                                    <span>Excellent</span><span>Good</span><span>Warning</span><span>Critical</span>
                                </div>
                            </div>

                            <div id="ucStatusGreen"  class="uc-status green">
                                <span class="uc-status-icon">✅</span>
                                <div class="uc-status-title">Excellent Utilization!</div>
                                Below 30% — lenders see you as a responsible borrower. Keep balances low to maintain or improve your score.
                            </div>
                            <div id="ucStatusYellow" class="uc-status yellow">
                                <span class="uc-status-icon">⚠️</span>
                                <div class="uc-status-title">Moderate, Room to Improve</div>
                                Between 30–75%. Pay down your highest-balance cards first. A 10–15% reduction can boost your score meaningfully.
                            </div>
                            <div id="ucStatusRed"    class="uc-status red">
                                <span class="uc-status-icon">🚨</span>
                                <div class="uc-status-title">High Utilization, Action Needed</div>
                                Above 75%. This is actively hurting your score. Prioritize paying down balances or request a limit increase now.
                            </div>

                            <div class="uc-breakdown-head">Per Card Breakdown</div>
                            <div id="ucBreakdown"></div>

                            <div class="uc-totals">
                                <div>
                                    <div class="uc-total-lbl">Total Balance</div>
                                    <div id="ucTotalBal" class="uc-total-val">$0</div>
                                </div>
                                <div style="text-align:center">
                                    <div class="uc-total-lbl">Cards</div>
                                    <div id="ucCardsUsed" class="uc-total-val">0</div>
                                </div>
                                <div style="text-align:right">
                                    <div class="uc-total-lbl">Total Limit</div>
                                    <div id="ucTotalLim" class="uc-total-val">$0</div>
                                </div>
                            </div>

                            <button class="uc-save-btn" id="ucSaveBtn" onclick="ucSave()">
                                💾 Save My Results
                            </button>

                        </div><!-- /ucResults -->
                    </div>
                </div>

                <div class="uc-tips">
                    <div class="uc-tips-title">💡 Pro Tips to Lower Utilization</div>
                    <div class="uc-tip"><span class="uc-tip-num">1</span>Prioritize paying down cards with the highest utilization percentage first.</div>
                    <div class="uc-tip"><span class="uc-tip-num">2</span>Consider requesting a credit limit increase; if approved, this may reduce your utilization.</div>
                    <div class="uc-tip"><span class="uc-tip-num">3</span>Make multiple payments each month to keep reported balances lower.</div>
                    <div class="uc-tip"><span class="uc-tip-num">4</span>Aim to keep at least one primary card under 10% utilization for potentially stronger scoring impact.</div>
                </div>

            </div>
        </div><!-- /uc-grid -->
    </div><!-- /uc-content -->
</main>
</div><!-- /nab-portal-wrap -->

<input type="hidden" id="ucNonce"   value="<?php echo wp_create_nonce( 'nab_save_utilization' ); ?>">
<input type="hidden" id="ucAjaxUrl" value="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">

<script>
(function () {
    'use strict';

    /* ── Sidebar toggle — same as Dispute Center ── */
    var btn = document.getElementById('nabHamburger');
    var sb  = document.getElementById('nabSidebar');
    var ov  = document.getElementById('nabOverlay');
    if (btn) {
        btn.addEventListener('click', function () { sb.classList.toggle('nab-open'); ov.classList.toggle('nab-open'); });
        ov.addEventListener('click',  function () { sb.classList.remove('nab-open'); ov.classList.remove('nab-open'); });
    }

    var CIRC   = 2 * Math.PI * 56;
    var COLORS = ['#0D5C9B','#6366f1','#0891b2','#059669','#d97706'];
    var LABELS = ['Card 1','Card 2','Card 3','Card 4','Card 5'];

    function el(id)   { return document.getElementById(id); }
    function getN(id) { return parseFloat(el(id) && el(id).value) || 0; }
    function getS(id) { return (el(id) && el(id).value || '').trim(); }
    function fmt(n)   { return '$' + Number(n).toLocaleString('en-US',{maximumFractionDigits:0}); }
    function bc(p)    { return p < 30 ? 'badge-green' : p < 75 ? 'badge-yellow' : 'badge-red'; }
    function cl(p)    { return p < 30 ? '#22c55e'    : p < 75 ? '#eab308'      : '#ef4444';    }

    window.ucMini = function (i) {
        var b = getN('b'+i), l = getN('l'+i), badge = el('mini'+i);
        if (!badge) return;
        if (b > 0 && l > 0) {
            var p = Math.min((b/l)*100,100);
            badge.textContent   = p.toFixed(1)+'%';
            badge.className     = 'uc-mini-badge uc-badge ' + bc(p);
            badge.style.display = 'inline-block';
        } else { badge.style.display = 'none'; }
    };
    for (var j = 1; j <= 5; j++) ucMini(j);

    window.ucCalculate = function () {
        var tb = 0, tl = 0, cards = [];
        for (var i = 1; i <= 5; i++) {
            var b = getN('b'+i), l = getN('l'+i), n = getS('name'+i) || LABELS[i-1];
            if (l > 0) { tb += b; tl += l; cards.push({label:n,balance:b,limit:l,pct:Math.min((b/l)*100,100),color:COLORS[i-1]}); }
        }
        if (tl === 0) { notice('Please enter at least one card with a credit limit.', 'error'); return; }
        var ov = Math.min((tb/tl)*100,100), c = cl(ov);

        el('ucPlaceholder').style.display = 'none';
        el('ucResults').style.display     = 'block';

        el('ucRing').style.strokeDashoffset = CIRC - (ov/100)*CIRC;
        el('ucRing').style.stroke           = c;
        el('ucScoreNum').textContent = ov.toFixed(1)+'%';
        el('ucScoreNum').style.color = c;

        var lbl, sub;
        if      (ov < 10) { lbl='🌟 Exceptional'; sub='Below 10% — ideal for maximum score impact'; }
        else if (ov < 30) { lbl='✅ Excellent';   sub='Below 30% — well within safe range'; }
        else if (ov < 75) { lbl='⚠️ Moderate';   sub='Between 30–75% — consider paying down balances'; }
        else              { lbl='🚨 High Risk';   sub='Above 75% — this is hurting your score'; }
        el('ucScoreLbl').textContent = lbl; el('ucScoreLbl').style.color = c;
        el('ucScoreSub').textContent = sub;

        el('ucMeterFill').style.width      = Math.min(ov,100).toFixed(1)+'%';
        el('ucMeterFill').style.background = c;

        el('ucStatusGreen').style.display  = ov < 30             ? 'block':'none';
        el('ucStatusYellow').style.display = ov>=30 && ov<75     ? 'block':'none';
        el('ucStatusRed').style.display    = ov >= 75            ? 'block':'none';

        var html='';
        cards.forEach(function(card){
            html += '<div class="uc-bd-item"><div class="uc-bd-left"><div class="uc-bd-dot" style="background:'+card.color+'"></div>'+esc(card.label)+'</div>'+
                    '<div class="uc-bd-right"><span class="uc-bd-amt">'+fmt(card.balance)+' / '+fmt(card.limit)+'</span>'+
                    '<span class="uc-badge '+bc(card.pct)+'">'+card.pct.toFixed(1)+'%</span></div></div>';
        });
        el('ucBreakdown').innerHTML = html;
        el('ucTotalBal').textContent  = fmt(tb);
        el('ucTotalLim').textContent  = fmt(tl);
        el('ucCardsUsed').textContent = cards.length;

        window._ucResult = { overall:ov.toFixed(2), totalBal:tb, totalLim:tl };
    };

    window.ucSave = function () {
        var nonce = el('ucNonce') && el('ucNonce').value;
        var url   = el('ucAjaxUrl') && el('ucAjaxUrl').value;
        if (!nonce || !url) return;
        var saveBtn = el('ucSaveBtn');
        saveBtn.disabled    = true;
        saveBtn.textContent = '⏳ Saving…';
        var fd = new FormData();
        fd.append('action','nab_save_utilization_data');
        fd.append('_wpnonce',nonce);
        for (var i=1;i<=5;i++){
            fd.append('card_name_'+i,    getS('name'+i));
            fd.append('card_balance_'+i, getN('b'+i));
            fd.append('card_limit_'+i,   getN('l'+i));
        }
        if (window._ucResult){
            fd.append('overall_pct',   window._ucResult.overall);
            fd.append('total_balance', window._ucResult.totalBal);
            fd.append('total_limit',   window._ucResult.totalLim);
        }
        fetch(url,{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.success){ notice('✅ '+(d.data&&d.data.message||'Saved successfully!'),'success'); var ls=document.querySelector('.uc-last-saved'); if(ls) ls.textContent='🕐 Last saved: just now'; }
                else          { notice('❌ '+(d.data&&d.data.message||'Could not save.'),'error'); }
            })
            .catch(function(){ notice('❌ Network error. Check your connection.','error'); })
            .finally(function(){ saveBtn.disabled=false; saveBtn.innerHTML='💾 Save My Results'; });
    };

    window.ucClearAll = function () {
        if (!confirm('Clear all card data? Server data is not deleted until you save again.')) return;
        for (var i=1;i<=5;i++){ ['name','b','l'].forEach(function(p){var x=el(p+i);if(x)x.value='';}); ucMini(i); }
        el('ucPlaceholder').style.display='block';
        el('ucResults').style.display='none';
        window._ucResult=null;
    };

    function notice(msg, type) {
        var n = el('ucNotice');
        if (!n) return;
        n.textContent=msg; n.className='uc-notice '+type; n.style.display='block';
        clearTimeout(n._t); n._t=setTimeout(function(){n.style.display='none';},5000);
    }
    function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
})();
</script>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>
