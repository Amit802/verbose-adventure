<?php
/**
 * Template Name: Credit Report Access
 * Description: Members-only credit report page — renders inside NAB dashboard layout
 *
 * Place in: /wp-content/themes/hello-elementor/credit-report-template.php
 */

// Block non-logged-in users
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) );
    exit;
}

// ── Current user info (for sidebar) ──────────────────────────────────────────
$current_user = wp_get_current_user();
$first_name   = $current_user->first_name ?: $current_user->display_name;
$last_name    = $current_user->last_name  ?: '';
$initials     = strtoupper( substr( $first_name, 0, 1 ) . substr( $last_name, 0, 1 ) ) ?: 'M';
$user_id      = $current_user->ID;

// ── Read sidebar links from the Dashboard page ────────────────────────────────
$dashboard_page = get_page_by_path( 'dashboard' ); // adjust slug if yours differs
$dash_id        = $dashboard_page ? $dashboard_page->ID : 0;

function cr_get_dash_field( $name, $dash_id ) {
    if ( ! function_exists( 'get_field' ) || ! $dash_id ) return '#';
    $val = get_field( $name, $dash_id );
    if ( is_array( $val ) && isset( $val['url'] ) ) return esc_url( $val['url'] );
    if ( is_string( $val ) && ! empty( $val ) )     return esc_url( $val );
    return '#';
}

$member_status = function_exists('get_field') ? ( get_field('nab_member_status', $dash_id) ?: 'Active' ) : 'Active';
$url_report    = esc_url( get_permalink() ); // this page = active item
$url_booking   = cr_get_dash_field( 'nab_link_booking',     $dash_id );
$url_dispute   = cr_get_dash_field( 'nab_link_dispute',     $dash_id );
$url_chatbot   = cr_get_dash_field( 'nab_link_chatbot',     $dash_id );
$url_util      = cr_get_dash_field( 'nab_link_utilization', $dash_id );
$url_sim       = cr_get_dash_field( 'nab_link_simulator',   $dash_id );
$url_blog      = cr_get_dash_field( 'nab_link_blog',        $dash_id );
$url_diy       = cr_get_dash_field( 'nab_link_diy',         $dash_id );
$url_learning  = cr_get_dash_field( 'nab_link_learning', $dash_id );
$url_support   = cr_get_dash_field( 'nab_link_support',  $dash_id );
$url_loan      = cr_get_dash_field( 'nab_link_loan',        $dash_id );
$url_card      = cr_get_dash_field( 'nab_link_card_match',        $dash_id );
$dashboard_url = $dash_id ? esc_url( get_permalink( $dash_id ) ) : esc_url( home_url('/dashboard/') );

// ── This page's ACF content fields ───────────────────────────────────────────
$page_id          = get_the_ID();
$hero_title       = function_exists('get_field') ? get_field('hero_title',       $page_id) : '';
$hero_subtitle    = function_exists('get_field') ? get_field('hero_subtitle',    $page_id) : '';
$hero_bg_color    = function_exists('get_field') ? get_field('hero_bg_color',    $page_id) : '';
$section_heading  = function_exists('get_field') ? get_field('section_heading',  $page_id) : '';
$section_subtitle = function_exists('get_field') ? get_field('section_subtitle', $page_id) : '';

$hero_title       = $hero_title       ?: 'Get Your Free Credit Report';
$hero_subtitle    = $hero_subtitle    ?: 'Access your credit report and score through trusted Canadian providers. Services listed below offer a free option; some may also promote paid upgrades.';
$hero_bg_color    = $hero_bg_color    ?: '#1A3C6E';
$section_heading  = $section_heading  ?: 'Choose a Credit Report Provider';
$section_subtitle = $section_subtitle ?: 'All services below offer a way to access your credit report and/or score at no cost. Some may require you to create an account and may promote paid products or services.';

// ── Partner slots ─────────────────────────────────────────────────────────────
$partners_acf = [];
if ( function_exists('get_field') ) {
    for ( $i = 1; $i <= 4; $i++ ) {
        $name = get_field( "partner_{$i}_name", $page_id );
        if ( empty( $name ) ) continue;
        $logo_id        = get_field( "partner_{$i}_logo", $page_id );
        $partners_acf[] = [
            'name'         => $name,
            'logo_url'     => $logo_id ? wp_get_attachment_url( $logo_id ) : '',
            'icon_class'   => get_field( "partner_{$i}_icon_class",  $page_id ) ?: 'dashicons dashicons-external',
            'description'  => get_field( "partner_{$i}_description", $page_id ) ?: '',
            'button_label' => get_field( "partner_{$i}_btn_label",   $page_id ) ?: 'Visit Site',
            'button_url'   => get_field( "partner_{$i}_btn_url",     $page_id ) ?: '#',
            'badge'        => get_field( "partner_{$i}_badge",       $page_id ) ?: '',
            'accent'       => get_field( "partner_{$i}_accent",      $page_id ) ?: '#1A3C6E',
        ];
    }
}

$default_partners = [
    [ 'name' => 'Equifax Canada',     'logo_url' => 'https://www.google.com/s2/favicons?sz=128&domain=equifax.ca',      'icon_class' => 'dashicons dashicons-shield',       'description' => 'Request your free Equifax credit report online or by mail once per year; paid products and subscriptions are also available on their site.',             'button_label' => 'Get Equifax Report',    'button_url' => 'https://www.consumer.equifax.ca/personal/products/credit-score-and-report/', 'badge' => 'Annual Free',    'accent' => '#E8173D' ],
    [ 'name' => 'TransUnion Canada',  'logo_url' => 'https://www.google.com/s2/favicons?sz=128&domain=transunion.ca', 'icon_class' => 'dashicons dashicons-chart-bar',    'description' => 'Create an account to access your TransUnion credit report and score; TransUnion also offers paid monitoring plans that may be advertised.',  'button_label' => 'Get TransUnion Report', 'button_url' => 'https://www.transunion.ca',                                           'badge' => 'Instant Access', 'accent' => '#00529B' ],
    [ 'name' => 'Borrowell',          'logo_url' => 'https://www.google.com/s2/favicons?sz=128&domain=borrowell.com',        'icon_class' => 'dashicons dashicons-calendar-alt', 'description' => 'Sign up for a free Borrowell account to see your Equifax credit score updated weekly; Borrowell may recommend financial products based on your profile.',             'button_label' => 'Visit Borrowell',       'button_url' => 'https://www.borrowell.com',                                           'badge' => 'Weekly Updates', 'accent' => '#4CAF50' ],
    [ 'name' => 'CreditKarma Canada', 'logo_url' => 'https://www.google.com/s2/favicons?sz=128&domain=creditkarma.ca', 'icon_class' => 'dashicons dashicons-star-filled', 'description' => 'Get free access to your TransUnion score and credit report, updated weekly; Credit Karma may recommend credit products based on your information.',             'button_label' => 'Visit Credit Karma',    'button_url' => 'https://www.creditkarma.ca',                                          'badge' => 'Weekly Updates', 'accent' => '#43A857' ],
];

$partners = ! empty( $partners_acf ) ? $partners_acf : $default_partners;

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Credit Report Access — NAB Solutions</title>
<?php wp_head(); ?>
<style>
/* ── Hide Hello Elementor chrome ──────────────────────────────────── */
.site-header,.site-footer,.elementor-location-header,
.elementor-location-footer,#masthead,#colophon { display:none !important; }
body { margin:0 !important; padding:0 !important; background:#F0F4FA !important; }
.elementor-page .elementor-section-wrap,
.e-page-settings .elementor-section-wrap { padding:0 !important; }

/* ══ PORTAL LAYOUT ════════════════════════════════════════════════════ */
.nab-portal-wrap { display:flex; min-height:100vh; font-family:'Inter',sans-serif; }

/* Sidebar */
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
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; border-radius:10px;
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
    width:34px; height:34px; border-radius:50%;
    background:#F5A623; color:#fff; font-weight:700; font-size:.85rem;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.nab-member-info { flex:1; min-width:0; }
.nab-member-name { color:#fff; font-size:.82rem; font-weight:600; }
.nab-member-role { color:rgba(255,255,255,.5); font-size:.72rem; }
.nab-logout-btn { color:rgba(255,255,255,.5); font-size:1.1rem; text-decoration:none; transition:color .2s; }
.nab-logout-btn:hover { color:#F5A623; }

/* Main */
.nab-main { flex:1; min-width:0; display:flex; flex-direction:column; }
.nab-topbar {
    display:flex; align-items:center; justify-content:space-between;
    padding:0 28px; height:60px;
    background:#fff; border-bottom:1px solid #e8edf5;
    position:sticky; top:0; z-index:50;
}
.nab-topbar-title { font-weight:700; font-size:1rem; color:#1a202c; }
.nab-topbar-right { display:flex; align-items:center; gap:12px; }
.nab-status-badge { font-size:.75rem; font-weight:600; padding:4px 12px; border-radius:50px; }
.nab-status-active    { background:#d1fae5; color:#065f46; }
.nab-status-pending   { background:#fef3c7; color:#92400e; }
.nab-status-suspended { background:#fee2e2; color:#991b1b; }
.nab-content { flex:1; overflow-y:auto; }

/* Mobile */
.nab-hamburger {
    display:none; position:fixed; top:14px; left:14px; z-index:200;
    background:#0D5C9B; color:#fff; border:none;
    width:40px; height:40px; border-radius:10px;
    font-size:1.2rem; cursor:pointer; align-items:center; justify-content:center;
}
.nab-sidebar-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.5); z-index:99;
}
@media (max-width:768px) {
    .nab-hamburger { display:flex; }
    .nab-sidebar { position:fixed; top:0; left:-270px; height:100vh; transition:left .3s ease; z-index:150; }
    .nab-sidebar.nab-open { left:0; }
    .nab-sidebar-overlay.nab-open { display:block; }
}

/* ══ CREDIT REPORT PAGE STYLES ════════════════════════════════════════ */
:root {
    --brand-dark:   <?php echo esc_attr( $hero_bg_color ); ?>;
    --brand-orange: #F07120;
    --brand-light:  #EEF3FA;
    --text-dark:    #1A1A2E;
    --text-mid:     #4A5568;
    --radius:       16px;
    --shadow-card:  0 4px 24px rgba(26,60,110,.10);
    --transition:   .25s cubic-bezier(.4,0,.2,1);
}

.cr-hero {
    background:var(--brand-dark); padding:52px 32px 60px;
    text-align:center; position:relative; overflow:hidden;
}
.cr-hero::before {
    content:''; position:absolute; inset:0; pointer-events:none;
    background:
        radial-gradient(ellipse 80% 60% at 20% 50%,rgba(240,113,32,.18) 0%,transparent 60%),
        radial-gradient(ellipse 60% 80% at 80% 40%,rgba(255,255,255,.06) 0%,transparent 55%);
}
.cr-hero__eyebrow {
    display:inline-flex; align-items:center; gap:8px;
    background:rgba(240,113,32,.18); border:1px solid rgba(240,113,32,.4);
    color:#FFB87A; font-size:.75rem; font-weight:700;
    letter-spacing:.1em; text-transform:uppercase;
    padding:6px 16px; border-radius:50px; margin-bottom:18px;
}
.cr-hero__title {
    font-size:clamp(1.6rem,4vw,2.6rem); font-weight:800;
    color:#fff; line-height:1.15; margin-bottom:14px;
}
.cr-hero__sub {
    font-size:1rem; color:rgba(255,255,255,.75);
    max-width:520px; margin:0 auto 24px; line-height:1.7;
}
.cr-hero__lock {
    display:inline-flex; align-items:center; gap:8px;
    background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15);
    color:rgba(255,255,255,.7); font-size:.82rem; padding:7px 16px; border-radius:50px;
}

.cr-section { max-width:1060px; margin:0 auto; padding:48px 28px 64px; }
.cr-section__head { text-align:center; margin-bottom:40px; }
.cr-section__head h2 { font-size:clamp(1.3rem,2.5vw,1.9rem); font-weight:700; color:var(--text-dark); margin-bottom:8px; }
.cr-section__head p  { color:var(--text-mid); font-size:.95rem; }

.cr-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:24px; }

.cr-card {
    background:#fff; border-radius:var(--radius); box-shadow:var(--shadow-card);
    display:flex; flex-direction:column; overflow:hidden;
    transition:transform var(--transition),box-shadow var(--transition);
}
.cr-card:hover { transform:translateY(-5px); box-shadow:0 12px 36px rgba(26,60,110,.15); }
.cr-card__accent { height:5px; background:var(--card-accent,var(--brand-dark)); }
.cr-card__body { padding:24px 24px 16px; flex:1; }
.cr-card__logo-wrap { height:48px; display:flex; align-items:center; margin-bottom:16px; }
.cr-card__logo { max-height:48px; max-width:48px; object-fit:contain; border-radius:8px; }
.cr-card__logo-fallback { font-size:1.1rem; font-weight:700; color:var(--card-accent,var(--brand-dark)); }
.cr-card__badge {
    display:inline-flex; align-items:center; gap:5px;
    background:var(--brand-light); color:var(--brand-dark);
    font-size:.7rem; font-weight:600; letter-spacing:.04em;
    padding:3px 10px; border-radius:50px; margin-bottom:12px;
}
.cr-card__name { font-size:1rem; font-weight:700; color:var(--text-dark); margin-bottom:8px; }
.cr-card__desc { font-size:.875rem; color:var(--text-mid); line-height:1.6; }
.cr-card__footer { padding:0 24px 24px; }
.cr-card__btn {
    display:flex; align-items:center; justify-content:center; gap:8px;
    width:100%; background:var(--card-accent,var(--brand-dark)); color:#fff;
    font-size:.85rem; font-weight:600; text-decoration:none;
    padding:12px 18px; border-radius:10px;
    transition:filter var(--transition),transform var(--transition);
}
.cr-card__btn:hover { filter:brightness(1.1); transform:scale(1.02); color:#fff; }

.cr-infobar {
    background:var(--brand-dark); color:rgba(255,255,255,.75);
    text-align:center; padding:18px 24px; font-size:.85rem;
}
.cr-infobar strong { color:#fff; }
.cr-infobar a { color:#F07120; text-decoration:none; }
.cr-infobar a:hover { text-decoration:underline; }
</style>
</head>
<body <?php body_class('nab-portal-body'); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">

  <!-- ── SIDEBAR ── now rendered via shared nab_render_sidebar() so all
       templates stay in sync automatically (was hardcoded before) ── -->
  <?php nab_render_sidebar( 'report' ); ?>

  <!-- ── MAIN ─────────────────────────────────────────────────── -->
  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title">Credit Report Access</div>
      <div class="nab-topbar-right">
        <span class="nab-status-badge nab-status-<?php echo esc_attr( strtolower( $member_status ) ); ?>">
          ● <?php echo esc_html( $member_status ); ?>
        </span>
      </div>
    </header>

    <div class="nab-content">

      <!-- Hero Banner -->
      <section class="cr-hero">
        <div class="cr-hero__eyebrow">
          <span class="dashicons dashicons-lock"></span>
          Members Only Resource
        </div>
        <h1 class="cr-hero__title"><?php echo esc_html( $hero_title ); ?></h1>
        <p class="cr-hero__sub"><?php echo esc_html( $hero_subtitle ); ?></p>
        <div class="cr-hero__lock">
          <span class="dashicons dashicons-shield-alt"></span>
          These are official, secure services that include free access options. Some providers may offer or advertise paid plans or add-ons.
        </div>
      </section>

      <!-- Partner Cards -->
      <div class="cr-section">
        <div class="cr-section__head">
          <h2><?php echo esc_html( $section_heading ); ?></h2>
          <p><?php echo esc_html( $section_subtitle ); ?></p>
        </div>
        <div class="cr-grid">
          <?php foreach ( $partners as $p ) :
            $accent = esc_attr( $p['accent'] ?? '#1A3C6E' );
            $name   = esc_html( $p['name'] );
            $desc   = esc_html( $p['description'] );
            $label  = esc_html( $p['button_label'] );
            $url    = esc_url( $p['button_url'] );
            $badge  = esc_html( $p['badge'] );
            $logo   = esc_url( $p['logo_url'] );
            $icon   = esc_attr( $p['icon_class'] );
          ?>
          <div class="cr-card" style="--card-accent:<?php echo $accent; ?>">
            <div class="cr-card__accent"></div>
            <div class="cr-card__body">
              <div class="cr-card__logo-wrap">
                <?php if ( $logo ) : ?>
                  <img src="<?php echo $logo; ?>" alt="<?php echo $name; ?> logo" class="cr-card__logo" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                  <span class="cr-card__logo-fallback" style="display:none"><?php echo $name; ?></span>
                <?php else : ?>
                  <span class="cr-card__logo-fallback"><?php echo $name; ?></span>
                <?php endif; ?>
              </div>
              <?php if ( $badge ) : ?>
              <span class="cr-card__badge">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo $badge; ?>
              </span>
              <?php endif; ?>
              <div class="cr-card__name"><?php echo $name; ?></div>
              <p class="cr-card__desc"><?php echo $desc; ?></p>
            </div>
            <div class="cr-card__footer">
              <a href="<?php echo $url; ?>" class="cr-card__btn" target="_blank" rel="noopener noreferrer">
                <span class="<?php echo $icon; ?>"></span>
                <?php echo $label; ?>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Info bar -->
      <div class="cr-infobar">
        <strong>Note:</strong> All links open third&#8209;party websites. NAB Solutions is not affiliated with, endorsed by, or compensated by these providers. We do not control their content, terms, or privacy practices. For questions about this resource page, contact us.
        Questions? <a href="<?php echo esc_url( home_url('/contact') ); ?>">Contact us</a>.
      </div>

    </div><!-- /nab-content -->
  </main>
</div><!-- /nab-portal-wrap -->

<script>
(function(){
  var btn     = document.getElementById('nabHamburger');
  var sidebar = document.getElementById('nabSidebar');
  var overlay = document.getElementById('nabOverlay');
  if (!btn) return;
  btn.addEventListener('click', function(){
    sidebar.classList.toggle('nab-open');
    overlay.classList.toggle('nab-open');
  });
  overlay.addEventListener('click', function(){
    sidebar.classList.remove('nab-open');
    overlay.classList.remove('nab-open');
  });
})();
</script>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>