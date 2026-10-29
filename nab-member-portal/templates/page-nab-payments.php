<?php
/**
 * Template Name: NAB Payment Calendar
 * Phase 2, item 4. Members list their bills and when they repeat, see every
 * due date on a month calendar, and tick payments off as they pay them.
 *
 * Data:    nab_bills / nab_bill_paid user meta (inc/payments.php)
 * Script:  assets/js/nab-payments.js (enqueued in inc/ajax-handlers.php)
 * Reminders for payments due within 3 days or overdue go to the
 * notification bell (nab_cal_send_reminders).
 *
 * @package NAB_Member_Portal
 * @since   1.11.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
}
do_action( 'litespeed_control_set_nocache', 'Payment calendar shows member-specific data' );

$uid  = get_current_user_id();
$data = nab_cal_page_data( $uid );

nab_head_open( 'Payment Calendar — NAB Member Portal' );
?>
<style>
body.nab-payments-page{
  --pc-bg:#F5F6F8;--pc-card:#fff;--pc-line:#E8EBF0;--pc-line2:#F1F3F6;
  --pc-text:#0F1B2D;--pc-muted:#6B7685;--pc-faint:#9AA4B2;
  --pc-blue:#0D5C9B;--pc-blue2:#0A4A7C;
  --pc-paid:#22A06B;--pc-paid-bg:#E9F7F0;--pc-paid-tx:#146C45;
  --pc-soon:#E4A11B;--pc-soon-bg:#FFF6E0;--pc-soon-tx:#7A5200;
  --pc-late:#E5484D;--pc-late-bg:#FDECEC;--pc-late-tx:#9E1D22;
  --pc-auto:#3B82F6;--pc-auto-bg:#EAF2FE;--pc-auto-tx:#1E4FA8;
  --pc-up:#8A94A3;--pc-up-bg:#F1F3F6;--pc-up-tx:#475467;
  background:var(--pc-bg)!important;color:var(--pc-text);-webkit-font-smoothing:antialiased
}
body.nab-payments-page [hidden]{display:none!important}
.nab-payments-page .nab-topbar-title{display:flex;align-items:center;gap:8px}
.pc-wrap{max-width:1040px;margin:0 auto;padding-bottom:40px}
.pc-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:18px}
.pc-eyebrow{font-size:12px;font-weight:600;color:var(--pc-faint);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
.pc-title{font-size:28px;line-height:1.15;font-weight:750;letter-spacing:-.02em;margin:0 0 6px}
.pc-sub{font-size:14px;color:var(--pc-muted);margin:0;max-width:540px;line-height:1.5}

.pc-alert{display:flex;align-items:center;gap:10px;border-radius:12px;padding:12px 14px;font-size:13.5px;margin-bottom:16px;border:1px solid}
.pc-alert.bad{background:var(--pc-late-bg);border-color:#F6C6C8;color:var(--pc-late-tx)}
.pc-alert.warn{background:var(--pc-soon-bg);border-color:#F5DFA6;color:var(--pc-soon-tx)}
.pc-alert .nab-i{flex:none}

.pc-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
.pc-tile{background:var(--pc-card);border:1px solid var(--pc-line);border-radius:14px;padding:14px 16px;min-width:0}
.pc-tile-l{font-size:12.5px;font-weight:600;color:var(--pc-muted)}
.pc-tile-v{font-size:24px;font-weight:750;letter-spacing:-.02em;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pc-tile-s{font-size:12px;color:var(--pc-faint);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pc-tile-v.pc-sm{font-size:17px;line-height:1.35;padding-top:5px}
.pc-green{color:var(--pc-paid-tx)!important}.pc-red{color:var(--pc-late-tx)!important}

.pc-card{background:var(--pc-card);border:1px solid var(--pc-line);border-radius:14px;padding:20px 22px;margin-bottom:16px}
.pc-card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.pc-card-title{font-size:16px;font-weight:700;margin:0}
.pc-card-sub{font-size:12.5px;color:var(--pc-muted);margin-top:3px}
.pc-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;font:inherit;font-size:13px;font-weight:600;line-height:1;padding:10px 14px;border-radius:10px;border:1px solid var(--pc-line);background:#fff;color:var(--pc-text);cursor:pointer;text-decoration:none;white-space:nowrap}
.pc-btn:hover{background:#F8F9FB;border-color:#D9DEE6}
.pc-btn-primary{background:var(--pc-blue);border-color:var(--pc-blue);color:#fff}
.pc-btn-primary:hover{background:var(--pc-blue2);border-color:var(--pc-blue2)}
.pc-btn-danger{color:var(--pc-late-tx)}
.pc-btn[disabled]{opacity:.6;cursor:wait}

/* Month navigation */
.pc-nav{display:flex;align-items:center;gap:6px}
.pc-nav-label{font-size:16px;font-weight:700;min-width:150px;text-align:center}
.pc-arrow{width:38px;height:38px;border:1px solid var(--pc-line);border-radius:10px;background:#fff;color:var(--pc-text);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}
.pc-arrow[disabled]{opacity:.35;cursor:default}
.pc-flip{transform:rotate(180deg)}

/* Calendar grid */
.pc-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border-top:1px solid var(--pc-line);border-left:1px solid var(--pc-line);border-radius:10px;overflow:hidden}
.pc-wd{font-size:11.5px;font-weight:650;color:var(--pc-faint);text-transform:uppercase;letter-spacing:.06em;text-align:center;padding:8px 0;background:#FAFBFC;border-right:1px solid var(--pc-line);border-bottom:1px solid var(--pc-line)}
.pc-day{position:relative;min-height:96px;padding:6px;border:0;border-right:1px solid var(--pc-line);border-bottom:1px solid var(--pc-line);background:#fff;font:inherit;color:inherit;text-align:left;cursor:pointer;display:flex;flex-direction:column;gap:4px;min-width:0}
.pc-day:hover{background:#FAFBFC}
.pc-day.pc-out{background:#FBFBFC;cursor:default}
.pc-num{font-size:12.5px;font-weight:650;color:var(--pc-muted);width:24px;height:24px;border-radius:999px;display:flex;align-items:center;justify-content:center}
.pc-day.is-today .pc-num{background:var(--pc-blue);color:#fff}
.pc-day.is-sel{box-shadow:inset 0 0 0 2px var(--pc-blue)}
.pc-chips{display:flex;flex-direction:column;gap:3px;min-width:0}
.pc-chip{display:block;font-size:11.5px;font-weight:600;line-height:1.3;padding:2px 6px;border-radius:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border-left:3px solid}
.pc-more{font-size:11px;color:var(--pc-muted);font-weight:600;padding-left:2px}
.pc-dots{display:none;gap:3px;justify-content:center;flex-wrap:wrap}
.pc-dots i{width:6px;height:6px;border-radius:999px;display:block}

.s-paid{background:var(--pc-paid-bg);color:var(--pc-paid-tx);border-color:var(--pc-paid)}
.s-soon{background:var(--pc-soon-bg);color:var(--pc-soon-tx);border-color:var(--pc-soon)}
.s-overdue{background:var(--pc-late-bg);color:var(--pc-late-tx);border-color:var(--pc-late)}
.s-auto{background:var(--pc-auto-bg);color:var(--pc-auto-tx);border-color:var(--pc-auto)}
.s-upcoming{background:var(--pc-up-bg);color:var(--pc-up-tx);border-color:var(--pc-up)}
.pc-dots i.s-paid{background:var(--pc-paid)}.pc-dots i.s-soon{background:var(--pc-soon)}.pc-dots i.s-overdue{background:var(--pc-late)}.pc-dots i.s-auto{background:var(--pc-auto)}.pc-dots i.s-upcoming{background:var(--pc-up)}

.pc-legend{display:flex;gap:14px;flex-wrap:wrap;margin-top:12px;font-size:12px;color:var(--pc-muted)}
.pc-legend span{display:inline-flex;align-items:center;gap:6px}
.pc-legend i{width:9px;height:9px;border-radius:3px;display:block}

/* Day panel + lists */
.pc-daypanel{margin-top:16px;border-top:1px solid var(--pc-line2);padding-top:14px}
.pc-daytitle{font-size:14px;font-weight:700;margin:0 0 4px}
.pc-none{font-size:13.5px;color:var(--pc-muted);margin:6px 0 0}
.pc-list{list-style:none;margin:0;padding:0}
.pc-row{display:grid;grid-template-columns:36px minmax(0,1fr) auto auto;column-gap:12px;align-items:center;padding:11px 0;border-bottom:1px solid var(--pc-line2)}
.pc-row:last-child{border-bottom:0}
.pc-ico.is-tile{background:none!important}
.pc-ico{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff;background:#8A94A3}
.pc-name{font-size:14px;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pc-meta{font-size:12.5px;color:var(--pc-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pc-amt{font-size:14px;font-weight:700;text-align:right;white-space:nowrap}
.pc-pill{display:inline-block;font-size:11.5px;font-weight:650;padding:3px 8px;border-radius:999px;margin-top:3px;white-space:nowrap}
.pc-act{display:flex;justify-content:flex-end}
.pc-tick{display:inline-flex;align-items:center;gap:5px;font:inherit;font-size:12.5px;font-weight:650;padding:8px 11px;border-radius:9px;border:1px solid var(--pc-line);background:#fff;color:var(--pc-text);cursor:pointer;white-space:nowrap}
.pc-tick:hover{border-color:var(--pc-paid);color:var(--pc-paid-tx)}
.pc-tick.is-paid{background:var(--pc-paid-bg);border-color:#BFE6D2;color:var(--pc-paid-tx)}
.pc-tick[disabled]{opacity:.6;cursor:wait}
.pc-group{font-size:11.5px;font-weight:700;color:var(--pc-faint);text-transform:uppercase;letter-spacing:.07em;margin:14px 0 2px}
.pc-group:first-child{margin-top:0}
.pc-auto-tag{display:inline-block;font-size:11px;font-weight:650;color:var(--pc-auto-tx);background:var(--pc-auto-bg);border-radius:6px;padding:1px 6px;margin-left:6px;vertical-align:1px}

.pc-empty{text-align:center;padding:8px 0 4px}
.pc-empty p{color:var(--pc-muted);font-size:14px;margin:0 0 14px;line-height:1.5}

/* Form */
.pc-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.pc-form label{display:block;font-size:12.5px;font-weight:600;color:#475467;margin-bottom:12px}
.pc-form input[type=text],.pc-form input[type=number],.pc-form input[type=date],.pc-form select{display:block;width:100%;box-sizing:border-box;height:44px;margin-top:6px;padding:0 12px;border:1px solid #D9DEE6;border-radius:10px;font:inherit;font-size:15px;color:var(--pc-text);background:#fff;outline:none;-webkit-appearance:none;appearance:none}
.pc-form select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7685' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px}
.pc-form input:focus,.pc-form select:focus{border-color:var(--pc-blue);box-shadow:0 0 0 3px rgba(13,92,155,.12)}
.pc-money{position:relative;display:block}
.pc-money input[type=number]{padding-left:26px}
.pc-money::before{content:'$';position:absolute;left:12px;top:50%;transform:translateY(-50%);margin-top:3px;color:var(--pc-faint);font-weight:600;font-size:14px;pointer-events:none}
.pc-check{display:flex!important;align-items:flex-start;gap:10px;font-weight:500!important;color:var(--pc-text)!important;font-size:13.5px!important;line-height:1.45;background:#F8F9FB;border:1px solid var(--pc-line);border-radius:10px;padding:12px}
.pc-check input{width:18px;height:18px;margin:1px 0 0;flex:none;accent-color:var(--pc-blue)}
.pc-check small{display:block;color:var(--pc-muted);font-size:12.5px}
.pc-hint{font-size:12.5px;color:var(--pc-muted);margin:-4px 0 12px}
.pc-msg{font-size:13px;font-weight:600;margin:0 0 10px}
.pc-msg:empty{display:none}
.pc-msg.ok{color:#157F4F}.pc-msg.err{color:#B42328}
.pc-form-foot{display:flex;gap:8px;flex-wrap:wrap}
.pc-form-foot .pc-btn-danger{margin-left:auto}

.pc-tips{margin:0;padding-left:18px;color:#344054;font-size:13.5px;line-height:1.6}
.pc-tips li{margin-bottom:6px}

@media(max-width:860px){
  .pc-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
  .pc-day{min-height:78px}
}
@media(max-width:640px){
  .pc-title{font-size:24px}
  .pc-summary{gap:10px}
  .pc-tile{padding:12px 14px}
  .pc-tile-v{font-size:20px}
  .pc-tile-v.pc-sm{font-size:15px;padding-top:3px}
  .pc-card{padding:16px}
  .pc-cal-head{flex-wrap:nowrap}
  .pc-cal-head .pc-card-title{display:none}
  .pc-nav{width:100%;justify-content:space-between}
  .pc-nav-label{flex:1;min-width:0}
  .pc-grid{border-radius:8px}
  .pc-wd{font-size:10.5px;letter-spacing:0;padding:6px 0}
  .pc-day{min-height:52px;padding:4px 2px;align-items:center;gap:3px}
  .pc-chips{display:none}
  .pc-dots{display:flex}
  .pc-num{font-size:12px;width:22px;height:22px}
  .pc-row{grid-template-columns:32px minmax(0,1fr) auto;row-gap:8px;column-gap:10px}
  .pc-ico{width:32px;height:32px;border-radius:9px}
  .pc-act{grid-column:2/4;justify-content:flex-start}
  .pc-form input[type=text],.pc-form input[type=number],.pc-form input[type=date],.pc-form select{font-size:16px} /* stops iOS zooming into the field */
  .pc-form-foot .pc-btn{flex:1}
  .pc-form-foot .pc-btn-danger{margin-left:0}
  .nab-payments-page .nab-hamburger{position:absolute} /* scrolls with the header instead of covering content */
}
@media(max-width:420px){
  .pc-form-grid{grid-template-columns:1fr}
}
</style>
</head>
<body <?php body_class( 'nab-portal-body nab-payments-page' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( 'payments' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title"><?php echo nab_portal_icon( 'calendar', 20 ); ?> Payment Calendar</div>
      <div class="nab-topbar-right">
        <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="pc-wrap">

        <section class="pc-head">
          <div>
            <div class="pc-eyebrow">Payments</div>
            <h1 class="pc-title">Never miss a payment</h1>
            <p class="pc-sub">Add your bills once and see every due date on one calendar. Mark each payment as paid, and we'll remind you a few days before something is due.</p>
          </div>
          <button type="button" class="pc-btn pc-btn-primary" data-pc-add><?php echo nab_portal_icon( 'plus', 16 ); ?> Add a payment</button>
        </section>

        <div class="pc-alert" id="pcAlert" role="status" hidden></div>

        <section class="pc-summary" aria-label="Payments this month">
          <div class="pc-tile"><div class="pc-tile-l" id="pcDueLabel">Due this month</div><div class="pc-tile-v" id="pcDue">$0</div><div class="pc-tile-s" id="pcDueSub">&nbsp;</div></div>
          <div class="pc-tile"><div class="pc-tile-l">Paid</div><div class="pc-tile-v" id="pcPaid">$0</div><div class="pc-tile-s" id="pcPaidSub">&nbsp;</div></div>
          <div class="pc-tile"><div class="pc-tile-l">Still to pay</div><div class="pc-tile-v" id="pcLeft">$0</div><div class="pc-tile-s" id="pcLeftSub">&nbsp;</div></div>
          <div class="pc-tile"><div class="pc-tile-l">Next payment</div><div class="pc-tile-v pc-sm" id="pcNext">None</div><div class="pc-tile-s" id="pcNextSub">&nbsp;</div></div>
        </section>

        <section class="pc-card" id="pcCalCard">
          <div class="pc-card-head pc-cal-head">
            <h2 class="pc-card-title">Calendar</h2>
            <div class="pc-nav">
              <button type="button" class="pc-arrow" data-pc-step="-1" aria-label="Previous month"><?php echo nab_portal_icon( 'chevron-right', 18, 'pc-flip' ); ?></button>
              <div class="pc-nav-label" id="pcMonthLabel" aria-live="polite">&nbsp;</div>
              <button type="button" class="pc-arrow" data-pc-step="1" aria-label="Next month"><?php echo nab_portal_icon( 'chevron-right', 18 ); ?></button>
              <button type="button" class="pc-btn" data-pc-today>Today</button>
            </div>
          </div>
          <div class="pc-grid" id="pcGrid" role="grid" aria-label="Payment calendar"></div>
          <div class="pc-legend" aria-hidden="true">
            <span><i style="background:var(--pc-paid)"></i>Paid</span>
            <span><i style="background:var(--pc-soon)"></i>Due soon</span>
            <span><i style="background:var(--pc-late)"></i>Overdue</span>
            <span><i style="background:var(--pc-auto)"></i>Autopay</span>
            <span><i style="background:var(--pc-up)"></i>Upcoming</span>
          </div>
          <div class="pc-daypanel">
            <h3 class="pc-daytitle" id="pcDayTitle">&nbsp;</h3>
            <ul class="pc-list" id="pcDayList"></ul>
          </div>
        </section>

        <section class="pc-card">
          <div class="pc-card-head">
            <div>
              <h2 class="pc-card-title">Coming up</h2>
              <div class="pc-card-sub">Overdue payments and everything due in the next 30 days</div>
            </div>
          </div>
          <div id="pcUpcoming"></div>
        </section>

        <!-- Add / edit form -->
        <section class="pc-card" id="pcFormCard" hidden>
          <form class="pc-form" id="pcForm" novalidate>
            <div class="pc-card-head">
              <div>
                <h2 class="pc-card-title" id="pcFormTitle">Add a payment</h2>
                <div class="pc-card-sub">Rent, credit card, car loan, phone, insurance, subscriptions and so on.</div>
              </div>
            </div>
            <input type="hidden" name="id" value="">
            <div class="pc-form-grid">
              <label>Name
                <input type="text" name="name" maxlength="60" placeholder="e.g. Rent" autocomplete="off">
              </label>
              <label>Amount
                <span class="pc-money"><input type="number" name="amount" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></span>
              </label>
              <label>How often
                <select name="freq">
                  <?php foreach ( $data['freqs'] as $fk => $fl ) : ?>
                  <option value="<?php echo esc_attr( $fk ); ?>"><?php echo esc_html( $fl ); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label><span id="pcStartLabel">Next due date</span>
                <input type="date" name="start" min="2000-01-01" max="2100-12-31">
              </label>
              <label>Category
                <select name="cat">
                  <option value="">No category</option>
                  <?php foreach ( $data['cats'] as $ck => $cat ) : ?>
                  <option value="<?php echo esc_attr( $ck ); ?>"><?php echo esc_html( $cat['label'] ); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <p class="pc-hint" id="pcRepeatHint">&nbsp;</p>
            <label class="pc-check">
              <input type="checkbox" name="autopay" value="1">
              <span>This is paid automatically (autopay)<small>We'll count it as paid on its due date and won't send reminders for it.</small></span>
            </label>
            <div class="pc-msg" role="status"></div>
            <div class="pc-form-foot">
              <button type="submit" class="pc-btn pc-btn-primary">Save payment</button>
              <button type="button" class="pc-btn" data-pc-close>Cancel</button>
              <button type="button" class="pc-btn pc-btn-danger" data-pc-delete hidden><?php echo nab_portal_icon( 'trash-2', 16 ); ?> Delete</button>
            </div>
          </form>
        </section>

        <section class="pc-card" id="pcBillsCard">
          <div class="pc-card-head">
            <div>
              <h2 class="pc-card-title">Your payments</h2>
              <div class="pc-card-sub" id="pcBillsSub">&nbsp;</div>
            </div>
            <button type="button" class="pc-btn" data-pc-add><?php echo nab_portal_icon( 'plus', 16 ); ?> Add</button>
          </div>
          <div class="pc-empty" id="pcEmpty" hidden>
            <p>You haven't added any payments yet. Start with the big ones, like rent and your credit card, so you can see them on the calendar.</p>
            <button type="button" class="pc-btn pc-btn-primary" data-pc-add><?php echo nab_portal_icon( 'plus', 16 ); ?> Add your first payment</button>
          </div>
          <ul class="pc-list" id="pcBills"></ul>
        </section>

        <section class="pc-card">
          <h2 class="pc-card-title" style="margin-bottom:12px">Payment tips</h2>
          <ul class="pc-tips">
            <li>Payment history is the biggest part of your credit score. Even one payment that is 30 days late can show on your report.</li>
            <li>Always pay at least the minimum on your credit cards before the due date, even when you can't pay the full balance.</li>
            <li>Autopay helps, but check your account a few days before each payment so there is enough money to cover it.</li>
            <li>If a due date doesn't line up with your payday, many lenders will move it if you ask.</li>
            <li>If you know you'll be late, call the lender before the due date. Many will work with you.</li>
          </ul>
        </section>

      </div>
    </div>
  </main>
</div>

<?php nab_portal_print_icons_js( array_merge( [ 'check', 'pencil', 'triangle-alert', 'clock', 'circle-check', 'receipt' ], wp_list_pluck( $data['cats'], 'icon' ) ) ); ?>
<script>
window.nabPayData = <?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>;
</script>

<?php nab_footer_scripts(); ?>
<?php wp_footer(); ?>
</body>
</html>
