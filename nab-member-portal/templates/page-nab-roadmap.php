<?php
/**
 * Template Name: NAB Financial Roadmap
 * Personalised checklist built from real portal data — nothing here is
 * stored separately, so a step's "done" state can never drift out of
 * sync with what the member has actually done. See nab_get_roadmap_steps()
 * and nab_get_roadmap_progress() in inc/helpers.php for the logic.
 *
 * Card Matcher and PAD Agreement are intentionally not included as
 * steps yet — neither tool currently saves a completion state to check
 * against. See the comment above nab_get_roadmap_steps() for detail.
 *
 * @package NAB_Member_Portal
 * @since   1.7.7
 *
 * v1.7.9: Added the same no-cache directive used on the Learning Center
 * and Dashboard pages — this page also computes live per-member state
 * on every load, so it must never be served from cache.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
}
do_action( 'litespeed_control_set_nocache', 'Financial Roadmap shows live, per-member checklist state' );

$user  = wp_get_current_user();
$uid   = $user->ID;
$first_name = trim( $user->first_name ) ?: explode( ' ', trim( $user->display_name ) )[0];

$roadmap  = nab_get_roadmap_progress( $uid );
$steps    = $roadmap['steps'];
$percent  = $roadmap['percent'];
$next     = $roadmap['next'];

$hero_title = get_field( 'roadmap_hero_title' ) ?: 'Your Financial Roadmap';
$hero_sub   = get_field( 'roadmap_hero_sub' ) ?: "A personalised checklist built from what you've already done in your portal. Complete a step and it checks off automatically.";

$notifications = function_exists( 'nab_get_user_notifications' ) ? nab_get_user_notifications( $uid ) : [];

nab_head_open( 'Financial Roadmap — NAB Member Portal' );
?>
<style>
.nab-rm-wrap{max-width:820px;margin:0 auto;padding-bottom:40px}
.nab-rm-hero{background:linear-gradient(135deg,#4338ca,#3730a3);border-radius:16px;padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:24px;flex-wrap:wrap}
.nab-rm-hero-icon{font-size:44px;flex-shrink:0}
.nab-rm-hero-title{font-size:20px;font-weight:800;margin:0 0 4px}
.nab-rm-hero-sub{font-size:13px;opacity:.85;margin:0;line-height:1.5;max-width:520px}

.nab-rm-progress-card{background:#fff;border-radius:16px;padding:24px 28px;box-shadow:0 2px 16px rgba(0,0,0,.07);margin-bottom:20px}
.nab-rm-progress-top{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px;flex-wrap:wrap;gap:6px}
.nab-rm-progress-label{font-size:14px;font-weight:700;color:#1e293b}
.nab-rm-progress-count{font-size:13px;color:#64748b;font-weight:600}
.nab-rm-progress-track{background:#f1f5f9;border-radius:999px;height:16px;overflow:hidden}
.nab-rm-progress-fill{background:linear-gradient(90deg,#4338ca,#6366f1);height:100%;border-radius:999px;transition:width .4s ease}

.nab-rm-next{margin-top:16px;background:#eef2ff;border:1.5px solid #c7d2fe;border-radius:12px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.nab-rm-next-txt{font-size:13px;color:#3730a3}
.nab-rm-next-txt strong{display:block;font-size:14px;color:#1e1b4b;margin-bottom:2px}
.nab-rm-next-btn{background:#4338ca;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-size:12.5px;font-weight:700;text-decoration:none;white-space:nowrap}
.nab-rm-next-btn:hover{background:#3730a3}
.nab-rm-complete{margin-top:16px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;padding:16px 18px;text-align:center;color:#166534;font-size:13.5px;font-weight:600}

.nab-rm-list{display:flex;flex-direction:column;gap:12px}
.nab-rm-row{background:#fff;border-radius:14px;padding:16px 18px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;gap:14px;text-decoration:none;transition:.15s;border:1.5px solid transparent}
.nab-rm-row:hover{box-shadow:0 4px 14px rgba(0,0,0,.09);border-color:#e0e7ff}
.nab-rm-row.done{opacity:.7}
.nab-rm-check{width:34px;height:34px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:16px;background:#f1f5f9;color:#94a3b8}
.nab-rm-row.done .nab-rm-check{background:#dcfce7;color:#16a34a}
.nab-rm-body{flex:1;min-width:0}
.nab-rm-title{font-size:14px;font-weight:700;color:#1e293b;margin-bottom:2px}
.nab-rm-row.done .nab-rm-title{text-decoration:line-through;text-decoration-color:#cbd5e1}
.nab-rm-desc{font-size:12.5px;color:#64748b;line-height:1.4}
.nab-rm-arrow{font-size:16px;color:#94a3b8;flex-shrink:0}
.nab-rm-row.done .nab-rm-arrow{visibility:hidden}
.nab-rm-icon{font-size:20px;flex-shrink:0}

@media(max-width:640px){
  .nab-rm-hero{padding:20px}
  .nab-rm-row{padding:14px}
  .nab-rm-desc{display:none}
  .nab-rm-next{flex-direction:column;align-items:stretch}
  .nab-rm-next-btn{text-align:center}
}
</style>
</head>
<body <?php body_class( 'nab-portal-body' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( 'roadmap' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title">🗺 Financial Roadmap</div>
      <div class="nab-topbar-right">
        <?php nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="nab-rm-wrap">

        <div class="nab-rm-hero">
          <div class="nab-rm-hero-icon">🗺</div>
          <div>
            <div class="nab-rm-hero-title"><?php echo esc_html( $hero_title ); ?></div>
            <p class="nab-rm-hero-sub"><?php echo esc_html( $hero_sub ); ?></p>
          </div>
        </div>

        <div class="nab-rm-progress-card">
          <div class="nab-rm-progress-top">
            <span class="nab-rm-progress-label">Your Progress</span>
            <span class="nab-rm-progress-count"><?php echo (int) $roadmap['done']; ?> of <?php echo (int) $roadmap['total']; ?> steps complete</span>
          </div>
          <div class="nab-rm-progress-track">
            <div class="nab-rm-progress-fill" style="width:<?php echo (int) $percent; ?>%"></div>
          </div>

          <?php if ( $next ) : ?>
          <div class="nab-rm-next">
            <div class="nab-rm-next-txt">
              <strong>Next step: <?php echo esc_html( $next['title'] ); ?></strong>
              <?php echo esc_html( $next['desc'] ); ?>
            </div>
            <?php $next_url = $next['link']; $next_dis = ( $next_url === '#' ); ?>
            <a class="nab-rm-next-btn" href="<?php echo $next_dis ? 'javascript:void(0)' : esc_url( $next_url ); ?>" <?php echo $next_dis ? 'aria-disabled="true"' : ''; ?>>
              <?php echo $next['icon']; ?> Go now →
            </a>
          </div>
          <?php else : ?>
          <div class="nab-rm-complete">🏆 You've completed every step on your roadmap right now — nice work!</div>
          <?php endif; ?>
        </div>

        <div class="nab-rm-list">
          <?php foreach ( $steps as $s ) :
            $url = $s['link'];
            $dis = ( $url === '#' );
          ?>
          <a class="nab-rm-row<?php echo $s['done'] ? ' done' : ''; ?>"
             href="<?php echo $dis ? 'javascript:void(0)' : esc_url( $url ); ?>"
             <?php echo $dis ? 'aria-disabled="true" style="cursor:default"' : ''; ?>>
            <span class="nab-rm-check"><?php echo $s['done'] ? '✓' : ''; ?></span>
            <span class="nab-rm-icon"><?php echo $s['icon']; ?></span>
            <span class="nab-rm-body">
              <span class="nab-rm-title"><?php echo esc_html( $s['title'] ); ?></span>
              <span class="nab-rm-desc"><?php echo esc_html( $s['desc'] ); ?></span>
            </span>
            <span class="nab-rm-arrow">→</span>
          </a>
          <?php endforeach; ?>
        </div>

      </div>
    </div>
  </main>
</div>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>
