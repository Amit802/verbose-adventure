<?php
/**
 * Template Name: Credit Dispute Center
 * v1.14.0 Dispute Letter Builder, reworked in v1.18.0 from Matthew's review.
 * The member picks what is wrong and one bureau, lists every account or
 * item, checks their details, reviews the whole letter and downloads the
 * PDF. They sign it and submit it on the bureau's website themselves, then
 * track it under My Letters (Draft → Ready to send → Sent → Response
 * received → Resolved or Not resolved). NAB sends nothing.
 *
 * Data, letter wording, PDF, private files and AJAX: inc/disputes.php
 * Script: assets/js/nab-disputes.js (enqueued in inc/ajax-handlers.php)
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

nab_head_open( 'Dispute Center | NAB Member Portal' );
?>
<style>
body.nab-dispute-page{
  --dl-bg:var(--nab-bg);--dl-card:var(--nab-surface);--dl-line:var(--nab-line);--dl-line2:var(--nab-line-soft);
  --dl-text:var(--nab-text);--dl-muted:var(--nab-text-2);--dl-faint:var(--nab-text-3);
  --dl-blue:var(--nab-brand);--dl-blue2:var(--nab-brand-strong);--dl-green:var(--nab-good);--dl-amber:var(--nab-accent);--dl-red:var(--nab-bad);
  color:var(--dl-text)
}
body.nab-dispute-page [hidden]{display:none!important}
.dl-wrap{max-width:1040px;margin:0 auto;padding-bottom:40px}
.dl-head{margin-bottom:16px}
.dl-eyebrow{margin-bottom:8px}
.dl-title{font-size:28px;line-height:1.18;margin:0 0 8px}
.dl-sub{font-size:14.5px;color:var(--dl-muted);margin:0;max-width:640px;line-height:1.55}

.dl-notice{display:flex;gap:10px;align-items:flex-start;background:#FFF8E6;border:1px solid #F5DFA6;color:#6B4A00;border-radius:12px;padding:12px 14px;font-size:13px;line-height:1.55;margin-bottom:16px}
.dl-notice .nab-i{flex-shrink:0;margin-top:1px}

.dl-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 16px;list-style:none;padding:0}
.dl-step{background:var(--dl-card);border:1px solid var(--dl-line);border-radius:14px;padding:14px 16px;min-width:0;box-shadow:var(--nab-shadow)}
.dl-step-n{width:24px;height:24px;border-radius:999px;background:var(--nab-brand-soft);color:var(--dl-blue);font-size:12px;font-weight:750;display:flex;align-items:center;justify-content:center;margin-bottom:8px}
.dl-step-t{font-size:13.5px;font-weight:650;margin-bottom:2px}
.dl-step-d{font-size:12.5px;color:var(--dl-muted);line-height:1.45}

.dl-card{background:var(--dl-card);border:1px solid var(--dl-line);border-radius:14px;padding:20px 22px;margin-bottom:16px;box-shadow:var(--nab-shadow)}
.dl-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.dl-card-title{font-size:16px;font-weight:700;margin:0}
.dl-card-sub{font-size:12.5px;color:var(--dl-muted);margin-top:3px}
.dl-actions{display:flex;gap:8px;flex-wrap:wrap}
.dl-btn{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;gap:6px;font:inherit;font-size:13px;font-weight:600;line-height:1;padding:10px 14px;border-radius:10px;border:1px solid var(--dl-line);background:#fff;color:var(--dl-text);cursor:pointer;text-decoration:none;white-space:nowrap}
.dl-btn:hover{background:#F8F9FB;border-color:#D9DEE6;color:var(--dl-text)}
.dl-btn-primary{background:var(--dl-blue);border-color:var(--dl-blue);color:#fff}
.dl-btn-primary:hover{background:var(--dl-blue2);border-color:var(--dl-blue2);color:#fff}
.dl-btn-accent{background:var(--nab-accent);border-color:var(--nab-accent);color:#fff}
.dl-btn-accent:hover{background:var(--nab-accent-strong);border-color:var(--nab-accent-strong);color:#fff}
.dl-btn-danger{color:var(--dl-red)}
.dl-btn-sm{padding:8px 11px;font-size:12.5px}
.dl-btn-block{width:100%}
.dl-btn[disabled]{opacity:.6;cursor:wait}

/* My letters */
.dl-list{list-style:none;margin:0;padding:0}
.dl-row{display:grid;grid-template-columns:40px minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--dl-line2)}
.dl-row:last-child{border-bottom:0}
.dl-row-t{font-size:14px;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dl-row-s{font-size:12.5px;color:var(--dl-muted);margin-top:2px}
.dl-row-a{display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
.dl-chip{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:650;padding:4px 9px;border-radius:999px;white-space:nowrap}
.dl-chip-draft{background:var(--nab-line-soft);color:var(--dl-muted)}
.dl-chip-ready{background:var(--nab-warn-soft);color:var(--nab-warn)}
.dl-chip-sent{background:var(--nab-brand-soft);color:var(--dl-blue)}
.dl-chip-response{background:#EEEDFB;color:#4A4FC0}
.dl-chip-resolved{background:var(--nab-good-soft);color:var(--nab-good)}
.dl-chip-unresolved{background:var(--nab-bad-soft);color:var(--nab-bad)}
.dl-empty{text-align:center;padding:20px 10px 8px;color:var(--dl-muted);font-size:14px}
.dl-empty p{margin:0 0 12px}

/* Builder */
.dl-form h3{font-size:14.5px;font-weight:700;margin:24px 0 10px;display:flex;align-items:center;gap:8px}
.dl-form h3:first-of-type{margin-top:0}
.dl-form h3 .dl-step-n{margin:0}
.dl-lead{font-size:13px;color:var(--dl-muted);margin:-4px 0 12px;line-height:1.5}
.dl-choices{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.dl-choices.two{grid-template-columns:repeat(2,minmax(0,1fr))}
.dl-choice{position:relative;display:flex;gap:10px;align-items:flex-start;border:1.5px solid var(--dl-line);border-radius:12px;padding:12px;cursor:pointer;background:#fff;min-width:0;transition:border-color .15s,background-color .15s,box-shadow .2s}
.dl-choice:hover{border-color:#C9D2DE}
.dl-choice input{position:absolute;opacity:0;pointer-events:none}
.dl-choice:has(input:checked){border-color:var(--dl-blue);background:#F3F8FD;box-shadow:0 0 0 3px var(--nab-brand-ring)}
.dl-choice:has(input:focus-visible){outline:2px solid var(--dl-blue);outline-offset:2px}
.dl-choice-t{display:block;font-size:13.5px;font-weight:650;line-height:1.3}
.dl-choice-d{display:block;font-size:12.5px;color:var(--dl-muted);line-height:1.45;margin-top:3px}
.dl-bureau{align-items:center;padding:14px 16px}
.dl-logo{display:flex;align-items:center;justify-content:center;width:132px;height:40px;flex:none;border-radius:8px;background:#fff;border:1px solid var(--dl-line2)}
.dl-logo img{max-width:116px;max-height:30px;object-fit:contain}
.dl-wordmark{font-size:17px;font-weight:800;letter-spacing:.02em}
.dl-wordmark-equifax{color:#9E1B32;letter-spacing:.08em;font-size:15px}
.dl-wordmark-transunion{color:#00A6CA}
.dl-remind{display:flex;gap:10px;align-items:flex-start;background:var(--nab-brand-soft);border-radius:12px;padding:12px 14px;font-size:13px;line-height:1.55;color:var(--dl-text);margin-top:10px}
.dl-remind .nab-i{color:var(--dl-blue);flex-shrink:0;margin-top:1px}
.dl-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.dl-grid .wide{grid-column:1/-1}
.dl-form label{display:block;font-size:13px;font-weight:600;color:var(--dl-text)}
.dl-form label.dl-choice,.dl-form label.dl-drop{display:flex}
.dl-form label .opt{font-weight:500;color:var(--dl-faint)}
.dl-form input[type=text],.dl-form input[type=date],.dl-form select,.dl-form textarea{display:block;width:100%;box-sizing:border-box;margin-top:6px;padding:10px 12px;font:inherit;font-size:14px}
.dl-form input,.dl-form select{height:44px}
.dl-form textarea{min-height:68px;resize:vertical;line-height:1.45}
.dl-hint{display:block;font-size:12px;font-weight:400;color:var(--dl-muted);margin-top:4px}
.dl-items{display:flex;flex-direction:column;gap:12px}
.dl-item{border:1px solid var(--dl-line);border-radius:12px;padding:14px;background:var(--nab-surface-2)}
.dl-item-h{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.dl-item-n{font-size:12px;font-weight:750;color:var(--dl-blue);text-transform:uppercase;letter-spacing:.06em}
.dl-item-x{border:0;background:none;color:var(--dl-muted);cursor:pointer;padding:5px;border-radius:7px;display:flex}
.dl-item-x:hover{color:var(--dl-red);background:#FDECEC}
.dl-add{margin-top:12px}
.dl-drop{display:flex;align-items:center;gap:12px;border:1.5px dashed var(--nab-line-strong);border-radius:12px;padding:14px;background:var(--nab-surface-2);cursor:pointer}
.dl-drop:hover{border-color:var(--dl-blue)}
.dl-drop input{position:absolute;width:1px;height:1px;opacity:0}
.dl-drop-t{font-size:13.5px;font-weight:650}
.dl-drop-d{font-size:12.5px;color:var(--dl-muted)}
.dl-picked{list-style:none;margin:8px 0 0;padding:0;font-size:13px;color:var(--dl-muted)}
.dl-picked li{display:flex;align-items:center;gap:6px;padding:3px 0}
.dl-msg{font-size:13px;margin-top:14px;min-height:1px}
.dl-msg.err{color:var(--dl-red)}
.dl-msg.ok{color:var(--dl-green)}
.dl-form-foot{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}

/* Preview */
.dl-preview{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px;align-items:start}
.dl-paper{background:#fff;border:1px solid var(--dl-line);border-radius:6px;box-shadow:0 6px 20px rgba(16,24,40,.06);padding:44px 48px;font-family:Helvetica,Arial,sans-serif!important;font-size:13.5px;line-height:1.55;color:#111;min-width:0;overflow-wrap:anywhere}
.dl-paper *{font-family:Helvetica,Arial,sans-serif!important}
.dl-paper p{margin:0 0 11px}
.dl-paper .b{font-weight:700}
.dl-paper ul{margin:0 0 11px;padding-left:20px}
.dl-paper li{margin:0 0 3px}
.dl-paper .item{margin:0 0 12px}
.dl-paper .item-t{font-weight:700;margin-bottom:3px}
.dl-paper .item-r{padding-left:16px;margin:0 0 2px}
.dl-paper .sign{height:34px;border-bottom:1px solid #111;width:220px;margin:0 0 6px}
.dl-side{display:flex;flex-direction:column;gap:12px}
.dl-box{border:1px solid var(--dl-line);border-radius:12px;padding:14px;background:#fff}
.dl-box h4{font-size:14px;font-weight:700;margin:0 0 8px}
.dl-box p{font-size:12.5px;color:var(--dl-muted);margin:0 0 8px;line-height:1.5}
.dl-checks{list-style:none;margin:0;padding:0;font-size:12.5px;line-height:1.5}
.dl-checks li{display:flex;gap:8px;align-items:flex-start;padding:4px 0;color:var(--nab-warn)}
.dl-checks li .nab-i{flex-shrink:0;margin-top:2px}
.dl-checks.ok li{color:var(--nab-good)}
.dl-todo{list-style:none;margin:0 0 10px;padding:0;font-size:13px;line-height:1.5}
.dl-todo li{display:flex;gap:9px;align-items:flex-start;padding:5px 0;border-bottom:1px solid var(--dl-line2)}
.dl-todo li:last-child{border-bottom:0}
.dl-todo .n{width:20px;height:20px;border-radius:50%;background:var(--nab-brand-soft);color:var(--dl-blue);font-size:11px;font-weight:750;display:flex;align-items:center;justify-content:center;flex:none;margin-top:1px}
.dl-todo small{display:block;color:var(--dl-muted);font-size:12px}
.dl-timeline{list-style:none;margin:0 0 12px;padding:0}
.dl-tl{display:flex;gap:10px;align-items:flex-start;position:relative;padding:0 0 12px}
.dl-tl:not(:last-child)::before{content:"";position:absolute;left:9px;top:20px;bottom:0;width:2px;background:var(--dl-line)}
.dl-tl.done:not(:last-child)::before{background:var(--dl-blue)}
.dl-tl-dot{width:20px;height:20px;border-radius:50%;border:2px solid var(--dl-line);background:#fff;flex:none;box-sizing:border-box;display:flex;align-items:center;justify-content:center;color:#fff}
.dl-tl.done .dl-tl-dot{background:var(--dl-blue);border-color:var(--dl-blue)}
.dl-tl.now .dl-tl-dot{border-color:var(--dl-blue);box-shadow:0 0 0 4px var(--nab-brand-ring)}
.dl-tl.bad .dl-tl-dot{background:var(--dl-red);border-color:var(--dl-red)}
.dl-tl.good .dl-tl-dot{background:var(--dl-green);border-color:var(--dl-green)}
.dl-tl-t{font-size:13px;font-weight:650;line-height:1.3}
.dl-tl-d{font-size:12px;color:var(--dl-muted)}
.dl-tl.todo .dl-tl-t{color:var(--dl-faint);font-weight:550}
.dl-next{display:flex;flex-direction:column;gap:8px}
.dl-change{display:flex;align-items:center;gap:8px;margin-top:10px;font-size:12.5px;color:var(--dl-muted)}
.dl-change select{flex:1;height:34px;padding:0 30px 0 10px;font-size:12.5px;border-radius:8px}
.dl-notes textarea{width:100%;box-sizing:border-box;min-height:80px;padding:9px 11px;font:inherit;font-size:13px;resize:vertical}
.dl-files{list-style:none;margin:0 0 10px;padding:0}
.dl-file{display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--dl-line2);font-size:13px;min-width:0}
.dl-file:last-child{border-bottom:0}
.dl-file a{color:var(--dl-text);text-decoration:none;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;min-width:0}
.dl-file a:hover{color:var(--dl-blue)}
.dl-file small{color:var(--dl-faint);white-space:nowrap}
.dl-file-tag{font-size:11px;font-weight:650;padding:2px 7px;border-radius:999px;background:var(--nab-line-soft);color:var(--dl-muted);white-space:nowrap}
.dl-upload{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.dl-upload select{height:34px;padding:0 30px 0 10px;font-size:12.5px;border-radius:8px;flex:1;min-width:0}
.dl-upload label{cursor:pointer}
.dl-upload input[type=file]{position:absolute;width:1px;height:1px;opacity:0}
.dl-side-msg{font-size:12.5px;margin-top:6px;min-height:1px}
.dl-side-msg.ok{color:var(--dl-green)}
.dl-side-msg.err{color:var(--dl-red)}

.dl-tips{margin:0;padding-left:18px;font-size:13.5px;line-height:1.6;color:var(--dl-text)}
.dl-tips li{margin-bottom:4px}

@media(max-width:900px){
  .dl-preview{grid-template-columns:1fr}
  .dl-steps{grid-template-columns:repeat(2,minmax(0,1fr))}
  .dl-choices{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:600px){
  .dl-title{font-size:24px}
  .dl-card{padding:16px}
  .dl-paper{padding:24px 18px;font-size:13px}
  .dl-grid,.dl-choices,.dl-choices.two{grid-template-columns:1fr}
  .dl-row{grid-template-columns:40px minmax(0,1fr)}
  .dl-row-a{grid-column:1/-1;justify-content:flex-start}
  .dl-form input,.dl-form select,.dl-form textarea{font-size:16px} /* stops iOS zooming into the field */
  .dl-logo{width:112px}
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
          <p class="dl-sub">Found an error on your credit report? Tell us what's wrong and we'll write a clear, professional letter for you. Review it, download it, then submit it on the Equifax or TransUnion website.</p>
        </section>

        <div class="dl-notice" role="note"><?php echo nab_portal_icon( 'info', 18 ); ?><span><?php echo esc_html( $disclosure ); ?></span></div>

        <ol class="dl-steps" aria-label="How it works">
          <li class="dl-step"><div class="dl-step-n">1</div><div class="dl-step-t">Choose the error and bureau</div><div class="dl-step-d">One bureau per letter.</div></li>
          <li class="dl-step"><div class="dl-step-n">2</div><div class="dl-step-t">Add your accounts</div><div class="dl-step-d">List every account or item you are disputing.</div></li>
          <li class="dl-step"><div class="dl-step-n">3</div><div class="dl-step-t">Review and download</div><div class="dl-step-d">Check the whole letter, then download the PDF.</div></li>
          <li class="dl-step"><div class="dl-step-n">4</div><div class="dl-step-t">Sign, send and track</div><div class="dl-step-d">Submit it to the bureau and track it here.</div></li>
        </ol>

        <!-- My letters -->
        <section class="dl-card" id="dlLettersCard">
          <div class="dl-card-head">
            <div>
              <h2 class="dl-card-title">My letters</h2>
              <div class="dl-card-sub">Every letter you create is saved here. Track where each one is and download it again anytime.</div>
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
            <p class="dl-lead">Pick the option that best describes the error. Not sure? Choose General dispute.</p>
            <div class="dl-choices" role="radiogroup" aria-label="What are you disputing?">
              <?php foreach ( $data['types'] as $tk => $t ) : ?>
              <label class="dl-choice">
                <input type="radio" name="type" value="<?php echo esc_attr( $tk ); ?>">
                <?php echo nab_portal_icon_tile( $t['icon'], $t['tone'], 34 ); ?>
                <span><span class="dl-choice-t"><?php echo esc_html( $t['label'] ); ?></span><span class="dl-choice-d"><?php echo esc_html( $t['desc'] ); ?></span></span>
              </label>
              <?php endforeach; ?>
            </div>

            <h3><span class="dl-step-n">2</span> Which credit bureau shows the error?</h3>
            <div class="dl-choices two" role="radiogroup" aria-label="Credit bureau">
              <?php foreach ( $data['bureaus'] as $bk => $b ) : ?>
              <label class="dl-choice dl-bureau">
                <input type="radio" name="bureau" value="<?php echo esc_attr( $bk ); ?>">
                <span class="dl-logo">
                  <?php if ( ! empty( $b['logo'] ) ) : ?>
                    <img src="<?php echo esc_url( $b['logo'] ); ?>" alt="<?php echo esc_attr( $b['name'] ); ?> logo">
                  <?php else : ?>
                    <span class="dl-wordmark dl-wordmark-<?php echo esc_attr( $bk ); ?>"><?php echo $bk === 'equifax' ? 'EQUIFAX' : 'TransUnion'; ?></span>
                  <?php endif; ?>
                </span>
                <span><span class="dl-choice-t"><?php echo esc_html( $b['name'] ); ?></span><span class="dl-choice-d">One letter for this bureau</span></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div class="dl-remind" role="note"><?php echo nab_portal_icon( 'info', 18 ); ?><span>Make sure you select the bureau where the error appears on your credit report. If the same error appears on both Equifax and TransUnion, you will need to create a separate dispute letter for each bureau.</span></div>

            <div id="dlTypeSection" hidden>
              <h3><span class="dl-step-n">3</span> <span id="dlItemsHeading">Account details</span></h3>
              <p class="dl-lead" id="dlItemsLead">Add each account you are disputing. You can add more than one.</p>
              <div class="dl-items" id="dlItems"></div>
              <button type="button" class="dl-btn dl-btn-sm dl-add" id="dlAddItem"><?php echo nab_portal_icon( 'plus', 14 ); ?> <span>Add another account</span></button>
              <div class="dl-grid" id="dlExtras" style="margin-top:14px"></div>
            </div>

            <h3><span class="dl-step-n" id="dlYouStep">3</span> Your details</h3>
            <p class="dl-lead">Filled in from your profile. Please check them and make any changes. These go at the top of your letter.</p>
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

            <h3><span class="dl-step-n" id="dlDocsStep">4</span> Supporting documents <span class="opt" style="font-weight:500;color:var(--dl-faint)">(optional)</span></h3>
            <p class="dl-lead">Save copies of your proof here so you have them ready when you submit. They stay private to you and are not sent anywhere.</p>
            <label class="dl-drop" for="dlDocs">
              <?php echo nab_portal_icon_tile( 'file-text', 'blue', 36 ); ?>
              <span><span class="dl-drop-t" style="display:block">Choose files</span><span class="dl-drop-d">PDF or photos, up to <?php echo (int) $data['maxFileMb']; ?> MB each</span></span>
              <input type="file" id="dlDocs" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,application/pdf,image/*">
            </label>
            <ul class="dl-picked" id="dlPicked"></ul>

            <div class="dl-msg" role="status" id="dlMsg"></div>
            <div class="dl-form-foot">
              <button type="submit" class="dl-btn dl-btn-primary" id="dlSubmit"><?php echo nab_portal_icon( 'file-text', 16 ); ?> <span>Review my letter</span></button>
              <button type="button" class="dl-btn" data-dl-close>Cancel</button>
            </div>
          </form>
        </section>

        <!-- Preview and tracking -->
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
                <h4>Check before you download</h4>
                <ul class="dl-checks" id="dlChecks"></ul>
                <a class="dl-btn dl-btn-primary dl-btn-block" id="dlDownload" href="#" style="margin-top:10px"><?php echo nab_portal_icon( 'download', 16 ); ?> Download PDF</a>
              </div>

              <div class="dl-box">
                <h4>How to send it</h4>
                <ul class="dl-todo">
                  <li><span class="n">1</span><span>Review and sign the letter.</span></li>
                  <li><span class="n">2</span><span>Attach a copy of your ID.<small>Government-issued photo ID, and proof of address if asked.</small></span></li>
                  <li><span class="n">3</span><span>Include your supporting evidence.<small id="dlAttach"></small></span></li>
                  <li><span class="n">4</span><span>Submit it through <span id="dlBureauName">the bureau</span>'s official dispute page.</span></li>
                </ul>
                <a class="dl-btn dl-btn-block" id="dlBureauLink" href="#" target="_blank" rel="noopener noreferrer"><span id="dlBureauLinkText">Open the bureau's dispute page</span> <?php echo nab_portal_icon( 'arrow-right', 16 ); ?></a>
              </div>

              <div class="dl-box">
                <h4>Status</h4>
                <ol class="dl-timeline" id="dlTimeline"></ol>
                <div class="dl-next" id="dlNext"></div>
                <label class="dl-change">Change status
                  <select id="dlStatusSel" aria-label="Change status">
                    <?php foreach ( $data['statuses'] as $sk => $st ) : ?>
                    <option value="<?php echo esc_attr( $sk ); ?>"><?php echo esc_html( $st['label'] ); ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <div class="dl-side-msg" id="dlStatusMsg" role="status"></div>
              </div>

              <div class="dl-box dl-notes">
                <h4>Notes</h4>
                <p>Anything you want to remember, like a reference number or who you spoke to.</p>
                <textarea id="dlNotes" maxlength="2000" aria-label="Notes"></textarea>
                <button type="button" class="dl-btn dl-btn-sm" id="dlNotesSave" style="margin-top:8px">Save notes</button>
                <div class="dl-side-msg" id="dlNotesMsg" role="status"></div>
              </div>

              <div class="dl-box">
                <h4>Your files</h4>
                <p>Your proof and the bureau's response, saved privately with this letter.</p>
                <ul class="dl-files" id="dlFiles"></ul>
                <div class="dl-upload">
                  <select id="dlFileKind" aria-label="What is this file?">
                    <option value="evidence">Supporting document</option>
                    <option value="response">Bureau response</option>
                  </select>
                  <label class="dl-btn dl-btn-sm" for="dlFileIn"><?php echo nab_portal_icon( 'plus', 14 ); ?> Add file</label>
                  <input type="file" id="dlFileIn" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,application/pdf,image/*">
                </div>
                <div class="dl-side-msg" id="dlFilesMsg" role="status"></div>
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
            <li>If the error isn't fixed, mark the letter as Not resolved and create a follow-up letter with more proof.</li>
          </ul>
        </section>

      </div>
    </div>
  </main>
</div>

<?php nab_portal_print_icons_js( array_merge( [ 'download', 'pencil', 'trash-2', 'x', 'check', 'clock', 'circle-check', 'circle-x', 'plus', 'file-text', 'triangle-alert', 'info', 'arrow-right' ], wp_list_pluck( $data['types'], 'icon' ) ) ); ?>
<script>
window.nabDisputeData = <?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>;
</script>

<?php nab_footer_scripts(); ?>
<?php wp_footer(); ?>
</body>
</html>
