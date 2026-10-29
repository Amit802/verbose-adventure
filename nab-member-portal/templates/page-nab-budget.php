<?php
/**
 * Template Name: NAB Monthly Budget Planner
 * Phase 2, item 3. Member sets a monthly budget per spending category and
 * compares it with what they actually spent that month.
 *
 * Budget:   nab_budget user meta (inc/budget.php)
 * Spending: the dashboard's monthly cash flow entries (nab_cashflow), saved
 *           through the same nab_dash_cashflow_save action, so the budget
 *           page and the dashboard charts never disagree.
 * Script:   assets/js/nab-budget.js (enqueued in inc/ajax-handlers.php)
 *
 * @package NAB_Member_Portal
 * @since   1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
}
do_action( 'litespeed_control_set_nocache', 'Budget planner shows member-specific numbers' );

$uid  = get_current_user_id();
$data = nab_budget_page_data( $uid );

nab_head_open( 'Monthly Budget Planner — NAB Member Portal' );
?>
<style>
body.nab-budget-page{
  --bp-bg:#F5F6F8;--bp-card:#fff;--bp-line:#E8EBF0;--bp-line2:#F1F3F6;
  --bp-text:#0F1B2D;--bp-muted:#6B7685;--bp-faint:#9AA4B2;
  --bp-blue:#0D5C9B;--bp-blue2:#0A4A7C;--bp-green:#22A06B;--bp-amber:#E4A11B;--bp-red:#E5484D;
  background:var(--bp-bg)!important;color:var(--bp-text);-webkit-font-smoothing:antialiased
}
body.nab-budget-page [hidden]{display:none!important}
.nab-budget-page .nab-topbar-title{display:flex;align-items:center;gap:8px}
.bp-wrap{max-width:980px;margin:0 auto;padding-bottom:40px}
.bp-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:18px}
.bp-eyebrow{font-size:12px;font-weight:600;color:var(--bp-faint);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
.bp-title{font-size:28px;line-height:1.15;font-weight:750;letter-spacing:-.02em;margin:0 0 6px}
.bp-sub{font-size:14px;color:var(--bp-muted);margin:0;max-width:520px;line-height:1.5}
.bp-month{display:flex;align-items:center;gap:6px}
.bp-month select{height:40px;border:1px solid var(--bp-line);border-radius:10px;background:#fff;font:inherit;font-size:14px;font-weight:600;padding:0 12px;color:var(--bp-text)}
.bp-arrow{width:40px;height:40px;border:1px solid var(--bp-line);border-radius:10px;background:#fff;color:var(--bp-text);cursor:pointer;display:flex;align-items:center;justify-content:center}
.bp-arrow[disabled]{opacity:.35;cursor:default}

.bp-insight{display:flex;align-items:center;gap:10px;background:#FFF8E6;border:1px solid #F5DFA6;color:#7A5200;border-radius:12px;padding:12px 14px;font-size:13.5px;margin-bottom:16px}
.bp-insight.good{background:#E9F7F0;border-color:#BFE6D2;color:#146C45}
.bp-insight.bad{background:#FDECEC;border-color:#F6C6C8;color:#9E1D22}

.bp-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
.bp-tile{background:var(--bp-card);border:1px solid var(--bp-line);border-radius:14px;padding:14px 16px;min-width:0}
.bp-tile-l{font-size:12.5px;font-weight:600;color:var(--bp-muted)}
.bp-tile-v{font-size:24px;font-weight:750;letter-spacing:-.02em;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bp-tile-s{font-size:12px;color:var(--bp-faint);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bp-up{color:var(--bp-green)!important}.bp-down{color:var(--bp-red)!important}

.bp-card{background:var(--bp-card);border:1px solid var(--bp-line);border-radius:14px;padding:20px 22px;margin-bottom:16px}
.bp-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.bp-card-title{font-size:16px;font-weight:700;margin:0}
.bp-card-sub{font-size:12.5px;color:var(--bp-muted);margin-top:3px}
.bp-actions{display:flex;gap:8px;flex-wrap:wrap}
.bp-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;font:inherit;font-size:13px;font-weight:600;line-height:1;padding:10px 14px;border-radius:10px;border:1px solid var(--bp-line);background:#fff;color:var(--bp-text);cursor:pointer;text-decoration:none;white-space:nowrap}
.bp-btn:hover{background:#F8F9FB;border-color:#D9DEE6}
.bp-btn-primary{background:var(--bp-blue);border-color:var(--bp-blue);color:#fff}
.bp-btn-primary:hover{background:var(--bp-blue2);border-color:var(--bp-blue2)}
.bp-btn[disabled]{opacity:.6;cursor:wait}

.bp-track{height:10px;background:var(--bp-line2);border-radius:999px;overflow:hidden}
.bp-track>span{display:block;height:100%;border-radius:999px;transition:width .45s ease}
.bp-overall-cap{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:13px;color:var(--bp-muted);margin-top:10px}
.bp-overall-cap b{color:var(--bp-text)}

.bp-list{list-style:none;margin:0;padding:0}
.bp-row{display:grid;grid-template-columns:36px minmax(0,1fr) auto;column-gap:12px;row-gap:6px;align-items:center;padding:12px 0;border-bottom:1px solid var(--bp-line2)}
.bp-row:last-child{border-bottom:0}
.bp-ico.is-tile{background:none!important}
.bp-ico{grid-row:1/3;width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff}
.bp-name{font-size:14px;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bp-amt{font-size:13.5px;font-weight:650;text-align:right;white-space:nowrap}
.bp-amt small{font-weight:500;color:var(--bp-faint)}
.bp-row .bp-track{height:7px}
.bp-status{font-size:12px;text-align:right;white-space:nowrap;color:var(--bp-muted)}

.bp-empty{text-align:center;padding:10px 0 4px}
.bp-empty p{color:var(--bp-muted);font-size:14px;margin:0 0 14px;line-height:1.5}
.bp-empty .bp-actions{justify-content:center}

.bp-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.bp-form label{display:block;font-size:12.5px;font-weight:600;color:#475467;margin-bottom:12px}
.bp-form input{display:block;width:100%;box-sizing:border-box;height:44px;margin-top:6px;padding:0 12px 0 26px;border:1px solid #D9DEE6;border-radius:10px;font:inherit;font-size:15px;color:var(--bp-text);background:#fff;outline:none}
.bp-form input:focus{border-color:var(--bp-blue);box-shadow:0 0 0 3px rgba(13,92,155,.12)}
.bp-money{position:relative;display:block}
.bp-money::before{content:'$';position:absolute;left:12px;top:50%;transform:translateY(-50%);margin-top:3px;color:var(--bp-faint);font-weight:600;font-size:14px;pointer-events:none}
.bp-dot{display:inline-block;width:8px;height:8px;border-radius:3px;margin-right:6px}
.bp-lbl{display:flex;align-items:center;gap:8px}
.bp-lbl .nab-tile{border-radius:7px}
.bp-helpers{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.bp-total{background:#F8F9FB;border:1px solid var(--bp-line);border-radius:10px;padding:12px 14px;font-size:13.5px;margin:4px 0 14px;line-height:1.5}
.bp-total b{font-weight:700}
.bp-msg{font-size:13px;font-weight:600;min-height:0;margin:0 0 10px}
.bp-msg.ok{color:#157F4F}.bp-msg.err{color:#B42328}
.bp-form-foot{display:flex;gap:8px;flex-wrap:wrap}

.bp-tips{margin:0;padding-left:18px;color:#344054;font-size:13.5px;line-height:1.6}
.bp-tips li{margin-bottom:6px}

@media(max-width:860px){
  .bp-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:640px){
  .bp-title{font-size:24px}
  .bp-head{align-items:stretch}
  .bp-month{width:100%}
  .bp-month select{flex:1;min-width:0}
  .bp-summary{gap:10px}
  .bp-tile{padding:12px 14px}
  .bp-tile-v{font-size:20px}
  .bp-card{padding:16px}
  .bp-actions .bp-btn{flex:1}
  .bp-form input{font-size:16px} /* stops iOS zooming into the field */
  .nab-budget-page .nab-hamburger{position:absolute} /* scrolls with the header instead of covering content */
}
@media(max-width:400px){
  .bp-form-grid{grid-template-columns:1fr}
}
</style>
</head>
<body <?php body_class( 'nab-portal-body nab-budget-page' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( 'budget' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title"><?php echo nab_portal_icon( 'wallet', 20 ); ?> Monthly Budget Planner</div>
      <div class="nab-topbar-right">
        <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="bp-wrap">

        <section class="bp-head">
          <div>
            <div class="bp-eyebrow">Budget</div>
            <h1 class="bp-title">Plan your month</h1>
            <p class="bp-sub">Set an amount for each spending category, then see how your actual spending compares as the month goes on.</p>
          </div>
          <div class="bp-month">
            <button type="button" class="bp-arrow" data-bp-step="-1" aria-label="Previous month"><?php echo nab_portal_icon( 'chevron-right', 18, 'bp-flip' ); ?></button>
            <select id="bpMonth" aria-label="Month"></select>
            <button type="button" class="bp-arrow" data-bp-step="1" aria-label="Next month"><?php echo nab_portal_icon( 'chevron-right', 18 ); ?></button>
          </div>
        </section>

        <div class="bp-insight" id="bpInsight" role="status" hidden></div>

        <section class="bp-summary" aria-label="Budget summary">
          <div class="bp-tile"><div class="bp-tile-l">Budget</div><div class="bp-tile-v" id="bpBudget">$0</div><div class="bp-tile-s" id="bpBudgetSub">Not set yet</div></div>
          <div class="bp-tile"><div class="bp-tile-l">Spent</div><div class="bp-tile-v" id="bpSpent">$0</div><div class="bp-tile-s" id="bpSpentSub">&nbsp;</div></div>
          <div class="bp-tile"><div class="bp-tile-l" id="bpLeftLabel">Left to spend</div><div class="bp-tile-v" id="bpLeft">$0</div><div class="bp-tile-s" id="bpLeftSub">&nbsp;</div></div>
          <div class="bp-tile"><div class="bp-tile-l">Income</div><div class="bp-tile-v" id="bpIncome">$0</div><div class="bp-tile-s" id="bpIncomeSub">&nbsp;</div></div>
        </section>

        <section class="bp-card" id="bpOverall">
          <div class="bp-card-head">
            <div>
              <h2 class="bp-card-title">This month so far</h2>
              <div class="bp-card-sub" id="bpOverallSub">&nbsp;</div>
            </div>
          </div>
          <div class="bp-track" aria-hidden="true"><span id="bpOverallBar" style="width:0"></span></div>
          <div class="bp-overall-cap"><span id="bpOverallPct">&nbsp;</span><span id="bpPerDay">&nbsp;</span></div>
        </section>

        <section class="bp-card">
          <div class="bp-card-head">
            <div>
              <h2 class="bp-card-title">Categories</h2>
              <div class="bp-card-sub">Budget compared with what you spent</div>
            </div>
            <div class="bp-actions">
              <button type="button" class="bp-btn" data-bp-open="spend"><?php echo nab_portal_icon( 'banknote', 16 ); ?> Update spending</button>
              <button type="button" class="bp-btn bp-btn-primary" data-bp-open="budget"><?php echo nab_portal_icon( 'pencil', 16 ); ?> Edit budget</button>
            </div>
          </div>
          <div class="bp-empty" id="bpEmpty" hidden>
            <p>You haven't set a budget yet. Pick a starting point and adjust the amounts to suit you.</p>
            <div class="bp-actions">
              <button type="button" class="bp-btn" data-bp-open="budget" data-bp-fill="spending">Start from my spending</button>
              <button type="button" class="bp-btn" data-bp-open="budget" data-bp-fill="guide">Use the 50/30/20 guide</button>
              <button type="button" class="bp-btn bp-btn-primary" data-bp-open="budget">Enter my own amounts</button>
            </div>
          </div>
          <ul class="bp-list" id="bpList"></ul>
        </section>

        <!-- Budget editor -->
        <section class="bp-card" id="bpBudgetCard" hidden>
          <form class="bp-form" id="bpBudgetForm" novalidate>
            <div class="bp-card-head">
              <div>
                <h2 class="bp-card-title">Your monthly budget</h2>
                <div class="bp-card-sub">These amounts apply to every month. Leave a category empty if you don't need it.</div>
              </div>
            </div>
            <div class="bp-helpers">
              <button type="button" class="bp-btn" data-bp-fill="spending">Fill from my spending</button>
              <button type="button" class="bp-btn" data-bp-fill="guide">Fill with the 50/30/20 guide</button>
            </div>
            <div class="bp-form-grid">
              <?php foreach ( $data['cats'] as $ck => $cat ) : ?>
              <label><span class="bp-lbl"><?php echo nab_portal_icon_tile( $cat['icon'], $cat['tone'], 22, 'bp-mini' ); ?><?php echo esc_html( $cat['label'] ); ?></span>
                <span class="bp-money"><input type="number" name="b_<?php echo esc_attr( $ck ); ?>" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div class="bp-total" id="bpBudgetTotal">&nbsp;</div>
            <div class="bp-msg" role="status"></div>
            <div class="bp-form-foot">
              <button type="submit" class="bp-btn bp-btn-primary">Save budget</button>
              <button type="button" class="bp-btn" data-bp-close>Cancel</button>
            </div>
          </form>
        </section>

        <!-- Spending for the selected month (same data as the dashboard Cash flow) -->
        <section class="bp-card" id="bpSpendCard" hidden>
          <form class="bp-form" id="bpSpendForm" novalidate>
            <div class="bp-card-head">
              <div>
                <h2 class="bp-card-title" id="bpSpendTitle">Spending</h2>
                <div class="bp-card-sub">This also updates the Cash flow and Spending charts on your dashboard.</div>
              </div>
            </div>
            <input type="hidden" name="month" value="">
            <label>Total income for the month (after tax)
              <span class="bp-money"><input type="number" name="income" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></span>
            </label>
            <div class="bp-form-grid">
              <?php foreach ( $data['cats'] as $ck => $cat ) : ?>
              <label><span class="bp-lbl"><?php echo nab_portal_icon_tile( $cat['icon'], $cat['tone'], 22, 'bp-mini' ); ?><?php echo esc_html( $cat['label'] ); ?></span>
                <span class="bp-money"><input type="number" name="exp_<?php echo esc_attr( $ck ); ?>" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div class="bp-msg" role="status"></div>
            <div class="bp-form-foot">
              <button type="submit" class="bp-btn bp-btn-primary">Save spending</button>
              <button type="button" class="bp-btn" data-bp-close>Cancel</button>
            </div>
          </form>
        </section>

        <section class="bp-card">
          <h2 class="bp-card-title" style="margin-bottom:12px">Budgeting tips</h2>
          <ul class="bp-tips">
            <li>Start with your fixed costs like rent, car payments and insurance, then plan the flexible ones.</li>
            <li>The 50/30/20 guide is a simple starting point: about 50% of income for needs, 30% for wants and 20% for savings and paying down debt.</li>
            <li>Update your spending once a week. Small check-ins are easier than catching up at the end of the month.</li>
            <li>If you go over in one category, move money from another one instead of giving up on the budget.</li>
            <li>Paying more than the minimum on your cards lowers your utilization, which helps your credit score.</li>
          </ul>
        </section>

      </div>
    </div>
  </main>
</div>

<?php nab_portal_print_icons_js( array_merge( [ 'pencil', 'banknote', 'triangle-alert', 'circle-check', 'info' ], wp_list_pluck( $data['cats'], 'icon' ) ) ); ?>
<script>
window.nabBudgetData = <?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>;
</script>
<style>.bp-flip{transform:rotate(180deg)}</style>

<?php nab_footer_scripts(); ?>
<?php wp_footer(); ?>
</body>
</html>
