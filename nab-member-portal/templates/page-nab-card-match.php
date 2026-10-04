<?php
/**
 * Template Name: NAB Credit Card Matcher
 * Rebuilt v1.6.2 per Suneil video reference — better design.
 * 5-step quiz → optional statement upload → results with Why This Card → NAB Credit Assistant
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user        = wp_get_current_user();
$uid         = $user->ID;
$saved_score = (int) get_user_meta( $uid, 'nab_credit_score', true );
$first_name  = get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ?: 'there';
$has_openai  = ! empty( get_field( 'nab_openai_key', nab_get_dash_page_id() ) );
$notifications = function_exists('nab_get_user_notifications') ? nab_get_user_notifications($uid) : [];

nab_head_open( 'Credit Card Finder — NAB Member Portal' );
?>
<style>
.nab-cf-wrap{max-width:820px;margin:0 auto;padding-bottom:40px}
.nab-cf-hero{background:linear-gradient(135deg,#0D5C9B,#0a4a7c);border-radius:16px;padding:28px 32px;margin-bottom:24px;color:#fff;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
.nab-cf-hero-title{font-size:22px;font-weight:800;margin:0 0 6px}
.nab-cf-hero-sub{font-size:13px;opacity:.85;margin:0;line-height:1.5}
.nab-cf-hero-icon{font-size:52px;flex-shrink:0}
.nab-cf-step-label{display:flex;justify-content:space-around;font-size:10px;color:#94a3b8;margin-bottom:8px}
.nab-cf-step-label span.done,.nab-cf-step-label span.active{color:#0D5C9B;font-weight:700}
.nab-cf-steps{display:flex;align-items:center;gap:0;margin-bottom:28px}
.nab-cf-step{flex:1;height:4px;background:#e2e8f0;border-radius:2px;transition:.3s;position:relative}
.nab-cf-step.done{background:#0D5C9B}
.nab-cf-step.active{background:linear-gradient(90deg,#0D5C9B 60%,#e2e8f0 100%)}
.nab-cf-step-dot{position:absolute;top:50%;right:-1px;transform:translateY(-50%);width:10px;height:10px;border-radius:50%;background:#e2e8f0;border:2px solid #fff;box-shadow:0 0 0 2px #e2e8f0;transition:.3s}
.nab-cf-step.done .nab-cf-step-dot,.nab-cf-step.active .nab-cf-step-dot{background:#0D5C9B;box-shadow:0 0 0 2px #0D5C9B}
.nab-cf-qcard{background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 16px rgba(0,0,0,.07);display:none}
.nab-cf-qcard.active{display:block}
.nab-cf-q-eyebrow{font-size:10px;font-weight:700;color:#0D5C9B;text-transform:uppercase;letter-spacing:.1em;margin-bottom:10px}
.nab-cf-q-title{font-size:20px;font-weight:800;color:#1e293b;margin:0 0 6px;line-height:1.3}
.nab-cf-q-hint{font-size:13px;color:#64748b;margin:0 0 24px;line-height:1.5}
.nab-cf-opts{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px}
.nab-cf-opt{background:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;padding:16px 18px;cursor:pointer;transition:.15s;text-align:left;display:flex;align-items:flex-start;gap:12px;width:100%}
.nab-cf-opt:hover{border-color:#0D5C9B;background:#f0f7ff}
.nab-cf-opt.selected{border-color:#0D5C9B;background:#dbeafe}
.nab-cf-opt.sel-yes{border-color:#16a34a;background:#dcfce7}
.nab-cf-opt.sel-no{border-color:#dc2626;background:#fee2e2}
.nab-cf-opt-icon{font-size:22px;flex-shrink:0;margin-top:2px}
.nab-cf-opt-label{font-size:14px;font-weight:700;color:#1e293b;display:block;margin-bottom:2px}
.nab-cf-opt-hint{font-size:11px;color:#64748b}
.nab-cf-q1-wrap{display:grid;grid-template-columns:1fr 1fr;gap:24px}
.nab-cf-exact-panel{background:#f8fafc;border-radius:12px;padding:20px;border:1.5px solid #e2e8f0}
.nab-cf-exact-label{font-size:12px;font-weight:700;color:#374151;margin-bottom:10px;display:block}
.nab-cf-exact-input{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:20px;font-weight:800;color:#0D5C9B;text-align:center;box-sizing:border-box}
.nab-cf-exact-input:focus{outline:none;border-color:#0D5C9B}
.nab-cf-score-bar{margin:14px 0 6px;height:6px;border-radius:3px;background:linear-gradient(90deg,#ef4444,#f97316 20%,#eab308 40%,#22c55e 70%,#0D5C9B)}
.nab-cf-score-labels{display:flex;justify-content:space-between;font-size:9px;color:#94a3b8}
.nab-cf-nav{display:flex;justify-content:space-between;align-items:center;margin-top:8px}
.nab-cf-back{background:none;border:1.5px solid #e2e8f0;border-radius:8px;padding:10px 20px;font-size:13px;color:#64748b;cursor:pointer;transition:.15s}
.nab-cf-back:hover{border-color:#94a3b8;color:#374151}
.nab-cf-next{background:#0D5C9B;color:#fff;border:none;border-radius:8px;padding:12px 28px;font-size:14px;font-weight:700;cursor:pointer;transition:.15s}
.nab-cf-next:hover{background:#0a4a7c}
.nab-cf-next:disabled{background:#cbd5e1;cursor:not-allowed}
.nab-cf-upload-zone{border:2px dashed #cbd5e1;border-radius:14px;padding:40px 20px;text-align:center;cursor:pointer;transition:.15s;background:#fafbfc;margin-bottom:16px}
.nab-cf-upload-zone:hover,.nab-cf-upload-zone.drag-over{border-color:#0D5C9B;background:#f0f7ff}
.nab-cf-upload-icon{font-size:40px;margin-bottom:12px}
.nab-cf-upload-title{font-size:15px;font-weight:700;color:#1e293b;margin-bottom:6px}
.nab-cf-upload-hint{font-size:12px;color:#64748b;line-height:1.5}
.nab-cf-upload-banks{font-size:11px;color:#94a3b8;margin-top:8px}
.nab-cf-upload-skip{background:none;border:none;color:#0D5C9B;font-size:13px;font-weight:600;cursor:pointer;padding:0;text-decoration:underline}
.nab-cf-file-chosen{background:#dcfce7;border:2px solid #16a34a;border-radius:10px;padding:14px 18px;display:none;align-items:center;gap:12px;margin-bottom:16px}
.nab-cf-file-chosen-name{font-size:13px;font-weight:600;color:#166534;flex:1}
.nab-cf-results{display:none}
.nab-cf-results-header{background:#fff;border-radius:16px;padding:24px 28px;box-shadow:0 2px 16px rgba(0,0,0,.07);margin-bottom:16px}
.nab-cf-results-title{font-size:20px;font-weight:800;color:#1e293b;margin:0 0 4px}
.nab-cf-results-sub{font-size:13px;color:#64748b;margin:0}
.nab-cf-result-item{background:#fff;border-radius:14px;padding:22px 24px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:14px;border-left:4px solid #e2e8f0;position:relative;transition:.15s}
.nab-cf-result-item:hover{box-shadow:0 4px 24px rgba(0,0,0,.1)}
.nab-cf-result-item.top{border-left-color:#F97316}
.nab-cf-top-badge{position:absolute;top:0;right:18px;background:#F97316;color:#fff;font-size:9px;font-weight:800;padding:4px 12px;border-radius:0 0 10px 10px;letter-spacing:.07em;text-transform:uppercase}
.nab-cf-card-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.nab-cf-card-name{font-size:16px;font-weight:800;color:#1e293b;margin:0 0 3px}
.nab-cf-card-issuer{font-size:12px;color:#64748b;margin:0 0 10px}
.nab-cf-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
.nab-cf-tag{font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;background:#f1f5f9;color:#374151}
.nab-cf-tag-score-b{background:#dcfce7;color:#166534}
.nab-cf-tag-score-f{background:#dbeafe;color:#1e40af}
.nab-cf-tag-score-g{background:#e0e7ff;color:#3730a3}
.nab-cf-why-toggle{background:none;border:none;color:#0D5C9B;font-size:12px;font-weight:700;cursor:pointer;padding:0;margin-bottom:10px}
.nab-cf-why-body{display:none;background:#f8fafc;border-radius:8px;padding:12px 14px;font-size:13px;color:#374151;line-height:1.6;margin-bottom:12px}
.nab-cf-why-body.open{display:block}
.nab-cf-match-pct{font-size:26px;font-weight:900;color:#0D5C9B;line-height:1;text-align:right}
.nab-cf-match-label{font-size:10px;color:#94a3b8;font-weight:600;text-transform:uppercase;text-align:right}
.nab-cf-retake{background:none;border:1.5px solid #e2e8f0;border-radius:8px;padding:9px 20px;font-size:13px;color:#64748b;cursor:pointer;margin-top:16px}
.nab-cf-disclaimer{background:#f8fafc;border-radius:10px;padding:14px 18px;font-size:11px;color:#94a3b8;line-height:1.6;margin-top:16px}
.nab-cf-assistant{background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.07);margin-top:20px;overflow:hidden;display:none}
.nab-cf-assistant-hdr{background:linear-gradient(135deg,#0D5C9B,#0a4a7c);padding:16px 20px;display:flex;align-items:center;gap:12px}
.nab-cf-assistant-dot{width:8px;height:8px;background:#22c55e;border-radius:50%;flex-shrink:0}
.nab-cf-assistant-title{font-size:14px;font-weight:700;color:#fff;margin:0}
.nab-cf-assistant-sub{font-size:11px;color:rgba(255,255,255,.75);margin:0}
.nab-cf-chat-body{padding:20px;min-height:100px;max-height:260px;overflow-y:auto}
.nab-cf-chat-msg{margin-bottom:14px;display:flex;gap:10px}
.nab-cf-chat-msg.user{flex-direction:row-reverse}
.nab-cf-bubble{max-width:75%;padding:10px 14px;border-radius:12px;font-size:13px;line-height:1.5}
.nab-cf-chat-msg.ai .nab-cf-bubble{background:#f1f5f9;color:#1e293b;border-radius:4px 12px 12px 12px}
.nab-cf-chat-msg.user .nab-cf-bubble{background:#0D5C9B;color:#fff;border-radius:12px 4px 12px 12px}
.nab-cf-chat-row{display:flex;gap:10px;padding:14px 20px;border-top:1px solid #f1f5f9}
.nab-cf-chat-in{flex:1;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px}
.nab-cf-chat-in:focus{outline:none;border-color:#0D5C9B}
.nab-cf-chat-btn{background:#0D5C9B;color:#fff;border:none;border-radius:8px;padding:10px 18px;font-size:13px;font-weight:700;cursor:pointer}
.nab-cf-chat-btn:hover{background:#0a4a7c}
.nab-cf-chat-ph{text-align:center;padding:20px;color:#94a3b8;font-size:13px}
.nab-cf-chat-ph strong{display:block;color:#64748b;margin-bottom:6px}
@media(max-width:640px){
  .nab-cf-q1-wrap{grid-template-columns:1fr}
  .nab-cf-opts{grid-template-columns:1fr}
  .nab-cf-hero{flex-direction:column;text-align:center}
}
</style>
</head>
<body <?php body_class('nab-portal-body'); ?>>
<?php wp_body_open(); ?>
<div class="nab-portal-wrap">
  <?php nab_render_sidebar('card-match'); ?>
  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title">💳 Credit Card Finder</div>
      <div class="nab-topbar-right"><?php nab_render_notification_bell(); ?></div>
    </header>
    <div class="nab-content">
      <div class="nab-cf-wrap">

        <div class="nab-cf-hero">
          <div>
            <div class="nab-cf-hero-title">Find Your Best Canadian Credit Card</div>
            <p class="nab-cf-hero-sub">Answer 5 questions — we match you to the right card for your profile and goals. No credit check. No affiliate links. 100% NAB guidance.</p>
          </div>
          <div class="nab-cf-hero-icon">💳</div>
        </div>

        <div class="nab-cf-step-label">
          <span id="nL1" class="active">Score</span><span id="nL2">Goal</span><span id="nL3">Deposit</span><span id="nL4">Income</span><span id="nL5">Annual Fee</span><span id="nL6">Statement</span>
        </div>
        <div class="nab-cf-steps">
          <div class="nab-cf-step active" id="nS1"><div class="nab-cf-step-dot"></div></div>
          <div class="nab-cf-step" id="nS2"><div class="nab-cf-step-dot"></div></div>
          <div class="nab-cf-step" id="nS3"><div class="nab-cf-step-dot"></div></div>
          <div class="nab-cf-step" id="nS4"><div class="nab-cf-step-dot"></div></div>
          <div class="nab-cf-step" id="nS5"><div class="nab-cf-step-dot"></div></div>
          <div class="nab-cf-step" id="nS6"></div>
        </div>

        <!-- Q1: Score -->
        <div class="nab-cf-qcard active" id="nQ1">
          <div class="nab-cf-q-eyebrow">Question 1 of 5</div>
          <div class="nab-cf-q-title">What is your current credit score range?</div>
          <div class="nab-cf-q-hint">Select a range, or enter your exact score for the most refined match.</div>
          <div class="nab-cf-q1-wrap">
            <div style="display:flex;flex-direction:column;gap:10px" id="nScoreOpts">
              <button class="nab-cf-opt" data-val="below_560" type="button"><span class="nab-cf-opt-icon">⚠️</span><span><span class="nab-cf-opt-label">Below 560</span><span class="nab-cf-opt-hint">No history or significant past issues</span></span></button>
              <button class="nab-cf-opt" data-val="560_649" type="button"><span class="nab-cf-opt-icon">📈</span><span><span class="nab-cf-opt-label">560 – 649</span><span class="nab-cf-opt-hint">Building or rebuilding credit</span></span></button>
              <button class="nab-cf-opt" data-val="650_699" type="button"><span class="nab-cf-opt-icon">🔄</span><span><span class="nab-cf-opt-label">650 – 699</span><span class="nab-cf-opt-hint">Fair — actively improving</span></span></button>
              <button class="nab-cf-opt" data-val="700_749" type="button"><span class="nab-cf-opt-icon">⭐</span><span><span class="nab-cf-opt-label">700 – 749</span><span class="nab-cf-opt-hint">Good credit standing</span></span></button>
              <button class="nab-cf-opt" data-val="750_plus" type="button"><span class="nab-cf-opt-icon">🏆</span><span><span class="nab-cf-opt-label">750 or above</span><span class="nab-cf-opt-hint">Excellent credit</span></span></button>
            </div>
            <div>
              <div class="nab-cf-exact-panel">
                <span class="nab-cf-exact-label">Or enter your exact score</span>
                <input type="number" class="nab-cf-exact-input" id="nExact" min="300" max="900" placeholder="e.g. 672" value="<?php echo $saved_score ?: ''; ?>">
                <div class="nab-cf-score-bar"></div>
                <div class="nab-cf-score-labels"><span>300 Poor</span><span>560 Fair</span><span>660 Good</span><span>800+ Excellent</span></div>
                <p style="font-size:11px;color:#64748b;margin:10px 0 0;line-height:1.5">Especially useful when you're near a card's qualifying threshold</p>
              </div>
            </div>
          </div>
          <div class="nab-cf-nav" style="margin-top:20px"><span></span><button class="nab-cf-next" id="nN1" disabled type="button">Next →</button></div>
        </div>

        <!-- Q2: Goal -->
        <div class="nab-cf-qcard" id="nQ2">
          <div class="nab-cf-q-eyebrow">Question 2 of 5</div>
          <div class="nab-cf-q-title">What is your main goal with a new credit card?</div>
          <div class="nab-cf-q-hint">Choose the one that best describes what you're looking for.</div>
          <div class="nab-cf-opts" id="nGoalOpts">
            <button class="nab-cf-opt" data-val="build_scratch" type="button"><span class="nab-cf-opt-icon">🏗️</span><span><span class="nab-cf-opt-label">Build credit from scratch</span><span class="nab-cf-opt-hint">Little or no Canadian credit history</span></span></button>
            <button class="nab-cf-opt" data-val="rebuild" type="button"><span class="nab-cf-opt-icon">🔄</span><span><span class="nab-cf-opt-label">Rebuild after past issues</span><span class="nab-cf-opt-hint">Missed payments, collections, or bankruptcy</span></span></button>
            <button class="nab-cf-opt" data-val="save_interest" type="button"><span class="nab-cf-opt-icon">📉</span><span><span class="nab-cf-opt-label">Save on interest</span><span class="nab-cf-opt-hint">I carry a balance and want a lower rate</span></span></button>
            <button class="nab-cf-opt" data-val="cashback" type="button"><span class="nab-cf-opt-icon">💰</span><span><span class="nab-cf-opt-label">Earn cashback</span><span class="nab-cf-opt-hint">I pay in full monthly and want rewards</span></span></button>
            <button class="nab-cf-opt" data-val="travel" type="button"><span class="nab-cf-opt-icon">✈️</span><span><span class="nab-cf-opt-label">Earn travel points</span><span class="nab-cf-opt-hint">I want points on everyday Canadian spending</span></span></button>
            <button class="nab-cf-opt" data-val="control" type="button"><span class="nab-cf-opt-icon">🛡️</span><span><span class="nab-cf-opt-label">Control spending</span><span class="nab-cf-opt-hint">Manage spending with zero debt risk</span></span></button>
          </div>
          <div class="nab-cf-nav"><button class="nab-cf-back" onclick="nGo(1)" type="button">Back</button><button class="nab-cf-next" id="nN2" disabled type="button">Next →</button></div>
        </div>

        <!-- Q3: Deposit -->
        <div class="nab-cf-qcard" id="nQ3">
          <div class="nab-cf-q-eyebrow">Question 3 of 5</div>
          <div class="nab-cf-q-title">Can you provide a security deposit if needed?</div>
          <div class="nab-cf-q-hint">Secured cards require a refundable deposit (typically $200–$500 CAD) held as your credit limit.</div>
          <div class="nab-cf-opts" id="nDepOpts">
            <button class="nab-cf-opt" data-val="yes" type="button"><span class="nab-cf-opt-icon">✅</span><span><span class="nab-cf-opt-label">Yes — I can provide a deposit</span><span class="nab-cf-opt-hint">Funds available for a deposit</span></span></button>
            <button class="nab-cf-opt" data-val="no" type="button"><span class="nab-cf-opt-icon">❌</span><span><span class="nab-cf-opt-label">No deposit needed</span><span class="nab-cf-opt-hint">Unsecured or prepaid cards only</span></span></button>
          </div>
          <div class="nab-cf-nav"><button class="nab-cf-back" onclick="nGo(2)" type="button">Back</button><button class="nab-cf-next" id="nN3" disabled type="button">Next →</button></div>
        </div>

        <!-- Q4: Income -->
        <div class="nab-cf-qcard" id="nQ4">
          <div class="nab-cf-q-eyebrow">Question 4 of 5</div>
          <div class="nab-cf-q-title">What is your approximate monthly income after tax?</div>
          <div class="nab-cf-q-hint">Some premium cards have minimum income requirements.</div>
          <div class="nab-cf-opts" id="nIncOpts">
            <button class="nab-cf-opt" data-val="under_2k" type="button"><span class="nab-cf-opt-icon">🎓</span><span><span class="nab-cf-opt-label">Under $2,000</span><span class="nab-cf-opt-hint">Part-time, student, or starting out</span></span></button>
            <button class="nab-cf-opt" data-val="2k_4k" type="button"><span class="nab-cf-opt-icon">💼</span><span><span class="nab-cf-opt-label">$2,000 – $3,999</span><span class="nab-cf-opt-hint">Moderate income</span></span></button>
            <button class="nab-cf-opt" data-val="4k_6k" type="button"><span class="nab-cf-opt-icon">📊</span><span><span class="nab-cf-opt-label">$4,000 – $5,999</span><span class="nab-cf-opt-hint">Solid income base</span></span></button>
            <button class="nab-cf-opt" data-val="6k_plus" type="button"><span class="nab-cf-opt-icon">🏦</span><span><span class="nab-cf-opt-label">$6,000 or above</span><span class="nab-cf-opt-hint">Higher income</span></span></button>
          </div>
          <div class="nab-cf-nav"><button class="nab-cf-back" onclick="nGo(3)" type="button">Back</button><button class="nab-cf-next" id="nN4" disabled type="button">Next →</button></div>
        </div>

        <!-- Q5: Annual Fee -->
        <div class="nab-cf-qcard" id="nQ5">
          <div class="nab-cf-q-eyebrow">Question 5 of 5</div>
          <div class="nab-cf-q-title">Are you comfortable paying an annual fee?</div>
          <div class="nab-cf-q-hint">Premium cards often return more in rewards than they cost.</div>
          <div class="nab-cf-opts" id="nFeeOpts">
            <button class="nab-cf-opt" data-val="no_fee" type="button"><span class="nab-cf-opt-icon">🆓</span><span><span class="nab-cf-opt-label">No — I want no annual fee</span><span class="nab-cf-opt-hint">Keep it free, always</span></span></button>
            <button class="nab-cf-opt" data-val="low_fee" type="button"><span class="nab-cf-opt-icon">💵</span><span><span class="nab-cf-opt-label">Yes — up to $100/year</span><span class="nab-cf-opt-hint">Small fee if rewards are worth it</span></span></button>
            <button class="nab-cf-opt" data-val="any_fee" type="button"><span class="nab-cf-opt-icon">🏆</span><span><span class="nab-cf-opt-label">Yes — any fee for best rewards</span><span class="nab-cf-opt-hint">Premium cards if value is there</span></span></button>
            <button class="nab-cf-opt" data-val="secured_fee" type="button"><span class="nab-cf-opt-icon">🔐</span><span><span class="nab-cf-opt-label">I need a secured card</span><span class="nab-cf-opt-hint">Secured or prepaid is fine</span></span></button>
          </div>
          <div class="nab-cf-nav"><button class="nab-cf-back" onclick="nGo(4)" type="button">Back</button><button class="nab-cf-next" id="nN5" disabled type="button">Next →</button></div>
        </div>

        <!-- Step 6: Statement Upload -->
        <div class="nab-cf-qcard" id="nQ6">
          <div class="nab-cf-q-eyebrow">Boost your results with statement analysis</div>
          <div class="nab-cf-q-title">Upload your credit card statement</div>
          <div class="nab-cf-q-hint">The AI will analyse your spending by category, score your existing card, and calculate how much you could save by switching. <strong>Your data is never stored.</strong></div>
          <div class="nab-cf-file-chosen" id="nFileChosen">
            <span>📄</span><span class="nab-cf-file-chosen-name" id="nFileName"></span>
            <button onclick="document.getElementById('nFileIn').value='';document.getElementById('nFileChosen').style.display='none'" type="button" style="background:none;border:none;cursor:pointer;color:#64748b;font-size:16px">✕</button>
          </div>
          <div class="nab-cf-upload-zone" id="nDropZone" onclick="document.getElementById('nFileIn').click()">
            <div class="nab-cf-upload-icon">📁</div>
            <div class="nab-cf-upload-title">Upload your credit card statement</div>
            <div class="nab-cf-upload-hint">PDF, CSV or image — drag and drop or click to browse</div>
            <div class="nab-cf-upload-banks">Supports TD, RBC, Scotiabank, BMO, CIBC, Desjardins, Capital One, Amex and more</div>
          </div>
          <input type="file" id="nFileIn" accept=".pdf,.csv,.png,.jpg,.jpeg" style="display:none">
          <div class="nab-cf-nav">
            <button class="nab-cf-back" onclick="nGo(5)" type="button">Back</button>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
              <button class="nab-cf-next" onclick="nRunMatch()" type="button">Find My Matches →</button>
              <button class="nab-cf-upload-skip" onclick="nRunMatch()" type="button">Skip — match without statement</button>
            </div>
          </div>
        </div>

        <!-- Results -->
        <div class="nab-cf-results" id="nResults">
          <div class="nab-cf-results-header">
            <div class="nab-cf-results-title">🎯 Your Personalised Matches</div>
            <div class="nab-cf-results-sub">Based on your answers, here are the best Canadian credit cards for your profile.</div>
            <button class="nab-cf-retake" onclick="nReset()" type="button">← Start over with different answers</button>
          </div>
          <div id="nResList"></div>
          <div class="nab-cf-disclaimer">Educational guidance only. NAB Solutions is not affiliated with any card issuer and receives no compensation for these recommendations. Card features, interest rates, and eligibility criteria change — always verify directly with the issuer before applying. This is not financial advice.</div>
        </div>

        <!-- NAB Credit Assistant -->
        <div class="nab-cf-assistant" id="nAssistant">
          <div class="nab-cf-assistant-hdr">
            <div class="nab-cf-assistant-dot"></div>
            <div><div class="nab-cf-assistant-title">NAB Credit Assistant</div><div class="nab-cf-assistant-sub">Ask anything about your results or Canadian credit cards</div></div>
          </div>
          <div class="nab-cf-chat-body" id="nChatBody">
            <div class="nab-cf-chat-ph" id="nChatPh"><strong>Your personalised analysis is ready.</strong>I can explain any recommendation in detail, compare two specific cards, help you understand minimum score requirements, or answer any question about Canadian credit cards.</div>
          </div>
          <div class="nab-cf-chat-row">
            <input type="text" class="nab-cf-chat-in" id="nChatIn" placeholder="e.g. Which card saves me the most if I carry a balance?">
            <button class="nab-cf-chat-btn" id="nChatSend" type="button">Send</button>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<script>
(function(){
var ANS={score:'',goal:'',deposit:'',income:'',fee:''};
var CUR=1;

var CARDS=[
  {id:'neo',name:'Neo Financial Secured Visa',issuer:'Neo Financial',score:['below_560','560_649'],goals:['build_scratch','rebuild','control'],dep:['yes'],inc:['under_2k','2k_4k','4k_6k','6k_plus'],fee:['secured_fee','no_fee'],tags:['No credit check','Reports to both bureaus','5% cashback at Neo partners'],slbl:'All credit levels',scls:'nab-cf-tag-score-b',why:'<strong>Best for building credit from scratch.</strong> No credit check required — you put down a refundable deposit and get a real Visa that reports to Equifax and TransUnion monthly. Neo partners offer 5% cashback which helps offset the deposit cost.'},
  {id:'cap1',name:'Capital One Guaranteed Mastercard',issuer:'Capital One',score:['below_560','560_649','650_699'],goals:['build_scratch','rebuild'],dep:['no'],inc:['under_2k','2k_4k','4k_6k','6k_plus'],fee:['no_fee','low_fee','secured_fee'],tags:['Guaranteed approval','$59/year','Reports monthly','No credit check'],slbl:'All credit levels',scls:'nab-cf-tag-score-b',why:'<strong>Guaranteed approval — truly no credit check.</strong> One of the few Canadian cards that guarantees approval regardless of history. Ideal when you need a card right now while rebuilding.'},
  {id:'ht',name:'Home Trust Secured Visa',issuer:'Home Trust',score:['below_560','560_649','650_699'],goals:['build_scratch','rebuild','save_interest'],dep:['yes'],inc:['under_2k','2k_4k','4k_6k','6k_plus'],fee:['no_fee','low_fee','secured_fee'],tags:['No annual fee option','14.9% interest rate','$500–$10,000 limit','No credit check'],slbl:'Poor to Fair credit OK',scls:'nab-cf-tag-score-b',why:'<strong>Best no-fee secured card in Canada.</strong> Home Trust offers one of the lowest interest rates for credit builders with no annual fee option — ideal if you are watching costs.'},
  {id:'tan',name:'Tangerine Money-Back Credit Card',issuer:'Tangerine (Scotiabank)',score:['560_649','650_699','700_749','750_plus'],goals:['cashback','control'],dep:['no'],inc:['under_2k','2k_4k','4k_6k','6k_plus'],fee:['no_fee'],tags:['No annual fee','2% cashback in 2 categories you choose','0.5% on everything else'],slbl:'Fair credit OK (580+)',scls:'nab-cf-tag-score-f',why:'<strong>Best no-fee cashback card.</strong> You choose your own 2% categories — groceries, gas, dining. No annual fee makes this zero-risk with strong rewards flexibility.'},
  {id:'sim',name:'Simplii Financial Cash Back Visa',issuer:'Simplii / CIBC',score:['560_649','650_699','700_749','750_plus'],goals:['cashback','save_interest'],dep:['no'],inc:['under_2k','2k_4k','4k_6k','6k_plus'],fee:['no_fee'],tags:['No annual fee','4% on restaurants (up to $5k/yr)','1.5% on gas & groceries'],slbl:'Fair credit OK (580+)',scls:'nab-cf-tag-score-f',why:'<strong>Best no-fee card for dining.</strong> 4% cashback on restaurants with no annual fee is exceptional. If you eat out regularly the rewards add up fast with zero cost.'},
  {id:'mbna',name:'MBNA True Line Mastercard',issuer:'MBNA / TD',score:['560_649','650_699','700_749'],goals:['save_interest','control'],dep:['no'],inc:['under_2k','2k_4k','4k_6k','6k_plus'],fee:['no_fee','low_fee'],tags:['12.99% purchase rate','No annual fee option','0% balance transfer intro'],slbl:'Fair to Good (580+)',scls:'nab-cf-tag-score-f',why:'<strong>Best low-interest card.</strong> 12.99% saves you significantly vs. the standard 19.99% most cards charge. The balance transfer offer adds extra value for those carrying existing debt.'},
  {id:'scot',name:'Scotia Momentum Visa Infinite',issuer:'Scotiabank',score:['700_749','750_plus'],goals:['cashback'],dep:['no'],inc:['4k_6k','6k_plus'],fee:['low_fee','any_fee'],tags:['10% cashback first 3 months','4% on groceries & gas','$120 annual fee'],slbl:'Good credit required (670+)',scls:'nab-cf-tag-score-g',why:'<strong>Top cashback card in Canada.</strong> If you spend heavily on groceries and gas, no card matches this rate. The $120 fee pays for itself quickly with moderate spending.'},
  {id:'cibc',name:'CIBC Aventura Visa Infinite',issuer:'CIBC',score:['700_749','750_plus'],goals:['travel'],dep:['no'],inc:['4k_6k','6k_plus'],fee:['low_fee','any_fee'],tags:['15,000 welcome points','2x on travel bookings','Travel insurance included'],slbl:'Good credit required (670+)',scls:'nab-cf-tag-score-g',why:'<strong>Best all-around travel card.</strong> Strong welcome bonus, flexible redemption across airlines, solid earn rate, and travel insurance built in.'},
  {id:'bmo',name:'BMO Air Miles World Elite Mastercard',issuer:'BMO',score:['750_plus'],goals:['travel','cashback'],dep:['no'],inc:['6k_plus'],fee:['any_fee'],tags:['3x Air Miles on travel','2x on groceries','Lounge access','Full travel insurance'],slbl:'Excellent credit required (700+)',scls:'nab-cf-tag-score-g',why:'<strong>Best for Air Miles collectors.</strong> High earners accumulate miles very quickly at Air Miles partners. Lounge access and full travel insurance add premium value.'},
  {id:'rog',name:'Rogers World Elite Mastercard',issuer:'Rogers Bank',score:['700_749','750_plus'],goals:['cashback','control'],dep:['no'],inc:['4k_6k','6k_plus'],fee:['no_fee'],tags:['No annual fee','1.5% cashback on all purchases','3% on USD transactions'],slbl:'Good credit required (670+)',scls:'nab-cf-tag-score-g',why:'<strong>Best flat-rate no-fee cashback card.</strong> 1.5% on everything with no restrictions. The 3% on USD purchases is exceptional for online shopping in US dollars.'},
];

function resolveScore(){
  var ex=parseInt(document.getElementById('nExact').value);
  if(ex>=300&&ex<=900){
    if(ex<560)return 'below_560';
    if(ex<650)return '560_649';
    if(ex<700)return '650_699';
    if(ex<750)return '700_749';
    return '750_plus';
  }
  return ANS.score;
}

function nGo(step){
  document.getElementById('nQ'+CUR).classList.remove('active');
  for(var i=1;i<=6;i++){
    var s=document.getElementById('nS'+i),l=document.getElementById('nL'+i);
    if(!s)continue;
    s.classList.remove('active','done');
    if(l)l.classList.remove('active','done');
    if(i<step){s.classList.add('done');if(l)l.classList.add('done');}
    if(i===step){s.classList.add('active');if(l)l.classList.add('active');}
  }
  CUR=step;
  document.getElementById('nQ'+step).classList.add('active');
  window.scrollTo({top:document.getElementById('nQ'+step).offsetTop-20,behavior:'smooth'});
}
window.nGo=nGo;

function bindQ(cid,key,nid){
  var c=document.getElementById(cid);
  if(!c)return;
  c.querySelectorAll('.nab-cf-opt').forEach(function(b){
    b.addEventListener('click',function(){
      c.querySelectorAll('.nab-cf-opt').forEach(function(x){x.classList.remove('selected','sel-yes','sel-no');});
      var cls=this.dataset.val==='yes'?'sel-yes':this.dataset.val==='no'?'sel-no':'selected';
      this.classList.add(cls);
      ANS[key]=this.dataset.val;
      var nb=document.getElementById(nid);if(nb)nb.disabled=false;
    });
  });
}

document.getElementById('nScoreOpts').querySelectorAll('.nab-cf-opt').forEach(function(b){
  b.addEventListener('click',function(){
    document.getElementById('nScoreOpts').querySelectorAll('.nab-cf-opt').forEach(function(x){x.classList.remove('selected');});
    this.classList.add('selected');ANS.score=this.dataset.val;
    document.getElementById('nN1').disabled=false;
  });
});
document.getElementById('nExact').addEventListener('input',function(){
  if(parseInt(this.value)>=300&&parseInt(this.value)<=900)document.getElementById('nN1').disabled=false;
});

bindQ('nGoalOpts','goal','nN2');
bindQ('nDepOpts','deposit','nN3');
bindQ('nIncOpts','income','nN4');
bindQ('nFeeOpts','fee','nN5');

document.getElementById('nN1').addEventListener('click',function(){nGo(2);});
document.getElementById('nN2').addEventListener('click',function(){nGo(3);});
document.getElementById('nN3').addEventListener('click',function(){nGo(4);});
document.getElementById('nN4').addEventListener('click',function(){nGo(5);});
document.getElementById('nN5').addEventListener('click',function(){nGo(6);});

var fi=document.getElementById('nFileIn');
fi.addEventListener('change',function(){if(this.files[0]){document.getElementById('nFileName').textContent=this.files[0].name;document.getElementById('nFileChosen').style.display='flex';}});
var dz=document.getElementById('nDropZone');
dz.addEventListener('dragover',function(e){e.preventDefault();this.classList.add('drag-over');});
dz.addEventListener('dragleave',function(){this.classList.remove('drag-over');});
dz.addEventListener('drop',function(e){e.preventDefault();this.classList.remove('drag-over');if(e.dataTransfer.files[0]){fi.files=e.dataTransfer.files;document.getElementById('nFileName').textContent=e.dataTransfer.files[0].name;document.getElementById('nFileChosen').style.display='flex';}});

<?php if($saved_score): ?>
(function(){var s=<?php echo(int)$saved_score;?>,v=s<560?'below_560':s<650?'560_649':s<700?'650_699':s<750?'700_749':'750_plus',b=document.querySelector('#nScoreOpts .nab-cf-opt[data-val="'+v+'"]');if(b)b.click();})();
<?php endif;?>

function scoreCard(c){
  var sr=resolveScore();if(!sr)return 0;
  if(c.score.indexOf(sr)===-1)return 0;
  var p=40;
  if(c.goals.indexOf(ANS.goal)!==-1)p+=30;
  if(c.dep.indexOf(ANS.deposit)!==-1)p+=15;
  if(c.inc.indexOf(ANS.income)!==-1)p+=10;
  if(c.fee.indexOf(ANS.fee)!==-1)p+=20;
  return p;
}

window.nRunMatch=function(){
  var scored=CARDS.map(function(c){return{c:c,s:scoreCard(c)};}).filter(function(x){return x.s>0;}).sort(function(a,b){return b.s-a.s;}).slice(0,4);
  document.getElementById('nQ6').classList.remove('active');
  document.getElementById('nResults').style.display='block';
  document.getElementById('nAssistant').style.display='block';
  if(!scored.length){
    document.getElementById('nResList').innerHTML='<div style="background:#fff;border-radius:14px;padding:32px;text-align:center;box-shadow:0 2px 12px rgba(0,0,0,.06)"><div style="font-size:32px;margin-bottom:12px">🤔</div><div style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:8px">No exact match found</div><p style="font-size:13px;color:#64748b">Try starting over with slightly different answers.</p><button onclick="nReset()" style="background:#0D5C9B;color:#fff;border:none;border-radius:8px;padding:11px 24px;font-size:13px;font-weight:700;cursor:pointer;margin-top:12px">← Try Again</button></div>';
    return;
  }
  var mx=115,html='';
  scored.forEach(function(x,i){
    var c=x.c,isT=(i===0),pct=Math.min(99,Math.round((x.s/mx)*100));
    html+='<div class="nab-cf-result-item'+(isT?' top':'')+'">';
    if(isT)html+='<div class="nab-cf-top-badge">⭐ Top Match</div>';
    html+='<div class="nab-cf-card-row"><div style="flex:1">';
    html+='<div class="nab-cf-card-name">'+c.name+'</div><div class="nab-cf-card-issuer">'+c.issuer+'</div>';
    html+='<div class="nab-cf-tags"><span class="nab-cf-tag '+c.scls+'">'+c.slbl+'</span>';
    c.tags.forEach(function(t){html+='<span class="nab-cf-tag">'+t+'</span>';});
    html+='</div>';
    html+='<button class="nab-cf-why-toggle" onclick="var w=this.nextElementSibling;w.classList.toggle(\'open\');this.textContent=w.classList.contains(\'open\')?\'▲ Hide details\':\'▼ Why this card for me?\'" type="button">▼ Why this card for me?</button>';
    html+='<div class="nab-cf-why-body">'+c.why+'</div>';
    html+='</div><div><div class="nab-cf-match-pct">'+pct+'%</div><div class="nab-cf-match-label">Match</div></div></div></div>';
  });
  document.getElementById('nResList').innerHTML=html;
  setTimeout(function(){document.getElementById('nResults').scrollIntoView({behavior:'smooth',block:'start'});},100);
  initChat(scored);
};

function initChat(matches){
  var hasOAI=<?php echo $has_openai?'true':'false';?>;
  var ctx='Member answers — Score:'+resolveScore()+' Goal:'+ANS.goal+' Deposit:'+ANS.deposit+' Income:'+ANS.income+' Fee:'+ANS.fee+' Top matches:'+matches.slice(0,3).map(function(x){return x.c.name;}).join(', ');
  var send=document.getElementById('nChatSend'),inp=document.getElementById('nChatIn'),body=document.getElementById('nChatBody');
  function addMsg(txt,role){
    var ph=document.getElementById('nChatPh');if(ph)ph.style.display='none';
    var d=document.createElement('div');d.className='nab-cf-chat-msg '+role;
    d.innerHTML='<div class="nab-cf-bubble">'+txt+'</div>';
    body.appendChild(d);body.scrollTop=body.scrollHeight;
  }
  function addTyping(){var d=document.createElement('div');d.className='nab-cf-chat-msg ai';d.id='nTyping';d.innerHTML='<div class="nab-cf-bubble" style="color:#94a3b8">NAB is thinking…</div>';body.appendChild(d);body.scrollTop=body.scrollHeight;return d;}
  function offlineReply(q){
    q=q.toLowerCase();
    var t=matches[0]?matches[0].c:null;
    if(q.indexOf('balance')>-1||q.indexOf('interest')>-1)return 'If you carry a balance, focus on low interest rate cards. The <strong>MBNA True Line</strong> at 12.99% can save you hundreds vs the standard 19.99% on most cards.';
    if(q.indexOf('score')>-1||q.indexOf('qualify')>-1)return t?'For the '+t.name+', the minimum score range is '+t.slbl+'. Apply when your score has been stable for 3+ months and space applications 6 months apart to minimise hard inquiry impact.':'Your matches above are already filtered for your score range — these issuers typically approve applicants in your range.';
    if(q.indexOf('secured')>-1||q.indexOf('deposit')>-1)return 'A secured card requires a refundable deposit ($200–$500 CAD) as your credit limit. After 12–18 months of on-time payments, most issuers review you for an unsecured upgrade. <strong>Home Trust Secured Visa</strong> and <strong>Neo Financial</strong> are the top options in Canada.';
    if(q.indexOf('fee')>-1||q.indexOf('worth')>-1)return 'An annual fee is worth it when rewards earned exceed the cost. A $120/year card with 4% on groceries becomes profitable at $3,000+ grocery spend per year. Calculate your actual category spending first.';
    if(q.indexOf('compare')>-1&&matches.length>=2)return 'Your top 2: <strong>'+matches[0].c.name+'</strong> — '+matches[0].c.tags[0]+'. <strong>'+matches[1].c.name+'</strong> — '+matches[1].c.tags[0]+'. The right choice depends on which feature matters most in your daily spending.';
    return 'Based on your profile, your top matches above are filtered specifically for your score, goal, and fee preference. When ready to apply, always verify current terms directly with the issuer as rates and offers change regularly. What else can I explain?';
  }
  send.addEventListener('click',function(){
    var q=inp.value.trim();if(!q)return;
    addMsg(q,'user');inp.value='';send.disabled=true;
    if(!hasOAI){var t=addTyping();setTimeout(function(){t.remove();addMsg(offlineReply(q),'ai');send.disabled=false;},700);return;}
    var t=addTyping();
    fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'action=nab_card_assistant&nonce=<?php echo wp_create_nonce("nab_card_assistant");?>&question='+encodeURIComponent(q)+'&context='+encodeURIComponent(ctx)})
    .then(function(r){return r.json();}).then(function(d){t.remove();addMsg(d.success?d.data.reply:'Unable to respond right now.','ai');send.disabled=false;})
    .catch(function(){t.remove();addMsg(offlineReply(q),'ai');send.disabled=false;});
  });
  inp.addEventListener('keydown',function(e){if(e.key==='Enter')send.click();});
}

window.nReset=function(){
  ANS={score:'',goal:'',deposit:'',income:'',fee:''};
  document.querySelectorAll('.nab-cf-opt').forEach(function(b){b.classList.remove('selected','sel-yes','sel-no');});
  document.querySelectorAll('.nab-cf-qcard').forEach(function(c){c.classList.remove('active');});
  document.getElementById('nResults').style.display='none';
  document.getElementById('nAssistant').style.display='none';
  document.getElementById('nChatBody').innerHTML='<div class="nab-cf-chat-ph" id="nChatPh"><strong>Your personalised analysis is ready.</strong>Ask me anything about your matches or Canadian credit cards.</div>';
  ['nN1','nN2','nN3','nN4','nN5'].forEach(function(id){var b=document.getElementById(id);if(b)b.disabled=true;});
  CUR=1;document.getElementById('nQ1').classList.add('active');
  for(var i=1;i<=6;i++){var s=document.getElementById('nS'+i),l=document.getElementById('nL'+i);if(s)s.classList.remove('active','done');if(l)l.classList.remove('active','done');}
  document.getElementById('nS1').classList.add('active');
  document.getElementById('nL1').classList.add('active');
  window.scrollTo({top:0,behavior:'smooth'});
};
})();
</script>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>
