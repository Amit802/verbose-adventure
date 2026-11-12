<?php
/**
 * Template Name: NAB Collection Assistance
 * Phase 2, item 7. Members tell us about a debt that is with a collection
 * agency; Customer Care gets a Freshdesk ticket and contacts them. The page
 * also explains the member's rights and links to the Dispute Center.
 *
 * Data:   inc/collections.php (user meta nab_collection_requests + Freshdesk).
 * Script: assets/js/nab-collections.js (enqueued in inc/ajax-handlers.php)
 *
 * @package NAB_Member_Portal
 * @since   1.22.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
}
do_action( 'litespeed_control_set_nocache', 'Collection Assistance shows member-specific requests' );

$uid      = get_current_user_id();
$user     = get_userdata( $uid );
$name     = trim( ( get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ) . ' ' . ( get_user_meta( $uid, 'nab_last_name', true ) ?: $user->last_name ) );
$phone    = get_user_meta( $uid, 'nab_phone', true );
$opts     = nab_collection_options();
$requests = nab_collection_public( nab_collection_get( $uid ) );

$nav         = nab_get_nav_links();
$dispute_url = nab_resolve_url( $nav['dispute'] ?? '#' );
$budget_url  = nab_get_template_url( 'nab-budget' );

nab_head_open( 'Collection Assistance | NAB Member Portal' );
?>
<style>
body.nab-collect-page{
  --cl-bg:var(--nab-bg);--cl-card:var(--nab-surface);--cl-line:var(--nab-line);--cl-line2:var(--nab-line-soft);
  --cl-text:var(--nab-text);--cl-muted:var(--nab-text-2);--cl-faint:var(--nab-text-3);
  --cl-blue:var(--nab-brand);--cl-blue2:var(--nab-brand-strong);--cl-green:var(--nab-good);--cl-red:var(--nab-bad);
  background:var(--cl-bg)!important;color:var(--cl-text);-webkit-font-smoothing:antialiased
}
body.nab-collect-page [hidden]{display:none!important}
.nab-collect-page .nab-topbar-title{display:flex;align-items:center;gap:10px}
.cl-wrap{max-width:1040px;margin:0 auto;padding-bottom:40px}
.cl-head{margin-bottom:18px}
.cl-eyebrow{font-size:12px;font-weight:600;color:var(--cl-faint);text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
.cl-title{font-size:28px;line-height:1.15;font-weight:750;letter-spacing:-.02em;margin:0 0 6px}
.cl-sub{font-size:14px;color:var(--cl-muted);margin:0;max-width:600px;line-height:1.5}

.cl-card{background:var(--cl-card);border:1px solid var(--cl-line);border-radius:16px;padding:20px 22px;margin-bottom:16px}
.cl-card-title{font-size:16px;font-weight:700;margin:0}
.cl-card-sub{font-size:12.5px;color:var(--cl-muted);margin:3px 0 14px;line-height:1.5}
.cl-main{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:16px;align-items:start}
.cl-main>.cl-card,.cl-side>.cl-card{margin-bottom:16px}

.cl-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;font:inherit;font-size:14px;font-weight:650;line-height:1;padding:12px 18px;border-radius:10px;border:1px solid var(--cl-line);background:#fff;color:var(--cl-text);cursor:pointer;text-decoration:none}
.cl-btn:hover{background:#F8F9FB}
.cl-btn-primary{background:var(--cl-blue);border-color:var(--cl-blue);color:#fff}
.cl-btn-primary:hover{background:var(--cl-blue2);border-color:var(--cl-blue2);color:#fff}
.cl-btn[disabled]{opacity:.6;cursor:wait}

/* Form */
.cl-step{font-size:11.5px;font-weight:700;color:var(--cl-faint);text-transform:uppercase;letter-spacing:.07em;margin:18px 0 10px}
.cl-step:first-of-type{margin-top:0}
.cl-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.cl-form label.cl-field{display:block;font-size:12.5px;font-weight:600;color:#475467;margin-bottom:12px}
.cl-form label small{font-weight:500;color:var(--cl-faint)}
.cl-form input[type=text],.cl-form input[type=tel],.cl-form input[type=number],.cl-form select,.cl-form textarea{display:block;width:100%;box-sizing:border-box;height:44px;margin-top:6px;padding:0 12px;border:1px solid #D9DEE6;border-radius:10px;font:inherit;font-size:15px;color:var(--cl-text);background:#fff;outline:none;-webkit-appearance:none;appearance:none}
.cl-form textarea{height:auto;min-height:90px;padding:10px 12px;resize:vertical;line-height:1.5}
.cl-form select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7685' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px}
.cl-form input:focus,.cl-form select:focus,.cl-form textarea:focus{border-color:var(--cl-blue);box-shadow:0 0 0 3px rgba(13,92,155,.12)}
.cl-money{position:relative;display:block}
.cl-money input{padding-left:26px!important}
.cl-money::before{content:'$';position:absolute;left:12px;top:50%;transform:translateY(-50%);margin-top:3px;color:var(--cl-faint);font-weight:600;font-size:14px;pointer-events:none}
.cl-q{font-size:12.5px;font-weight:600;color:#475467;margin:0 0 8px}
.cl-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}
.cl-chip{position:relative;display:inline-flex}
.cl-chip input{position:absolute;opacity:0;pointer-events:none}
.cl-chip span{display:inline-flex;align-items:center;gap:6px;padding:9px 13px;border:1px solid var(--cl-line);border-radius:999px;font-size:13px;font-weight:600;color:var(--cl-text);background:#fff;cursor:pointer;transition:border-color .15s,background .15s}
.cl-chip span:hover{border-color:#CFE0EF;background:#F7FAFD}
.cl-chip input:checked+span{border-color:var(--cl-blue);background:#F2F7FC;color:var(--cl-blue2);box-shadow:0 0 0 3px rgba(13,92,155,.10)}
.cl-chip input:focus-visible+span{outline:2px solid var(--cl-blue);outline-offset:2px}
.cl-group.cl-bad .cl-q{color:#B42328}
.cl-form .cl-bad input,.cl-form input.cl-bad{border-color:var(--cl-red)}
.cl-notmine{display:flex;gap:10px;align-items:flex-start;background:#F7FAFD;border:1px solid #DCE8F4;border-radius:12px;padding:12px 14px;font-size:13px;line-height:1.5;margin:-4px 0 14px}
.cl-notmine a{color:var(--cl-blue);font-weight:650}
.cl-consent{display:flex;gap:10px;align-items:flex-start;font-size:12.5px;color:var(--cl-muted);line-height:1.5;margin:6px 0 14px}
.cl-consent input{margin-top:3px;width:16px;height:16px;accent-color:var(--cl-blue);flex:0 0 auto}
.cl-msg{font-size:13.5px;font-weight:600;margin:0 0 12px}
.cl-msg:empty{display:none}
.cl-msg.ok{color:#157F4F}.cl-msg.err{color:#B42328}

/* Requests */
.cl-req{list-style:none;margin:0;padding:0}
.cl-req li{padding:12px 0;border-bottom:1px solid var(--cl-line2)}
.cl-req li:last-child{border-bottom:0}
.cl-req-top{display:flex;justify-content:space-between;gap:10px;align-items:baseline}
.cl-req-name{font-size:14px;font-weight:700}
.cl-req-meta{font-size:12px;color:var(--cl-muted);margin-top:3px;line-height:1.45}
.cl-pill{display:inline-block;font-size:11.5px;font-weight:700;padding:3px 9px;border-radius:999px;background:#FFF4E5;color:#8A4B00;white-space:nowrap}
.cl-pill.done{background:#E9F7EF;color:#157F4F}
.cl-empty{font-size:13.5px;color:var(--cl-muted);margin:0}

/* Rights + next steps */
.cl-list{margin:0;padding-left:18px;color:#344054;font-size:13.5px;line-height:1.6}
.cl-list li{margin-bottom:6px}
.cl-card-title+.cl-list{margin-top:12px}
.cl-note{font-size:12px;color:var(--cl-faint);margin:10px 0 0;line-height:1.5}
.cl-links{display:grid;gap:10px}
.cl-links a{display:flex;align-items:center;gap:12px;padding:12px;border:1px solid var(--cl-line);border-radius:12px;color:var(--cl-text);text-decoration:none;background:#fff}
.cl-links a:hover{border-color:#CFE0EF;background:#F7FAFD}
.cl-links b{display:block;font-size:13.5px}
.cl-links span span{display:block;font-size:12px;color:var(--cl-muted);margin-top:2px}

@media(max-width:900px){.cl-main{grid-template-columns:1fr}}
@media(max-width:640px){
  .cl-title{font-size:24px}
  .cl-card{padding:16px}
  .cl-grid{grid-template-columns:1fr}
  .cl-form input,.cl-form select,.cl-form textarea{font-size:16px!important} /* stops iOS zooming into the field */
  .cl-form .cl-btn-primary{width:100%}
  .nab-collect-page .nab-hamburger{position:absolute}
}
</style>
</head>
<body <?php body_class( 'nab-portal-body nab-collect-page' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( 'collections' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title"><?php echo nab_portal_icon_tile( 'phone-call', 'orange', 30 ); ?> Collection Assistance</div>
      <div class="nab-topbar-right">
        <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="cl-wrap">

        <section class="cl-head">
          <div class="cl-eyebrow">Collection assistance</div>
          <h1 class="cl-title">Get help with a debt in collections</h1>
          <p class="cl-sub">Is a collection agency calling you or showing up on your credit report? Tell us about it and our Customer Care team will review it and contact you within 1 to 2 business days with your options.</p>
        </section>

        <div class="cl-main">
          <section class="cl-card" aria-label="Tell us about the collection">
            <h2 class="cl-card-title">Tell us about the collection</h2>
            <p class="cl-card-sub">Fill in what you know. Only the agency name is required, the rest helps us help you faster.</p>

            <form class="cl-form" id="clForm" novalidate>
              <div class="cl-step">1. The debt</div>
              <div class="cl-grid">
                <label class="cl-field">Collection agency name
                  <input type="text" name="agency" maxlength="120" placeholder="The name on the letter or call" autocomplete="off" required>
                </label>
                <label class="cl-field">Original company you owed <small>(optional)</small>
                  <input type="text" name="creditor" maxlength="120" placeholder="e.g. Rogers, a bank, a landlord" autocomplete="off">
                </label>
                <label class="cl-field">Amount they say you owe <small>(optional)</small>
                  <span class="cl-money"><input type="number" name="amount" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></span>
                </label>
                <label class="cl-field">Account or reference number <small>(optional)</small>
                  <input type="text" name="account" maxlength="40" placeholder="Last 4 digits is fine" autocomplete="off">
                </label>
              </div>

              <div class="cl-group" data-group="contact">
                <p class="cl-q">How are they contacting you? <small style="font-weight:500;color:var(--cl-faint)">(pick any)</small></p>
                <div class="cl-chips">
                  <?php foreach ( $opts['contact'] as $k => $label ) : ?>
                  <label class="cl-chip"><input type="checkbox" name="contact[]" value="<?php echo esc_attr( $k ); ?>"><span><?php echo esc_html( $label ); ?></span></label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="cl-group" data-group="mine">
                <p class="cl-q">Is this debt yours?</p>
                <div class="cl-chips" role="radiogroup">
                  <?php foreach ( $opts['mine'] as $k => $label ) : ?>
                  <label class="cl-chip"><input type="radio" name="mine" value="<?php echo esc_attr( $k ); ?>"><span><?php echo esc_html( $label ); ?></span></label>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="cl-notmine" id="clNotMine" hidden>
                <?php echo nab_portal_icon( 'info', 18 ); ?>
                <div>If it's not yours, you can also dispute it with Equifax or TransUnion right away.
                  <?php if ( $dispute_url !== '#' ) : ?><a href="<?php echo esc_url( $dispute_url ); ?>">Create a dispute letter</a><?php endif; ?></div>
              </div>

              <div class="cl-step">2. How we can help</div>
              <div class="cl-group" data-group="help">
                <p class="cl-q">What would you like help with? <small style="font-weight:500;color:var(--cl-faint)">(pick any)</small></p>
                <div class="cl-chips">
                  <?php foreach ( $opts['help'] as $k => $label ) : ?>
                  <label class="cl-chip"><input type="checkbox" name="help[]" value="<?php echo esc_attr( $k ); ?>"><span><?php echo esc_html( $label ); ?></span></label>
                  <?php endforeach; ?>
                </div>
              </div>
              <label class="cl-field">Anything else we should know? <small>(optional)</small>
                <textarea name="notes" maxlength="2000" placeholder="For example, when the calls started, or what they've told you."></textarea>
              </label>

              <div class="cl-step">3. How to reach you</div>
              <div class="cl-grid">
                <label class="cl-field">Your name
                  <input type="text" name="name" maxlength="120" value="<?php echo esc_attr( $name ); ?>" autocomplete="name">
                </label>
                <label class="cl-field">Phone number
                  <input type="tel" name="phone" maxlength="30" value="<?php echo esc_attr( $phone ); ?>" autocomplete="tel" placeholder="(403) 555-0123">
                </label>
                <label class="cl-field">Best time to call
                  <select name="time">
                    <?php foreach ( $opts['time'] as $k => $label ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
              </div>

              <label class="cl-consent" data-group="consent"><input type="checkbox" name="consent" value="1">
                <span>I understand NAB Solutions will review my situation and guide me on my options. NAB Solutions is not a law firm, a debt settlement company or a collection agency, and doesn't pay or settle debts for me.</span>
              </label>

              <div class="cl-msg" id="clMsg" role="status"></div>
              <button type="submit" class="cl-btn cl-btn-primary"><?php echo nab_portal_icon( 'send', 16 ); ?> Send to our team</button>
            </form>
          </section>

          <div class="cl-side">
            <section class="cl-card">
              <h2 class="cl-card-title">Your requests</h2>
              <p class="cl-card-sub">We'll reply by email or phone. Updates show here too.</p>
              <ul class="cl-req" id="clRequests"></ul>
              <p class="cl-empty" id="clEmpty" hidden>You haven't sent a request yet.</p>
            </section>

            <section class="cl-card">
              <h2 class="cl-card-title">Know your rights</h2>
              <p class="cl-card-sub">Collection agencies in Canada must follow provincial rules. In general:</p>
              <ul class="cl-list">
                <li>They must tell you who they are and who they're collecting for.</li>
                <li>They can't threaten you, use abusive language or harass you.</li>
                <li>They can only contact you at reasonable times.</li>
                <li>They can't discuss your debt with your employer, friends or neighbours.</li>
                <li>You can ask for the details of the debt in writing before you pay anything.</li>
              </ul>
              <p class="cl-note">General information only, not legal advice. The exact rules depend on your province.</p>
            </section>

            <section class="cl-card">
              <h2 class="cl-card-title">Before you pay</h2>
              <ul class="cl-list">
                <li>Don't pay a debt you don't recognize until you have the details in writing.</li>
                <li>Get any payment plan or settlement agreed in writing first.</li>
                <li>Never give a collector access to your bank account over the phone.</li>
                <li>Keep notes of every call: the date, the person's name and what was said.</li>
                <li>A paid collection still stays on your credit report for a while, usually about 6 years from your last payment on the debt, but it shows as paid.</li>
              </ul>
            </section>

            <?php if ( $dispute_url !== '#' || $budget_url !== '#' ) : ?>
            <section class="cl-card">
              <h2 class="cl-card-title" style="margin-bottom:12px">Helpful tools</h2>
              <div class="cl-links">
                <?php if ( $dispute_url !== '#' ) : ?>
                <a href="<?php echo esc_url( $dispute_url ); ?>"><?php echo nab_portal_icon_tile( 'shield-check', 'indigo', 34 ); ?><span><b>Dispute Center</b><span>Dispute a collection that isn't yours or has wrong details</span></span></a>
                <?php endif; ?>
                <?php if ( $budget_url !== '#' ) : ?>
                <a href="<?php echo esc_url( $budget_url ); ?>"><?php echo nab_portal_icon_tile( 'wallet', 'green', 34 ); ?><span><b>Budget Planner</b><span>See what payment you can really afford</span></span></a>
                <?php endif; ?>
              </div>
            </section>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<?php nab_portal_print_icons_js( [ 'info', 'send', 'circle-check' ] ); ?>
<script>
window.nabCollectData = <?php echo wp_json_encode( [ 'requests' => $requests ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>;
</script>

<?php nab_footer_scripts(); ?>
<?php wp_footer(); ?>
</body>
</html>
