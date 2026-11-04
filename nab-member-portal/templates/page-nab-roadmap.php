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

nab_head_open( 'Financial Roadmap | NAB Member Portal' );
?>
<style>
.nab-rm-wrap{max-width:820px;margin:0 auto;padding-bottom:40px}

.nab-rm-progress-card{background:var(--nab-surface);border:1px solid var(--nab-line);border-radius:var(--nab-radius);padding:24px 28px;box-shadow:var(--nab-shadow);margin-bottom:20px}
.nab-rm-progress-top{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:10px;flex-wrap:wrap;gap:6px}
.nab-rm-progress-label{font-size:14px;font-weight:700;color:var(--nab-text)}
.nab-rm-progress-count{font-size:13px;color:var(--nab-text-2);font-weight:600}
.nab-rm-progress-track{background:var(--nab-line-soft);border-radius:999px;height:16px;overflow:hidden}
.nab-rm-progress-fill{background:var(--nab-brand);height:100%;border-radius:999px;transition:width .4s ease}

.nab-rm-next{margin-top:16px;background:var(--nab-brand-soft);border:0;border-radius:12px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.nab-rm-next-txt{font-size:13px;color:var(--nab-text-2)}
.nab-rm-next-txt strong{display:block;font-size:14px;color:#1e1b4b;margin-bottom:2px}
.nab-rm-next-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;background:var(--nab-brand);color:#fff;border:none;border-radius:10px;padding:9px 16px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap}
.nab-rm-next-btn:hover{background:var(--nab-brand-strong);color:#fff}
.nab-rm-complete{margin-top:16px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;padding:16px 18px;text-align:center;color:#166534;font-size:13.5px;font-weight:600}

.nab-rm-list{display:flex;flex-direction:column;gap:12px}
.nab-rm-row{background:var(--nab-surface);border:1px solid var(--nab-line);border-radius:var(--nab-radius);padding:16px 18px;box-shadow:var(--nab-shadow);display:flex;align-items:center;gap:14px;text-decoration:none;transition:.15s}
.nab-rm-row:hover{box-shadow:0 6px 18px rgba(16,24,40,.06);border-color:#D0D5DD}
.nab-rm-row.done{opacity:.7}
.nab-rm-check{width:34px;height:34px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:16px;background:var(--nab-line-soft);color:var(--nab-text-3)}
.nab-rm-row.done .nab-rm-check{background:var(--nab-good-soft);color:var(--nab-good)}
.nab-rm-body{flex:1;min-width:0}
.nab-rm-title{font-size:14px;font-weight:700;color:var(--nab-text);margin-bottom:2px}
.nab-rm-row.done .nab-rm-title{text-decoration:line-through;text-decoration-color:var(--nab-text-3)}
.nab-rm-desc{font-size:12.5px;color:var(--nab-text-2);line-height:1.4}
.nab-rm-arrow{display:flex;color:var(--nab-text-3);flex-shrink:0;margin-left:auto}
.nab-rm-row.done .nab-rm-arrow{visibility:hidden}
.nab-rm-icon{flex-shrink:0}

@media(max-width:640px){
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
      <div class="nab-topbar-title"><?php echo nab_portal_icon( 'map', 20 ); ?> Financial Roadmap</div>
      <div class="nab-topbar-right">
        <?php nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="nab-rm-wrap">

        <section class="nab-page-head">
          <div>
            <div class="nab-page-eyebrow">Your progress</div>
            <h1 class="nab-page-title"><?php echo esc_html( $hero_title ); ?></h1>
            <p class="nab-page-sub"><?php echo esc_html( $hero_sub ); ?></p>
          </div>
        </section>

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
              Go now <?php echo nab_portal_icon( 'arrow-right', 16 ); ?>
            </a>
          </div>
          <?php else : ?>
          <div class="nab-rm-complete">You've completed every step on your roadmap right now, nice work!</div>
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
            <span class="nab-rm-check"><?php echo $s['done'] ? nab_portal_icon( 'check', 16 ) : ''; ?></span>
            <?php echo nab_portal_icon_tile( $s['icon'], $s['done'] ? 'green' : 'blue', 36, 'nab-rm-icon' ); ?>
            <span class="nab-rm-body">
              <span class="nab-rm-title"><?php echo esc_html( $s['title'] ); ?></span>
              <span class="nab-rm-desc"><?php echo esc_html( $s['desc'] ); ?></span>
            </span>
            <span class="nab-rm-arrow"><?php echo nab_portal_icon( 'chevron-right', 18 ); ?></span>
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
