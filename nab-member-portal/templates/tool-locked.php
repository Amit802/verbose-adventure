<?php
/**
 * Shown instead of a money tool that hasn't opened yet (inc/unlock.php).
 * Tells the member which step comes first, links to it, and shows the
 * whole journey. Uses the portal's normal layout and sidebar.
 *
 * @package NAB_Member_Portal
 * @since   1.23.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$it      = $GLOBALS['nab_locked_tool'] ?? [ 'key' => '', 'label' => 'This tool', 'icon' => 'lock', 'tone' => 'slate' ];
$uid     = get_current_user_id();
$steps   = nab_unlock_steps();
$me      = $steps[ $it['key'] ] ?? [ 'step' => 0, 'after' => '', 'lessons' => 0 ];
$prevKey = $me['after'] ?? '';
$prev    = $steps[ $prevKey ] ?? [ 'label' => 'the tool before', 'task' => 'finish the step before' ];
$prevUrl = nab_unlock_tool_url( $prevKey );
$need    = (int) ( $me['lessons'] ?? 0 );
$have    = min( $need, nab_unlock_lessons_done( $uid ) );
$learn   = nab_resolve_url( nab_get_nav_links()['learning'] ?? '#' );
$open    = nab_unlock_sync( $uid );
$total   = count( $steps );

if ( ! headers_sent() ) header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
do_action( 'litespeed_control_set_nocache', 'Locked tool page depends on the member' );

nab_head_open( $it['label'] . ' | NAB Member Portal' );
?>
<style>
body.nab-locked-page{background:var(--nab-bg)!important;color:var(--nab-text);-webkit-font-smoothing:antialiased}
.nab-locked-page .nab-topbar-title{display:flex;align-items:center;gap:10px}
.lk-wrap{max-width:720px;margin:0 auto;padding:12px 0 40px}
.lk-card{background:var(--nab-surface);border:1px solid var(--nab-line);border-radius:16px;padding:28px 28px 24px;margin-bottom:16px}
.lk-top{display:flex;align-items:center;gap:14px;margin-bottom:14px}
.lk-eyebrow{font-size:12px;font-weight:600;color:var(--nab-text-3);text-transform:uppercase;letter-spacing:.08em}
.lk-title{font-size:24px;line-height:1.2;font-weight:750;letter-spacing:-.02em;margin:2px 0 0}
.lk-text{font-size:15px;line-height:1.6;color:var(--nab-text-2);margin:0 0 18px}
.lk-text b{color:var(--nab-text)}
.lk-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.lk-btn{display:inline-flex;align-items:center;gap:8px;font:inherit;font-size:14px;font-weight:650;padding:12px 18px;border-radius:10px;border:1px solid var(--nab-line);background:#fff;color:var(--nab-text);text-decoration:none}
.lk-btn:hover{background:#F8F9FB}
.lk-btn-primary{background:var(--nab-brand);border-color:var(--nab-brand);color:#fff}
.lk-btn-primary:hover{background:var(--nab-brand-strong);border-color:var(--nab-brand-strong);color:#fff}
.lk-or{font-size:13.5px;color:var(--nab-text-2);margin:16px 0 0;line-height:1.55}
.lk-or a{color:var(--nab-brand);font-weight:650}
.lk-bar{height:6px;border-radius:999px;background:var(--nab-line-soft);overflow:hidden;margin-top:8px;max-width:260px}
.lk-bar span{display:block;height:100%;background:var(--nab-brand);border-radius:999px}
.lk-steps{list-style:none;margin:0;padding:0}
.lk-steps li{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--nab-line-soft)}
.lk-steps li:last-child{border-bottom:0}
.lk-num{flex:0 0 28px;width:28px;height:28px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;background:var(--nab-line-soft);color:var(--nab-text-2)}
.lk-steps li.done .lk-num{background:#E9F7EF;color:#157F4F}
.lk-steps li.here .lk-num{background:var(--nab-brand);color:#fff}
.lk-name{font-size:14.5px;font-weight:650}
.lk-sub{font-size:12.5px;color:var(--nab-text-2);margin-top:2px}
.lk-tag{margin-left:auto;font-size:12px;font-weight:650;color:var(--nab-text-3);display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.lk-tag.ok{color:#157F4F}
.lk-card h2{font-size:16px;font-weight:700;margin:0 0 6px}
@media(max-width:640px){
  .lk-card{padding:20px 16px}
  .lk-title{font-size:21px}
  .lk-actions .lk-btn{width:100%;justify-content:center}
  .nab-locked-page .nab-hamburger{position:absolute}
}
</style>
</head>
<body <?php body_class( 'nab-portal-body nab-locked-page' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( $it['key'] ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title"><?php echo nab_portal_icon_tile( $it['icon'] ?? 'lock', $it['tone'] ?? 'slate', 30 ); ?> <?php echo esc_html( $it['label'] ); ?></div>
      <div class="nab-topbar-right">
        <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="lk-wrap">

        <section class="lk-card">
          <div class="lk-top">
            <?php echo nab_portal_icon_tile( 'lock', 'slate', 44 ); ?>
            <div>
              <div class="lk-eyebrow">Step <?php echo (int) $me['step']; ?> of <?php echo (int) $total; ?></div>
              <h1 class="lk-title"><?php echo esc_html( $it['label'] ); ?> opens next</h1>
            </div>
          </div>
          <p class="lk-text">We open the money tools one at a time so you can build your plan in the right order. To open this one, first <b><?php echo esc_html( lcfirst( $prev['task'] ) ); ?></b> in the <?php echo esc_html( $prev['label'] ); ?>. It only takes a couple of minutes.</p>
          <div class="lk-actions">
            <?php if ( $prevUrl !== '#' ) : ?>
            <a class="lk-btn lk-btn-primary" href="<?php echo esc_url( $prevUrl ); ?>">Go to <?php echo esc_html( $prev['label'] ); ?> <?php echo nab_portal_icon( 'arrow-right', 16 ); ?></a>
            <?php endif; ?>
          </div>
          <?php if ( $need > 0 ) : ?>
          <p class="lk-or">Or finish <?php echo (int) $need; ?> lesson<?php echo $need === 1 ? '' : 's'; ?> in the <?php if ( $learn !== '#' ) : ?><a href="<?php echo esc_url( $learn ); ?>">Learning Center</a><?php else : ?>Learning Center<?php endif; ?>. You've done <?php echo (int) $have; ?> of <?php echo (int) $need; ?>.</p>
          <div class="lk-bar" aria-hidden="true"><span style="width:<?php echo $need ? (int) round( 100 * $have / $need ) : 0; ?>%"></span></div>
          <?php endif; ?>
        </section>

        <section class="lk-card">
          <h2>Your money plan</h2>
          <ol class="lk-steps">
            <?php foreach ( $steps as $k => $s ) :
              $isOpen = ! empty( $open[ $k ] );
              $done   = nab_unlock_step_done( $uid, $k );
              $cls    = $k === $it['key'] ? 'here' : ( $done ? 'done' : '' ); ?>
            <li class="<?php echo esc_attr( $cls ); ?>">
              <span class="lk-num"><?php echo $done ? nab_portal_icon( 'check', 14 ) : (int) $s['step']; ?></span>
              <div>
                <div class="lk-name"><?php echo esc_html( $s['label'] ); ?></div>
                <div class="lk-sub"><?php echo esc_html( $s['task'] ); ?></div>
              </div>
              <span class="lk-tag<?php echo $isOpen ? ' ok' : ''; ?>"><?php echo $isOpen ? ( $done ? 'Done' : 'Open' ) : nab_portal_icon( 'lock', 13 ) . ' Locked'; ?></span>
            </li>
            <?php endforeach; ?>
          </ol>
        </section>

      </div>
    </div>
  </main>
</div>

<?php nab_footer_scripts(); ?>
<?php wp_footer(); ?>
</body>
</html>
