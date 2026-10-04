<?php
/**
 * Template Name: NAB PAD Agreement
 * Pre-Authorised Debit / Membership Agreement viewer + PDF download.
 *
 * v1.7.5: Rebuilt to use the verbatim executed Membership Agreement
 * instead of a paraphrased summary. Both this on-screen view and
 * the PDF-download branch below now pull from ONE shared function,
 * nab_get_membership_agreement_html() in inc/helpers.php -- they can
 * no longer drift apart from each other or from the real signed
 * agreement, which is what caused the original mismatch.
 *
 * v1.8.1: Source updated to NABSolutions-ServicesAgreementwithPADAndCS
 * (Last updated: October 2025). See the docblock above
 * nab_get_membership_agreement_html() in inc/helpers.php for the full
 * list of what changed from the March 2024 version.
 *
 * NOTE: the source document is internally inconsistent about billing
 * language elsewhere on this site (weekly vs monthly) -- this page now
 * reflects the real agreement (weekly, $20.45/week, 30-day written
 * cancellation notice). The "Cancel Membership" button on the dashboard
 * still cancels instantly with no notice period enforced -- that is a
 * separate, unresolved mismatch flagged to Matt, not fixed here.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user       = wp_get_current_user();
$uid        = $user->ID;
$first_name = trim( $user->first_name ) ?: explode( ' ', trim( $user->display_name ) )[0];

$full_name   = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
$email       = $user->user_email;
$address     = get_user_meta( $uid, 'nab_address', true ) ?: 'On file';
$dob         = get_user_meta( $uid, 'nab_dob', true ) ?: 'On file';
$bank_name   = get_user_meta( $uid, 'nab_bank_name', true ) ?: 'On file';
$institution = get_user_meta( $uid, 'nab_institution', true ) ?: 'On file';
$transit     = get_user_meta( $uid, 'nab_transit', true ) ?: 'On file';
$account     = get_user_meta( $uid, 'nab_account', true ) ?: 'On file';
$since       = get_user_meta( $uid, 'nab_member_since', true ) ?: date( 'd M Y', strtotime( $user->user_registered ) );
$member_id   = get_user_meta( $uid, 'nab_member_id', true ) ?: 'NAB-' . str_pad( $uid, 4, '0', STR_PAD_LEFT );

$agreement_vars = [
    'name'    => $full_name,
    'email'   => $email,
    'address' => $address,
    'dob'     => $dob,
    'date'    => $since,
    'bank'    => $bank_name,
    'inst'    => $institution,
    'transit' => $transit,
    'account' => $account,
];

// --- HANDLE PDF DOWNLOAD ----------------------------------------------------
if ( isset( $_GET['nab_pad_download'] ) && wp_verify_nonce( $_GET['nab_pad_nonce'] ?? '', 'nab_pad_dl' ) ) {
    header( 'Content-Type: text/html; charset=utf-8' );
    $agreement_html = function_exists( 'nab_get_membership_agreement_html' ) ? nab_get_membership_agreement_html( $agreement_vars ) : '<p>Agreement content unavailable.</p>';
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>NAB Membership Agreement</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;font-size:11pt;color:#000;padding:40px;max-width:800px;margin:0 auto}
h1{text-align:center;font-size:15pt;margin:16px 0}
h2{font-size:12pt;text-decoration:underline;margin:20px 0 8px}
p{margin-bottom:10px;line-height:1.7}
table{width:100%;border-collapse:collapse;margin:14px 0}
td{border:1px solid #000;padding:10px;vertical-align:top}
td.lbl{font-weight:bold;width:42%;background:#f8fafc}
.hdr{text-align:center;border-bottom:2px solid #0D5C9B;padding-bottom:16px;margin-bottom:20px}
.logo{font-size:18pt;font-weight:900;color:#0D5C9B}
.logo b{color:#FF6B35}
.no-print{background:#fff3cd;padding:12px;border-radius:8px;margin-bottom:20px;text-align:center}
.print-btn{margin-top:8px;padding:10px 28px;background:#0D5C9B;color:#fff;border:none;font-size:12pt;border-radius:6px;cursor:pointer;display:inline-block}
@media print{.no-print{display:none!important}}
</style>
<script>window.onload=function(){window.print()}</script>
</head><body>
<div class="no-print">
  <strong>To save as PDF:</strong> In the print dialog select <strong>"Save as PDF"</strong> as the printer/destination.<br>
  <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
</div>
<div class="hdr">
  <div class="logo">NAB <b>Solutions</b></div>
  <p style="font-size:10pt;color:#555;margin-top:4px">Member Portal -- Membership Agreement</p>
  <p style="font-size:9pt;color:#888;margin-top:4px">Member ID: ' . esc_html( $member_id ) . '</p>
</div>
' . $agreement_html . '
</body></html>';
    exit;
}

$notifications = function_exists( 'nab_get_user_notifications' ) ? nab_get_user_notifications( $uid ) : [];

nab_head_open( 'PAD Agreement -- NAB Member Portal' );
?>
<style>
.nab-pad-wrap{max-width:820px;margin:0 auto}
.nab-pad-card{background:#fff;border-radius:16px;padding:32px 36px;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}

.nab-pad-doc-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;padding-bottom:20px;border-bottom:2px solid #f1f5f9}
.nab-pad-doc-title{font-size:20px;font-weight:800;color:#1e293b}
.nab-pad-doc-sub{font-size:12px;color:#64748b;margin-top:4px}
.nab-pad-logo{font-size:24px;font-weight:900;color:#0D5C9B;display:flex;align-items:center;gap:8px}
.nab-pad-logo-badge{width:36px;height:36px;background:#F97316;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:900;color:#fff;flex-shrink:0}

.nab-pad-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px}
.nab-pad-field{display:flex;flex-direction:column;gap:3px}
.nab-pad-field-label{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em}
.nab-pad-field-value{font-size:14px;font-weight:600;color:#1e293b}

.nab-pad-status{display:inline-flex;align-items:center;gap:6px;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;margin-bottom:20px}
.nab-pad-highlight{background:#f0f9ff;border-left:3px solid #0D5C9B;border-radius:0 8px 8px 0;padding:12px 16px;margin:16px 0;font-size:13px;color:#1e40af;font-weight:600}

.nab-pad-legal{font-size:12.5px;color:#374151;line-height:1.75;max-height:600px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:10px;padding:20px 24px;background:#fafbfc}
.nab-pad-legal h1{font-size:16px;text-align:center;margin:0 0 16px;color:#1e293b}
.nab-pad-legal h2{font-size:13px;font-weight:800;margin:18px 0 8px;color:#1e293b;text-decoration:underline}
.nab-pad-legal p{margin:0 0 10px}
.nab-pad-legal ul{margin:0 0 10px;padding-left:22px}
.nab-pad-legal li{margin-bottom:4px}
.nab-pad-legal table{width:100%;border-collapse:collapse;margin:10px 0}
.nab-pad-legal td{border:1px solid #cbd5e1;padding:8px 10px;vertical-align:top;font-size:12px}
.nab-pad-legal td.lbl{font-weight:700;width:42%;background:#f1f5f9}

.nab-pad-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px;padding-top:20px;border-top:1px solid #f1f5f9}
.nab-pad-download-btn{display:flex;align-items:center;gap:8px;background:#0D5C9B;color:#fff;padding:11px 22px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;transition:.15s}
.nab-pad-download-btn:hover{background:#0a4a7c;color:#fff}
.nab-pad-print-btn{display:flex;align-items:center;gap:8px;background:#f1f5f9;color:#374151;padding:11px 22px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid #e2e8f0;transition:.15s}
.nab-pad-print-btn:hover{background:#e2e8f0}

@media(max-width:600px){
  .nab-pad-grid{grid-template-columns:1fr}
  .nab-pad-card{padding:20px}
  .nab-pad-doc-header{flex-direction:column;gap:12px}
  .nab-pad-legal{max-height:none}
}
@media print{
  .nab-sidebar,.nab-topbar,.nab-pad-actions,.nab-hamburger,.nab-sidebar-overlay{display:none!important}
  .nab-main{margin:0!important}
  .nab-pad-card{box-shadow:none!important;border:1px solid #e2e8f0}
  .nab-pad-legal{max-height:none;overflow:visible}
}
</style>
</head>
<body <?php body_class('nab-portal-body'); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar('pad'); ?>

  <main class="nab-main">

    <header class="nab-topbar">
      <div class="nab-topbar-title">📄 PAD Agreement</div>
      <div class="nab-topbar-right">
        <?php nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="nab-pad-wrap">

        <div class="nab-pad-card">

          <div class="nab-pad-doc-header">
            <div>
              <div class="nab-pad-doc-title">Membership Agreement &amp; Pre-Authorised Debit</div>
              <div class="nab-pad-doc-sub">Your executed agreement -- keep this for your records</div>
            </div>
            <div class="nab-pad-logo">
              <div class="nab-pad-logo-badge">N</div>
              NAB <span style="color:#F97316">Solutions</span>
            </div>
          </div>

          <div class="nab-pad-status">✅ Active Agreement</div>

          <div class="nab-pad-grid">
            <div class="nab-pad-field">
              <div class="nab-pad-field-label">Member Name</div>
              <div class="nab-pad-field-value"><?php echo esc_html( $full_name ); ?></div>
            </div>
            <div class="nab-pad-field">
              <div class="nab-pad-field-label">Member ID</div>
              <div class="nab-pad-field-value"><?php echo esc_html( $member_id ); ?></div>
            </div>
            <div class="nab-pad-field">
              <div class="nab-pad-field-label">Email Address</div>
              <div class="nab-pad-field-value"><?php echo esc_html( $email ); ?></div>
            </div>
            <div class="nab-pad-field">
              <div class="nab-pad-field-label">Agreement Date</div>
              <div class="nab-pad-field-value"><?php echo esc_html( $since ); ?></div>
            </div>
            <div class="nab-pad-field">
              <div class="nab-pad-field-label">Weekly Amount</div>
              <div class="nab-pad-field-value">$20.45 CAD</div>
            </div>
            <?php if ( $account !== 'On file' ) : ?>
            <div class="nab-pad-field">
              <div class="nab-pad-field-label">Account Number</div>
              <div class="nab-pad-field-value">••••••<?php echo esc_html( substr( $account, -4 ) ); ?></div>
            </div>
            <?php endif; ?>
          </div>

          <div class="nab-pad-highlight">
            Billing occurs weekly ($20.45 CAD/week) per Section 1(a) of your Membership Agreement. Cancellation requires 30 days written notice per Sections 1(c), 2, and 3(b) of the agreement below.
          </div>

          <!-- Verbatim executed agreement text -- single source shared with the PDF download -->
          <div class="nab-pad-legal">
            <?php echo function_exists( 'nab_get_membership_agreement_html' ) ? nab_get_membership_agreement_html( $agreement_vars ) : '<p>Agreement content unavailable.</p>'; ?>
          </div>

          <div class="nab-pad-actions">
            <a class="nab-pad-download-btn" href="<?php echo esc_url( add_query_arg( [ 'nab_pad_download' => '1', 'nab_pad_nonce' => wp_create_nonce('nab_pad_dl') ], get_permalink() ) ); ?>">
              ⬇ Download PDF
            </a>
            <button class="nab-pad-print-btn" onclick="window.print()" type="button">
              🖨 Print Agreement
            </button>
          </div>

        </div><!-- /pad-card -->
      </div>
    </div>
  </main>
</div>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>
