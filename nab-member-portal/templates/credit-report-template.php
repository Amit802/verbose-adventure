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
<title>Credit Report Access | NAB Solutions</title>
<?php wp_head(); ?>
<style>
/* ── Hide Hello Elementor chrome ──────────────────────────────────── */
.site-header,.site-footer,.elementor-location-header,
.elementor-location-footer,#masthead,#colophon { display:none !important; }
body { margin:0 !important; padding:0 !important; background:var(--nab-bg) !important; }
.elementor-page .elementor-section-wrap,
.e-page-settings .elementor-section-wrap { padding:0 !important; }

/* ══ PORTAL LAYOUT ════════════════════════════════════════════════════ */
.nab-portal-wrap { display:flex; min-height:100vh; font-family:'Inter',sans-serif; }

/* Sidebar */
.nab-sidebar {
    width:260px; min-width:260px;
    background:linear-gradient(160deg,var(--nab-brand) 0%,var(--nab-brand-strong) 100%);
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
.nab-topbar-title { font-weight:700; font-size:1rem; color:var(--nab-text); }
.nab-topbar-right { display:flex; align-items:center; gap:12px; }
.nab-status-badge { font-size:.75rem; font-weight:600; padding:4px 12px; border-radius:50px; }
.nab-status-active    { background:#d1fae5; color:#065f46; }
.nab-status-pending   { background:#fef3c7; color:#92400e; }
.nab-status-suspended { background:#fee2e2; color:#991b1b; }
.nab-content { flex:1; overflow-y:auto; }

/* Mobile */
.nab-hamburger {
    display:none; position:fixed; top:14px; left:14px; z-index:200;
    background:var(--nab-brand); color:#fff; border:none;
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
    --brand-light:  var(--nab-bg);
    --text-dark:    #1A1A2E;
    --text-mid:     var(--nab-text-2);
    --radius:       16px;
    --shadow-card:  0 4px 24px rgba(26,60,110,.10);
    --transition:   .25s cubic-bezier(.4,0,.2,1);
}

.cr-note{display:flex;gap:10px;align-items:flex-start;background:var(--nab-brand-soft);color:var(--nab-text-2);border-radius:var(--nab-radius);padding:12px 14px;font-size:13.5px;line-height:1.55;margin:0 0 20px}
.cr-note .nab-i{color:var(--nab-brand);flex-shrink:0;margin-top:1px}
.cr-section{margin:0}
.cr-section__head{margin:0 0 14px}
.cr-section__head h2{font-size:16px;font-weight:700;color:var(--nab-text);margin:0 0 4px}
.cr-section__head p{color:var(--nab-text-2);font-size:13.5px;margin:0}
.cr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.cr-card{background:var(--nab-surface);border:1px solid var(--nab-line);border-radius:var(--nab-radius);box-shadow:var(--nab-shadow);display:flex;flex-direction:column;overflow:hidden;transition:border-color .15s,box-shadow .15s}
.cr-card:hover{border-color:#D0D5DD;box-shadow:0 6px 18px rgba(16,24,40,.06)}
.cr-card__body{padding:20px 20px 14px;flex:1}
.cr-card__logo-wrap{height:40px;display:flex;align-items:center;margin-bottom:14px}
.cr-card__logo{max-height:40px;max-width:120px;object-fit:contain;border-radius:6px}
.cr-card__logo-fallback{display:inline-flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:var(--nab-text)}
.cr-card__logo-fallback::before{content:"";width:10px;height:10px;border-radius:3px;background:var(--card-accent,var(--nab-brand))}
.cr-card__badge{display:inline-flex;align-items:center;gap:5px;background:var(--nab-good-soft);color:var(--nab-good);font-size:12px;font-weight:600;padding:3px 9px;border-radius:999px;margin-bottom:10px}
.cr-card__name{display:flex;align-items:center;gap:8px;font-size:15px;font-weight:700;color:var(--nab-text);margin-bottom:6px}
.cr-card__name::before{content:"";width:8px;height:8px;border-radius:50%;background:var(--card-accent,var(--nab-brand));flex:none}
.cr-card__desc{font-size:13.5px;color:var(--nab-text-2);line-height:1.55;margin:0}
.cr-card__footer{padding:0 20px 20px}
.cr-card__btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;box-sizing:border-box;height:40px;background:var(--nab-brand);color:#fff;font-size:13.5px;font-weight:600;text-decoration:none;border-radius:10px;transition:background .15s}
.cr-card__btn:hover{background:var(--nab-brand-strong);color:#fff}
.cr-infobar{margin-top:20px;color:var(--nab-text-2);font-size:12.5px;line-height:1.6;padding:14px 16px;border:1px solid var(--nab-line);border-radius:var(--nab-radius);background:var(--nab-surface)}
.cr-infobar strong{color:var(--nab-text)}
.cr-infobar a{color:var(--nab-brand);text-decoration:none;font-weight:600}
.cr-infobar a:hover{text-decoration:underline}
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
      <div class="nab-topbar-title"><?php echo nab_portal_icon( 'file-text', 20 ); ?> Credit Report Access</div>
      <div class="nab-topbar-right">
        <span class="nab-status-badge nab-status-<?php echo esc_attr( strtolower( $member_status ) ); ?>">
          <?php echo esc_html( $member_status ); ?>
        </span>
      </div>
    </header>

    <div class="nab-content">

      <section class="nab-page-head">
        <div>
          <div class="nab-page-eyebrow">Credit tools</div>
          <h1 class="nab-page-title"><?php echo esc_html( $hero_title ); ?></h1>
          <p class="nab-page-sub"><?php echo esc_html( $hero_subtitle ); ?></p>
        </div>
      </section>

      <div class="cr-note" role="note"><?php echo nab_portal_icon( 'shield-check', 18 ); ?><span>These are official, secure services that include free access options. Some providers may also offer paid plans or add-ons.</span></div>

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
            <div class="cr-card__body">
              <?php if ( $logo ) : ?>
              <div class="cr-card__logo-wrap">
                <img src="<?php echo $logo; ?>" alt="<?php echo $name; ?> logo" class="cr-card__logo" loading="lazy" onerror="this.parentNode.style.display='none'">
              </div>
              <?php endif; ?>
              <?php if ( $badge ) : ?>
              <span class="cr-card__badge">
                <?php echo nab_portal_icon( 'check', 13 ); ?>
                <?php echo $badge; ?>
              </span>
              <?php endif; ?>
              <div class="cr-card__name"><?php echo $name; ?></div>
              <p class="cr-card__desc"><?php echo $desc; ?></p>
            </div>
            <div class="cr-card__footer">
              <a href="<?php echo $url; ?>" class="cr-card__btn" target="_blank" rel="noopener noreferrer">
                <?php echo $label; ?> <?php echo nab_portal_icon( 'arrow-right', 16 ); ?>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Info bar -->
      <div class="cr-infobar">
        <strong>Note:</strong> All links open third-party websites. NAB Solutions is not affiliated with, endorsed by, or paid by these providers, and we don't control their content, terms or privacy practices.
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