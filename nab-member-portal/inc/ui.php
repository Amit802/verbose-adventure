<?php
/**
 * NAB Member Portal — shared look (v1.15.0)
 *
 * One palette and one set of base styles for every portal page, printed in
 * <head> on each registered tool (inc/tools.php). Pages keep their own
 * layout CSS but take their colours from these tokens, so a colour change
 * is made here once (roadmap item 17).
 *
 * Tokens (CSS custom properties on :root):
 *   --nab-bg / --nab-surface / --nab-surface-2   page, cards, quiet fills
 *   --nab-line / --nab-line-soft                  borders and dividers
 *   --nab-text / --nab-text-2 / --nab-text-3      ink, secondary, faint
 *   --nab-brand / --nab-brand-strong / --nab-brand-soft   NAB blue
 *   --nab-accent / --nab-accent-soft              NAB orange, used sparingly
 *   --nab-good / --nab-warn / --nab-bad (+ -soft)  status colours
 *   --nab-radius / --nab-radius-sm, --nab-shadow / --nab-shadow-lg, --nab-font
 *
 * Shared pieces for page content:
 *   .nab-page-head  (.nab-page-eyebrow, .nab-page-title, .nab-page-sub, .nab-page-actions)
 *   .nab-panel      white card with a hairline border
 *   .nab-ui-btn, .nab-ui-btn-primary
 *
 * The left sidebar is styled in nab_portal_head_css() (inc/helpers.php) and
 * is deliberately left as it is.
 *
 * @package NAB_Member_Portal
 * @since   1.15.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function nab_portal_ui_css() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    ?>
<style id="nab-ui-css">
:root{
  --nab-bg:#F6F7F9;--nab-surface:#fff;--nab-surface-2:#F9FAFB;
  --nab-line:#E4E7EC;--nab-line-soft:#F0F2F5;
  --nab-text:#101828;--nab-text-2:#475467;--nab-text-3:#98A2B3;
  --nab-brand:#0D5C9B;--nab-brand-strong:#0A4A7C;--nab-brand-soft:#EAF2FA;
  --nab-accent:#E8890C;--nab-accent-soft:#FEF4E6;
  --nab-good:#16805A;--nab-good-soft:#E8F5EF;
  --nab-warn:#B25E09;--nab-warn-soft:#FEF4E6;
  --nab-bad:#C93A32;--nab-bad-soft:#FDEEED;
  --nab-radius:12px;--nab-radius-sm:8px;
  --nab-shadow:0 1px 2px rgba(16,24,40,.04);
  --nab-shadow-lg:0 12px 32px rgba(16,24,40,.10);
  --nab-font:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
}
html body:is(.nab-portal-body,.nab-portal-page){background:var(--nab-bg)!important;color:var(--nab-text);font-family:var(--nab-font);-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;text-rendering:optimizeLegibility}
body:is(.nab-portal-body,.nab-portal-page) :is(button,input,select,textarea){font-family:inherit}
body:is(.nab-portal-body,.nab-portal-page) .nab-portal-wrap{font-family:var(--nab-font)}

/* Top bar */
body:is(.nab-portal-body,.nab-portal-page) .nab-topbar{box-sizing:border-box;min-height:64px;padding:12px 32px;gap:16px;background:rgba(255,255,255,.94);-webkit-backdrop-filter:saturate(1.4) blur(8px);backdrop-filter:saturate(1.4) blur(8px);border-bottom:1px solid var(--nab-line);box-shadow:none}
body:is(.nab-portal-body,.nab-portal-page) .nab-topbar-title{display:flex;align-items:center;gap:10px;font-size:16px;font-weight:650;letter-spacing:-.01em;color:var(--nab-text);white-space:nowrap;min-width:0}
body:is(.nab-portal-body,.nab-portal-page) .nab-topbar-title .nab-i{color:var(--nab-brand)}
body:is(.nab-portal-body,.nab-portal-page) .nab-topbar-right{display:flex;align-items:center;gap:10px}
body:is(.nab-portal-body,.nab-portal-page) .nab-bell-btn{width:40px;height:40px;border-radius:10px;background:var(--nab-surface);border:1px solid var(--nab-line);color:var(--nab-text-2);box-shadow:var(--nab-shadow)}
body:is(.nab-portal-body,.nab-portal-page) .nab-bell-btn:hover{background:var(--nab-surface-2);border-color:#D0D5DD}
body:is(.nab-portal-body,.nab-portal-page) .nab-notif-drop{border-color:var(--nab-line);border-radius:var(--nab-radius);box-shadow:var(--nab-shadow-lg)}
body:is(.nab-portal-body,.nab-portal-page) .nab-status-badge{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:5px 10px;border-radius:999px;background:var(--nab-good-soft);color:var(--nab-good);border:0}
body:is(.nab-portal-body,.nab-portal-page) .nab-status-suspended{background:var(--nab-bad-soft);color:var(--nab-bad)}
body:is(.nab-portal-body,.nab-portal-page) .nab-status-pending{background:var(--nab-warn-soft);color:var(--nab-warn)}

/* Search (dashboard top bar) */
body:is(.nab-portal-body,.nab-portal-page) .nab-search-wrap{position:relative;flex:1;max-width:460px;min-width:0;margin:0}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-input{display:block;width:100%;box-sizing:border-box;height:40px;margin:0;padding:0 46px 0 40px;border:1px solid var(--nab-line);border-radius:10px;background:var(--nab-surface-2);font-size:14px;line-height:40px;color:var(--nab-text);box-shadow:none;outline:none;transition:border-color .15s,box-shadow .15s,background .15s}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-input::placeholder{color:var(--nab-text-3);opacity:1}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-input:hover{border-color:#D0D5DD}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-input:focus{background:var(--nab-surface);border-color:var(--nab-brand);box-shadow:0 0 0 3px rgba(13,92,155,.12)}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-input::-webkit-search-cancel-button{display:none}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);display:flex;align-items:center;color:var(--nab-text-3);pointer-events:none;font-size:0;line-height:0}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-kbd{position:absolute;right:10px;top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;box-sizing:border-box;border:1px solid var(--nab-line);border-bottom-width:2px;border-radius:6px;background:var(--nab-surface);font-size:11.5px;font-weight:600;color:var(--nab-text-3);pointer-events:none;font-family:inherit}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-wrap:focus-within .nab-search-kbd{display:none}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-results{top:calc(100% + 8px);border:1px solid var(--nab-line);border-radius:var(--nab-radius);box-shadow:var(--nab-shadow-lg);padding:6px}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-result-item{border:0;border-radius:8px;padding:10px 10px}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-result-item:hover,body:is(.nab-portal-body,.nab-portal-page) .nab-search-result-item.active{background:var(--nab-surface-2)}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-result-title{font-size:13.5px;font-weight:600;color:var(--nab-text)}
body:is(.nab-portal-body,.nab-portal-page) .nab-search-result-desc{font-size:12px;color:var(--nab-text-2)}

/* Page content */
body:is(.nab-portal-body,.nab-portal-page) .nab-content{padding:28px 32px 56px;background:transparent}
.nab-page-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin:0 0 20px}
.nab-page-eyebrow{font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--nab-text-3);margin:0 0 6px}
.nab-page-title{font-size:26px;line-height:1.2;font-weight:700;letter-spacing:-.02em;color:var(--nab-text);margin:0 0 6px}
.nab-page-sub{font-size:14.5px;line-height:1.55;color:var(--nab-text-2);margin:0;max-width:640px}
.nab-page-actions{display:flex;gap:8px;flex-wrap:wrap}
.nab-page-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.nab-chip{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:5px 10px;border-radius:999px;background:var(--nab-surface);border:1px solid var(--nab-line);color:var(--nab-text-2)}
.nab-chip .nab-i{color:var(--nab-text-3)}
.nab-chip-good{background:var(--nab-good-soft);border-color:transparent;color:var(--nab-good)}
.nab-panel{background:var(--nab-surface);border:1px solid var(--nab-line);border-radius:var(--nab-radius);box-shadow:var(--nab-shadow)}
.nab-ui-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;box-sizing:border-box;height:38px;padding:0 14px;border-radius:10px;border:1px solid var(--nab-line);background:var(--nab-surface);color:var(--nab-text);font:inherit;font-size:13.5px;font-weight:600;line-height:1;text-decoration:none;white-space:nowrap;cursor:pointer;transition:background .15s,border-color .15s}
.nab-ui-btn:hover{background:var(--nab-surface-2);border-color:#D0D5DD;color:var(--nab-text)}
.nab-ui-btn-primary{background:var(--nab-brand);border-color:var(--nab-brand);color:#fff}
.nab-ui-btn-primary:hover{background:var(--nab-brand-strong);border-color:var(--nab-brand-strong);color:#fff}

/* Focus ring everywhere in the content */
body:is(.nab-portal-body,.nab-portal-page) .nab-content :is(a,button,input,select,textarea):focus-visible{outline:2px solid var(--nab-brand);outline-offset:2px}

@media(max-width:768px){
  body:is(.nab-portal-body,.nab-portal-page) .nab-topbar{min-height:60px;padding:10px 14px 10px 66px}
  body:is(.nab-portal-body,.nab-portal-page) .nab-content{padding:18px 16px 40px}
  body:is(.nab-portal-body,.nab-portal-page) .nab-topbar{flex-wrap:wrap;row-gap:10px}
  body:is(.nab-portal-body,.nab-portal-page) .nab-topbar-title{flex:1;overflow:hidden;text-overflow:ellipsis}
  body:is(.nab-portal-body,.nab-portal-page) .nab-search-wrap{order:3;flex:1 1 100%;max-width:none;margin:0}
  body:is(.nab-portal-body,.nab-portal-page) .nab-search-kbd{display:none}
  body:is(.nab-portal-body,.nab-portal-page) .nab-search-input{font-size:16px} /* stops iOS zooming into the field */
  .nab-page-title{font-size:22px}
  .nab-page-sub{font-size:14px}
}
</style>
    <?php
}
add_action( 'wp_head', function() {
    if ( function_exists( 'nab_portal_current_tool' ) && nab_portal_current_tool() ) nab_portal_ui_css();
}, 15 );
