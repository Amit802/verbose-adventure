<?php
/**
 * Template Name: NAB Auto Loan Tool
 * LoanConnect Partner API v1.4 — /api/submit-application
 * Loan type hardcoded to 8 (Buy a Car) per client requirement.
 * All API calls server-side only — credentials never exposed to browser.
 *
 * API Endpoint: https://loanconnect.ca/api/submit-application
 * Docs: Partner API v1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user        = wp_get_current_user();
$uid         = $user->ID;
$saved_score = (int) get_user_meta( $uid, 'nab_credit_score', true );

// Map 300-900 score to LC credit_score scale (1=Excellent,2=Good,3=Fair,4=Poor)
$lc_credit_preset = '';
if ( $saved_score >= 740 )      $lc_credit_preset = '1'; // Excellent
elseif ( $saved_score >= 670 )  $lc_credit_preset = '2'; // Good
elseif ( $saved_score >= 580 )  $lc_credit_preset = '3'; // Fair
elseif ( $saved_score >= 300 )  $lc_credit_preset = '4'; // Poor

$notifications = function_exists('nab_get_user_notifications') ? nab_get_user_notifications($uid) : [];

nab_head_open( 'Auto Loan Tool — NAB Member Portal' );
?>
<style>
/* ══ LOAN TOOL ═══════════════════════════════════════════ */
.nab-loan-wrap{max-width:800px;margin:0 auto}
.nab-loan-card{background:#fff;border-radius:16px;padding:28px 32px;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
.nab-loan-heading{font-size:20px;font-weight:800;color:#1e293b;margin-bottom:6px}
.nab-loan-sub{font-size:13px;color:#64748b;margin-bottom:24px;line-height:1.5}

/* Steps */
.nab-loan-steps{display:flex;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:20px}
.nab-loan-step{flex:1;padding:12px 8px;text-align:center;font-size:11px;font-weight:600;color:#94a3b8;border-right:1px solid #f1f5f9;transition:.2s}
.nab-loan-step:last-child{border-right:none}
.nab-loan-step.active{background:#dbeafe;color:#1d4ed8}
.nab-loan-step.done{background:#dcfce7;color:#166534}
.nab-loan-step-num{font-size:18px;display:block;margin-bottom:2px}

/* Form grid */
.nab-loan-section-title{font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid #f1f5f9}
.nab-loan-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px}
.nab-loan-field{display:flex;flex-direction:column;gap:4px}
.nab-loan-field label{font-size:12px;font-weight:700;color:#374151}
.nab-loan-field input,.nab-loan-field select{padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;color:#1e293b;outline:none;transition:.15s;background:#fff;width:100%;box-sizing:border-box}
.nab-loan-field input:focus,.nab-loan-field select:focus{border-color:#0D5C9B;box-shadow:0 0 0 3px rgba(13,92,155,.08)}
.nab-loan-field-full{grid-column:1/-1}
.nab-field-hint{font-size:10px;color:#94a3b8;margin-top:1px}
.nab-loan-field input.error,.nab-loan-field select.error{border-color:#ef4444}

/* Submit */
.nab-loan-submit{width:100%;padding:14px;background:#0D5C9B;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;transition:.15s;display:flex;align-items:center;justify-content:center;gap:10px;margin-top:4px}
.nab-loan-submit:hover:not(:disabled){background:#0a4a7c}
.nab-loan-submit:disabled{background:#94a3b8;cursor:not-allowed}
.nab-spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:lcSpin .7s linear infinite;display:none;flex-shrink:0}
@keyframes lcSpin{to{transform:rotate(360deg)}}

/* Alert */
.nab-loan-alert{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:16px;display:none}
.nab-loan-alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.nab-loan-alert-info{background:#dbeafe;color:#1e40af;border:1px solid #93c5fd}

/* Disclaimer */
.nab-loan-disclaimer{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;font-size:11px;color:#64748b;line-height:1.6;margin-top:16px}

/* Loading */
.nab-loan-loading{display:none;text-align:center;padding:60px 20px;background:#fff;border-radius:16px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.nab-loan-loading-ring{width:48px;height:48px;border:4px solid #f1f5f9;border-top-color:#0D5C9B;border-radius:50%;animation:lcSpin .9s linear infinite;margin:0 auto 20px}
.nab-loan-loading-text{font-size:14px;color:#64748b;font-weight:600}
.nab-loan-loading-sub{font-size:12px;color:#94a3b8;margin-top:6px}

/* Results */
.nab-loan-results{display:none}
.nab-loan-results-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px}
.nab-loan-results-title{font-size:18px;font-weight:800;color:#1e293b}
.nab-loan-results-sub{font-size:12px;color:#64748b;margin-top:2px}

/* Offer cards */
.nab-loan-offer{background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:20px 22px;margin-bottom:14px;transition:.15s;position:relative;border-left:4px solid #0D5C9B;overflow:hidden}
.nab-loan-offer:hover{box-shadow:0 4px 20px rgba(0,0,0,.08);transform:translateY(-1px)}
.nab-loan-offer-top{display:flex;align-items:flex-start;gap:14px;margin-bottom:14px}
.nab-loan-offer-logo{width:100px;height:44px;object-fit:contain;flex-shrink:0;border-radius:6px;border:1px solid #f1f5f9;padding:4px;background:#fff}
.nab-loan-offer-logo-ph{width:100px;height:44px;background:#f1f5f9;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;color:#94a3b8;font-weight:600;flex-shrink:0}
.nab-loan-offer-lender{flex:1}
.nab-loan-offer-name{font-size:14px;font-weight:700;color:#1e293b}
.nab-loan-offer-type{font-size:11px;color:#64748b}
.nab-loan-offer-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px}
.nab-loan-offer-stat{background:#f8fafc;border-radius:8px;padding:10px;text-align:center}
.nab-loan-offer-stat-val{font-size:16px;font-weight:800;color:#0D5C9B;line-height:1.2}
.nab-loan-offer-stat-lbl{font-size:9px;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-top:3px}
.nab-loan-apply-btn{display:block;text-align:center;background:#F97316;color:#fff;padding:11px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;transition:.15s}
.nab-loan-apply-btn:hover{background:#ea6c0a;color:#fff}
.nab-loan-offer-note{font-size:10px;color:#94a3b8;margin-top:8px;line-height:1.4;text-align:center}
.nab-loan-preapproved{position:absolute;top:0;right:16px;background:#dcfce7;color:#166534;font-size:9px;font-weight:700;padding:3px 10px;border-radius:0 0 8px 8px;letter-spacing:.05em;text-transform:uppercase}

/* No results */
.nab-loan-no-results{padding:40px;text-align:center;background:#fff;border-radius:14px;border:2px dashed #e2e8f0}
.nab-loan-no-results-icon{font-size:48px;margin-bottom:16px}
.nab-loan-no-results-title{font-size:16px;font-weight:700;color:#1e293b;margin-bottom:8px}
.nab-loan-no-results-text{font-size:13px;color:#64748b;line-height:1.7;max-width:420px;margin:0 auto 20px}
.nab-loan-book-btn{display:inline-flex;align-items:center;gap:8px;background:#0D5C9B;color:#fff;padding:11px 22px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none}

/* Reset btn */
.nab-loan-reset{display:none;background:none;border:1px solid #e2e8f0;border-radius:8px;padding:8px 18px;font-size:12px;color:#64748b;cursor:pointer;transition:.15s;margin-top:12px}
.nab-loan-reset:hover{background:#f8fafc}

@media(max-width:640px){
  .nab-loan-form-grid{grid-template-columns:1fr}
  .nab-loan-offer-stats{grid-template-columns:1fr 1fr}
  .nab-loan-card{padding:18px}
  .nab-loan-step{font-size:10px;padding:10px 4px}
}
</style>
</head>
<body <?php body_class('nab-portal-body'); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar('loan'); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title">🚗 Auto Loan Tool</div>
      <div class="nab-topbar-right">
        <?php nab_render_notification_bell(); ?>
        <span class="nab-status-badge nab-status-active">● LoanConnect Powered</span>
      </div>
    </header>

    <div class="nab-content">
      <div class="nab-loan-wrap">

        <!-- Progress steps -->
        <div class="nab-loan-steps">
          <div class="nab-loan-step active" id="lcStep1"><span class="nab-loan-step-num">1</span>Your Info</div>
          <div class="nab-loan-step"        id="lcStep2"><span class="nab-loan-step-num">2</span>Loan Details</div>
          <div class="nab-loan-step"        id="lcStep3"><span class="nab-loan-step-num">3</span>View Offers</div>
        </div>

        <!-- ── APPLICATION FORM ──────────────────────── -->
        <div class="nab-loan-card" id="lcFormCard">
          <div class="nab-loan-heading">🚗 Find Your Auto Loan</div>
          <div class="nab-loan-sub">We'll match you with Canadian lenders in under 30 seconds. Your information is transmitted securely to LoanConnect. This does <strong>not</strong> affect your credit score.</div>

          <div class="nab-loan-alert nab-loan-alert-error" id="lcError"></div>

          <!-- Personal Information -->
          <div class="nab-loan-section-title">Personal Information</div>
          <div class="nab-loan-form-grid">
            <div class="nab-loan-field">
              <label>First Name *</label>
              <input type="text" id="lc_firstname" value="<?php echo esc_attr($user->first_name); ?>" placeholder="First name" autocomplete="given-name">
            </div>
            <div class="nab-loan-field">
              <label>Last Name *</label>
              <input type="text" id="lc_lastname" value="<?php echo esc_attr($user->last_name); ?>" placeholder="Last name" autocomplete="family-name">
            </div>
            <div class="nab-loan-field">
              <label>Email Address *</label>
              <input type="email" id="lc_email" value="<?php echo esc_attr($user->user_email); ?>" placeholder="your@email.com" autocomplete="email">
            </div>
            <div class="nab-loan-field">
              <label>Phone Number * <span class="nab-field-hint" style="display:inline">(10 digits)</span></label>
              <input type="tel" id="lc_phone" placeholder="416-555-1234" autocomplete="tel">
            </div>
            <div class="nab-loan-field">
              <label>Date of Birth *</label>
              <input type="date" id="lc_dob" autocomplete="bday">
            </div>
            <div class="nab-loan-field">
              <label>Citizenship Status *</label>
              <select id="lc_citizenship">
                <option value="">Select…</option>
                <option value="1">Canadian Citizen</option>
                <option value="2">Permanent Resident</option>
                <option value="3">Work Permit</option>
                <option value="4">International Student</option>
                <option value="5">Visitor</option>
                <option value="0">Other</option>
              </select>
            </div>
          </div>

          <!-- Address -->
          <div class="nab-loan-section-title">Address</div>
          <div class="nab-loan-form-grid">
            <div class="nab-loan-field nab-loan-field-full">
              <label>Street Address *</label>
              <input type="text" id="lc_address" placeholder="123 Main Street" autocomplete="street-address">
            </div>
            <div class="nab-loan-field">
              <label>City *</label>
              <input type="text" id="lc_city" placeholder="Toronto" autocomplete="address-level2">
            </div>
            <div class="nab-loan-field">
              <label>Province *</label>
              <select id="lc_province" autocomplete="address-level1">
                <option value="">Select…</option>
                <option value="AB">Alberta</option>
                <option value="BC">British Columbia</option>
                <option value="MB">Manitoba</option>
                <option value="NB">New Brunswick</option>
                <option value="NL">Newfoundland &amp; Labrador</option>
                <option value="NS">Nova Scotia</option>
                <option value="NT">Northwest Territories</option>
                <option value="NU">Nunavut</option>
                <option value="ON">Ontario</option>
                <option value="PE">Prince Edward Island</option>
                <option value="QC">Quebec</option>
                <option value="SK">Saskatchewan</option>
                <option value="YT">Yukon</option>
              </select>
            </div>
            <div class="nab-loan-field">
              <label>Postal Code *</label>
              <input type="text" id="lc_pc" placeholder="M5V 2T6" maxlength="7" autocomplete="postal-code">
            </div>
            <div class="nab-loan-field">
              <label>Housing Status *</label>
              <select id="lc_housing_status">
                <option value="">Select…</option>
                <option value="1">Own</option>
                <option value="2">Rent</option>
                <option value="0">Neither</option>
              </select>
            </div>
            <div class="nab-loan-field">
              <label>Monthly Housing Cost *</label>
              <input type="number" id="lc_rent_payment" placeholder="e.g. 1500" min="0">
              <span class="nab-field-hint">Rent or mortgage payment per month (CAD)</span>
            </div>
          </div>

          <!-- Employment & Income -->
          <div class="nab-loan-section-title">Employment &amp; Income</div>
          <div class="nab-loan-form-grid">
            <div class="nab-loan-field">
              <label>Employment Status *</label>
              <select id="lc_employment_status">
                <option value="">Select…</option>
                <option value="1">Full Time Employment</option>
                <option value="2">Part Time Employment</option>
                <option value="3">Self Employed</option>
                <option value="4">Unemployed</option>
                <option value="5">Retired</option>
                <option value="6">Disabled</option>
                <option value="7">Social Assistance</option>
                <option value="0">Other</option>
              </select>
            </div>
            <div class="nab-loan-field">
              <label>Annual Income (before tax) *</label>
              <input type="number" id="lc_income" placeholder="e.g. 48000" min="0">
              <span class="nab-field-hint">Your gross yearly income in CAD</span>
            </div>
            <div class="nab-loan-field">
              <label>Total Other Monthly Payments *</label>
              <input type="number" id="lc_monthly_payment" placeholder="e.g. 400" min="0">
              <span class="nab-field-hint">Credit cards, loans etc. — excluding housing</span>
            </div>
            <div class="nab-loan-field">
              <label>Credit Score Range *</label>
              <select id="lc_credit_score">
                <option value="">Select…</option>
                <option value="1" <?php echo $lc_credit_preset==='1'?'selected':''; ?>>Excellent (740+)</option>
                <option value="2" <?php echo $lc_credit_preset==='2'?'selected':''; ?>>Good (670–739)</option>
                <option value="3" <?php echo $lc_credit_preset==='3'?'selected':''; ?>>Fair (580–669)</option>
                <option value="4" <?php echo $lc_credit_preset==='4'?'selected':''; ?>>Poor (300–579)</option>
              </select>
              <?php if ($saved_score): ?>
              <span class="nab-field-hint">Pre-filled from your NAB portal score (<?php echo (int)$saved_score; ?>)</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Loan Details -->
          <div class="nab-loan-section-title">Loan Details</div>
          <div class="nab-loan-form-grid">
            <div class="nab-loan-field">
              <label>Loan Amount Requested *</label>
              <input type="number" id="lc_amount" placeholder="e.g. 15000" min="1000" max="75000">
              <span class="nab-field-hint">$1,000 – $75,000 CAD</span>
            </div>
            <div class="nab-loan-field">
              <label>Past Bankruptcy or Consumer Proposal? *</label>
              <select id="lc_p_and_c">
                <option value="">Select…</option>
                <option value="0">No</option>
                <option value="1">Yes</option>
              </select>
            </div>
          </div>

          <!-- Consent -->
          <div style="display:flex;align-items:flex-start;gap:10px;margin:8px 0 16px;padding:14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0">
            <input type="checkbox" id="lc_terms" style="width:16px;height:16px;margin-top:2px;flex-shrink:0;cursor:pointer">
            <label for="lc_terms" style="font-size:12px;color:#374151;cursor:pointer;line-height:1.5">
              I consent to NAB Solutions sharing my information with LoanConnect and its network of Canadian lenders to find auto loan offers. I understand this does not affect my credit score. I have read and accept LoanConnect's <a href="https://loanconnect.ca/terms" target="_blank" rel="noopener" style="color:#0D5C9B">Terms &amp; Conditions</a>. *
            </label>
          </div>

          <button class="nab-loan-submit" id="lcSubmitBtn" type="button">
            <span id="lcSubmitLabel">🚗 Find My Auto Loan Offers</span>
            <span class="nab-spinner" id="lcBtnSpinner"></span>
          </button>

          <div class="nab-loan-disclaimer">
            <strong>Privacy Notice:</strong> Your information is transmitted securely via HTTPS to LoanConnect Canada. NAB Solutions is a registered LoanConnect affiliate partner. Submitting this form constitutes your consent for LoanConnect to share your application with their lender network. All data handling complies with PIPEDA. Approval is not guaranteed and subject to each lender's individual criteria.
          </div>
        </div><!-- /form card -->

        <!-- ── LOADING ──────────────────────────────── -->
        <div class="nab-loan-loading" id="lcLoading">
          <div class="nab-loan-loading-ring"></div>
          <div class="nab-loan-loading-text">Searching lender network…</div>
          <div class="nab-loan-loading-sub">Matching your profile with Canadian lenders — usually takes 10–20 seconds</div>
        </div>

        <!-- ── RESULTS ──────────────────────────────── -->
        <div class="nab-loan-results" id="lcResults">
          <div class="nab-loan-card">
            <div class="nab-loan-results-header">
              <div>
                <div class="nab-loan-results-title" id="lcResultsTitle">✅ Your Auto Loan Offers</div>
                <div class="nab-loan-results-sub">Click "Apply Now" to proceed with a lender. Opening in a new tab.</div>
              </div>
            </div>
            <div id="lcOffersList"></div>
            <div style="text-align:center;margin-top:16px">
              <button class="nab-loan-reset" id="lcResetBtn" onclick="lcReset()" type="button">← Start a New Search</button>
            </div>
          </div>
        </div>

      </div><!-- /wrap -->
    </div><!-- /content -->
  </main>
</div>

<script>
(function(){
  var formCard = document.getElementById('lcFormCard');
  var loading  = document.getElementById('lcLoading');
  var results  = document.getElementById('lcResults');
  var errEl    = document.getElementById('lcError');
  var submitBtn= document.getElementById('lcSubmitBtn');
  var submitLbl= document.getElementById('lcSubmitLabel');
  var spinner  = document.getElementById('lcBtnSpinner');

  // Step indicator
  function setStep(n){
    [1,2,3].forEach(function(i){
      var el = document.getElementById('lcStep'+i);
      el.classList.remove('active','done');
      if(i < n) el.classList.add('done');
      if(i === n) el.classList.add('active');
    });
  }

  function showErr(msg){
    errEl.textContent = msg;
    errEl.style.display = 'block';
    errEl.scrollIntoView({behavior:'smooth',block:'center'});
  }
  function clearErr(){ errEl.style.display='none'; }

  function markInvalid(id){
    var el = document.getElementById(id);
    if(el) el.classList.add('error');
  }
  function clearInvalid(){
    document.querySelectorAll('.error').forEach(function(el){el.classList.remove('error');});
  }

  function getVal(id){ var el=document.getElementById(id); return el ? el.value.trim() : ''; }

  function validate(){
    clearInvalid();
    var required = [
      ['lc_firstname','First name'],['lc_lastname','Last name'],
      ['lc_email','Email'],['lc_phone','Phone'],['lc_dob','Date of birth'],
      ['lc_citizenship','Citizenship status'],
      ['lc_address','Street address'],['lc_city','City'],
      ['lc_province','Province'],['lc_pc','Postal code'],
      ['lc_housing_status','Housing status'],['lc_rent_payment','Monthly housing cost'],
      ['lc_employment_status','Employment status'],['lc_income','Annual income'],
      ['lc_monthly_payment','Other monthly payments'],
      ['lc_credit_score','Credit score range'],
      ['lc_amount','Loan amount'],['lc_p_and_c','Past bankruptcy / consumer proposal']
    ];
    for(var i=0;i<required.length;i++){
      if(!getVal(required[i][0])){ markInvalid(required[i][0]); showErr('Please complete: '+required[i][1]); return false; }
    }
    var email = getVal('lc_email');
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){ markInvalid('lc_email'); showErr('Please enter a valid email address.'); return false; }
    var phone = getVal('lc_phone').replace(/\D/g,'');
    if(phone.length < 10){ markInvalid('lc_phone'); showErr('Please enter a valid 10-digit phone number.'); return false; }
    var amt = parseFloat(getVal('lc_amount'));
    if(isNaN(amt)||amt<1000||amt>75000){ markInvalid('lc_amount'); showErr('Loan amount must be between $1,000 and $75,000.'); return false; }
    if(!document.getElementById('lc_terms').checked){ showErr('Please accept the Terms & Conditions to continue.'); return false; }
    return true;
  }

  // Format phone for LC API: 555-555-5555
  function formatPhone(p){
    p = p.replace(/\D/g,'').slice(0,10);
    if(p.length===10) return p.slice(0,3)+'-'+p.slice(3,6)+'-'+p.slice(6);
    return p;
  }

  // Render one offer from LC API response
  function renderOffer(o, idx, declineUrl){
    var isPreapproved = o.preapproved ? true : false;
    var lenderName = o.lender_name || 'Lender #'+(idx+1);
    var amount     = o.amount ? '$'+parseFloat(o.amount).toLocaleString('en-CA') : '—';
    var term       = o.term ? o.term+' months' : '—';
    var aprLow     = o.apr_range_low ? parseFloat(o.apr_range_low).toFixed(2)+'%' : '—';
    var aprHigh    = o.apr_range_high && parseFloat(o.apr_range_high) > 0 ? '–'+parseFloat(o.apr_range_high).toFixed(2)+'%' : '';
    var rate       = aprLow + aprHigh;
    var pmt        = o.monthly_payment_range_low && parseFloat(o.monthly_payment_range_low) > 0
                     ? '$'+parseFloat(o.monthly_payment_range_low).toFixed(2)+'/mo' : '—';
    // Use select_url directly — do NOT append decline_url as it causes
    // some lenders (e.g. MDG) to redirect to the decline URL instead of their own page
    var applyUrl = o.select_url || o.url || o.apply_url || o.next_step_url || '';

    var html = '<div class="nab-loan-offer">';
    if(isPreapproved) html += '<div class="nab-loan-preapproved">✅ Pre-approved</div>';
    html += '<div class="nab-loan-offer-top">';
    if(o.lender_logo){
      html += '<img class="nab-loan-offer-logo" src="'+escH(o.lender_logo)+'" alt="'+escH(lenderName)+'" loading="lazy">';
    } else {
      html += '<div class="nab-loan-offer-logo-ph">🏦</div>';
    }
    html += '<div class="nab-loan-offer-lender"><div class="nab-loan-offer-name">'+escH(lenderName)+'</div><div class="nab-loan-offer-type">Auto Loan — Buy a Car</div></div>';
    html += '</div>';
    html += '<div class="nab-loan-offer-stats">';
    html += '<div class="nab-loan-offer-stat"><div class="nab-loan-offer-stat-val">'+escH(amount)+'</div><div class="nab-loan-offer-stat-lbl">Loan Amount</div></div>';
    html += '<div class="nab-loan-offer-stat"><div class="nab-loan-offer-stat-val">'+escH(term)+'</div><div class="nab-loan-offer-stat-lbl">Term</div></div>';
    html += '<div class="nab-loan-offer-stat"><div class="nab-loan-offer-stat-val">'+escH(rate)+'</div><div class="nab-loan-offer-stat-lbl">APR</div></div>';
    html += '<div class="nab-loan-offer-stat"><div class="nab-loan-offer-stat-val">'+escH(pmt)+'</div><div class="nab-loan-offer-stat-lbl">Est. Payment</div></div>';
    html += '</div>';
    if(applyUrl){
      html += '<a class="nab-loan-apply-btn" href="'+escH(applyUrl)+'" target="_blank" rel="noopener">Apply Now with '+escH(lenderName)+' →</a>';
      html += '<div class="nab-loan-offer-note">Clicking Apply opens the lender\'s site in a new tab. Rate shown is an estimate — final rate determined by lender.</div>';
    } else {
      // Lender has no direct URL — send to LoanConnect tagged application form
      var fallbackUrl = 'https://loanconnect.ca/apply_now?pd=NABSolutions';
      html += '<a class="nab-loan-apply-btn" href="'+fallbackUrl+'" target="_blank" rel="noopener">Apply Now with '+escH(lenderName)+' →</a>';
      html += '<div class="nab-loan-offer-note">You will be taken to LoanConnect to complete your application with this lender.</div>';
    }
    html += '</div>';
    return html;
  }

  function escH(s){ var d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

  // ── Submit ─────────────────────────────────────────────
  submitBtn.addEventListener('click', function(){
    clearErr();
    if(!validate()) return;

    setStep(2);
    submitBtn.disabled = true;
    submitLbl.style.display = 'none';
    spinner.style.display   = 'block';
    formCard.style.display  = 'none';
    loading.style.display   = 'block';

    var phone = formatPhone(getVal('lc_phone'));

    var body = [
      'action=nab_loanconnect_search',
      'nonce='+(typeof nabPortal!=='undefined' ? nabPortal.nonce : ''),
      'firstname='+encodeURIComponent(getVal('lc_firstname')),
      'lastname='+encodeURIComponent(getVal('lc_lastname')),
      'email='+encodeURIComponent(getVal('lc_email')),
      'phone='+encodeURIComponent(phone),
      'dob='+encodeURIComponent(getVal('lc_dob')),
      'citizenship_status='+encodeURIComponent(getVal('lc_citizenship')),
      'address='+encodeURIComponent(getVal('lc_address')),
      'city='+encodeURIComponent(getVal('lc_city')),
      'province='+encodeURIComponent(getVal('lc_province')),
      'pc='+encodeURIComponent(getVal('lc_pc').replace(/\s/g,'').toUpperCase()),
      'housing_status='+encodeURIComponent(getVal('lc_housing_status')),
      'rent_payment='+encodeURIComponent(getVal('lc_rent_payment')),
      'employment_status='+encodeURIComponent(getVal('lc_employment_status')),
      'income='+encodeURIComponent(getVal('lc_income')),
      'monthly_payment='+encodeURIComponent(getVal('lc_monthly_payment')),
      'credit_score='+encodeURIComponent(getVal('lc_credit_score')),
      'amount='+encodeURIComponent(getVal('lc_amount')),
      'p_and_c='+encodeURIComponent(getVal('lc_p_and_c'))
    ].join('&');

    fetch((typeof nabPortal!=='undefined'?nabPortal.ajax:'/wp-admin/admin-ajax.php'),{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: body
    })
    .then(function(r){return r.json();})
    .then(function(data){
      loading.style.display = 'none';
      setStep(3);
      results.style.display = 'block';
      document.getElementById('lcResetBtn').style.display = 'block';

      var declineUrl = data.data && data.data.decline_url ? data.data.decline_url : '';

      if(data.success && data.data && data.data.offers && data.data.offers.length > 0){
        var offers = data.data.offers;
        document.getElementById('lcResultsTitle').textContent = '✅ '+offers.length+' Auto Loan Offer'+(offers.length!==1?'s':'')+' Found';
        var html = '';
        offers.forEach(function(o,i){ html += renderOffer(o, i, declineUrl); });
        document.getElementById('lcOffersList').innerHTML = html;
      } else {
        document.getElementById('lcResultsTitle').textContent = 'Search Complete';
        var msg = data.data && data.data.api_message ? data.data.api_message : '';
        document.getElementById('lcOffersList').innerHTML =
          '<div class="nab-loan-no-results">'
          +'<div class="nab-loan-no-results-icon">💬</div>'
          +'<div class="nab-loan-no-results-title">No Offers Matched Your Profile Right Now</div>'
          +'<div class="nab-loan-no-results-text">This doesn\'t mean you won\'t qualify — lender criteria change frequently and working on your credit score can open new options. Our credit specialist can review your profile and help you find the best path forward.'+(msg?' <em>'+escH(msg)+'</em>':'')+'</div>'
          +'<a class="nab-loan-book-btn" href="https://loanconnect.ca/apply_now?pd=NABSolutions" target="_blank">📅 Try LoanConnect Directly →</a>'
          +'</div>';
      }
    })
    .catch(function(err){
      loading.style.display  = 'none';
      formCard.style.display = 'block';
      submitBtn.disabled     = false;
      submitLbl.style.display= '';
      spinner.style.display  = 'none';
      setStep(1);
      showErr('Connection error — please try again in a moment.');
    });
  });

  window.lcReset = function(){
    clearErr(); clearInvalid();
    formCard.style.display  = 'block';
    loading.style.display   = 'none';
    results.style.display   = 'none';
    submitBtn.disabled      = false;
    submitLbl.style.display = '';
    spinner.style.display   = 'none';
    document.getElementById('lcOffersList').innerHTML = '';
    document.getElementById('lc_terms').checked = false;
    setStep(1);
  };
})();
</script>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>
