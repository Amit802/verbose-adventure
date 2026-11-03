<?php
/**
 * Template Name: Credit Dispute Center
 * v1.14.0 — Dispute Letter Builder. The member picks what is wrong and the
 * bureau, fills in a short form, and the portal writes their dispute letter.
 * They preview it, download the PDF and submit it on the bureau's website
 * themselves. NAB sends nothing, and no admin review is needed.
 *
 * Data, letter wording, PDF and AJAX: inc/disputes.php
 * Script: assets/js/nab-disputes.js (enqueued in inc/ajax-handlers.php)
 *
 * Replaces the Gravity Forms submission + admin-set status tracker
 * (the tracker never showed: its admin values didn't match the page).
 *
 * @package NAB_Member_Portal
 * @since   1.14.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
}
do_action( 'litespeed_control_set_nocache', 'Dispute Center shows member-specific letters' );

$uid  = get_current_user_id();
$data = nab_dispute_page_data( $uid );

$disclosure = function_exists( 'get_field' ) ? trim( (string) get_field( 'dc_letter_notice', get_the_ID() ) ) : '';
if ( $disclosure === '' ) {
    $disclosure = 'NAB Solutions helps you prepare your dispute letter. We are not a credit bureau, lender, collection agency or law firm, and we do not send anything to the bureaus on your behalf. You can dispute information directly with Equifax and TransUnion at no cost. Creating a letter does not guarantee any specific outcome or change to your credit report.';
}

nab_head_open( 'Dispute Center — NAB Member Portal' );
?>
<style>
body.nab-dispute-page{
  --dl-bg:#F5F6F8;--dl-card:#fff;--dl-line:#E8EBF0;--dl-line2:#F1F3F6;
  --dl-text:#0F1B2D;--dl-muted:#6B7685;--dl-faint:#9AA4B2;
  --dl-blue:#0D5C9B;--dl-blue2:#0A4A7C;--dl-green:#22A06B;--dl-amber:#E4A11B;--dl-red:#E5484D;
  background:var(--dl-bg)!important;color:var(--dl-text);-webkit-font-smoothing:antialiased
}
body.nab-dispute-page [hidden]{display:none!important}
.nab-dispute-page .nab-topbar-title{display:flex;align-items:center;gap:8px}
.dl-wrap{max-width:980px;margin:0 auto;padding-bottom:40px}
.dl-head{margin-bottom:16px}
.dl-eyebrow{font-size:12px;font-weight:600;color:var(--dl-faint);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
.dl-title{font-size:28px;line-height:1.15;font-weight:750;letter-spacing:-.02em;margin:0 0 6px}
.dl-sub{font-size:14px;color:var(--dl-muted);margin:0;max-width:600px;line-height:1.5}

.dl-notice{display:flex;gap:10px;align-items:flex-start;background:#FFF8E6;border:1px solid #F5DFA6;color:#6B4A00;border-radius:12px;padding:12px 14px;font-size:13px;line-height:1.55;margin-bottom:16px}
.dl-notice .nab-i{flex-shrink:0;margin-top:1px}

.dl-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px;list-style:none;padding:0}
.dl-step{background:var(--dl-card);border:1px solid var(--dl-line);border-radius:14px;padding:14px 16px;min-width:0}
.dl-step-n{width:24px;height:24px;border-radius:999px;background:#E8F0F8;color:var(--dl-blue);font-size:12px;font-weight:750;display:flex;align-items:center;justify-content:center;margin-bottom:8px}
.dl-step-t{font-size:13.5px;font-weight:650;margin-bottom:2px}
.dl-step-d{font-size:12.5px;color:var(--dl-muted);line-height:1.45}

.dl-card{background:var(--dl-card);border:1px solid var(--dl-line);border-radius:14px;padding:20px 22px;margin-bottom:16px}
.dl-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.dl-card-title{font-size:16px;font-weight:700;margin:0}
.dl-card-sub{font-size:12.5px;color:var(--dl-muted);margin-top:3px}
.dl-actions{display:flex;gap:8px;flex-wrap:wrap}
.dl-btn{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;gap:6px;font:inherit;font-size:13px;font-weight:600;line-height:1;padding:10px 14px;border-radius:10px;border:1px solid var(--dl-line);background:#fff;color:var(--dl-text);cursor:pointer;text-decoration:none;white-space:nowrap}
.dl-btn:hover{background:#F8F9FB;border-color:#D9DEE6;color:var(--dl-text)}
.dl-btn-primary{background:var(--dl-blue);border-color:var(--dl-blue);color:#fff}
.dl-btn-primary:hover{background:var(--dl-blue2);border-color:var(--dl-blue2);color:#fff}
.dl-btn-danger{color:var(--dl-red)}
.dl-btn-sm{padding:8px 11px;font-size:12.5px}
.dl-btn[disabled]{opacity:.6;cursor:wait}

/* My letters */
.dl-list{list-style:none;margin:0;padding:0}
.dl-row{display:grid;grid-template-columns:40px minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--dl-line2)}
.dl-row:last-child{border-bottom:0}
.dl-row-t{font-size:14px;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dl-row-s{font-size:12.5px;color:var(--dl-muted);margin-top:2px}
.dl-row-a{display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
.dl-chip{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:650;padding:4px 9px;border-radius:999px;white-space:nowrap}
.dl-chip-created{background:#FFF4DA;color:#8A5A00}
.dl-chip-sent{background:#E8F0F8;color:var(--dl-blue)}
.dl-chip-response{background:#E3F5EC;color:#146C45}
.dl-empty{text-align:center;padding:20px 10px 8px;color:var(--dl-muted);font-size:14px}
.dl-empty p{margin:0 0 12px}

/* Builder */
.dl-form h3{font-size:14px;font-weight:700;margin:20px 0 10px;display:flex;align-items:center;gap:8px}
.dl-form h3:first-of-type{margin-top:0}
.dl-form h3 .dl-step-n{margin:0}
.dl-choices{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.dl-choices.two{grid-template-columns:repeat(2,minmax(0,1fr))}
.dl-choice{position:relative;display:flex;gap:10px;align-items:flex-start;border:1.5px solid var(--dl-line);border-radius:12px;padding:12px;cursor:pointer;background:#fff;min-width:0}
.dl-choice:hover{border-color:#C9D2DE}
.dl-choice input{position:absolute;opacity:0;pointer-events:none}
.dl-choice:has(input:checked){border-color:var(--dl-blue);background:#F3F8FD}
.dl-choice:has(input:focus-visible){outline:2px solid var(--dl-blue);outline-offset:2px}
.dl-choice-t{font-size:13.5px;font-weight:650;line-height:1.3}
.dl-choice-d{font-size:12px;color:var(--dl-muted);line-height:1.4;margin-top:2px}
.dl-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.dl-grid .wide{grid-column:1/-1}
.dl-form label{display:block;font-size:13px;font-weight:600;color:var(--dl-text)}
.dl-form label .opt{font-weight:500;color:var(--dl-faint)}
.dl-form input[type=text],.dl-form input[type=date],.dl-form select,.dl-form textarea{display:block;width:100%;box-sizing:border-box;margin-top:6px;padding:10px 12px;border:1px solid #D9DEE6;border-radius:10px;font:inherit;font-size:14px;color:var(--dl-text);background:#fff;outline:none}
.dl-form input,.dl-form select{height:44px}
.dl-form textarea{min-height:76px;resize:vertical;line-height:1.45}
.dl-form input:focus,.dl-form select:focus,.dl-form textarea:focus{border-color:var(--dl-blue);box-shadow:0 0 0 3px rgba(13,92,155,.12)}
.dl-hint{display:block;font-size:12px;font-weight:400;color:var(--dl-muted);margin-top:4px}
.dl-rows{display:flex;flex-direction:column;gap:10px}
.dl-rowset{border:1px solid var(--dl-line);border-radius:12px;padding:12px;position:relative}
.dl-rowset-h{display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:700;color:var(--dl-faint);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px}
.dl-rowset-x{border:0;background:none;color:var(--dl-muted);cursor:pointer;padding:4px;border-radius:6px;display:flex}
.dl-rowset-x:hover{color:var(--dl-red);background:#FDECEC}
.dl-msg{font-size:13px;margin-top:14px;min-height:1px}
.dl-msg.err{color:var(--dl-red)}
.dl-msg.ok{color:var(--dl-green)}
.dl-form-foot{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}

/* Preview */
.dl-preview{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:16px;align-items:start}
.dl-paper{background:#fff;border:1px solid var(--dl-line);border-radius:6px;box-shadow:0 6px 20px rgba(16,24,40,.06);padding:44px 48px;font-family:Helvetica,Arial,sans-serif;font-size:13.5px;line-height:1.55;color:#111;min-width:0;overflow-wrap:anywhere}
.dl-paper p{margin:0 0 11px}
.dl-paper .b{font-weight:700}
.dl-paper ul{margin:0 0 11px;padding-left:20px}
.dl-paper li{margin:0 0 3px}
.dl-paper .sign{height:40px}
.dl-side{display:flex;flex-direction:column;gap:12px}
.dl-box{border:1px solid var(--dl-line);border-radius:12px;padding:14px}
.dl-box h4{font-size:14px;font-weight:700;margin:0 0 8px}
.dl-box ol{margin:0;padding-left:18px;font-size:13px;line-height:1.55;color:var(--dl-text)}
.dl-box ol li{margin-bottom:6px}
.dl-box ul{margin:6px 0 0;padding-left:18px;font-size:12.5px;color:var(--dl-muted);line-height:1.5}
.dl-box .dl-btn{width:100%;margin-top:8px}
.dl-status-line{font-size:13px;color:var(--dl-muted);margin-bottom:6px}

.dl-tips{margin:0;padding-left:18px;font-size:13.5px;line-height:1.6;color:var(--dl-text)}
.dl-tips li{margin-bottom:4px}

@media(max-width:860px){
  .dl-preview{grid-template-columns:1fr}
  .dl-steps{grid-template-columns:repeat(2,minmax(0,1fr))}
  .dl-choices{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:600px){
  .dl-title{font-size:24px}
  .dl-card{padding:16px}
  .dl-paper{padding:24px 20px;font-size:13px}
  .dl-grid,.dl-choices,.dl-choices.two{grid-template-columns:1fr}
  .dl-row{grid-template-columns:40px minmax(0,1fr)}
  .dl-row-a{grid-column:1/-1;justify-content:flex-start}
  .dl-form input,.dl-form select,.dl-form textarea{font-size:16px} /* stops iOS zooming into the field */
  .nab-dispute-page .nab-hamburger{position:absolute}
}
@media(max-width:400px){
  .dl-steps{grid-template-columns:1fr}
}
</style>
</head>
<body <?php body_class( 'nab-portal-body nab-dispute-page' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( 'dispute' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title"><?php echo nab_portal_icon( 'shield-check', 20 ); ?> Dispute Center</div>
      <div class="nab-topbar-right">
        <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="dl-wrap">

        <section class="dl-head">
          <div class="dl-eyebrow">Credit disputes</div>
          <h1 class="dl-title">Create your dispute letter</h1>
          <p class="dl-sub">Found an error on your credit report? Answer a few questions and we'll write the letter for you. Download it, then submit it on the Equifax or TransUnion website.</p>
        </section>

        <div class="dl-notice" role="note"><?php echo nab_portal_icon( 'info', 18 ); ?><span><?php echo esc_html( $disclosure ); ?></span></div>

        <ol class="dl-steps" aria-label="How it works">
          <li class="dl-step"><div class="dl-step-n">1</div><div class="dl-step-t">Choose what's wrong</div><div class="dl-step-d">Pick the type of error and the bureau showing it.</div></li>
          <li class="dl-step"><div class="dl-step-n">2</div><div class="dl-step-t">Fill in your details</div><div class="dl-step-d">A short form. Your details are filled in for you next time.</div></li>
          <li class="dl-step"><div class="dl-step-n">3</div><div class="dl-step-t">Download your letter</div><div class="dl-step-d">Check the preview, then download it as a PDF.</div></li>
          <li class="dl-step"><div class="dl-step-n">4</div><div class="dl-step-t">Submit it yourself</div><div class="dl-step-d">Upload it on the bureau's website with your documents.</div></li>
        </ol>

        <!-- My letters -->
        <section class="dl-card" id="dlLettersCard">
          <div class="dl-card-head">
            <div>
              <h2 class="dl-card-title">My letters</h2>
              <div class="dl-card-sub">Every letter you create is saved here. Download it again anytime.</div>
            </div>
            <button type="button" class="dl-btn dl-btn-primary" data-dl-new><?php echo nab_portal_icon( 'plus', 16 ); ?> Create a letter</button>
          </div>
          <div class="dl-empty" id="dlEmpty" hidden>
            <p>You haven't created a dispute letter yet.</p>
            <button type="button" class="dl-btn dl-btn-primary" data-dl-new>Create your first letter</button>
          </div>
          <ul class="dl-list" id="dlList"></ul>
        </section>

        <!-- Builder -->
        <section class="dl-card" id="dlBuilder" hidden>
          <form class="dl-form" id="dlForm" novalidate>
            <div class="dl-card-head">
              <div>
                <h2 class="dl-card-title" id="dlFormTitle">Create a dispute letter</h2>
                <div class="dl-card-sub">Use the details exactly as they appear on your credit report.</div>
              </div>
            </div>

            <h3><span class="dl-step-n">1</span> What are you disputing?</h3>
            <div class="dl-choices" role="radiogroup" aria-label="What are you disputing?">
              <?php foreach ( $data['types'] as $tk => $t ) : ?>
              <label class="dl-choice">
                <input type="radio" name="type" value="<?php echo esc_attr( $tk ); ?>">
                <?php echo nab_portal_icon_tile( $t['icon'], $t['tone'], 32 ); ?>
                <span><span class="dl-choice-t"><?php echo esc_html( $t['label'] ); ?></span><span class="dl-choice-d" style="display:block"><?php echo esc_html( $t['desc'] ); ?></span></span>
              </label>
              <?php endforeach; ?>
            </div>

            <h3><span class="dl-step-n">2</span> Which credit bureau shows the error?</h3>
            <div class="dl-choices two" role="radiogroup" aria-label="Credit bureau">
              <?php foreach ( $data['bureaus'] as $bk => $b ) : ?>
              <label class="dl-choice">
                <input type="radio" name="bureau" value="<?php echo esc_attr( $bk ); ?>">
                <?php echo nab_portal_icon_tile( 'landmark', $bk === 'equifax' ? 'red' : 'teal', 32 ); ?>
                <span><span class="dl-choice-t"><?php echo esc_html( $b['name'] ); ?></span><span class="dl-choice-d" style="display:block">If the error is on both reports, create one letter for each.</span></span>
              </label>
              <?php endforeach; ?>
            </div>

            <div id="dlTypeSection" hidden>
              <h3><span class="dl-step-n">3</span> <span id="dlTypeHeading">About the item</span></h3>
              <div class="dl-grid" id="dlFields"></div>
            </div>

            <h3><span class="dl-step-n" id="dlYouStep">3</span> Your details</h3>
            <div class="dl-grid">
              <label class="wide">Full name <input type="text" name="name" maxlength="80" autocomplete="name"></label>
              <label class="wide">Street address <input type="text" name="street" maxlength="120" autocomplete="street-address"></label>
              <label>City <input type="text" name="city" maxlength="60" autocomplete="address-level2"></label>
              <label>Province or territory
                <select name="province" autocomplete="address-level1">
                  <option value="">Choose…</option>
                  <?php foreach ( $data['provinces'] as $pk => $pn ) : ?>
                  <option value="<?php echo esc_attr( $pk ); ?>"><?php echo esc_html( $pn ); ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Postal code <input type="text" name="postal" maxlength="10" autocomplete="postal-code" placeholder="A1A 1A1"></label>
            </div>

            <div class="dl-msg" role="status" id="dlMsg"></div>
            <div class="dl-form-foot">
              <button type="submit" class="dl-btn dl-btn-primary" id="dlSubmit"><?php echo nab_portal_icon( 'file-text', 16 ); ?> Create my letter</button>
              <button type="button" class="dl-btn" data-dl-close>Cancel</button>
            </div>
          </form>
        </section>

        <!-- Preview -->
        <section class="dl-card" id="dlPreviewCard" hidden>
          <div class="dl-card-head">
            <div>
              <h2 class="dl-card-title" id="dlPreviewTitle">Your letter</h2>
              <div class="dl-card-sub" id="dlPreviewSub">&nbsp;</div>
            </div>
            <div class="dl-actions">
              <button type="button" class="dl-btn" data-dl-edit><?php echo nab_portal_icon( 'pencil', 16 ); ?> Edit</button>
              <button type="button" class="dl-btn dl-btn-danger" data-dl-delete><?php echo nab_portal_icon( 'trash-2', 16 ); ?> Delete</button>
              <button type="button" class="dl-btn" data-dl-close>Close</button>
            </div>
          </div>
          <div class="dl-preview">
            <div class="dl-paper" id="dlPaper" aria-label="Letter preview"></div>
            <aside class="dl-side">
              <div class="dl-box">
                <h4>Send it yourself</h4>
                <ol>
                  <li>Download your letter.
                    <a class="dl-btn dl-btn-primary" id="dlDownload" href="#"><?php echo nab_portal_icon( 'download', 16 ); ?> Download PDF</a>
                  </li>
                  <li>Go to the <span id="dlBureauName">bureau</span> website and start a dispute. Upload your letter there.
                    <a class="dl-btn" id="dlBureauLink" href="#" target="_blank" rel="noopener noreferrer"><?php echo nab_portal_icon( 'arrow-right', 16 ); ?> <span id="dlBureauLinkText">Open the bureau's dispute page</span></a>
                  </li>
                  <li>Attach copies of:
                    <ul id="dlAttach"></ul>
                  </li>
                  <li>Come back and mark it as submitted, so we can remind you if you don't hear back.</li>
                </ol>
              </div>
              <div class="dl-box" id="dlStatusBox">
                <h4>Status</h4>
                <div class="dl-status-line" id="dlStatusLine">&nbsp;</div>
                <div id="dlStatusBtns"></div>
              </div>
            </aside>
          </div>
        </section>

        <section class="dl-card">
          <h2 class="dl-card-title" style="margin-bottom:12px">Before you submit</h2>
          <ul class="dl-tips">
            <li>Get your free credit reports from both Equifax and TransUnion first, so your letter matches exactly what each report shows.</li>
            <li>Create one letter per bureau. If the same error is on both reports, dispute it with both.</li>
            <li>Keep a copy of your letter and everything you attach.</li>
            <li>Bureaus generally have about 30 days to investigate, and they may ask you for more documents.</li>
            <li>If the bureau doesn't correct the error, you can create a new letter with more evidence.</li>
          </ul>
        </section>

      </div>
    </div>
  </main>
</div>

<?php nab_portal_print_icons_js( array_merge( [ 'download', 'pencil', 'trash-2', 'x', 'check', 'clock', 'circle-check', 'plus', 'file-text' ], wp_list_pluck( $data['types'], 'icon' ) ) ); ?>
<script>
window.nabDisputeData = <?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>;
</script>

<?php nab_footer_scripts(); ?>
<?php wp_footer(); ?>
</body>
</html>
