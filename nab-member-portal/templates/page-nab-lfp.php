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

nab_head_open( 'Lending Finder Program — NAB Member Portal' );
?>
<style>
.nab-lfp-wrap{max-width:1100px;margin:0 auto;padding:24px}
.nab-lfp-hero{background:linear-gradient(135deg,#0D5C9B,#0a4a7c);border-radius:16px;padding:32px;color:#fff;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px}
.nab-lfp-hero h1{margin:0;font-size:22px;font-weight:800}
.nab-lfp-hero p{margin:8px 0 0;opacity:.85;font-size:14px}
.nab-lfp-badge{background:rgba(255,255,255,.15);border-radius:10px;padding:8px 16px;font-size:13px;font-weight:600;white-space:nowrap}
.nab-lfp-info{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px}
.nab-lfp-info-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 12px rgba(13,92,155,.08);border:1px solid #f1f5f9;text-align:center}
.nab-lfp-info-card .icon{font-size:28px;margin-bottom:8px}
.nab-lfp-info-card h3{margin:0 0 4px;font-size:14px;color:#1e293b;font-weight:700}
.nab-lfp-info-card p{margin:0;font-size:12px;color:#64748b}
.nab-lfp-widget-wrap{background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(13,92,155,.1);border:1px solid #e8f0fb;overflow:hidden;padding:28px;margin-bottom:20px}
.nab-lfp-widget-title{font-size:16px;font-weight:800;color:#0D5C9B;margin:0 0 20px;padding-bottom:16px;border-bottom:2px solid #f1f5f9}
.nab-lfp-disclaimer{background:#fef9e7;border:1px solid #f1c40f;border-radius:10px;padding:14px 18px;font-size:12px;color:#7d6608}
@media(max-width:700px){.nab-lfp-info{grid-template-columns:1fr}.nab-lfp-hero{flex-direction:column}}
</style>
<?php nab_open_body( 'lfp' ); ?>

<div class="nab-main-content">
  <div class="nab-lfp-wrap">

    <div class="nab-lfp-hero">
      <div>
        <h1>🏦 Lending Finder Program</h1>
        <p>Hi <?php echo esc_html($first_name); ?>! Find loan offers from trusted Canadian lenders matched to your profile.</p>
      </div>
      <div class="nab-lfp-badge">✅ Soft Credit Check Only</div>
    </div>

    <div class="nab-lfp-info">
      <div class="nab-lfp-info-card">
        <div class="icon">🔍</div>
        <h3>Smart Matching</h3>
        <p>We search multiple lenders to find offers that match your credit profile</p>
      </div>
      <div class="nab-lfp-info-card">
        <div class="icon">🛡️</div>
        <h3>No Hard Inquiry</h3>
        <p>Checking your options won't affect your credit score</p>
      </div>
      <div class="nab-lfp-info-card">
        <div class="icon">⚡</div>
        <h3>Instant Results</h3>
        <p>Get matched with loan offers in minutes, not days</p>
      </div>
    </div>

    <div class="nab-lfp-widget-wrap">
      <div class="nab-lfp-widget-title">🔎 Find Your Loan Options</div>
      <div class="custsection" id="nabLfpCustsection">
        <script type="module" id="partnerForm" form_id="lc-604cb8ab23" url="https://builder.loanconnect.ca/api" src="https://nyc3.digitaloceanspaces.com/prod-assetdirect/assets/lc-embed.js"></script>
      </div>
      <div id="nabLfpFallback" style="display:none;text-align:center;padding:32px 16px">
        <div style="font-size:13px;color:#64748b;margin-bottom:14px">The loan finder tool is taking longer than expected to load. You can continue directly on LoanConnect instead:</div>
        <a href="https://loanconnect.ca/apply_now?pd=NABSolutions" target="_blank" rel="noopener" style="display:inline-block;background:#0D5C9B;color:#fff;padding:11px 24px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none">Continue to LoanConnect →</a>
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

    <div class="nab-lfp-disclaimer">
      ⚠️ <strong>Disclaimer:</strong> NAB Solutions is not a lender or loan provider. This tool connects you with third-party accredited lenders. We do not guarantee loan approval. Results depend on individual credit profiles and lender decisions.
    </div>

  </div>
</div>

<?php nab_portal_footer_js(); wp_footer(); ?>
</body>
</html>
