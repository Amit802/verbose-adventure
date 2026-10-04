<?php
/**
 * Template Name: Credit Dispute Center</div>
          <div class="nab-dispute-disclosure" style="background:#fef9c3;border:1px solid #fde68a;border-radius:10px;padding:14px 16px;margin:12px 0;font-size:12px;color:#78350f;line-height:1.6;">
            <strong>Important Notice:</strong> We assist you in preparing and submitting dispute information but we are not a credit bureau, lender, collection agency, or law firm.
            You may dispute information on your credit report directly with the credit bureaus at no cost.
            Submitting this form does not guarantee any specific outcome, deletion, or change to your credit report.
          </div
 * Description: Members-only credit dispute submission page with NAB dashboard layout
 *
 * Place in: /wp-content/themes/hello-elementor/dispute-center-template.php
 *
 * Requires:
 *  - Advanced Custom Fields FREE
 *  - Gravity Forms (https://www.gravityforms.com)
 *  - Gravity Forms Signature Add-On
 *  - PDF for Gravity Forms (optional — for auto PDF generation)
 */

if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

// ── Current user ──────────────────────────────────────────────────────────────
$current_user = wp_get_current_user();
$first_name   = $current_user->first_name ?: $current_user->display_name;
$last_name    = $current_user->last_name  ?: '';
$initials     = strtoupper( substr( $first_name, 0, 1 ) . substr( $last_name, 0, 1 ) ) ?: 'M';

// ── Read sidebar links from Dashboard page ────────────────────────────────────
$dashboard_page = get_page_by_path( 'dashboard' ); // change slug if needed
$dash_id        = $dashboard_page ? $dashboard_page->ID : 0;

function dc_get_dash_field( $name, $dash_id ) {
    if ( ! function_exists( 'get_field' ) || ! $dash_id ) return '#';
    $val = get_field( $name, $dash_id );
    if ( is_array( $val ) && isset( $val['url'] ) ) return esc_url( $val['url'] );
    if ( is_string( $val ) && ! empty( $val ) )     return esc_url( $val );
    return '#';
}

$member_status  = function_exists('get_field') ? ( get_field('nab_member_status', $dash_id) ?: 'Active' ) : 'Active';
$dashboard_url  = $dash_id ? esc_url( get_permalink( $dash_id ) ) : esc_url( home_url('/dashboard/') );
$url_report     = dc_get_dash_field( 'nab_link_report',      $dash_id );
$url_dispute    = esc_url( get_permalink() ); // this page = active
$url_booking    = dc_get_dash_field( 'nab_link_booking',     $dash_id );
$url_chatbot    = dc_get_dash_field( 'nab_link_chatbot',     $dash_id );
$url_util       = dc_get_dash_field( 'nab_link_utilization', $dash_id );
$url_sim        = dc_get_dash_field( 'nab_link_simulator',   $dash_id );
$url_blog       = dc_get_dash_field( 'nab_link_blog',        $dash_id );
$url_diy        = dc_get_dash_field( 'nab_link_diy',         $dash_id );
$url_learning  = dc_get_dash_field( 'nab_link_learning', $dash_id );
$url_support   = dc_get_dash_field( 'nab_link_support',  $dash_id );
$url_loan      = dc_get_dash_field( 'nab_link_loan',        $dash_id );
$url_card      = dc_get_dash_field( 'nab_link_card_match',  $dash_id );

// ── This page's ACF fields ────────────────────────────────────────────────────
$page_id = get_the_ID();

$hero_title       = function_exists('get_field') ? get_field('dc_hero_title',       $page_id) : '';
$hero_subtitle    = function_exists('get_field') ? get_field('dc_hero_subtitle',    $page_id) : '';
$hero_bg_color    = function_exists('get_field') ? get_field('dc_hero_bg_color',    $page_id) : '';
$section_heading  = function_exists('get_field') ? get_field('dc_section_heading',  $page_id) : '';
$section_subtitle = function_exists('get_field') ? get_field('dc_section_subtitle', $page_id) : '';
$gravity_form_id  = function_exists('get_field') ? get_field('dc_gravity_form_id',  $page_id) : '';
$form_intro       = function_exists('get_field') ? get_field('dc_form_intro',       $page_id) : '';
$support_email    = function_exists('get_field') ? get_field('dc_support_email',    $page_id) : '';
$support_phone    = function_exists('get_field') ? get_field('dc_support_phone',    $page_id) : '';
$support_note     = function_exists('get_field') ? get_field('dc_support_note',     $page_id) : '';

// Defaults
$hero_title       = $hero_title       ?: 'Credit Dispute Center';
$hero_subtitle    = $hero_subtitle    ?: 'Submit a credit dispute, upload supporting documents, and track your case, all in one secure place.';
$hero_bg_color    = $hero_bg_color    ?: '#0D5C9B';
$section_heading  = $section_heading  ?: 'Submit a Credit Dispute';
$section_subtitle = $section_subtitle ?: 'Fill out the form below. We will review your submission and respond with guidance within 5–7 business days. This is our internal response time and does not control how quickly credit bureaus update your report.';
$form_intro       = $form_intro       ?: 'Please complete all required fields. Your submission is encrypted and securely stored.';
$support_email    = $support_email    ?: 'admin@nabsolutions.ca';
$support_note     = $support_note     ?: 'All dispute submissions are encrypted and stored securely. We do not share your information.';

// ── Letter template slots ─────────────────────────────────────────────────────
$letter_templates = [];
if ( function_exists('get_field') ) {
    for ( $i = 1; $i <= 4; $i++ ) {
        $title = get_field( "dispute_tpl_{$i}_title", $page_id );
        $file  = get_field( "dispute_tpl_{$i}_file",  $page_id );
        if ( empty($title) && empty($file) ) continue;
        $letter_templates[] = [
            'title' => $title ?: "Dispute Template {$i}",
            'desc'  => get_field( "dispute_tpl_{$i}_desc",  $page_id ) ?: '',
            'badge' => get_field( "dispute_tpl_{$i}_badge", $page_id ) ?: '',
            'file'  => $file  ?: '#',
            'color' => get_field( "dispute_tpl_{$i}_color", $page_id ) ?: '#0D5C9B',
        ];
    }
}

// Default templates if none set
// v1.6.8: now pulled from shared nab_get_dispute_templates() (PDF versions)
// so this list and the Dashboard "DIY Dispute Letter" tab never drift apart.
if ( empty($letter_templates) && function_exists( 'nab_get_dispute_templates' ) ) {
    $letter_templates = nab_get_dispute_templates();
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Credit Dispute Center — NAB Solutions</title>
<?php wp_head(); ?>
<style>
/* ── Hide Hello Elementor chrome ──────────────────────────────────── */
.site-header,.site-footer,.elementor-location-header,
.elementor-location-footer,#masthead,#colophon { display:none !important; }
body { margin:0 !important; padding:0 !important; background:#F0F4FA !important; }
.elementor-page .elementor-section-wrap,
.e-page-settings .elementor-section-wrap { padding:0 !important; }

/* ══ PORTAL LAYOUT ════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing:border-box; }
.nab-portal-wrap { display:flex; min-height:100vh; font-family:'Inter',system-ui,sans-serif; }

/* ── Sidebar ─────────────────────────────────────────────────────── */
.nab-sidebar {
    width:260px; min-width:260px;
    background:linear-gradient(160deg,#0D5C9B 0%,#0a4a7c 100%);
    display:flex; flex-direction:column;
    position:sticky; top:0; height:100vh; overflow-y:auto; z-index:100;
}
.nab-sidebar-logo {
    display:flex; align-items:center; gap:10px;
    padding:24px 20px 20px; border-bottom:1px solid rgba(255,255,255,.1);
}
.nab-logo-icon {
    width:36px; height:36px; border-radius:10px;
    background:#F5A623; color:#fff; font-weight:800; font-size:1.1rem;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.nab-logo-text { color:#fff; font-weight:700; font-size:.95rem; line-height:1.2; }
.nab-logo-text span { color:#F5A623; }
.nab-logo-text small { display:block; font-size:.7rem; font-weight:400; opacity:.6; }
.nab-sidebar-nav { flex:1; padding:16px 12px; display:flex; flex-direction:column; gap:2px; }
.nab-nav-label {
    font-size:.68rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
    color:rgba(255,255,255,.4); padding:12px 8px 4px;
}
.nab-nav-item {
    display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px;
    color:rgba(255,255,255,.75); font-size:.88rem; font-weight:500;
    text-decoration:none; transition:background .2s,color .2s;
}
.nab-nav-item:hover { background:rgba(255,255,255,.1); color:#fff; }
.nab-nav-item.nab-active { background:rgba(255,255,255,.15); color:#fff; font-weight:600; }
.nab-sidebar-bottom { padding:16px 12px; border-top:1px solid rgba(255,255,255,.1); }
.nab-member-chip {
    display:flex; align-items:center; gap:10px;
    background:rgba(255,255,255,.08); border-radius:12px; padding:10px 12px;
}
.nab-avatar {
    width:34px; height:34px; border-radius:50%; background:#F5A623; color:#fff;
    font-weight:700; font-size:.85rem; display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.nab-member-info { flex:1; min-width:0; }
.nab-member-name { color:#fff; font-size:.82rem; font-weight:600; }
.nab-member-role { color:rgba(255,255,255,.5); font-size:.72rem; }
.nab-logout-btn { color:rgba(255,255,255,.5); font-size:1.1rem; text-decoration:none; transition:color .2s; }
.nab-logout-btn:hover { color:#F5A623; }

/* ── Main ────────────────────────────────────────────────────────── */
.nab-main { flex:1; min-width:0; display:flex; flex-direction:column; }
.nab-topbar {
    display:flex; align-items:center; justify-content:space-between;
    padding:0 28px; height:60px; background:#fff;
    border-bottom:1px solid #e8edf5; position:sticky; top:0; z-index:50;
}
.nab-topbar-title { font-weight:700; font-size:1rem; color:#1a202c; }
.nab-topbar-right { display:flex; align-items:center; gap:12px; }
.nab-status-badge { font-size:.75rem; font-weight:600; padding:4px 12px; border-radius:50px; }
.nab-status-active    { background:#d1fae5; color:#065f46; }
.nab-status-pending   { background:#fef3c7; color:#92400e; }
.nab-status-suspended { background:#fee2e2; color:#991b1b; }
.nab-content { flex:1; overflow-y:auto; }

/* ── Mobile ──────────────────────────────────────────────────────── */
.nab-hamburger {
    display:none; position:fixed; top:14px; left:14px; z-index:200;
    background:#0D5C9B; color:#fff; border:none;
    width:40px; height:40px; border-radius:10px; font-size:1.2rem; cursor:pointer;
    align-items:center; justify-content:center;
}
.nab-sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:99; }
@media (max-width:768px) {
    .nab-hamburger { display:flex; }
    .nab-sidebar { position:fixed; top:0; left:-270px; height:100vh; transition:left .3s ease; z-index:150; }
    .nab-sidebar.nab-open { left:0; }
    .nab-sidebar-overlay.nab-open { display:block; }
}

/* ══ PAGE CONTENT STYLES ══════════════════════════════════════════════ */
:root {
    --dc-blue:      <?php echo esc_attr($hero_bg_color); ?>;
    --dc-orange:    #F5A623;
    --dc-red:       #dc2626;
    --dc-light:     #EEF3FA;
    --text-dark:    #1A1A2E;
    --text-mid:     #4A5568;
    --radius:       16px;
    --shadow:       0 4px 24px rgba(13,92,155,.10);
    --trans:        .25s cubic-bezier(.4,0,.2,1);
}

/* Hero */
.dc-hero {
    background:var(--dc-blue); padding:52px 32px 60px;
    text-align:center; position:relative; overflow:hidden;
}
.dc-hero::before {
    content:''; position:absolute; inset:0; pointer-events:none;
    background:
        radial-gradient(ellipse 70% 60% at 15% 50%, rgba(245,166,35,.2) 0%, transparent 55%),
        radial-gradient(ellipse 55% 70% at 85% 40%, rgba(255,255,255,.07) 0%, transparent 50%);
}
.dc-hero__eyebrow {
    display:inline-flex; align-items:center; gap:8px;
    background:rgba(245,166,35,.18); border:1px solid rgba(245,166,35,.4);
    color:#FFD580; font-size:.72rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
    padding:6px 16px; border-radius:50px; margin-bottom:18px;
}
.dc-hero__title { font-size:clamp(1.6rem,4vw,2.6rem); font-weight:800; color:#fff; line-height:1.15; margin-bottom:14px; }
.dc-hero__sub { font-size:1rem; color:rgba(255,255,255,.75); max-width:540px; margin:0 auto 28px; line-height:1.7; }
.dc-hero__pills { display:flex; flex-wrap:wrap; gap:10px; justify-content:center; }
.dc-hero__pill {
    display:inline-flex; align-items:center; gap:7px;
    background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18);
    color:rgba(255,255,255,.85); font-size:.8rem; padding:7px 16px; border-radius:50px;
}

/* ── Content wrapper ─────────────────────────────────────────────── */
.dc-wrap { max-width:1080px; margin:0 auto; padding:48px 28px 72px; }

/* ── Step guide strip ────────────────────────────────────────────── */
.dc-steps {
    display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px;
    margin-bottom:48px;
}
.dc-status-dot.done {
    background: #0D5C9B;
    box-shadow: 0 0 0 3px rgba(13,92,155,.2);
}
.dc-step {
    background:#fff; border-radius:14px; padding:20px;
    box-shadow:var(--shadow); border-top:4px solid var(--dc-blue);
    display:flex; gap:14px; align-items:flex-start;
}
.dc-step__num {
    width:32px; height:32px; border-radius:50%; background:var(--dc-blue);
    color:#fff; font-weight:800; font-size:.85rem;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.dc-step__title { font-weight:700; font-size:.88rem; color:var(--text-dark); margin-bottom:4px; }
.dc-step__desc  { font-size:.78rem; color:var(--text-mid); line-height:1.5; }

/* ── Two-column layout ───────────────────────────────────────────── */
.dc-cols { display:grid; grid-template-columns:1fr 360px; gap:28px; align-items:start; }
@media (max-width:900px) { .dc-cols { grid-template-columns:1fr; } }

/* ── Form card ───────────────────────────────────────────────────── */
.dc-form-card {
    background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
    overflow:hidden;
}
.dc-form-card__head {
    background:var(--dc-blue); padding:24px 28px;
    display:flex; align-items:center; gap:12px;
}
.dc-form-card__icon {
    width:44px; height:44px; border-radius:12px;
    background:rgba(255,255,255,.15); font-size:1.4rem;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.dc-form-card__head-text h2 { font-size:1.1rem; font-weight:700; color:#fff; margin-bottom:3px; }
.dc-form-card__head-text p  { font-size:.8rem; color:rgba(255,255,255,.7); }
.dc-form-card__body { padding:28px; }
.dc-form-intro {
    background:#EEF3FA; border-left:4px solid var(--dc-blue);
    border-radius:0 10px 10px 0; padding:14px 16px;
    font-size:.85rem; color:var(--text-mid); line-height:1.6; margin-bottom:24px;
}
.dc-form-intro strong { color:var(--dc-blue); }

/* Gravity Forms overrides */
.dc-form-card__body .gform_wrapper { margin:0 !important; }
.dc-form-card__body .gform_fields { gap:16px; display:flex; flex-direction:column; }
.dc-form-card__body .gfield { margin-bottom:0 !important; }
.dc-form-card__body .gfield label,.dc-form-card__body .gfield_label {
    font-weight:600; font-size:.85rem; color:var(--text-dark); margin-bottom:6px; display:block;
}
.dc-form-card__body input[type=text],
.dc-form-card__body input[type=email],
.dc-form-card__body input[type=number],
.dc-form-card__body textarea,
.dc-form-card__body select {
    width:100%; padding:11px 14px; border:1.5px solid #e2e8f0;
    border-radius:10px; font-size:.9rem; color:var(--text-dark);
    background:#fff; transition:border-color var(--trans),box-shadow var(--trans);
    outline:none;
}
.dc-form-card__body input:focus,
.dc-form-card__body textarea:focus,
.dc-form-card__body select:focus {
    border-color:var(--dc-blue); box-shadow:0 0 0 3px rgba(13,92,155,.12);
}
.dc-form-card__body .gform_footer,
.dc-form-card__body .gform_button,
.dc-form-card__body input[type=submit] {
    width:100%; background:var(--dc-blue); color:#fff;
    font-weight:700; font-size:.95rem; padding:14px 24px;
    border:none; border-radius:12px; cursor:pointer;
    transition:filter var(--trans); margin-top:8px;
}
.dc-form-card__body .gform_button:hover,
.dc-form-card__body input[type=submit]:hover { filter:brightness(1.1); }

/* No-GF placeholder */
.dc-no-form {
    text-align:center; padding:40px 20px;
    background:#f8fafc; border:2px dashed #cbd5e0; border-radius:12px;
    color:var(--text-mid); font-size:.9rem; line-height:1.7;
}
.dc-no-form strong { color:var(--dc-blue); display:block; font-size:1rem; margin-bottom:8px; }

/* ── Sidebar cards ───────────────────────────────────────────────── */
.dc-sidebar { display:flex; flex-direction:column; gap:20px; }

.dc-info-card {
    background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
    overflow:hidden;
}
.dc-info-card__head {
    padding:16px 20px; font-weight:700; font-size:.9rem; color:#fff;
    background:var(--dc-blue); display:flex; align-items:center; gap:8px;
}
.dc-info-card__body { padding:16px 20px; }

/* Status tracker */
.dc-status-list { display:flex; flex-direction:column; gap:0; }
.dc-status-item {
    display:flex; align-items:flex-start; gap:12px;
    padding:12px 0; border-bottom:1px solid #f0f4fa;
}
.dc-status-item:last-child { border-bottom:none; }
.dc-status-dot {
    width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:5px;
}
.dc-status-dot.active   { background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.2); }
.dc-status-dot.pending  { background:#f59e0b; }
.dc-status-dot.inactive { background:#e2e8f0; }
.dc-status-title { font-size:.82rem; font-weight:600; color:var(--text-dark); }
.dc-status-desc  { font-size:.75rem; color:var(--text-mid); margin-top:2px; }

/* Contact card */
.dc-contact-row { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid #f0f4fa; font-size:.85rem; }
.dc-contact-row:last-child { border-bottom:none; }
.dc-contact-icon { font-size:1.1rem; flex-shrink:0; }
.dc-contact-val  { color:var(--text-dark); font-weight:500; }
.dc-contact-val a { color:var(--dc-blue); text-decoration:none; }
.dc-contact-val a:hover { text-decoration:underline; }

/* ── Letter templates section ────────────────────────────────────── */
.dc-tpl-section { margin-top:40px; }
.dc-tpl-head    { margin-bottom:20px; }
.dc-tpl-head h3 { font-size:1.2rem; font-weight:700; color:var(--text-dark); margin-bottom:6px; }
.dc-tpl-head p  { font-size:.88rem; color:var(--text-mid); }

.dc-tpl-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:20px; }
.dc-tpl-card {
    background:#fff; border-radius:14px; box-shadow:var(--shadow);
    overflow:hidden; display:flex; flex-direction:column;
    transition:transform var(--trans),box-shadow var(--trans);
}
.dc-tpl-card:hover { transform:translateY(-4px); box-shadow:0 10px 32px rgba(13,92,155,.15); }
.dc-tpl-card__bar { height:5px; }
.dc-tpl-card__body { padding:20px; flex:1; }
.dc-tpl-card__badge {
    display:inline-block; font-size:.67rem; font-weight:700; letter-spacing:.05em;
    text-transform:uppercase; padding:3px 9px; border-radius:50px;
    background:var(--dc-light); color:var(--dc-blue); margin-bottom:10px;
}
.dc-tpl-card__title { font-weight:700; font-size:.95rem; color:var(--text-dark); margin-bottom:8px; }
.dc-tpl-card__desc  { font-size:.82rem; color:var(--text-mid); line-height:1.55; }
.dc-tpl-card__footer { padding:0 20px 20px; }
.dc-tpl-card__btn {
    display:flex; align-items:center; justify-content:center; gap:7px;
    width:100%; padding:11px 16px; border-radius:10px;
    font-size:.83rem; font-weight:600; text-decoration:none;
    color:#fff; transition:filter var(--trans);
}
.dc-tpl-card__btn:hover { filter:brightness(1.12); color:#fff; }
.dc-tpl-card__btn--disabled {
    background:#e2e8f0 !important; color:#94a3b8 !important; cursor:not-allowed;
    pointer-events:none;
}

/* ── Security bar ────────────────────────────────────────────────── */
.dc-security-bar {
    background:var(--dc-blue); color:rgba(255,255,255,.8);
    text-align:center; padding:18px 24px; font-size:.84rem;
    display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:20px;
}
.dc-security-bar span { display:inline-flex; align-items:center; gap:6px; }
.dc-security-bar strong { color:#fff; }
</style>
</head>
<body <?php body_class('nab-portal-body'); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">

  <!-- ════ SIDEBAR ── now rendered via shared nab_render_sidebar() so all
       templates stay in sync automatically (was hardcoded before) ════ -->
  <?php nab_render_sidebar( 'dispute' ); ?>

  <!-- ════ MAIN ════════════════════════════════════════════════════ -->
  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title">🛡️ Credit Dispute Center</div>
      <div class="nab-topbar-right">
        <span class="nab-status-badge nab-status-<?php echo esc_attr(strtolower($member_status)); ?>">
          ● <?php echo esc_html($member_status); ?>
        </span>
      </div>
    </header>

    <div class="nab-content">

      <!-- Hero -->
      <section class="dc-hero">
        <div class="dc-hero__eyebrow">🛡️ Secure Submission</div>
        <h1 class="dc-hero__title"><?php echo esc_html($hero_title); ?></h1>
        <p class="dc-hero__sub"><?php echo esc_html($hero_subtitle); ?></p>
        <div class="dc-hero__pills">
          <span class="dc-hero__pill">🔒 SSL Encrypted</span>
          <span class="dc-hero__pill">📄 PDF Auto-Generated</span>
          <span class="dc-hero__pill">✍️ Digital Signature</span>
          <span class="dc-hero__pill">📬 5–7 Day Response</span>
        </div>
      </section>

      <div class="dc-wrap">

        <!-- Process Steps -->
        <div class="dc-steps">
          <?php
          $steps = [
            [ '1', 'Choose Bureau',       'Select Equifax or TransUnion — the bureau reporting the error.' ],
            [ '2', 'Fill the Form',        'Enter your details, account number, and explain the dispute.' ],
            [ '3', 'Upload Documents',     'Attach any supporting proof (statements, letters, IDs).' ],
            [ '4', 'Sign Digitally',       'Add your signature to authorise the dispute submission. By signing, you certify that the information provided is true and accurate to the best of your knowledge.' ],
            [ '5', 'Submit & Track',       'You receive a reference number by email to track your case.' ],
          ];
          foreach ( $steps as $s ) : ?>
          <div class="dc-step">
            <div class="dc-step__num"><?php echo $s[0]; ?></div>
            <div>
              <div class="dc-step__title"><?php echo esc_html($s[1]); ?></div>
              <div class="dc-step__desc"><?php echo esc_html($s[2]); ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Two column: Form + Sidebar -->
        <div class="dc-cols">

          <!-- ── FORM CARD ─────────────────────────────────────── -->
          <div>
            <div class="dc-form-card">
              <div class="dc-form-card__head">
                <div class="dc-form-card__icon">🛡️</div>
                <div class="dc-form-card__head-text">
                  <h2><?php echo esc_html($section_heading); ?></h2>
                  <p><?php echo esc_html($section_subtitle); ?></p>
                </div>
              </div>
              <div class="dc-form-card__body">
                <div class="dc-form-intro">
                  <strong>🔒 Secure Submission:</strong> <?php echo esc_html($form_intro); ?>
                </div>

                <?php
                // Render Gravity Form if plugin active and form ID set
                if ( $gravity_form_id && function_exists('gravity_form') ) :
                    gravity_form( intval($gravity_form_id), false, false, false, null, true );
                else :
                ?>
                <div class="dc-no-form">
                  <strong>⚙️ Gravity Forms Setup Required</strong>
                  Follow the step guide below to create your dispute form in Gravity Forms,
                  then enter the Form ID in the ACF settings for this page.<br><br>
                  <em>Once set up, the form will appear here automatically.</em>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- ── SIDEBAR CARDS ─────────────────────────────────── -->
          <div class="dc-sidebar">

<!-- Status tracker — dynamic (reads from user meta set in WP Admin > Users > Edit User) -->
<div class="dc-info-card">
  <div class="dc-info-card__head">📋 Dispute Status</div>
  <div class="dc-info-card__body">

    <?php
    $dispute_status = get_user_meta( $current_user->ID, 'dispute_status', true ) ?: 'none';
    $dispute_ref    = get_user_meta( $current_user->ID, 'dispute_reference', true );
    $dispute_note   = get_user_meta( $current_user->ID, 'dispute_note', true );

    $order = [ 'submitted', 'reviewing', 'contacted', 'resolved' ];

    $statuses = [
        'submitted' => [ 'label' => 'Form Submitted',   'desc' => 'Your dispute has been received'           ],
        'reviewing' => [ 'label' => 'Under Review',      'desc' => 'Our team is reviewing your case'          ],
        'contacted' => [ 'label' => 'Bureau Contacted',  'desc' => 'Dispute sent to Equifax / TransUnion'     ],
        'resolved'  => [ 'label' => 'Resolved',          'desc' => 'The bureau has responded to your dispute' ],
    ];

    $current_index = array_search( $dispute_status, $order );
    ?>

    <?php if ( $dispute_status === 'none' || $current_index === false ) : ?>
      <p style="font-size:.83rem;color:#718096;text-align:center;padding:12px 0;">
        No active dispute is currently recorded in your NAB Solutions member profile. Submitting this form creates a new dispute case in our system for review.
      </p>
    <?php else : ?>

      <?php if ( $dispute_ref ) : ?>
      <div style="background:#EEF3FA;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:.8rem;color:#4A5568;">
        🔖 <strong>Reference:</strong> #<?php echo esc_html( $dispute_ref ); ?>
      </div>
      <?php endif; ?>

      <div class="dc-status-list">
        <?php foreach ( $order as $i => $key ) :
          $step_index = $i;
          if ( $step_index < $current_index ) {
              $dot = 'done';    // past step — completed
          } elseif ( $step_index === $current_index ) {
              $dot = 'active';  // current step
          } else {
              $dot = 'inactive'; // future step
          }
        ?>
        <div class="dc-status-item">
          <div class="dc-status-dot <?php echo $dot; ?>"></div>
          <div>
            <div class="dc-status-title"><?php echo esc_html( $statuses[$key]['label'] ); ?></div>
            <div class="dc-status-desc"><?php echo esc_html( $statuses[$key]['desc'] ); ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ( $dispute_note ) : ?>
      <div style="margin-top:14px;padding:12px 14px;background:#EEF3FA;border-left:4px solid #0D5C9B;border-radius:0 10px 10px 0;font-size:.82rem;color:#4A5568;line-height:1.6;">
        <strong style="color:#0D5C9B;">Latest Update:</strong><br>
        <?php echo esc_html( $dispute_note ); ?>
      </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

            <!-- What to include -->
            <div class="dc-info-card">
              <div class="dc-info-card__head">📎 What to Include</div>
              <div class="dc-info-card__body" style="font-size:.83rem;color:#4A5568;line-height:1.7;">
                <ul style="padding-left:18px;margin:0;">
                  <li>Government-issued ID (passport or driver's licence)</li>
                  <li>Bank or credit card statements</li>
                  <li>Any letters from the creditor</li>
                  <li>Police report (for fraud disputes)</li>
                  <li>Proof of on-time payment (for late payment errors)</li>
                </ul>
<p style="font-size:11px;color:#718096;margin-top:10px;line-height:1.5;"><?php echo esc_html(get_field('dc_bureau_note') ?: 'Credit bureaus generally have up to 30 days to investigate disputes you submit to them directly, and they may request additional documentation.'); ?></p>
                  <p style="font-size:11px;color:#718096;margin-top:10px;line-height:1.5;">Credit bureaus generally have up to 30 days to investigate disputes you submit to them directly, and they may request additional documentation.</p>
              </div>
            </div>

            <!-- Contact -->
            <div class="dc-info-card">
              <div class="dc-info-card__head">📞 Need Help?</div>
              <div class="dc-info-card__body">
                <?php if ($support_email) : ?>
                <div class="dc-contact-row">
                  <span class="dc-contact-icon">✉️</span>
                  <span class="dc-contact-val"><a href="mailto:<?php echo esc_attr($support_email); ?>"><?php echo esc_html($support_email); ?></a></span>
                </div>
                <?php endif; ?>
                <?php if ($support_phone) : ?>
                <div class="dc-contact-row">
                  <span class="dc-contact-icon">📱</span>
                  <span class="dc-contact-val"><a href="tel:<?php echo esc_attr($support_phone); ?>"><?php echo esc_html($support_phone); ?></a></span>
                </div>
                <?php endif; ?>
                <div class="dc-contact-row">
                  <span class="dc-contact-icon">🕐</span>
                  <span class="dc-contact-val">Response within 5–7 business days (internal review only — bureau timelines vary)</span>
                </div>
              </div>
            </div>

          </div><!-- /dc-sidebar -->
        </div><!-- /dc-cols -->

        <!-- ── LETTER TEMPLATES ──────────────────────────────────── -->
        <div class="dc-tpl-section">
          <div class="dc-tpl-head">
            <h3>📄 Dispute Letter Templates</h3>
            <p>Download a pre-written template, fill in your details, and upload it with your dispute form.</p>
          </div>
          <div class="dc-tpl-grid">
            <?php foreach ( $letter_templates as $tpl ) :
              $has_file = ( $tpl['file'] && $tpl['file'] !== '#' );
            ?>
            <div class="dc-tpl-card">
              <div class="dc-tpl-card__bar" style="background:<?php echo esc_attr($tpl['color']); ?>"></div>
              <div class="dc-tpl-card__body">
                <?php if ($tpl['badge']) : ?>
                <span class="dc-tpl-card__badge"><?php echo esc_html($tpl['badge']); ?></span>
                <?php endif; ?>
                <div class="dc-tpl-card__title"><?php echo esc_html($tpl['title']); ?></div>
                <?php if ($tpl['desc']) : ?>
                <div class="dc-tpl-card__desc"><?php echo esc_html($tpl['desc']); ?></div>
                <?php endif; ?>
              </div>
              <div class="dc-tpl-card__footer">
                <a href="<?php echo esc_url($tpl['file']); ?>"
                   class="dc-tpl-card__btn <?php echo $has_file ? '' : 'dc-tpl-card__btn--disabled'; ?>"
                   style="background:<?php echo esc_attr($tpl['color']); ?>"
                   <?php echo $has_file ? 'download' : ''; ?>>
                  <?php echo $has_file ? '⬇ Download Template' : '📋 Coming Soon'; ?>
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /dc-wrap -->

      <!-- Security bar -->
      <div class="dc-security-bar">
        <span>🔒 <strong>SSL Encrypted</strong> — all submissions are encrypted in transit</span>
        <span>🛡️ <strong>Secure Storage</strong> — <?php echo esc_html($support_note); ?></span>
        <span>✉️ Questions? <strong><a href="mailto:<?php echo esc_attr($support_email); ?>" style="color:#F5A623;"><?php echo esc_html($support_email); ?></a></strong></span>
      </div>

    </div><!-- /nab-content -->
  </main>
</div><!-- /nab-portal-wrap -->

<script>
(function(){
  var btn = document.getElementById('nabHamburger');
  var sb  = document.getElementById('nabSidebar');
  var ov  = document.getElementById('nabOverlay');
  if (!btn) return;
  btn.addEventListener('click',  function(){ sb.classList.toggle('nab-open'); ov.classList.toggle('nab-open'); });
  ov.addEventListener('click',   function(){ sb.classList.remove('nab-open'); ov.classList.remove('nab-open'); });
})();
</script>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>
