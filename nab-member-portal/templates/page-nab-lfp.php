<?php
/**
 * Template Name: NAB Lending Finder Program
 * v1.0 — LoanConnect embedded widget
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$uid        = get_current_user_id();
$user       = wp_get_current_user();
$first_name = get_user_meta( $uid, 'first_name', true ) ?: $user->display_name ?: 'Member';

nab_head_open( 'Lending Finder Program | NAB Member Portal' );
?>
<style>
.nab-lfp-wrap{max-width:1100px;margin:0 auto}
.nab-lfp-info{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px}
.nab-lfp-info-card{background:var(--nab-surface);border:1px solid var(--nab-line);border-radius:var(--nab-radius);box-shadow:var(--nab-shadow);padding:18px 20px;display:flex;gap:12px;align-items:flex-start}
.nab-lfp-info-card h3{margin:0 0 4px;font-size:14px;color:var(--nab-text);font-weight:700}
.nab-lfp-info-card p{margin:0;font-size:13px;color:var(--nab-text-2);line-height:1.5}
.nab-lfp-widget-wrap{background:var(--nab-surface);border:1px solid var(--nab-line);border-radius:var(--nab-radius);box-shadow:var(--nab-shadow);overflow:hidden;padding:24px;margin-bottom:20px}
.nab-lfp-widget-title{font-size:16px;font-weight:700;color:var(--nab-text);margin:0 0 18px;padding-bottom:14px;border-bottom:1px solid var(--nab-line-soft)}
.nab-lfp-disclaimer{display:flex;gap:10px;align-items:flex-start;background:var(--nab-warn-soft);border-radius:var(--nab-radius);padding:12px 14px;font-size:13px;line-height:1.55;color:var(--nab-warn)}
.nab-lfp-disclaimer .nab-i{flex-shrink:0;margin-top:1px}
@media(max-width:700px){.nab-lfp-info{grid-template-columns:1fr}}
</style>
<?php nab_open_body( 'lfp' ); ?>

<main class="nab-main">
  <header class="nab-topbar">
    <div class="nab-topbar-title"><?php echo nab_portal_icon( 'landmark', 20 ); ?> Lending Finder Program</div>
    <div class="nab-topbar-right">
      <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
    </div>
  </header>
  <div class="nab-content">
  <div class="nab-lfp-wrap">

    <section class="nab-page-head">
      <div>
        <div class="nab-page-eyebrow">Loan tools</div>
        <h1 class="nab-page-title">Lending Finder Program</h1>
        <p class="nab-page-sub">Hi <?php echo esc_html( $first_name ); ?>, find loan offers from trusted Canadian lenders matched to your profile.</p>
      </div>
      <span class="nab-chip nab-chip-good"><?php echo nab_portal_icon( 'shield-check', 14 ); ?> Soft credit check only</span>
    </section>

    <div class="nab-lfp-info">
      <div class="nab-lfp-info-card">
        <?php echo nab_portal_icon_tile( 'search', 'blue', 36 ); ?>
        <div><h3>Smart matching</h3><p>We search several lenders for offers that fit your credit profile.</p></div>
      </div>
      <div class="nab-lfp-info-card">
        <?php echo nab_portal_icon_tile( 'shield-check', 'green', 36 ); ?>
        <div><h3>No hard inquiry</h3><p>Checking your options won't affect your credit score.</p></div>
      </div>
      <div class="nab-lfp-info-card">
        <?php echo nab_portal_icon_tile( 'clock', 'orange', 36 ); ?>
        <div><h3>Quick results</h3><p>Get matched with loan offers in minutes, not days.</p></div>
      </div>
    </div>

    <div class="nab-lfp-widget-wrap">
      <div class="nab-lfp-widget-title">Find your loan options</div>
      <div class="custsection" id="nabLfpCustsection">
        <script type="module" id="partnerForm" form_id="lc-604cb8ab23" url="https://builder.loanconnect.ca/api" src="https://nyc3.digitaloceanspaces.com/prod-assetdirect/assets/lc-embed.js"></script>
      </div>
      <div id="nabLfpFallback" style="display:none;text-align:center;padding:32px 16px">
        <div style="font-size:13px;color:var(--nab-text-2);margin-bottom:14px">The loan finder tool is taking longer than expected to load. You can continue directly on LoanConnect instead:</div>
        <a href="https://loanconnect.ca/apply_now?pd=NABSolutions" target="_blank" rel="noopener" class="nab-ui-btn nab-ui-btn-primary">Continue to LoanConnect <?php echo nab_portal_icon( 'arrow-right', 16 ); ?></a>
      </div>
    </div>
    <script>
    (function(){
      // If the LoanConnect embed widget hasn't rendered any content into
      // the custsection within 5s (script blocked, ad-blocker, CDN issue,
      // etc.), show a direct fallback link instead of leaving a blank box.
      setTimeout(function(){
        var section = document.getElementById('nabLfpCustsection');
        var fallback = document.getElementById('nabLfpFallback');
        if (!section || !fallback) return;
        // The widget replaces/appends content next to the <script> tag.
        // If nothing besides the original <script> element exists, it never rendered.
        var hasRenderedContent = section.children.length > 1 ||
          (section.children.length === 1 && section.children[0].tagName !== 'SCRIPT');
        if (!hasRenderedContent) {
          fallback.style.display = 'block';
        }
      }, 5000);
    })();
    </script>

    <div class="nab-lfp-disclaimer"><?php echo nab_portal_icon( 'info', 16 ); ?><span>
      <strong>Disclaimer:</strong> NAB Solutions is not a lender or loan provider. This tool connects you with third-party accredited lenders. We do not guarantee loan approval. Results depend on individual credit profiles and lender decisions.
    </span></div>

  </div>
  </div>
</main>
</div>

<?php nab_portal_footer_js(); wp_footer(); ?>
</body>
</html>
