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
 *   --nab-radius / --nab-radius-sm, --nab-shadow / --nab-shadow-hover / --nab-shadow-lg, --nab-font
 *
 * v1.16.0: colours follow the NAB logo (navy blue + orange accent), type is
 * Plus Jakarta Sans (bundled in assets/fonts, SIL Open Font License, no CDN),
 * and every input, select, button and card in the content area shares one
 * style, with a short entrance animation (off for prefers-reduced-motion).
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
<link rel="preload" href="<?php echo esc_url( NAB_URL . 'assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2' ); ?>" as="font" type="font/woff2" crossorigin>
<style id="nab-ui-css">
@font-face{font-family:"Plus Jakarta Sans";font-style:normal;font-weight:200 800;font-display:swap;src:url("<?php echo esc_url( NAB_URL . 'assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2' ); ?>") format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD}
:root{
  --nab-bg:#F3F5F8;--nab-surface:#fff;--nab-surface-2:#F8FAFC;
  --nab-line:#E3E8EF;--nab-line-soft:#EEF2F6;--nab-line-strong:#CDD5DF;
  --nab-text:#0E1B2C;--nab-text-2:#4A5A6E;--nab-text-3:#8A97A8;
  --nab-brand:#0D5C9B;--nab-brand-strong:#0A4A7C;--nab-brand-soft:#EAF2FA;--nab-brand-ring:rgba(13,92,155,.16);
  --nab-accent:#EE6C1C;--nab-accent-strong:#D25A0F;--nab-accent-soft:#FEF0E6;
  --nab-good:#16805A;--nab-good-soft:#E8F5EF;
  --nab-warn:#B25E09;--nab-warn-soft:#FEF4E6;
  --nab-bad:#C93A32;--nab-bad-soft:#FDEEED;
  --nab-radius:12px;--nab-radius-sm:8px;
  --nab-shadow:0 1px 2px rgba(14,27,44,.04),0 1px 3px rgba(14,27,44,.03);
  --nab-shadow-hover:0 2px 4px rgba(14,27,44,.04),0 10px 24px -6px rgba(14,27,44,.10);
  --nab-shadow-lg:0 16px 40px -8px rgba(14,27,44,.16);
  --nab-ease:cubic-bezier(.2,.7,.2,1);
  --nab-font:"Plus Jakarta Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
}
html body:is(.nab-portal-body,.nab-portal-page){background:var(--nab-bg)!important;color:var(--nab-text);font-family:var(--nab-font);-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;text-rendering:optimizeLegibility}
body:is(.nab-portal-body,.nab-portal-page) :is(button,input,select,textarea){font-family:inherit}
/* One type face in the content area (the sidebar keeps its own) */
body:is(.nab-portal-body,.nab-portal-page) :is(.nab-main,.nab-plans-page,.nab-aiw,.nab-ai-page) :is(*:not(svg):not(svg *):not(code):not(pre)){font-family:var(--nab-font)}
body:is(.nab-portal-body,.nab-portal-page) .nab-main{background:var(--nab-bg);background-image:linear-gradient(180deg,#E9F0F8 0,rgba(233,240,248,.55) 180px,rgba(243,245,248,0) 360px);background-repeat:no-repeat}

/* Top bar */
body:is(.nab-portal-body,.nab-portal-page) .nab-topbar{box-sizing:border-box;min-height:64px;padding:12px 32px;gap:16px;background:rgba(255,255,255,.94);-webkit-backdrop-filter:saturate(1.4) blur(8px);backdrop-filter:saturate(1.4) blur(8px);border-bottom:1px solid var(--nab-line);box-shadow:none}
body:is(.nab-portal-body,.nab-portal-page) .nab-topbar-title{display:flex;align-items:center;gap:10px;font-size:16px;font-weight:650;letter-spacing:-.01em;color:var(--nab-text);white-space:nowrap;min-width:0}
body:is(.nab-portal-body,.nab-portal-page) .nab-topbar-title>.nab-i{box-sizing:content-box;padding:7px;border-radius:9px;background:var(--nab-brand-soft);color:var(--nab-brand)}
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
.nab-page-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--nab-accent-strong);margin:0 0 8px}
.nab-page-eyebrow::before{content:"";width:14px;height:2px;border-radius:2px;background:var(--nab-accent)}
body:is(.nab-portal-body,.nab-portal-page) :is(.dl-eyebrow,.bp-eyebrow,.pc-eyebrow,.nw-eyebrow,.nd-hero-date){display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--nab-accent-strong)}
body:is(.nab-portal-body,.nab-portal-page) :is(.dl-eyebrow,.bp-eyebrow,.pc-eyebrow,.nw-eyebrow,.nd-hero-date)::before{content:"";width:14px;height:2px;border-radius:2px;background:var(--nab-accent)}
body:is(.nab-portal-body,.nab-portal-page) :is(.dl-title,.bp-title,.pc-title,.nw-title,.nd-hero-title){font-weight:750;letter-spacing:-.025em}
.nab-page-title{font-size:28px;line-height:1.18;font-weight:750;letter-spacing:-.025em;color:var(--nab-text);margin:0 0 8px}
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
.nab-ui-btn-accent{background:var(--nab-accent);border-color:var(--nab-accent);color:#fff}
.nab-ui-btn-accent:hover{background:var(--nab-accent-strong);border-color:var(--nab-accent-strong);color:#fff}

/* Form fields, everywhere in the content area */
body:is(.nab-portal-body,.nab-portal-page) .nab-main :is(input:is([type=text],[type=number],[type=email],[type=tel],[type=date],[type=url],[type=password],:not([type])),select,textarea):not(.nab-search-input){border:1px solid var(--nab-line-strong);border-radius:10px;background-color:var(--nab-surface);color:var(--nab-text);box-shadow:0 1px 2px rgba(14,27,44,.04);transition:border-color .15s,box-shadow .15s,background-color .15s;outline:none}
body:is(.nab-portal-body,.nab-portal-page) .nab-main :is(input,select,textarea):not(.nab-search-input):hover{border-color:#B5C0CE}
body:is(.nab-portal-body,.nab-portal-page) .nab-main :is(input,select,textarea):not(.nab-search-input):focus{border-color:var(--nab-brand);box-shadow:0 0 0 4px var(--nab-brand-ring)}
body:is(.nab-portal-body,.nab-portal-page) .nab-main :is(input,textarea)::placeholder{color:#A3AEBD;opacity:1}
body:is(.nab-portal-body,.nab-portal-page) .nab-main select:not([multiple]){-webkit-appearance:none;appearance:none;padding-right:38px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%234A5A6E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;background-size:16px}
body:is(.nab-portal-body,.nab-portal-page) .nab-main :is(input[type=checkbox],input[type=radio],input[type=range]){accent-color:var(--nab-brand)}

/* Buttons: one feel for every button */
body:is(.nab-portal-body,.nab-portal-page) :is(.nab-main,.nab-plans-page) :is(button,[class*="btn"]):not(.nab-tile):not(.nab-bell-btn){transition:background-color .15s,border-color .15s,box-shadow .2s,transform .12s var(--nab-ease),color .15s,filter .15s}
body:is(.nab-portal-body,.nab-portal-page) :is(.nab-main,.nab-plans-page) :is(button,[class*="btn"]):not(:disabled):active{transform:translateY(1px) scale(.985)}
body:is(.nab-portal-body,.nab-portal-page) :is(.nab-main,.nab-plans-page) :is(.nab-ui-btn-primary,.nab-ui-btn-accent,.bp-btn-primary,.dl-btn-primary,.pc-btn-primary,.nw-btn-primary,.nd-btn-primary,.sim-run-btn,.uc-calc-btn,.cr-card__btn,.nab-btn-check,.nab-btn-continue,.nab-submit-btn,.nab-loan-submit,.nab-ef-submit,.nab-lc-btn,.nab-quiz-btn,.nab-cf-next,.nab-cf-chat-btn,.nab-rm-next-btn,.nab-pad-download-btn,.nab-btn-plan.primary,.nab-lc-quiz-next){box-shadow:inset 0 1px 0 rgba(255,255,255,.14),0 1px 2px rgba(14,27,44,.18),0 4px 10px -4px rgba(13,92,155,.45);font-weight:650;letter-spacing:.005em}
body:is(.nab-portal-body,.nab-portal-page) :is(.nab-main,.nab-plans-page) :is(.nab-ui-btn-primary,.bp-btn-primary,.dl-btn-primary,.pc-btn-primary,.nw-btn-primary,.nd-btn-primary,.sim-run-btn,.uc-calc-btn,.cr-card__btn,.nab-btn-check,.nab-btn-continue,.nab-submit-btn,.nab-loan-submit,.nab-ef-submit,.nab-lc-btn,.nab-quiz-btn,.nab-cf-next,.nab-cf-chat-btn,.nab-rm-next-btn,.nab-pad-download-btn,.nab-btn-plan.primary,.nab-lc-quiz-next):not(:disabled):hover{background-color:var(--nab-brand-strong);filter:none;box-shadow:inset 0 1px 0 rgba(255,255,255,.14),0 2px 4px rgba(14,27,44,.18),0 10px 18px -8px rgba(13,92,155,.6)}

/* Cards: soft hover, calm entrance */
body:is(.nab-portal-body,.nab-portal-page) .nab-main :is(.nab-panel,.sim-card,.uc-card,.nd-card,.bp-card,.pc-card,.nw-card,.dl-card,.nab-ef-card,.nab-support-card,.nab-loan-card,.nab-cf-qcard,.cr-card,.nab-rm-progress-card,.nab-lc-welcome,.nab-lc-why,.nab-pad-card,.nab-lfp-info-card,.nab-lfp-widget-wrap){transition:box-shadow .25s var(--nab-ease),border-color .25s var(--nab-ease)}
body:is(.nab-portal-body,.nab-portal-page) .nab-main :is(.cr-card,.nab-lfp-info-card,.nab-rm-row,.nab-feat-card,.nab-plan-card):hover{box-shadow:var(--nab-shadow-hover);border-color:var(--nab-line-strong)}
@keyframes nabRise{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@keyframes nabFade{from{opacity:0}to{opacity:1}}
body:is(.nab-portal-body,.nab-portal-page) .nab-content>*,body:is(.nab-portal-body,.nab-portal-page) .nab-content>:only-child>*,body:is(.nab-portal-body,.nab-portal-page) .uc-content>*{animation:nabRise .5s var(--nab-ease) both}
body:is(.nab-portal-body,.nab-portal-page) .nab-content>:only-child>:nth-child(2),body:is(.nab-portal-body,.nab-portal-page) .nab-content>:nth-child(2),body:is(.nab-portal-body,.nab-portal-page) .uc-content>:nth-child(2){animation-delay:.05s}
body:is(.nab-portal-body,.nab-portal-page) .nab-content>:only-child>:nth-child(3),body:is(.nab-portal-body,.nab-portal-page) .nab-content>:nth-child(3),body:is(.nab-portal-body,.nab-portal-page) .uc-content>:nth-child(3){animation-delay:.1s}
body:is(.nab-portal-body,.nab-portal-page) .nab-content>:only-child>:nth-child(4),body:is(.nab-portal-body,.nab-portal-page) .nab-content>:nth-child(4),body:is(.nab-portal-body,.nab-portal-page) .uc-content>:nth-child(4){animation-delay:.15s}
body:is(.nab-portal-body,.nab-portal-page) .nab-content>:only-child>:nth-child(n+5),body:is(.nab-portal-body,.nab-portal-page) .nab-content>:nth-child(n+5),body:is(.nab-portal-body,.nab-portal-page) .uc-content>:nth-child(n+5){animation-delay:.2s}
body:is(.nab-portal-body,.nab-portal-page) .nab-topbar{animation:nabFade .35s ease both}
@media (prefers-reduced-motion:reduce){
  body:is(.nab-portal-body,.nab-portal-page) *{animation:none!important;transition:none!important}
}

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
