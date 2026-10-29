<?php
/**
 * Template Name: NAB Net Worth Calculator
 * Phase 2, item 5. Members list what they own and what they owe, and see
 * their net worth, how it is split, and how it changes month to month.
 *
 * Data:   the dashboard's own accounts (nab_accounts) and monthly snapshots
 *         (nab_networth_history), saved through the same AJAX actions
 *         (nab_dash_account_save / nab_dash_account_delete in
 *         inc/dashboard-data.php), so this page and the dashboard's Net
 *         Worth card always show the same numbers.
 * Script: assets/js/nab-networth.js (enqueued in inc/ajax-handlers.php)
 *
 * @package NAB_Member_Portal
 * @since   1.13.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
}
do_action( 'litespeed_control_set_nocache', 'Net worth shows member-specific numbers' );

$uid   = get_current_user_id();
$dash  = nab_dash_get_chart_data( $uid );
$data  = [
    'today'    => $dash['today'],
    'month'    => $dash['month'],
    'accounts' => $dash['accounts'],
    'networth' => $dash['networth'],
    'types'    => $dash['types'],
    'tones'    => nab_portal_tile_tones(),
];
$types = $dash['types'];

nab_head_open( 'Net Worth Calculator — NAB Member Portal' );
?>
<style>
body.nab-networth-page{
  --nw-bg:#F5F6F8;--nw-card:#fff;--nw-line:#E8EBF0;--nw-line2:#F1F3F6;
  --nw-text:#0F1B2D;--nw-muted:#6B7685;--nw-faint:#9AA4B2;
  --nw-blue:#0D5C9B;--nw-blue2:#0A4A7C;--nw-green:#16A35A;--nw-red:#DC2F38;
  background:var(--nw-bg)!important;color:var(--nw-text);-webkit-font-smoothing:antialiased
}
body.nab-networth-page [hidden]{display:none!important}
.nab-networth-page .nab-topbar-title{display:flex;align-items:center;gap:10px}
.nw-wrap{max-width:1040px;margin:0 auto;padding-bottom:40px}
.nw-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:18px}
.nw-eyebrow{font-size:12px;font-weight:600;color:var(--nw-faint);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
.nw-title{font-size:28px;line-height:1.15;font-weight:750;letter-spacing:-.02em;margin:0 0 6px}
.nw-sub{font-size:14px;color:var(--nw-muted);margin:0;max-width:540px;line-height:1.5}

.nw-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;font:inherit;font-size:13px;font-weight:600;line-height:1;padding:10px 14px;border-radius:10px;border:1px solid var(--nw-line);background:#fff;color:var(--nw-text);cursor:pointer;text-decoration:none;white-space:nowrap}
.nw-btn:hover{background:#F8F9FB;border-color:#D9DEE6}
.nw-btn-primary{background:var(--nw-blue);border-color:var(--nw-blue);color:#fff}
.nw-btn-primary:hover{background:var(--nw-blue2);border-color:var(--nw-blue2)}
.nw-btn-danger{color:#B42328}
.nw-btn[disabled]{opacity:.6;cursor:wait}

.nw-card{background:var(--nw-card);border:1px solid var(--nw-line);border-radius:16px;padding:20px 22px;margin-bottom:16px}
.nw-card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.nw-card-title{font-size:16px;font-weight:700;margin:0}
.nw-card-sub{font-size:12.5px;color:var(--nw-muted);margin-top:3px}

/* Hero */
.nw-hero{position:relative;overflow:hidden;border:0;color:#fff;padding:24px 26px;
  background:radial-gradient(120% 140% at 0% 0%,#1F5A9C 0%,#0E3866 48%,#081C38 100%);box-shadow:0 18px 40px -22px rgba(8,28,56,.8)}
.nw-hero::after{content:"";position:absolute;right:-60px;top:-60px;width:220px;height:220px;border-radius:50%;background:radial-gradient(circle,rgba(240,207,122,.22),rgba(240,207,122,0) 70%);pointer-events:none}
.nw-hero-top{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap}
.nw-hero-l{font-size:13px;font-weight:600;color:rgba(255,255,255,.72)}
.nw-hero-v{font-size:40px;font-weight:800;letter-spacing:-.03em;line-height:1.1;margin:4px 0 6px;white-space:nowrap}
.nw-change{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:650;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.12);color:#fff}
.nw-change.up{background:rgba(85,216,146,.18);color:#8EF0B9}.nw-change.down{background:rgba(255,126,131,.18);color:#FFB4B7}
.nw-split{display:flex;height:10px;border-radius:999px;overflow:hidden;background:rgba(255,255,255,.12);margin:20px 0 12px}
.nw-split .a{background:linear-gradient(90deg,#55D892,#14A058);transition:width .45s ease}
.nw-split .d{background:linear-gradient(90deg,#FF7E83,#DC2F38);transition:width .45s ease}
.nw-hero-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.nw-hs-l{font-size:12px;color:rgba(255,255,255,.68);display:flex;align-items:center;gap:6px}
.nw-hs-l i{width:8px;height:8px;border-radius:3px;display:block}
.nw-hs-v{font-size:18px;font-weight:750;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

.nw-insight{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--nw-line);border-radius:14px;padding:12px 14px;font-size:13.5px;line-height:1.5;margin-bottom:16px}

.nw-chart{position:relative;height:240px}
.nw-chart-empty{font-size:13.5px;color:var(--nw-muted);margin:0}

.nw-two{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.nw-two .nw-card{margin-bottom:16px}
.nw-list{list-style:none;margin:0;padding:0}
.nw-row{display:grid;grid-template-columns:auto minmax(0,1fr) auto;column-gap:12px;row-gap:6px;align-items:center;padding:10px 0;border-bottom:1px solid var(--nw-line2)}
.nw-row:last-child{border-bottom:0}
.nw-row .nab-tile{grid-row:1/3}
.nw-name{font-size:14px;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nw-amt{font-size:14px;font-weight:700;text-align:right;white-space:nowrap}
.nw-meta{font-size:12px;color:var(--nw-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nw-track{height:6px;background:var(--nw-line2);border-radius:999px;overflow:hidden;grid-column:2/4}
.nw-track>span{display:block;height:100%;border-radius:999px;transition:width .45s ease}
.nw-none{font-size:13.5px;color:var(--nw-muted);margin:4px 0 0}

.nw-acc{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto;column-gap:12px;align-items:center;padding:11px 0;border-bottom:1px solid var(--nw-line2)}
.nw-acc:last-child{border-bottom:0}
.nw-group{font-size:11.5px;font-weight:700;color:var(--nw-faint);text-transform:uppercase;letter-spacing:.07em;margin:14px 0 2px;display:flex;justify-content:space-between}
.nw-group:first-child{margin-top:0}
.nw-edit{display:inline-flex;align-items:center;gap:5px;font:inherit;font-size:12.5px;font-weight:650;padding:8px 11px;border-radius:9px;border:1px solid var(--nw-line);background:#fff;color:var(--nw-text);cursor:pointer;white-space:nowrap}
.nw-edit:hover{border-color:var(--nw-blue);color:var(--nw-blue)}
.nw-debt{color:var(--nw-red)}

.nw-empty{text-align:center;padding:6px 0 2px}
.nw-empty p{color:var(--nw-muted);font-size:14px;margin:0 0 14px;line-height:1.5}
.nw-starters{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
.nw-starter{display:inline-flex;align-items:center;gap:8px;font:inherit;font-size:13px;font-weight:600;padding:6px 12px 6px 6px;border-radius:12px;border:1px solid var(--nw-line);background:#fff;color:var(--nw-text);cursor:pointer}
.nw-starter:hover{border-color:#CFE0EF;background:#F7FAFD}

/* Form */
.nw-form-grid{display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:0 14px}
.nw-form label{display:block;font-size:12.5px;font-weight:600;color:#475467;margin-bottom:12px}
.nw-form input,.nw-form select{display:block;width:100%;box-sizing:border-box;height:44px;margin-top:6px;padding:0 12px;border:1px solid #D9DEE6;border-radius:10px;font:inherit;font-size:15px;color:var(--nw-text);background:#fff;outline:none;-webkit-appearance:none;appearance:none}
.nw-form select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7685' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px}
.nw-form input:focus,.nw-form select:focus{border-color:var(--nw-blue);box-shadow:0 0 0 3px rgba(13,92,155,.12)}
.nw-money{position:relative;display:block}
.nw-money input{padding-left:26px}
.nw-money::before{content:'$';position:absolute;left:12px;top:50%;transform:translateY(-50%);margin-top:3px;color:var(--nw-faint);font-weight:600;font-size:14px;pointer-events:none}
.nw-hint{font-size:12.5px;color:var(--nw-muted);margin:-4px 0 12px}
.nw-msg{font-size:13px;font-weight:600;margin:0 0 10px}
.nw-msg:empty{display:none}
.nw-msg.ok{color:#157F4F}.nw-msg.err{color:#B42328}
.nw-form-foot{display:flex;gap:8px;flex-wrap:wrap}
.nw-form-foot .nw-btn-danger{margin-left:auto}

.nw-tips{margin:0;padding-left:18px;color:#344054;font-size:13.5px;line-height:1.6}
.nw-tips li{margin-bottom:6px}

@media(max-width:860px){
  .nw-two{grid-template-columns:1fr;gap:0}
  .nw-form-grid{grid-template-columns:1fr 1fr}
  .nw-form-grid label:first-child{grid-column:1/3}
}
@media(max-width:640px){
  .nw-title{font-size:24px}
  .nw-head .nw-btn-primary{width:100%}
  .nw-card{padding:16px}
  .nw-hero{padding:20px 18px}
  .nw-hero-v{font-size:34px}
  .nw-hero-stats{gap:8px}
  .nw-hs-v{font-size:15px}
  .nw-chart{height:200px}
  .nw-acc{grid-template-columns:auto minmax(0,1fr) auto;row-gap:8px}
  .nw-acc .nw-edit{grid-column:2/4;justify-self:start}
  .nw-form input,.nw-form select{font-size:16px} /* stops iOS zooming into the field */
  .nw-form-foot .nw-btn{flex:1}
  .nw-form-foot .nw-btn-danger{margin-left:0}
  .nab-networth-page .nab-hamburger{position:absolute} /* scrolls with the header instead of covering content */
}
@media(max-width:420px){
  .nw-form-grid{grid-template-columns:1fr}
  .nw-form-grid label:first-child{grid-column:auto}
}
</style>
</head>
<body <?php body_class( 'nab-portal-body nab-networth-page' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( 'networth' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title"><?php echo nab_portal_icon_tile( 'scale', 'indigo', 30 ); ?> Net Worth Calculator</div>
      <div class="nab-topbar-right">
        <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="nw-wrap">

        <section class="nw-head">
          <div>
            <div class="nw-eyebrow">Net worth</div>
            <h1 class="nw-title">What you own, minus what you owe</h1>
            <p class="nw-sub">Add your bank accounts, savings, property, cards and loans. Update the balances once a month and watch your net worth grow.</p>
          </div>
          <button type="button" class="nw-btn nw-btn-primary" data-nw-add><?php echo nab_portal_icon( 'plus', 16 ); ?> Add an account</button>
        </section>

        <section class="nw-card nw-hero" aria-label="Your net worth">
          <div class="nw-hero-top">
            <div>
              <div class="nw-hero-l">Your net worth</div>
              <div class="nw-hero-v" id="nwValue">$0</div>
              <span class="nw-change" id="nwChange">Add your accounts to start</span>
            </div>
          </div>
          <div class="nw-split" aria-hidden="true"><span class="a" id="nwSplitA" style="width:0"></span><span class="d" id="nwSplitD" style="width:0"></span></div>
          <div class="nw-hero-stats">
            <div><div class="nw-hs-l"><i style="background:#55D892"></i>What you own</div><div class="nw-hs-v" id="nwAssets">$0</div></div>
            <div><div class="nw-hs-l"><i style="background:#FF7E83"></i>What you owe</div><div class="nw-hs-v" id="nwDebts">$0</div></div>
            <div><div class="nw-hs-l">Debt to assets</div><div class="nw-hs-v" id="nwRatio">—</div></div>
          </div>
        </section>

        <div class="nw-insight" id="nwInsight" role="status" hidden></div>

        <section class="nw-card">
          <div class="nw-card-head">
            <div>
              <h2 class="nw-card-title">Net worth over time</h2>
              <div class="nw-card-sub">Saved each month you update a balance</div>
            </div>
          </div>
          <div class="nw-chart" id="nwChartBox"><canvas id="nwChart" role="img" aria-label="Net worth over time"></canvas></div>
          <p class="nw-chart-empty" id="nwChartEmpty" hidden>Your chart will start once you have two months of balances. Come back next month and update them.</p>
        </section>

        <div class="nw-two">
          <section class="nw-card">
            <div class="nw-card-head"><div><h2 class="nw-card-title">What you own</h2><div class="nw-card-sub" id="nwOwnSub">&nbsp;</div></div></div>
            <ul class="nw-list" id="nwOwn"></ul>
          </section>
          <section class="nw-card">
            <div class="nw-card-head"><div><h2 class="nw-card-title">What you owe</h2><div class="nw-card-sub" id="nwOweSub">&nbsp;</div></div></div>
            <ul class="nw-list" id="nwOwe"></ul>
          </section>
        </div>

        <!-- Add / edit form -->
        <section class="nw-card" id="nwFormCard" hidden>
          <form class="nw-form" id="nwForm" novalidate>
            <div class="nw-card-head">
              <div>
                <h2 class="nw-card-title" id="nwFormTitle">Add an account</h2>
                <div class="nw-card-sub">Use today's balance. For a house or car, use what it would sell for.</div>
              </div>
            </div>
            <input type="hidden" name="id" value="">
            <div class="nw-form-grid">
              <label>Name
                <input type="text" name="name" maxlength="60" placeholder="e.g. TD Chequing" autocomplete="off">
              </label>
              <label>Type
                <select name="type">
                  <optgroup label="What you own">
                    <?php foreach ( $types as $tk => $t ) if ( $t['group'] === 'asset' ) : ?>
                    <option value="<?php echo esc_attr( $tk ); ?>"><?php echo esc_html( $t['label'] ); ?></option>
                    <?php endif; ?>
                  </optgroup>
                  <optgroup label="What you owe">
                    <?php foreach ( $types as $tk => $t ) if ( $t['group'] === 'debt' ) : ?>
                    <option value="<?php echo esc_attr( $tk ); ?>"><?php echo esc_html( $t['label'] ); ?></option>
                    <?php endif; ?>
                  </optgroup>
                </select>
              </label>
              <label>Balance
                <span class="nw-money"><input type="number" name="balance" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></span>
              </label>
            </div>
            <p class="nw-hint" id="nwTypeHint">&nbsp;</p>
            <div class="nw-msg" role="status"></div>
            <div class="nw-form-foot">
              <button type="submit" class="nw-btn nw-btn-primary">Save account</button>
              <button type="button" class="nw-btn" data-nw-close>Cancel</button>
              <button type="button" class="nw-btn nw-btn-danger" data-nw-delete hidden><?php echo nab_portal_icon( 'trash-2', 16 ); ?> Delete</button>
            </div>
          </form>
        </section>

        <section class="nw-card" id="nwAccountsCard">
          <div class="nw-card-head">
            <div>
              <h2 class="nw-card-title">Your accounts</h2>
              <div class="nw-card-sub" id="nwAccSub">&nbsp;</div>
            </div>
            <button type="button" class="nw-btn" data-nw-add><?php echo nab_portal_icon( 'plus', 16 ); ?> Add</button>
          </div>
          <div class="nw-empty" id="nwEmpty" hidden>
            <p>Start with the accounts you know best. You can add the rest later.</p>
            <div class="nw-starters">
              <?php foreach ( [ [ 'cash', 'Chequing account' ], [ 'cash', 'Savings account' ], [ 'investment', 'TFSA or RRSP' ], [ 'credit_card', 'Credit card' ], [ 'loan', 'Car or student loan' ], [ 'property', 'Home' ] ] as $st ) : ?>
              <button type="button" class="nw-starter" data-nw-start="<?php echo esc_attr( $st[0] ); ?>" data-nw-name="<?php echo esc_attr( $st[1] ); ?>"><?php echo nab_portal_icon_tile( $types[ $st[0] ]['icon'], $types[ $st[0] ]['tone'], 28 ); ?><?php echo esc_html( $st[1] ); ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div id="nwAccounts"></div>
        </section>

        <section class="nw-card">
          <h2 class="nw-card-title" style="margin-bottom:12px">Net worth tips</h2>
          <ul class="nw-tips">
            <li>Net worth is simply what you own minus what you owe. Watching it go up over time matters more than the number today.</li>
            <li>Update your balances once a month, for example on payday, so your chart shows a clear trend.</li>
            <li>Paying down high-interest debt like credit cards raises your net worth and can also help your credit score.</li>
            <li>Use a fair resale value for your car or home, not what you paid for it.</li>
            <li>A negative net worth is common when you have student or car loans. Every payment moves it in the right direction.</li>
          </ul>
        </section>

      </div>
    </div>
  </main>
</div>

<?php nab_portal_print_icons_js( array_merge( [ 'pencil', 'trending-up', 'trending-down', 'info', 'circle-check', 'triangle-alert', 'scale' ], wp_list_pluck( $types, 'icon' ) ) ); ?>
<script>
window.nabNetData = <?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>;
</script>

<?php nab_footer_scripts(); ?>
<?php wp_footer(); ?>
</body>
</html>
