<?php
/**
 * Template Name: NAB Loan Calculator
 * Phase 2, item 6. Members enter a loan (amount, rate, length, how often
 * they pay) and see the payment, the total interest, the payoff date, how
 * much extra payments save, and a year-by-year schedule.
 *
 * Data:   nothing is saved. All maths runs in the browser
 *         (assets/js/nab-loan-calc.js). The member's own loans from the
 *         dashboard (nab_accounts) are offered as a one-click starting amount.
 * Script: assets/js/nab-loan-calc.js (enqueued in inc/ajax-handlers.php)
 *
 * @package NAB_Member_Portal
 * @since   1.20.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
}
do_action( 'litespeed_control_set_nocache', 'Loan calculator shows member-specific loans' );

$uid   = get_current_user_id();
$dash  = nab_dash_get_chart_data( $uid );
$types = $dash['types'];

// The member's own loans, offered as a starting amount.
$my_loans = [];
foreach ( (array) $dash['accounts'] as $a ) {
    $type = $a['type'] ?? '';
    $bal  = (float) ( $a['bal'] ?? 0 );
    if ( in_array( $type, [ 'loan', 'mortgage', 'other_debt' ], true ) && $bal > 0 ) {
        $my_loans[] = [ 'name' => (string) ( $a['name'] ?? '' ), 'type' => $type, 'bal' => round( $bal, 2 ) ];
    }
}
$my_loans = array_slice( $my_loans, 0, 4 );

// Starting points. Mortgages compound twice a year in Canada.
$presets = [
    'car'      => [ 'label' => 'Car loan',      'icon' => 'car',        'tone' => 'orange', 'amount' => 25000,  'rate' => 7.99,  'years' => 5,  'freq' => 'monthly', 'mortgage' => false ],
    'personal' => [ 'label' => 'Personal loan', 'icon' => 'hand-coins', 'tone' => 'pink',   'amount' => 10000,  'rate' => 11.99, 'years' => 3,  'freq' => 'monthly', 'mortgage' => false ],
    'consol'   => [ 'label' => 'Debt consolidation', 'icon' => 'receipt', 'tone' => 'slate', 'amount' => 15000, 'rate' => 9.99, 'years' => 4, 'freq' => 'monthly', 'mortgage' => false ],
    'mortgage' => [ 'label' => 'Mortgage',      'icon' => 'key-round',  'tone' => 'purple', 'amount' => 400000, 'rate' => 4.79,  'years' => 25, 'freq' => 'monthly', 'mortgage' => true ],
];

$nav       = nab_get_nav_links();
$lfp_url   = nab_resolve_url( $nav['lfp'] ?? '#' );
$auto_url  = nab_resolve_url( $nav['loan'] ?? '#' );

$data = [
    'presets' => $presets,
    'myLoans' => $my_loans,
    'types'   => array_map( function ( $t ) { return [ 'label' => $t['label'], 'icon' => $t['icon'], 'tone' => $t['tone'] ]; }, $types ),
];

nab_head_open( 'Loan Calculator | NAB Member Portal' );
?>
<style>
body.nab-loancalc-page{
  --lc-bg:var(--nab-bg);--lc-card:var(--nab-surface);--lc-line:var(--nab-line);--lc-line2:var(--nab-line-soft);
  --lc-text:var(--nab-text);--lc-muted:var(--nab-text-2);--lc-faint:var(--nab-text-3);
  --lc-blue:var(--nab-brand);--lc-blue2:var(--nab-brand-strong);--lc-green:var(--nab-good);--lc-red:var(--nab-bad);
  background:var(--lc-bg)!important;color:var(--lc-text);-webkit-font-smoothing:antialiased
}
body.nab-loancalc-page [hidden]{display:none!important}
.nab-loancalc-page .nab-topbar-title{display:flex;align-items:center;gap:10px}
.lc-wrap{max-width:1040px;margin:0 auto;padding-bottom:40px}
.lc-head{margin-bottom:18px}
.lc-eyebrow{font-size:12px;font-weight:600;color:var(--lc-faint);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
.lc-title{font-size:28px;line-height:1.15;font-weight:750;letter-spacing:-.02em;margin:0 0 6px}
.lc-sub{font-size:14px;color:var(--lc-muted);margin:0;max-width:560px;line-height:1.5}

.lc-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;font:inherit;font-size:13px;font-weight:600;line-height:1;padding:10px 14px;border-radius:10px;border:1px solid var(--lc-line);background:#fff;color:var(--lc-text);cursor:pointer;text-decoration:none;white-space:nowrap}
.lc-btn:hover{background:#F8F9FB;border-color:#D9DEE6}
.lc-btn-primary{background:var(--lc-blue);border-color:var(--lc-blue);color:#fff}
.lc-btn-primary:hover{background:var(--lc-blue2);border-color:var(--lc-blue2);color:#fff}

.lc-card{background:var(--lc-card);border:1px solid var(--lc-line);border-radius:16px;padding:20px 22px;margin-bottom:16px}
.lc-card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.lc-card-title{font-size:16px;font-weight:700;margin:0}
.lc-card-sub{font-size:12.5px;color:var(--lc-muted);margin-top:3px}

/* Layout: form left, result right */
.lc-main{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,1fr);gap:16px;align-items:start}
.lc-main>.lc-card{margin-bottom:0}
.lc-side{position:sticky;top:16px}

/* Presets */
.lc-presets{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.lc-preset{display:inline-flex;align-items:center;gap:8px;font:inherit;font-size:13px;font-weight:600;padding:6px 12px 6px 6px;border-radius:12px;border:1px solid var(--lc-line);background:#fff;color:var(--lc-text);cursor:pointer}
.lc-preset:hover{border-color:#CFE0EF;background:#F7FAFD}
.lc-preset[aria-pressed="true"]{border-color:var(--lc-blue);background:#F2F7FC;box-shadow:0 0 0 3px rgba(13,92,155,.10)}
.lc-mine{margin:-6px 0 16px;font-size:12.5px;color:var(--lc-muted);display:flex;gap:6px;flex-wrap:wrap;align-items:center}
.lc-mine button{font:inherit;font-size:12.5px;font-weight:600;padding:5px 10px;border-radius:999px;border:1px dashed #C9D3DF;background:#fff;color:var(--lc-blue);cursor:pointer}
.lc-mine button:hover{border-style:solid;background:#F7FAFD}

/* Form */
.lc-form{margin:0}
.lc-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.lc-form label{display:block;font-size:12.5px;font-weight:600;color:#475467;margin-bottom:14px}
.lc-form label small{font-weight:500;color:var(--lc-faint)}
.lc-form input,.lc-form select{display:block;width:100%;box-sizing:border-box;height:44px;margin-top:6px;padding:0 12px;border:1px solid #D9DEE6;border-radius:10px;font:inherit;font-size:15px;color:var(--lc-text);background:#fff;outline:none;-webkit-appearance:none;appearance:none}
.lc-form select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7685' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px}
.lc-form input:focus,.lc-form select:focus{border-color:var(--lc-blue);box-shadow:0 0 0 3px rgba(13,92,155,.12)}
.lc-form input[aria-invalid="true"]{border-color:var(--lc-red);box-shadow:0 0 0 3px rgba(180,35,40,.10)}
.lc-unit{position:relative;display:block}
.lc-unit input{padding-left:26px}
.lc-unit::before{content:attr(data-pre);position:absolute;left:12px;top:50%;transform:translateY(-50%);margin-top:3px;color:var(--lc-faint);font-weight:600;font-size:14px;pointer-events:none}
.lc-unit.post input{padding-left:12px;padding-right:30px}
.lc-unit.post::before{content:none}
.lc-unit.post::after{content:attr(data-post);position:absolute;right:12px;top:50%;transform:translateY(-50%);margin-top:3px;color:var(--lc-faint);font-weight:600;font-size:14px;pointer-events:none}
.lc-term{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.lc-range{display:block;width:100%;margin:10px 0 0;accent-color:var(--lc-blue)}
.lc-form input.lc-range{height:auto;padding:0;border:0;box-shadow:none;-webkit-appearance:auto;appearance:auto}
.lc-more{border-top:1px solid var(--lc-line2);padding-top:14px;margin-top:2px}
.lc-note{font-size:12.5px;color:var(--lc-muted);margin:-6px 0 0;line-height:1.5}
.lc-err{font-size:13px;font-weight:600;color:#B42328;margin:0 0 12px}
.lc-err:empty{display:none}

/* Result hero (same as the Net Worth hero) */
.lc-hero{border:0;color:#fff;padding:24px 26px;background:var(--nab-brand-strong);box-shadow:var(--nab-shadow)}
.lc-hero-l{font-size:13px;font-weight:600;color:rgba(255,255,255,.72)}
.lc-hero-v{font-size:40px;font-weight:800;letter-spacing:-.03em;line-height:1.1;margin:4px 0 6px;white-space:nowrap}
.lc-hero-v small{font-size:15px;font-weight:600;color:rgba(255,255,255,.72);letter-spacing:0;margin-left:4px}
.lc-pill{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:650;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.12);color:#fff}
.lc-pill.good{background:rgba(85,216,146,.18);color:#8EF0B9}
.lc-split{display:flex;height:10px;border-radius:999px;overflow:hidden;background:rgba(255,255,255,.12);margin:20px 0 12px}
.lc-split .p{background:#7CC0F5;transition:width .45s ease}
.lc-split .i{background:var(--nab-accent,#F28C28);transition:width .45s ease}
.lc-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 12px}
.lc-hs-l{font-size:12px;color:rgba(255,255,255,.68);display:flex;align-items:center;gap:6px}
.lc-hs-l i{width:8px;height:8px;border-radius:3px;display:block}
.lc-hs-v{font-size:18px;font-weight:750;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.lc-hero-foot{margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.14);font-size:12.5px;color:rgba(255,255,255,.75);line-height:1.5}

/* Insights */
.lc-insights{display:grid;gap:10px;margin:16px 0}
.lc-insight{display:flex;align-items:flex-start;gap:12px;background:#fff;border:1px solid var(--lc-line);border-radius:14px;padding:12px 14px;font-size:13.5px;line-height:1.5}
.lc-insight .nab-tile{flex:0 0 auto}
.lc-insight b{font-weight:700}
.lc-insight button{font:inherit;font-size:12.5px;font-weight:650;color:var(--lc-blue);background:none;border:0;padding:0;margin-left:2px;cursor:pointer;text-decoration:underline;text-underline-offset:2px}

/* Chart + schedule */
.lc-chart{position:relative;height:260px}
.lc-legend{display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:var(--lc-muted)}
.lc-legend span{display:inline-flex;align-items:center;gap:6px}
.lc-legend i{width:14px;height:3px;border-radius:2px;display:block}
.lc-legend i.dash{background:repeating-linear-gradient(90deg,#98A2B3 0 4px,transparent 4px 7px)}
.lc-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.lc-table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:520px}
.lc-table th{font-size:11.5px;font-weight:700;color:var(--lc-faint);text-transform:uppercase;letter-spacing:.06em;text-align:right;padding:8px 10px;border-bottom:1px solid var(--lc-line)}
.lc-table th:first-child,.lc-table td:first-child{text-align:left}
.lc-table td{text-align:right;padding:10px;border-bottom:1px solid var(--lc-line2);white-space:nowrap;font-variant-numeric:tabular-nums}
.lc-table tr:last-child td{border-bottom:0}
.lc-table td.int{color:#B54708}
.lc-table tfoot td{font-weight:700;border-top:1px solid var(--lc-line)}

/* Next steps */
.lc-next{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.lc-next a{display:flex;align-items:center;gap:12px;padding:14px;border:1px solid var(--lc-line);border-radius:14px;color:var(--lc-text);text-decoration:none;background:#fff}
.lc-next a:hover{border-color:#CFE0EF;background:#F7FAFD}
.lc-next b{display:block;font-size:14px}
.lc-next span span{display:block;font-size:12.5px;color:var(--lc-muted);margin-top:2px}
.lc-tips{margin:0;padding-left:18px;color:#344054;font-size:13.5px;line-height:1.6}
.lc-tips li{margin-bottom:6px}

@media(max-width:900px){
  .lc-main{grid-template-columns:1fr}
  .lc-side{position:static;order:-1} /* the answer first on small screens */
}
@media(max-width:640px){
  .lc-title{font-size:24px}
  .lc-card{padding:16px}
  .lc-hero{padding:20px 18px}
  .lc-hero-v{font-size:34px}
  .lc-hs-v{font-size:16px}
  .lc-chart{height:220px}
  .lc-next{grid-template-columns:1fr}
  .lc-form input,.lc-form select{font-size:16px} /* stops iOS zooming into the field */
  .nab-loancalc-page .nab-hamburger{position:absolute}
}
@media(max-width:420px){
  .lc-grid{grid-template-columns:1fr}
}
</style>
</head>
<body <?php body_class( 'nab-portal-body nab-loancalc-page' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( 'loan-calc' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title"><?php echo nab_portal_icon_tile( 'calculator', 'blue', 30 ); ?> Loan Calculator</div>
      <div class="nab-topbar-right">
        <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="lc-wrap">

        <section class="lc-head">
          <div class="lc-eyebrow">Loan calculator</div>
          <h1 class="lc-title">See what a loan will really cost</h1>
          <p class="lc-sub">Enter the amount, the rate and how long you'll take to pay it back. You'll see your payment, the interest you'll pay, and how much paying a little extra saves you.</p>
        </section>

        <div class="lc-main">
          <section class="lc-card" aria-label="Loan details">
            <div class="lc-presets" role="group" aria-label="Type of loan">
              <?php foreach ( $presets as $key => $p ) : ?>
              <button type="button" class="lc-preset" data-lc-preset="<?php echo esc_attr( $key ); ?>" aria-pressed="false"><?php echo nab_portal_icon_tile( $p['icon'], $p['tone'], 28 ); ?><?php echo esc_html( $p['label'] ); ?></button>
              <?php endforeach; ?>
            </div>
            <?php if ( $my_loans ) : ?>
            <div class="lc-mine" id="lcMine"><span>Use a balance from your accounts:</span></div>
            <?php endif; ?>

            <form class="lc-form" id="lcForm" novalidate>
              <div class="lc-err" id="lcErr" role="alert"></div>
              <label>Loan amount
                <span class="lc-unit" data-pre="$"><input type="number" name="amount" min="100" max="5000000" step="100" inputmode="decimal" autocomplete="off"></span>
                <input type="range" class="lc-range" name="amountRange" min="1000" max="100000" step="500" aria-label="Loan amount slider">
              </label>
              <div class="lc-grid">
                <label>Interest rate <small>(per year)</small>
                  <span class="lc-unit post" data-post="%"><input type="number" name="rate" min="0" max="60" step="0.01" inputmode="decimal" autocomplete="off"></span>
                </label>
                <label>How often you pay
                  <select name="freq">
                    <option value="monthly">Monthly</option>
                    <option value="semimonthly">Twice a month</option>
                    <option value="biweekly">Every 2 weeks</option>
                    <option value="accelerated">Every 2 weeks (accelerated)</option>
                    <option value="weekly">Weekly</option>
                  </select>
                </label>
              </div>
              <label>Length of the loan
                <span class="lc-term">
                  <span class="lc-unit post" data-post="yrs"><input type="number" name="years" min="0" max="40" step="1" inputmode="numeric" aria-label="Years"></span>
                  <span class="lc-unit post" data-post="mos"><input type="number" name="months" min="0" max="11" step="1" inputmode="numeric" aria-label="Months"></span>
                </span>
              </label>
              <div class="lc-more">
                <div class="lc-grid">
                  <label>Down payment or trade-in <small>(optional)</small>
                    <span class="lc-unit" data-pre="$"><input type="number" name="down" min="0" step="100" inputmode="decimal" placeholder="0"></span>
                  </label>
                  <label>Extra on each payment <small>(optional)</small>
                    <span class="lc-unit" data-pre="$"><input type="number" name="extra" min="0" step="10" inputmode="decimal" placeholder="0"></span>
                  </label>
                </div>
                <p class="lc-note" id="lcNote"></p>
              </div>
            </form>
          </section>

          <aside class="lc-side">
            <section class="lc-card lc-hero" aria-live="polite" aria-label="Your result">
              <div class="lc-hero-l" id="lcPayLabel">Your monthly payment</div>
              <div class="lc-hero-v" id="lcPay">$0</div>
              <span class="lc-pill" id="lcPayoff">Enter your loan</span>
              <div class="lc-split" aria-hidden="true"><span class="p" id="lcSplitP" style="width:0"></span><span class="i" id="lcSplitI" style="width:0"></span></div>
              <div class="lc-stats">
                <div><div class="lc-hs-l"><i style="background:#7CC0F5"></i>You borrow</div><div class="lc-hs-v" id="lcPrincipal">$0</div></div>
                <div><div class="lc-hs-l"><i style="background:#F28C28"></i>Total interest</div><div class="lc-hs-v" id="lcInterest">$0</div></div>
                <div><div class="lc-hs-l">Total you pay back</div><div class="lc-hs-v" id="lcTotal">$0</div></div>
                <div><div class="lc-hs-l">Number of payments</div><div class="lc-hs-v" id="lcCount">0</div></div>
              </div>
              <div class="lc-hero-foot" id="lcHeroFoot">This is an estimate. Your lender's numbers can differ a little because of fees and how they count days.</div>
            </section>
          </aside>
        </div>

        <div class="lc-insights" id="lcInsights"></div>

        <section class="lc-card" id="lcChartCard">
          <div class="lc-card-head">
            <div>
              <h2 class="lc-card-title">What you still owe over time</h2>
              <div class="lc-card-sub">Your balance after each year of payments</div>
            </div>
            <div class="lc-legend" id="lcLegend">
              <span><i style="background:#0D5C9B"></i>Balance</span>
              <span id="lcLegendBase" hidden><i class="dash"></i>Without extra payments</span>
            </div>
          </div>
          <div class="lc-chart"><canvas id="lcChart" role="img" aria-label="Loan balance over time"></canvas></div>
        </section>

        <section class="lc-card">
          <div class="lc-card-head">
            <div>
              <h2 class="lc-card-title">Year by year</h2>
              <div class="lc-card-sub">How each year's payments are split between the loan and the interest</div>
            </div>
          </div>
          <div class="lc-table-wrap">
            <table class="lc-table">
              <thead><tr><th>Year</th><th>You pay</th><th>To the loan</th><th>Interest</th><th>Still owe</th></tr></thead>
              <tbody id="lcRows"></tbody>
              <tfoot><tr><td>Total</td><td id="lcFootPaid"></td><td id="lcFootPrin"></td><td id="lcFootInt" class="int"></td><td></td></tr></tfoot>
            </table>
          </div>
        </section>

        <?php if ( $lfp_url !== '#' || $auto_url !== '#' ) : ?>
        <section class="lc-card">
          <h2 class="lc-card-title" style="margin-bottom:12px">Ready to look for a loan?</h2>
          <div class="lc-next">
            <?php if ( $auto_url !== '#' ) : ?>
            <a href="<?php echo esc_url( $auto_url ); ?>"><?php echo nab_portal_icon_tile( 'car', 'orange', 36 ); ?><span><b>Auto Loan Matcher</b><span>See car loan offers from Canadian lenders</span></span></a>
            <?php endif; ?>
            <?php if ( $lfp_url !== '#' ) : ?>
            <a href="<?php echo esc_url( $lfp_url ); ?>"><?php echo nab_portal_icon_tile( 'landmark', 'navy', 36 ); ?><span><b>Lending Finder Program</b><span>Find lenders that fit your profile</span></span></a>
            <?php endif; ?>
          </div>
        </section>
        <?php endif; ?>

        <section class="lc-card">
          <h2 class="lc-card-title" style="margin-bottom:12px">Loan tips</h2>
          <ul class="lc-tips">
            <li>A shorter loan has a bigger payment but costs far less interest. Try changing the length to see the difference.</li>
            <li>Your credit score has a big effect on your rate. Even 2% lower can save you hundreds or thousands of dollars.</li>
            <li>Paying every 2 weeks instead of monthly, or adding a small extra amount, pays the loan off sooner.</li>
            <li>Before you sign, ask if there's a penalty for paying the loan off early, and what fees are added to the amount.</li>
            <li>Keep your payment comfortable. A good rule is to keep all your debt payments under about 36% of your take-home pay.</li>
          </ul>
        </section>

      </div>
    </div>
  </main>
</div>

<?php nab_portal_print_icons_js( array_merge( [ 'trending-down', 'clock', 'piggy-bank', 'gauge', 'info', 'calendar' ], wp_list_pluck( $types, 'icon' ) ) ); ?>
<script>
window.nabLoanData = <?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>;
</script>

<?php nab_footer_scripts(); ?>
<?php wp_footer(); ?>
</body>
</html>
