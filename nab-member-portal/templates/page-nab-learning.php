<?php
/**
 * Template Name: NAB Learning Center
 * Redesigned to match Matthew's reference image.
 * Two-column layout: video grid left, financial roadmap right.
 *
 * @package NAB_Member_Portal
 * @since   1.6.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
}
do_action( 'litespeed_control_set_nocache', 'Learning Center has user-specific progress' );

$user       = wp_get_current_user();
$uid        = $user->ID;
$first_name = get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ?: 'Member';

$completed_modules = json_decode( get_user_meta( $uid, 'nab_modules_completed', true ) ?: '[]', true );
if ( ! is_array( $completed_modules ) ) $completed_modules = [];
$completed_count = count( $completed_modules );
$total_modules   = 7;
$progress_pct    = $total_modules > 0 ? round( ( $completed_count / $total_modules ) * 100 ) : 0;

$did = nab_get_dash_page_id();
$edu_modules = [
    1 => [ 'title'=>'What is Credit, Really?',                                        'duration'=>'8 Min',  'category'=>'Credit Basics',    'desc'=>'Understand what credit is and why it matters in your financial life.', 'url'=>($did&&get_field('nab_vid1_url',$did))?get_field('nab_vid1_url',$did):'https://www.youtube.com/embed/9wjyyDtHZu4',  'thumb'=>($did&&get_field('nab_vid1_thumb',$did))?get_field('nab_vid1_thumb',$did):NAB_URL.'assets/img/modules/module-1.png', 'overview'=>'Credit is more than just a number; it is a formal transactional relationship built on documented trust.', 'concepts'=>['The Price of Speed: Interest is the fee you pay for the convenience of using someone else money now.','The Lender\'s Perspective: Every time you borrow, the lender is making a bet on your reliability.','Credit Bureaus as Historians: In Canada, Equifax and TransUnion collect data to build your financial file.'], 'steps'=>['Check Both Reports Annually.','Monitor for Accuracy.','Do Not Fear Checking Your Own Score.'] ],
    2 => [ 'title'=>'The Trust Recipe: How Credit Scores Are Calculated',               'duration'=>'10 Min', 'category'=>'Credit Scores',    'desc'=>'Explore Equifax, TransUnion, Borrowell, and Credit Karma.', 'url'=>($did&&get_field('nab_vid2_url',$did))?get_field('nab_vid2_url',$did):'https://www.youtube.com/embed/aAMOEmWziak',  'thumb'=>($did&&get_field('nab_vid2_thumb',$did))?get_field('nab_vid2_thumb',$did):NAB_URL.'assets/img/modules/module-2.png', 'overview'=>'Your credit score is a trust recipe built from your everyday financial habits.', 'concepts'=>['Payment History (35%): Measures your consistency in paying bills on time.','Credit Utilization (30%): Keep balances below 30% of your available credit limit.','Length of Credit History (15%): The age of your oldest and newest accounts matters.'], 'steps'=>['Prioritize On-Time Payments.','Preserve Old Accounts.','Space Out Applications.'] ],
    3 => [ 'title'=>'The Escalating Cost of Bad Advice',                               'duration'=>'9 Min',  'category'=>'Credit Education', 'desc'=>'Learn the difference between hard and soft inquiries and their impact.', 'url'=>($did&&get_field('nab_vid3_url',$did))?get_field('nab_vid3_url',$did):'https://www.youtube.com/embed/KNhx-HFivNA',  'thumb'=>($did&&get_field('nab_vid3_thumb',$did))?get_field('nab_vid3_thumb',$did):NAB_URL.'assets/img/modules/module-3.png', 'overview'=>'This module replaces financial guesswork with algorithmic facts.', 'concepts'=>['Hard vs Soft Inquiries: Applying for new debt triggers a hard inquiry.','The 15% History Anchor: Closing old accounts deletes history.','The Utilization Fraction: Closing a card shrinks your available credit.'], 'steps'=>['Monitor Freely.','Preserve Old Accounts.','Avoid Unnecessary Interest.'] ],
    4 => [ 'title'=>'Free Canadian Credit Reports: The Complete Guide',                 'duration'=>'12 Min', 'category'=>'Credit Reports',   'desc'=>'Step-by-step guide to access your free Canadian credit reports.', 'url'=>($did&&get_field('nab_vid4_url',$did))?get_field('nab_vid4_url',$did):'https://www.youtube.com/embed/5Sc4L2lIOxo',  'thumb'=>($did&&get_field('nab_vid4_thumb',$did))?get_field('nab_vid4_thumb',$did):NAB_URL.'assets/img/modules/module-4.png', 'overview'=>'Every Canadian is legally entitled to a free copy of their credit report from both bureaus.', 'concepts'=>['Legal Entitlement: Free access from Equifax and TransUnion.','Zero Score Impact: Pulling your own report is a soft inquiry.','Security Prerequisites: You will need your SIN and address history.'], 'steps'=>['Pull from equifax.ca and transunion.ca.','Audit Every Line.','Correct to Boost — dispute inaccuracies.'] ],
    5 => [ 'title'=>'Securing Your Free Canadian Credit Reports',                       'duration'=>'7 Min',  'category'=>'Credit Reports',   'desc'=>'Actionable strategies to strengthen your credit over time.', 'url'=>($did&&get_field('nab_vid5_url',$did))?get_field('nab_vid5_url',$did):'https://www.youtube.com/embed/8_ns3DHBVII',  'thumb'=>($did&&get_field('nab_vid5_thumb',$did))?get_field('nab_vid5_thumb',$did):NAB_URL.'assets/img/modules/module-5.png', 'overview'=>'This module guides you through securing your official credit reports safely.', 'concepts'=>['Preparation is Critical: Have your documents ready before you start.','Identity Verification: You will be asked time-sensitive questions.','The Upsell Trap: Look past paid services to find the free options.'], 'steps'=>['Gather Documents.','Access Equifax at consumer.equifax.ca.','Download and Organize both reports.'] ],
    6 => [ 'title'=>'The Credit Inquiry Guide: Understanding Hard and Soft Pulls',      'duration'=>'11 Min', 'category'=>'Credit Inquiries', 'desc'=>'Avoid costly credit mistakes that could hold you back.', 'url'=>($did&&get_field('nab_vid6_url',$did))?get_field('nab_vid6_url',$did):'https://www.youtube.com/embed/idthInO6S98',  'thumb'=>($did&&get_field('nab_vid6_thumb',$did))?get_field('nab_vid6_thumb',$did):NAB_URL.'assets/img/modules/module-6.png', 'overview'=>'Every time your credit file is accessed, it leaves a digital footprint.', 'concepts'=>['Soft Inquiries: Routine checks with zero score impact.','Hard Inquiries: Triggered when you apply for new credit.','Penalty Decay: Hard inquiry penalty fades within 12 months.'], 'steps'=>['Perform a Color-Coded Audit.','Cluster Applications.','Dispute Fraud immediately.'] ],
    7 => [ 'title'=>'Building Better Financial Habits',                                 'duration'=>'13 Min', 'category'=>'Financial Habits', 'desc'=>'Build habits that support long-term financial success and peace of mind.', 'url'=>($did&&get_field('nab_vid7_url',$did))?get_field('nab_vid7_url',$did):'https://www.youtube.com/embed/eZW2-mcXqz4',  'thumb'=>($did&&get_field('nab_vid7_thumb',$did))?get_field('nab_vid7_thumb',$did):NAB_URL.'assets/img/modules/module-7.png', 'overview'=>'This module shifts the focus from perfection to building resilience through consistent daily habits.', 'concepts'=>['The Diagnostic Budget: A budget is a diagnostic tool.','The 50/30/20 Framework: 50% needs, 30% wants, 20% savings.','The Savings Shock Absorber: An emergency fund protects your credit.'], 'steps'=>['Shift Mindsets.','Monitor Accounts Regularly.','Scale Savings Strategically.'] ],
];

$notifications = function_exists('nab_get_user_notifications') ? nab_get_user_notifications($uid) : [];

nab_head_open( 'Learning Center — NAB Member Portal' );
?>
<style>
/* ── Learning Center v2 ──────────────────────────────────── */
.nab-lc-page{max-width:1100px;margin:0 auto;padding-bottom:60px}

/* Hero */
.nab-lc-hero-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
.nab-lc-welcome{background:#fff;border-radius:16px;padding:28px;box-shadow:0 1px 8px rgba(0,0,0,.07)}
.nab-lc-welcome-icon{font-size:36px;margin-bottom:10px}
.nab-lc-welcome-title{font-size:20px;font-weight:800;color:#1e293b;margin:0 0 8px}
.nab-lc-welcome-sub{font-size:13px;color:#64748b;line-height:1.6;margin:0 0 20px}
.nab-lc-prog-label{font-size:12px;font-weight:600;color:#374151;margin-bottom:6px}
.nab-lc-prog-bar{height:8px;background:#e2e8f0;border-radius:4px;margin-bottom:6px}
.nab-lc-prog-fill{height:100%;background:#0D5C9B;border-radius:4px;transition:.6s}
.nab-lc-prog-count{font-size:12px;color:#64748b}

.nab-lc-why{background:#fff;border-radius:16px;padding:28px;box-shadow:0 1px 8px rgba(0,0,0,.07)}
.nab-lc-why-title{font-size:16px;font-weight:800;color:#1e293b;margin:0 0 16px}
.nab-lc-why-item{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px}
.nab-lc-why-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.nab-lc-why-text strong{display:block;font-size:13px;font-weight:700;color:#1e293b;margin-bottom:2px}
.nab-lc-why-text span{font-size:12px;color:#64748b}

/* Main layout */
.nab-lc-main{display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start}

/* Video series */
.nab-lc-series-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 8px rgba(0,0,0,.07)}
.nab-lc-series-title{font-size:16px;font-weight:800;color:#1e293b;margin:0 0 20px}

/* Module grid - 3 columns */
.nab-lc-module-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.nab-lc-mod{border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden;cursor:pointer;transition:.2s;position:relative}
.nab-lc-mod:hover{border-color:#0D5C9B;box-shadow:0 4px 16px rgba(13,92,155,.1)}
.nab-lc-mod.completed{border-color:#16a34a}
.nab-lc-mod.locked{opacity:.6;cursor:not-allowed;pointer-events:none}
.nab-lc-mod.active-mod{border-color:#0D5C9B;box-shadow:0 4px 16px rgba(13,92,155,.15)}
.nab-lc-mod-thumb{width:100%;height:100px;object-fit:cover;display:block;background:#e2e8f0}
.nab-lc-mod-thumb-placeholder{width:100%;height:100px;background:linear-gradient(135deg,#0D5C9B,#0a4a7c);display:flex;align-items:center;justify-content:center;font-size:28px}
.nab-lc-mod-badge{position:absolute;top:8px;right:8px;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px}
.badge-completed{background:#16a34a;color:#fff}
.badge-inprogress{background:#0D5C9B;color:#fff}
.badge-locked{background:rgba(0,0,0,.5);color:#fff}
.nab-lc-mod-body{padding:10px 12px}
.nab-lc-mod-num{font-size:10px;color:#94a3b8;margin-bottom:2px}
.nab-lc-mod-title{font-size:12px;font-weight:700;color:#1e293b;line-height:1.3;margin-bottom:4px}
.nab-lc-mod-desc{font-size:11px;color:#64748b;line-height:1.4}
.nab-lc-mod-status{font-size:10px;font-weight:700;margin-top:6px;padding:2px 8px;border-radius:20px;display:inline-block}
.status-completed{background:#dcfce7;color:#166534}
.status-inprogress{background:#dbeafe;color:#1e40af}
.status-locked{background:#f1f5f9;color:#94a3b8}

/* Stay consistent bar */
.nab-lc-tip{background:#fffbeb;border:1.5px solid #fbbf24;border-radius:10px;padding:12px 16px;margin-top:20px;display:flex;align-items:center;gap:10px;font-size:13px;color:#78350f}
.nab-lc-tip-icon{font-size:18px;flex-shrink:0}

/* Financial Roadmap sidebar */
.nab-lc-roadmap{background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 8px rgba(0,0,0,.07);position:sticky;top:20px}
.nab-lc-roadmap-badge{background:#16a34a;color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;display:inline-block;margin-bottom:12px}
.nab-lc-roadmap-title{font-size:16px;font-weight:800;color:#1e293b;margin:0 0 8px}
.nab-lc-roadmap-img{width:80px;height:80px;margin:12px auto;display:block}
.nab-lc-roadmap-desc{font-size:12px;color:#64748b;line-height:1.6;margin-bottom:14px}
.nab-lc-roadmap-check{display:flex;align-items:center;gap:8px;font-size:12px;color:#374151;margin-bottom:8px}
.nab-lc-roadmap-check::before{content:"✅";font-size:14px}
.nab-lc-roadmap-btn{background:#F97316;color:#fff;border:none;border-radius:8px;padding:12px 20px;font-size:13px;font-weight:700;cursor:pointer;width:100%;margin-top:16px;transition:.15s}
.nab-lc-roadmap-btn:hover{background:#ea6c00}
.nab-lc-roadmap-sub{font-size:11px;color:#94a3b8;text-align:center;margin-top:8px}

/* Module detail panel — full width below grid */
.nab-lc-detail{display:none}
.nab-lc-fullpanel{display:none;margin-top:20px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(13,92,155,.12);border:2px solid #0D5C9B;overflow:hidden}
.nab-lc-fullpanel.open{display:block}
.nab-lc-fullpanel-inner{display:flex;flex-direction:column;gap:0}
.nab-lc-fullpanel-video-row{padding:24px 24px 0}
.nab-lc-fullpanel-bottom{display:flex;flex-direction:column}
.nab-lc-fullpanel-left{padding:24px;border-bottom:1px solid #f1f5f9}
.nab-lc-fullpanel-right{padding:24px}
.nab-lc-fullpanel-header{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;background:linear-gradient(135deg,#0D5C9B,#0a4a7c);color:#fff}
.nab-lc-fullpanel-title{font-size:16px;font-weight:800;margin:0}
.nab-lc-fullpanel-close{background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;transition:.15s}
.nab-lc-fullpanel-close:hover{background:rgba(255,255,255,.35)}
.nab-lc-detail-vid{aspect-ratio:16/9;background:#000;border-radius:10px;overflow:hidden;margin-bottom:16px;cursor:pointer;position:relative}
.nab-lc-detail-vid img{width:100%;height:100%;object-fit:cover;display:block}
.nab-lc-detail-play{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:56px;height:56px;background:rgba(255,0,0,.9);border-radius:50%;display:flex;align-items:center;justify-content:center}
.nab-lc-detail-play div{width:0;height:0;border-top:10px solid transparent;border-bottom:10px solid transparent;border-left:18px solid #fff;margin-left:4px}

.nab-lc-section-lbl{font-size:10px;font-weight:700;color:#0D5C9B;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}
.nab-lc-overview-text{font-size:13px;color:#374151;line-height:1.7;margin-bottom:16px}
.nab-lc-list{padding-left:18px;margin:6px 0 16px}
.nab-lc-list li{font-size:13px;color:#374151;line-height:1.6;margin-bottom:4px}
.nab-lc-quiz-section{background:#f8fafc;border-radius:10px;padding:16px;margin-top:8px}
.nab-lc-quiz-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.nab-lc-btn{background:#0D5C9B;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer;transition:.15s}
.nab-lc-btn:hover{background:#0a4a7c}
.nab-lc-btn-green{background:#16a34a}
.nab-lc-btn-green:hover{background:#15803d}

/* Quiz */
.nab-lc-quiz-ui{margin-top:14px}
.nab-lc-quiz-bar{height:4px;background:#e2e8f0;border-radius:2px;margin-bottom:16px}
.nab-lc-quiz-bar-fill{height:100%;background:#0D5C9B;border-radius:2px;transition:.3s}
.nab-lc-quiz-q{font-size:15px;font-weight:700;color:#1e293b;margin-bottom:6px}
.nab-lc-quiz-hint{font-size:12px;color:#94a3b8;font-style:italic;margin-bottom:14px}
.nab-lc-quiz-opts{display:flex;flex-direction:column;gap:8px;margin-bottom:16px}
.nab-lc-quiz-opt{background:#fff;border:2px solid #e2e8f0;border-radius:10px;padding:11px 14px;font-size:13px;cursor:pointer;text-align:left;display:flex;align-items:center;gap:10px;transition:.15s;width:100%}
.nab-lc-quiz-opt:hover{border-color:#0D5C9B;background:#f0f7ff}
.nab-lc-quiz-opt.correct{border-color:#16a34a;background:#dcfce7;color:#166534;font-weight:700}
.nab-lc-quiz-opt.wrong{border-color:#dc2626;background:#fee2e2;color:#991b1b}
.nab-lc-opt-letter{width:24px;height:24px;border-radius:50%;background:#f1f5f9;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.nab-lc-quiz-opt.correct .nab-lc-opt-letter{background:#16a34a;color:#fff}
.nab-lc-quiz-opt.wrong .nab-lc-opt-letter{background:#dc2626;color:#fff}
.nab-lc-feedback{padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px;display:none}
.nab-lc-feedback.correct{background:#dcfce7;color:#166534;border-left:4px solid #16a34a}
.nab-lc-feedback.wrong{background:#fee2e2;color:#991b1b;border-left:4px solid #dc2626}
.nab-lc-quiz-nav{display:flex;justify-content:flex-end}
.nab-lc-quiz-next{background:#0D5C9B;color:#fff;border:none;border-radius:8px;padding:10px 20px;font-size:13px;font-weight:700;cursor:pointer}
.nab-lc-quiz-next:disabled{background:#cbd5e1;cursor:not-allowed}
.nab-lc-result{text-align:center;padding:20px}
.nab-lc-result-score{font-size:28px;font-weight:900;color:#0D5C9B}
.nab-lc-result-label{font-size:14px;color:#64748b;margin:4px 0 16px}

@media(max-width:900px){
  .nab-lc-hero-grid,.nab-lc-main{grid-template-columns:1fr}
  .nab-lc-module-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:540px){
  .nab-lc-module-grid{grid-template-columns:1fr}
}
</style>
</head>
<body <?php body_class('nab-portal-body'); ?>>
<?php wp_body_open(); ?>

<script>
// Stubs
window._nabLcQ=[];
window.nabLcToggleModule=function(mid){window._nabLcQ.push({t:'toggle',mid:mid});};
window.nabLcStartQuiz=function(mid){window._nabLcQ.push({t:'quiz',mid:mid});};
window.nabLcSelectAnswer=function(mid,idx){window._nabLcQ.push({t:'ans',mid:mid,idx:idx});};
window.nabLcNextQuestion=function(mid){window._nabLcQ.push({t:'next',mid:mid});};
window.nabLcMarkComplete=function(mid){window._nabLcQ.push({t:'done',mid:mid});};
</script>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar('learning'); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title">🎓 Learning Center</div>
      <div class="nab-topbar-right"><?php if(function_exists('nab_render_notification_bell')) nab_render_notification_bell(); ?></div>
    </header>

    <div class="nab-content">
      <div class="nab-lc-page">

        <!-- Hero Row -->
        <div class="nab-lc-hero-grid">
          <!-- Welcome + Progress -->
          <div class="nab-lc-welcome">
            <div class="nab-lc-welcome-icon">🎓</div>
            <div class="nab-lc-welcome-title">Welcome to Your Education Hub</div>
            <p class="nab-lc-welcome-sub">Learn how Canadian credit works through our step-by-step video series. Complete each lesson at your own pace and build a stronger financial future.</p>
            <div class="nab-lc-prog-label">Course Progress</div>
            <div class="nab-lc-prog-bar">
              <div class="nab-lc-prog-fill" id="nabLcProgressBar" style="width:<?php echo $progress_pct; ?>%"></div>
            </div>
            <div class="nab-lc-prog-count" id="nabLcProgressLabel">
              ✅ <?php echo $completed_count; ?> of <?php echo $total_modules; ?> Lessons Completed
            </div>
          </div>

          <!-- Why Learn -->
          <div class="nab-lc-why">
            <div class="nab-lc-why-title">Why Learn With NAB Solutions?</div>
            <div class="nab-lc-why-item">
              <div class="nab-lc-why-icon" style="background:#dbeafe">📊</div>
              <div class="nab-lc-why-text"><strong>Understand credit reports and scores</strong><span>Learn how lenders evaluate your credit.</span></div>
            </div>
            <div class="nab-lc-why-item">
              <div class="nab-lc-why-icon" style="background:#dcfce7">💚</div>
              <div class="nab-lc-why-text"><strong>Build better financial habits</strong><span>Discover strategies to strengthen your credit.</span></div>
            </div>
            <div class="nab-lc-why-item">
              <div class="nab-lc-why-icon" style="background:#fef9c3">🧠</div>
              <div class="nab-lc-why-text"><strong>Make informed financial decisions</strong><span>Knowledge empowers you to achieve your goals.</span></div>
            </div>
            <div class="nab-lc-why-item">
              <div class="nab-lc-why-icon" style="background:#fee2e2">🔒</div>
              <div class="nab-lc-why-text"><strong>Exclusive member content</strong><span>Access expert videos and resources only for members.</span></div>
            </div>
          </div>
        </div>

        <!-- Main: Video Grid + Roadmap Sidebar -->
        <div class="nab-lc-main">

          <!-- Left: 7-Part Video Series -->
          <div class="nab-lc-series-card">
            <div class="nab-lc-series-title">🎥 7-Part Video Series</div>
            <div class="nab-lc-module-grid" id="nabLcGrid">
              <?php foreach($edu_modules as $mid => $mod):
                $is_done   = in_array($mid, $completed_modules);
                $is_locked = ($mid > 1) && !in_array($mid-1, $completed_modules) && !$is_done;
                $is_next   = !$is_done && !$is_locked;
                $status_class = $is_done ? 'completed' : ($is_locked ? 'locked' : '');

                preg_match('/embed\/([a-zA-Z0-9_-]+)/', $mod['url'], $yt_match);
                $yt_id = $yt_match[1] ?? '';
                $thumb = !empty($mod['thumb']) ? $mod['thumb'] : ($yt_id ? "https://img.youtube.com/vi/{$yt_id}/maxresdefault.jpg" : '');
              ?>
              <div class="nab-lc-mod <?php echo $status_class; ?>" id="nabLcMod<?php echo $mid; ?>">
                <div data-toggle-mid="<?php echo $mid; ?>" style="cursor:<?php echo $is_locked?'not-allowed':'pointer'; ?>">
                <?php if($thumb): ?>
                <img class="nab-lc-mod-thumb" src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($mod['title']); ?>" loading="lazy">
                <?php else: ?>
                <div class="nab-lc-mod-thumb-placeholder">🎓</div>
                <?php endif; ?>
                <div class="nab-lc-mod-badge <?php echo $is_done?'badge-completed':($is_locked?'badge-locked':'badge-inprogress'); ?>">
                  <?php echo $is_done?'✓ Completed':($is_locked?'🔒 Locked':'▶ In Progress'); ?>
                </div>
                <div class="nab-lc-mod-body">
                  <div class="nab-lc-mod-num">Lesson <?php echo $mid; ?></div>
                  <div class="nab-lc-mod-title"><?php echo esc_html($mod['title']); ?></div>
                  <div class="nab-lc-mod-desc"><?php echo esc_html($mod['desc']); ?></div>
                  <div class="nab-lc-mod-status <?php echo $is_done?'status-completed':($is_locked?'status-locked':'status-inprogress'); ?>">
                    <?php echo $is_done?'Completed':($is_locked?'Locked':'In Progress'); ?>
                  </div>
                </div>
                </div><!-- end toggle header -->
              </div>
              <?php endforeach; ?>
            </div>

            <!-- Full-width module detail panel -->
            <div class="nab-lc-fullpanel" id="nabLcFullPanel">
              <div class="nab-lc-fullpanel-header">
                <div class="nab-lc-fullpanel-title" id="nabLcPanelTitle">Module</div>
                <button class="nab-lc-fullpanel-close" id="nabLcPanelClose" type="button" aria-label="Close">✕</button>
              </div>
              <div class="nab-lc-fullpanel-inner">
                <!-- Top: Full width video -->
                <div class="nab-lc-fullpanel-video-row">
                  <div class="nab-lc-detail-vid" id="nabLcPanelVid" data-action="play-lc-video" style="max-height:480px;border-radius:12px">
                    <img id="nabLcPanelThumb" src="" alt="" style="width:100%;height:100%;object-fit:cover">
                    <div class="nab-lc-detail-play"><div></div></div>
                  </div>
                </div>
                <!-- Bottom: Description left, Quiz right -->
                <div class="nab-lc-fullpanel-bottom">
                <div class="nab-lc-fullpanel-left">
                  <div class="nab-lc-section-lbl">📚 Lesson Overview</div>
                  <p class="nab-lc-overview-text" id="nabLcPanelOverview"></p>
                  <div id="nabLcPanelConcepts"></div>
                  <div id="nabLcPanelSteps"></div>
                </div>
                <!-- Right: Quiz -->
                <div class="nab-lc-fullpanel-right">
                  <div id="nabLcPanelQuizWrap">
                    <div class="nab-lc-quiz-section">
                      <div class="nab-lc-quiz-row" id="nabLcPanelQuizStart">
                        <div><strong style="font-size:15px;color:#1e293b">📝 Test Your Knowledge</strong><br><span style="font-size:13px;color:#64748b">Complete the quiz to earn 10 points.</span></div>
                        <button class="nab-lc-btn" id="nabLcPanelQuizBtn" type="button">Start Quiz →</button>
                      </div>
                      <div class="nab-lc-quiz-ui" id="nabLcPanelQuizUI" style="display:none"></div>
                    </div>
                  </div>
                </div>
                </div><!-- end nab-lc-fullpanel-bottom -->
              </div>
            </div>

            <!-- Tip bar -->
            <div class="nab-lc-tip">
              <div class="nab-lc-tip-icon">💡</div>
              <div><strong>Stay Consistent!</strong> Learning a little each day can lead to big financial improvements. Keep going!</div>
            </div>
          </div>

          <!-- Right: Financial Roadmap -->
          <div class="nab-lc-roadmap">
            <div class="nab-lc-roadmap-badge">NEW</div>
            <div class="nab-lc-roadmap-title">Personalized Financial Roadmap</div>
            <div style="text-align:center;padding:16px 0">
              <div style="font-size:64px">🗺</div>
            </div>
            <p class="nab-lc-roadmap-desc">Receive a tailored, step-by-step guide designed specifically for your unique financial situation. This roadmap outlines actionable steps to help you achieve your goals and build long-term financial health.</p>
            <div class="nab-lc-roadmap-check">Personalized Action Plan</div>
            <div class="nab-lc-roadmap-check">Step-by-Step Guidance</div>
            <div class="nab-lc-roadmap-check">Track Your Progress</div>
            <div class="nab-lc-roadmap-check">Reach Your Financial Goals</div>
            <button class="nab-lc-roadmap-btn" type="button" onclick="alert('Financial Roadmap coming soon! We are building this feature for you.')">Create Your Roadmap →</button>
            <div class="nab-lc-roadmap-sub">Takes just a few minutes to get started.</div>
          </div>

        </div>
      </div>
    </div>
  </main>
</div>

<?php nab_portal_footer_js(); ?>
<script>
// Ensure nabPortal is always defined on learning page (fallback if wp_add_inline_script missed)
if(typeof nabPortal === 'undefined'){
  var nabPortal = {
    ajax: '<?php echo esc_js(admin_url("admin-ajax.php")); ?>',
    nonce: '<?php echo wp_create_nonce("nab_portal_nonce"); ?>',
    siteUrl: '<?php echo esc_js(home_url()); ?>'
  };
}
</script>
<script>
// Module data for full-width panel
var nabLcModData = <?php
$mod_js = [];
foreach($edu_modules as $mid => $mod){
    preg_match('/embed\/([a-zA-Z0-9_-]+)/', $mod['url'], $yt_match);
    $yt_id = $yt_match[1] ?? '';
    $thumb = !empty($mod['thumb']) ? $mod['thumb'] : ($yt_id ? "https://img.youtube.com/vi/{$yt_id}/maxresdefault.jpg" : '');
    $mod_js[$mid] = [
        'title'    => $mod['title'],
        'url'      => $mod['url'] ?: '',
        'thumb'    => $thumb,
        'overview' => $mod['overview'] ?? '',
        'concepts' => $mod['concepts'] ?? [],
        'steps'    => $mod['steps'] ?? [],
        'done'     => in_array($mid, $completed_modules),
    ];
}
echo json_encode($mod_js, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
?>;
</script>
<script>
var nabLcQuizData = {
  1:{questions:[
    {q:"What is the social logic that defines credit?",hint:"Think of the everyday feeling of trust when lending money to a reliable friend.",opts:["A government-mandated savings plan.","A foundation of trust that allows you to borrow today based on a promise to pay later.","A legal penalty for those who do not use cash.","A one-time gift from a bank."],answer:1},
    {q:"How is interest conceptually described?",hint:"It is the $5 difference between borrowing $100 today and paying back $105 next month.",opts:["A storage fee for keeping money in a bank.","A tax on all digital transactions.","The price of speed - the cost for the convenience of using someone else money now.","A reward given to borrowers for taking out loans."],answer:2},
    {q:"Why might a lender charge a borrower a higher interest rate?",hint:"Lenders use extra costs to balance out the risk they are taking.",opts:["To reward the borrower for having a high credit score.","Because the borrower history suggests a higher risk that they might stop paying.","Because the borrower has no existing debts.","To encourage the borrower to spend more money."],answer:1},
    {q:"In Canada, which two private companies act as historians by maintaining credit records?",hint:"These organizations are known as credit bureaus.",opts:["Visa and Mastercard.","The Bank of Canada and the Government of Canada.","Equifax and TransUnion.","NAB Solutions and local credit unions."],answer:2},
    {q:"Does checking your own credit score lower your score?",hint:"The idea that checking your own score damages it is a common myth.",opts:["Yes, it lowers it by 10 points each time.","No, it is a private background check that has zero impact on your score.","Only if you check it more than once a month.","Yes, because it shows lenders you are desperate."],answer:1}
  ]},
  2:{questions:[
    {q:"What is the single most important factor in calculating a credit score, making up 35% of the total?",hint:"This factor asks the simple question: Do you pay on time?",opts:["Credit Mix","Credit Utilization","Payment History","Length of Credit History"],answer:2},
    {q:"In Canada, what is the range for a credit score and what is generally considered an excellent rating?",hint:"The scale starts at 300 and the green zone begins above 760.",opts:["0 to 1000; 800+","300 to 900; 760+","400 to 800; 700+","500 to 900; 850+"],answer:1},
    {q:"To maintain a healthy credit utilization ratio, lenders prefer to see your balances kept below what percentage?",hint:"Warning lights start to flash for lenders once your tank is filled past this specific percentage.",opts:["10%","50%","30%","75%"],answer:2},
    {q:"Why is it often recommended to keep your oldest credit card account open even if you rarely use it?",hint:"This factor is worth about 15% of your score and looks at the average age of all your accounts.",opts:["It improves your credit mix","It prevents new inquiries","It increases your length of credit history","It automatically lowers your utilization"],answer:2},
    {q:"Which of the following is considered a soft inquiry and will NOT lower your credit score?",hint:"There is a common myth that doing this yourself will hurt your rating, but it is actually completely safe.",opts:["Applying for a new car loan","Checking your own credit score","Applying for three credit cards in one month","Opening a new student loan"],answer:1}
  ]},
  3:{questions:[
    {q:"Does checking your own credit score through a bank app hurt your score?",hint:"Checking your own score is a soft inquiry and is invisible to lenders.",opts:["Yes, it lowers it every time.","No, it has no effect on your score.","Yes, but only if you check it once a year.","Only if you are using a computer."],answer:1},
    {q:"What happens if you close your oldest credit card?",hint:"The length of your credit history makes up about 15% of your total score.",opts:["Your score will go up instantly.","It cleans up your file and makes it better.","Your score may drop because you lose your credit history.","Nothing happens at all."],answer:2},
    {q:"Do you need to leave a small balance on your card and pay interest to build credit?",hint:"The credit scoring formula does not look at interest charges; it only asks if you paid on time.",opts:["Yes, banks need to see you pay interest.","No, you only need to make the minimum payment on time.","Yes, it shows you are a good customer.","Only if the balance is over $1,000."],answer:1},
    {q:"Does using your debit card help build your credit score?",hint:"Debit cards manage your own cash and are not part of the credit reporting system.",opts:["Yes, because it shows how you spend cash.","Yes, but only for small purchases.","No, debit card use is not reported to credit bureaus.","Only if you use it at a grocery store."],answer:2},
    {q:"What is a hard inquiry?",hint:"This happens when you are actively seeking new debt from a lender.",opts:["When you check your own score for fun.","When you apply for a new credit card or a car loan.","When you pay your monthly phone bill.","When you look at your bank balance."],answer:1}
  ]},
  4:{questions:[
    {q:"How much does it cost to get your official credit report directly from Equifax or TransUnion?",hint:"You are legally entitled to a copy of this document at no cost.",opts:["$20.00","$50.00","It is free","A monthly fee"],answer:2},
    {q:"Does checking your own credit report hurt your credit score?",hint:"Pulling your own report is considered a soft inquiry.",opts:["Yes, it lowers the score","No, it has no impact","Only if you check it once a year","Yes, it is a hard inquiry"],answer:1},
    {q:"What information do you need to have ready to get your report online?",hint:"You need basic personal details and your SIN to pass security checks.",opts:["Your driver licence number and a credit card","Your birth date, current address, and Social Insurance Number","Your mother maiden name only","A list of all your monthly grocery bills"],answer:1},
    {q:"When using the TransUnion website, which option should you look for to get your free report?",hint:"Avoid buttons for monitoring as they often lead to paid subscriptions.",opts:["Credit Monitoring","Monthly Subscription","Paid Service","Consumer Disclosure"],answer:3},
    {q:"How often should you check your credit reports to help spot errors or identity theft early?",hint:"Reviewing your file regularly allows you to catch mistakes before applying for a loan.",opts:["Every five years","Once every ten years","Once or twice a year","Never"],answer:2}
  ]},
  5:{questions:[
    {q:"What information should you have ready before starting to avoid timing out?",hint:"Think about the basic personal details used to identify you on legal forms.",opts:["Your favorite color and pet name","Your legal name, current address, and date of birth","Your high school graduation date","A list of your favorite stores"],answer:1},
    {q:"Why should you have a physical credit card or loan statement in front of you?",hint:"The credit bureaus will ask very specific questions to prove you are who you say you are.",opts:["To pay a fee for the credit report","To use as a bookmark","To answer exact questions about account balances or opening dates","To take a picture of it for the website"],answer:2},
    {q:"What should you do when Equifax shows you premium paid monthly services?",hint:"Look past the big bright buttons for a smaller link that lets you keep going without paying.",opts:["Sign up for the most expensive one","Close your browser immediately","Find the subtle option to skip or continue for free","Enter your credit card information"],answer:2},
    {q:"What specific phrase must you look for on the TransUnion website to get your free report?",hint:"TransUnion uses this legal term instead of saying free credit report in their main menu.",opts:["Free Credit Score","Consumer Disclosure","Member Login","Buy Now"],answer:1},
    {q:"What should you do after downloading both PDF reports to your computer?",hint:"These reports use shorthand that is easy to misunderstand without help.",opts:["Print them out and throw them away","Open them and try to read the industry codes immediately","Put them in a new folder and wait for guidance to decode them","Email them to all your friends"],answer:2}
  ]},
  6:{questions:[
    {q:"What is a soft inquiry on your credit report?",hint:"These are harmless and do not change your credit score.",opts:["A check for a new credit card","A routine check like a background check or pre-approved offer","A mistake on your report","A check for a new car loan"],answer:1},
    {q:"Which type of credit check can lower your credit score?",hint:"These happen when you actively apply for things like a mortgage or a credit card.",opts:["Soft inquiries","Checking your own score","Hard inquiries from applying for new debt","Background checks"],answer:2},
    {q:"How long does it take for the score penalty from a hard inquiry to completely go away?",hint:"While it stays on your report longer, the negative effect on your score fades after the first year.",opts:["1 month","6 months","12 months (1 year)","3 years"],answer:2},
    {q:"If you are shopping for one car loan and visit three different banks in one week, how does the credit score model treat those checks?",hint:"The system uses a rate shopping exception for the exact same type of loan within a short window.",opts:["As three separate penalties","As one single hit to your score","As a sign of financial distress","They are ignored completely"],answer:1},
    {q:"What should you do if you see a hard inquiry on your report that you do not recognize?",hint:"An unauthorized check is a warning sign that someone else might be trying to use your credit.",opts:["Ignore it because it will go away in a year","Highlight it in green","Assume it is a soft inquiry","Circle it in red as it could be a sign of identity theft"],answer:3}
  ]},
  7:{questions:[
    {q:"What is the most important thing for good financial health?",hint:"It is about being consistent every day, not being perfect.",opts:["Having a perfect math score","Never making a single mistake","Small, steady habits you can keep doing","Winning the lottery"],answer:2},
    {q:"Why should you use a budget?",hint:"Think of it as a tool to help you make better choices with your money.",opts:["To see where your money is actually going","Because it is a math test you must pass","To make yourself feel bad about spending","To predict the future perfectly"],answer:0},
    {q:"What is a risk of using autopay for your bills?",hint:"Even if it is automatic, you still need to keep an eye on your bank balance.",opts:["It makes you too much money","You might stop checking your accounts and miss a problem","It is too difficult to set up","There are no risks to using it"],answer:1},
    {q:"How can reporting your rent help you?",hint:"It uses the rent you are already paying to show you are reliable.",opts:["It lowers your rent cost","It gives you free money","It turns a habit you already have into credit history","It pays your bills for you"],answer:2},
    {q:"How much should you try to save first for emergencies?",hint:"Start small with enough to cover your bills for just one month.",opts:["10 years of salary","Nothing at all","Enough to cover 1 month of basic needs","Exactly one million dollars"],answer:2}
  ]}
};

var nabLcState={};
function escH(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}

// Full-width panel state
var _nabLcCurrentMid = 0;

window._nabLcToggleModule=function(mid){
  var modEl=document.getElementById('nabLcMod'+mid);
  if(modEl&&modEl.classList.contains('locked')) return;

  var panel=document.getElementById('nabLcFullPanel');

  // If same card clicked again — close panel
  if(_nabLcCurrentMid===mid && panel&&panel.classList.contains('open')){
    panel.classList.remove('open');
    document.querySelectorAll('.nab-lc-mod').forEach(function(m){m.classList.remove('active-mod');});
    _nabLcCurrentMid=0;
    return;
  }

  // Mark active card
  document.querySelectorAll('.nab-lc-mod').forEach(function(m){m.classList.remove('active-mod');});
  if(modEl) modEl.classList.add('active-mod');
  _nabLcCurrentMid=mid;

  // Populate panel from nabLcModData
  var mod=nabLcModData[mid];
  if(!mod) return;

  // Header title
  var titleEl=document.getElementById('nabLcPanelTitle');
  if(titleEl) titleEl.textContent='Lesson '+mid+': '+mod.title;

  // Thumb + video
  var vidEl=document.getElementById('nabLcPanelVid');
  var thumbEl=document.getElementById('nabLcPanelThumb');
  if(vidEl){
    vidEl.setAttribute('data-video-url', mod.url);
    vidEl.setAttribute('data-mid', mid);
    // Reset to thumbnail (remove any playing iframe)
    if(vidEl.querySelector('iframe')) vidEl.innerHTML='<img id="nabLcPanelThumb" src="'+mod.thumb+'" alt="" style="width:100%;height:100%;object-fit:cover"><div class="nab-lc-detail-play"><div></div></div>';
    else if(thumbEl) thumbEl.src=mod.thumb;
  }

  // Overview
  var ovEl=document.getElementById('nabLcPanelOverview');
  if(ovEl) ovEl.textContent=mod.overview||'';

  // Concepts
  var conEl=document.getElementById('nabLcPanelConcepts');
  if(conEl){
    if(mod.concepts&&mod.concepts.length){
      var h='<div class="nab-lc-section-lbl">🔑 Key Takeaways</div><ul class="nab-lc-list">';
      mod.concepts.forEach(function(c){h+='<li>'+escH(c)+'</li>';});
      h+='</ul>';
      conEl.innerHTML=h;
    } else { conEl.innerHTML=''; }
  }

  // Steps
  var stEl=document.getElementById('nabLcPanelSteps');
  if(stEl){
    if(mod.steps&&mod.steps.length){
      var sh='<div class="nab-lc-section-lbl">✅ Actionable Steps</div><ol class="nab-lc-list">';
      mod.steps.forEach(function(s){sh+='<li>'+escH(s)+'</li>';});
      sh+='</ol>';
      stEl.innerHTML=sh;
    } else { stEl.innerHTML=''; }
  }

  // Quiz section
  var qBtn=document.getElementById('nabLcPanelQuizBtn');
  var qStart=document.getElementById('nabLcPanelQuizStart');
  var qUI=document.getElementById('nabLcPanelQuizUI');
  if(qBtn){
    qBtn.setAttribute('data-panel-quiz-mid', mid);
    if(mod.done){
      qStart.innerHTML='<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px"><div><strong style="color:#166534">✅ Module Complete!</strong><br><span style="font-size:13px;color:#64748b">You have already completed this module.</span></div><button class="nab-lc-btn" style="background:#64748b" data-panel-quiz-mid="'+mid+'" type="button">🔄 Retake Quiz</button></div>';
    } else {
      qStart.innerHTML='<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px"><div><strong style="font-size:15px;color:#1e293b">📝 Test Your Knowledge</strong><br><span style="font-size:13px;color:#64748b">Complete the quiz to earn 10 points.</span></div><button class="nab-lc-btn" data-panel-quiz-mid="'+mid+'" type="button">Start Quiz →</button></div>';
    }
    if(qUI){ qUI.style.display='none'; qUI.innerHTML=''; }
  }

  // Show panel + scroll
  if(panel){
    panel.classList.add('open');
    setTimeout(function(){panel.scrollIntoView({behavior:'smooth',block:'nearest'});},80);
  }
};

// Close button
document.addEventListener('DOMContentLoaded',function(){
  var closeBtn=document.getElementById('nabLcPanelClose');
  if(closeBtn) closeBtn.addEventListener('click',function(){
    var panel=document.getElementById('nabLcFullPanel');
    if(panel) panel.classList.remove('open');
    document.querySelectorAll('.nab-lc-mod').forEach(function(m){m.classList.remove('active-mod');});
    _nabLcCurrentMid=0;
  });
});

window._nabLcStartQuizPanel=function(mid){
  nabLcState[mid]={qIndex:0,score:0,answered:false};
  _nabLcRenderQPanel(mid);
};

function _nabLcRenderQPanel(mid){
  var st=nabLcState[mid];
  var qData=nabLcQuizData[mid];
  if(!qData||!st) return;
  var q=qData.questions[st.qIndex];
  var total=qData.questions.length;
  var pct=Math.round((st.qIndex/total)*100);
  var letters=['A','B','C','D'];
  var html='<div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between;margin-bottom:4px"><span style="font-size:12px;color:#64748b">Question '+(st.qIndex+1)+' of '+total+'</span><span style="font-size:12px;color:#64748b">'+st.score+' correct</span></div><div class="nab-lc-quiz-bar"><div class="nab-lc-quiz-bar-fill" style="width:'+pct+'%"></div></div></div>';
  html+='<div class="nab-lc-quiz-q">'+escH(q.q)+'</div>';
  html+='<div class="nab-lc-quiz-hint">💡 '+escH(q.hint)+'</div>';
  html+='<div class="nab-lc-quiz-opts">';
  q.opts.forEach(function(opt,i){
    html+='<button class="nab-lc-quiz-opt" data-lc-panel-mid="'+mid+'" data-lc-idx="'+i+'" type="button"><span class="nab-lc-opt-letter">'+letters[i]+'</span>'+escH(opt)+'</button>';
  });
  html+='</div><div class="nab-lc-feedback" id="nabLcPanelFb'+mid+'"></div>';
  html+='<div class="nab-lc-quiz-nav"><button class="nab-lc-quiz-next" id="nabLcPanelNext'+mid+'" data-lc-panel-next="'+mid+'" disabled type="button">'+(st.qIndex<total-1?'Next Question →':'See Results →')+'</button></div>';
  var ui=document.getElementById('nabLcPanelQuizUI');
  if(ui) ui.innerHTML=html;
  st.answered=false;
}

window._nabLcStartQuiz=function(mid){
  nabLcState[mid]={qIndex:0,score:0,answered:false};
  var quizEl=document.getElementById('nabLcQuiz'+mid);
  if(quizEl) quizEl.style.display='block';
  _nabLcRenderQ(mid);
};

function _nabLcRenderQ(mid){
  var st=nabLcState[mid];
  var qData=nabLcQuizData[mid];
  if(!qData||!st)return;
  var q=qData.questions[st.qIndex];
  var total=qData.questions.length;
  var pct=Math.round((st.qIndex/total)*100);
  var letters=['A','B','C','D'];
  var html='<div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between;margin-bottom:4px"><span style="font-size:12px;color:#64748b">Question '+(st.qIndex+1)+' of '+total+'</span><span style="font-size:12px;color:#64748b">'+st.score+' correct</span></div><div class="nab-lc-quiz-bar"><div class="nab-lc-quiz-bar-fill" style="width:'+pct+'%"></div></div></div>';
  html+='<div class="nab-lc-quiz-q">'+escH(q.q)+'</div>';
  html+='<div class="nab-lc-quiz-hint">💡 '+escH(q.hint)+'</div>';
  html+='<div class="nab-lc-quiz-opts">';
  q.opts.forEach(function(opt,i){
    html+='<button class="nab-lc-quiz-opt" data-lc-mid="'+mid+'" data-lc-idx="'+i+'" type="button"><span class="nab-lc-opt-letter">'+letters[i]+'</span>'+escH(opt)+'</button>';
  });
  html+='</div><div class="nab-lc-feedback" id="nabLcFb'+mid+'"></div>';
  html+='<div class="nab-lc-quiz-nav"><button class="nab-lc-quiz-next" id="nabLcNext'+mid+'" data-lc-next="'+mid+'" disabled type="button">'+(st.qIndex<total-1?'Next Question →':'See Results →')+'</button></div>';
  document.getElementById('nabLcQuiz'+mid).innerHTML=html;
  st.answered=false;
}

window._nabLcSelectAnswer=function(mid,idx){
  var st=nabLcState[mid];
  if(!st||st.answered)return;
  st.answered=true;
  var q=nabLcQuizData[mid].questions[st.qIndex];
  var correct=(idx===q.answer);
  if(correct)st.score++;
  document.querySelectorAll('#nabLcQuiz'+mid+' .nab-lc-quiz-opt').forEach(function(btn,i){
    if(i===q.answer)btn.classList.add('correct');
    else if(i===idx&&!correct)btn.classList.add('wrong');
    btn.disabled=true;
    var l=btn.querySelector('.nab-lc-opt-letter');
    if(l){if(i===q.answer){l.style.background='#16a34a';l.style.color='#fff';}else if(i===idx&&!correct){l.style.background='#dc2626';l.style.color='#fff';}}
  });
  var fb=document.getElementById('nabLcFb'+mid);
  if(fb){fb.className='nab-lc-feedback '+(correct?'correct':'wrong');fb.textContent=correct?'Correct! Well done.':'Not quite. The correct answer is highlighted above.';fb.style.display='block';}
  var nb=document.getElementById('nabLcNext'+mid);if(nb)nb.disabled=false;
};

window._nabLcNextQuestion=function(mid){
  var st=nabLcState[mid];if(!st)return;
  st.qIndex++;
  if(st.qIndex>=nabLcQuizData[mid].questions.length)_nabLcShowResult(mid);
  else _nabLcRenderQ(mid);
};

window._nabLcSelectAnswerPanel=function(mid,idx){
  var st=nabLcState[mid];
  if(!st||st.answered)return;
  st.answered=true;
  var q=nabLcQuizData[mid].questions[st.qIndex];
  var correct=(idx===q.answer);
  if(correct)st.score++;
  var ui=document.getElementById('nabLcPanelQuizUI');
  if(ui){
    ui.querySelectorAll('.nab-lc-quiz-opt').forEach(function(btn,i){
      if(i===q.answer)btn.classList.add('correct');
      else if(i===idx&&!correct)btn.classList.add('wrong');
      btn.disabled=true;
      var l=btn.querySelector('.nab-lc-opt-letter');
      if(l){if(i===q.answer){l.style.background='#16a34a';l.style.color='#fff';}else if(i===idx&&!correct){l.style.background='#dc2626';l.style.color='#fff';}}
    });
  }
  var fb=document.getElementById('nabLcPanelFb'+mid);
  if(fb){fb.className='nab-lc-feedback '+(correct?'correct':'wrong');fb.textContent=correct?'Correct! Well done.':'Not quite. The correct answer is highlighted above.';fb.style.display='block';}
  var nb=document.getElementById('nabLcPanelNext'+mid);if(nb)nb.disabled=false;
};

window._nabLcNextQuestionPanel=function(mid){
  var st=nabLcState[mid];if(!st)return;
  st.qIndex++;
  if(st.qIndex>=nabLcQuizData[mid].questions.length)_nabLcShowResultPanel(mid);
  else _nabLcRenderQPanel(mid);
};

function _nabLcShowResultPanel(mid){
  var st=nabLcState[mid];
  var total=nabLcQuizData[mid].questions.length;
  var pct=Math.round((st.score/total)*100);
  var passed=pct>=60;
  var html='<div class="nab-lc-result"><div style="font-size:48px;margin-bottom:8px">'+(pct===100?'🏆':passed?'⭐':'📚')+'</div>';
  html+='<div class="nab-lc-result-score">'+st.score+' / '+total+'</div>';
  html+='<div class="nab-lc-result-label">'+pct+'% — '+(pct===100?'Perfect!':passed?'Passed':'Keep Learning')+'</div>';
  if(passed){html+='<button data-lc-complete="'+mid+'" class="nab-lc-btn nab-lc-btn-green" id="nabLcCompleteBtn'+mid+'" type="button" style="margin:8px 4px">✅ Mark Complete & Earn Points</button>';}
  html+='<button data-lc-retake-panel="'+mid+'" class="nab-lc-btn" style="background:#64748b;margin:8px 4px" type="button">🔄 Retake</button></div>';
  var ui=document.getElementById('nabLcPanelQuizUI');
  if(ui) ui.innerHTML=html;
}

function _nabLcShowResult(mid){
  var st=nabLcState[mid];
  var total=nabLcQuizData[mid].questions.length;
  var score=st.score;
  var pct=Math.round((score/total)*100);
  var passed=pct>=60;
  var html='<div class="nab-lc-result"><div style="font-size:48px;margin-bottom:8px">'+(pct===100?'🏆':passed?'⭐':'📚')+'</div>';
  html+='<div class="nab-lc-result-score">'+score+' / '+total+'</div>';
  html+='<div class="nab-lc-result-label">'+pct+'% — '+(pct===100?'Perfect!':passed?'Passed':'Keep Learning')+'</div>';
  if(passed){html+='<button data-lc-complete="'+mid+'" class="nab-lc-btn nab-lc-btn-green" id="nabLcCompleteBtn'+mid+'" type="button" style="margin-right:8px">✅ Mark as Complete & Earn Points</button>';}
  html+='<button data-lc-retake="'+mid+'" class="nab-lc-btn" style="background:#64748b;margin-left:8px" type="button">🔄 Retake</button></div>';
  document.getElementById('nabLcQuiz'+mid).innerHTML=html;
}

window._nabLcMarkComplete=function(mid){
  if(typeof nabPortal==='undefined'){alert('Session error. Please refresh the page.');return;}
  // Find button in panel OR legacy quiz div
  var btn=document.querySelector('[data-lc-complete="'+mid+'"]');
  if(btn){btn.disabled=true;btn.textContent='Saving...';}
  fetch(nabPortal.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=nab_complete_module&nonce='+nabPortal.nonce+'&module_id='+mid})
  .then(function(r){return r.json();}).then(function(d){
    if(d.success){
      var completed=d.data.completed||0,total=d.data.total||7,pct=Math.round((completed/total)*100);

      // Update progress bar on learning page
      var bar=document.getElementById('nabLcProgressBar');
      var lbl=document.getElementById('nabLcProgressLabel');
      if(bar)bar.style.width=pct+'%';
      if(lbl)lbl.innerHTML='✅ '+completed+' of '+total+' Lessons Completed';

      // Update card in grid
      var modEl=document.getElementById('nabLcMod'+mid);
      if(modEl){
        modEl.classList.add('completed');
        modEl.classList.remove('active-mod');
        var badge=modEl.querySelector('.nab-lc-mod-badge');
        if(badge){badge.className='nab-lc-mod-badge badge-completed';badge.textContent='✓ Completed';}
        var status=modEl.querySelector('.nab-lc-mod-status');
        if(status){status.className='nab-lc-mod-status status-completed';status.textContent='Completed';}
      }

      // Unlock next module card
      var nextMod=document.getElementById('nabLcMod'+(mid+1));
      if(nextMod){
        nextMod.classList.remove('locked');
        nextMod.style.opacity='';
        nextMod.style.pointerEvents='';
        var nb=nextMod.querySelector('.nab-lc-mod-badge');
        if(nb){nb.className='nab-lc-mod-badge badge-inprogress';nb.textContent='▶ In Progress';}
        var ns=nextMod.querySelector('.nab-lc-mod-status');
        if(ns){ns.className='nab-lc-mod-status status-inprogress';ns.textContent='In Progress';}
      }

      // Show success in panel
      var panelUI=document.getElementById('nabLcPanelQuizUI');
      var panelStart=document.getElementById('nabLcPanelQuizStart');
      if(panelUI){
        panelUI.style.display='block';
        var nextBtn=mid<7?'<button data-toggle-mid="'+(mid+1)+'" class="nab-lc-btn" type="button" style="margin:8px 4px">▶ Next Lesson</button>':'';
        panelUI.innerHTML='<div class="nab-lc-result"><div style="font-size:48px">🎉</div>'
          +'<div class="nab-lc-result-score" style="font-size:20px">Module Complete!</div>'
          +'<div class="nab-lc-result-label">+10 Points Earned &bull; '+pct+'% ('+completed+'/'+total+')</div>'
          +(mid<7?nextBtn:'<div style="font-weight:700;color:#166534;margin-top:8px">🏆 All modules complete!</div>')
          +'</div>';
        if(panelStart)panelStart.style.display='none';
      }

      // Also update legacy inline quiz div if present
      var quizEl=document.getElementById('nabLcQuiz'+mid);
      if(quizEl)quizEl.innerHTML='<div class="nab-lc-result"><div style="font-size:48px">🎉</div><div class="nab-lc-result-score" style="font-size:20px">Module Complete!</div><div class="nab-lc-result-label">+10 Points Earned</div></div>';

    }else{
      if(btn){btn.disabled=false;btn.textContent='✅ Mark Complete & Earn Points';}
      alert('Could not save. Please try again.');
    }
  }).catch(function(err){
    if(btn){btn.disabled=false;btn.textContent='✅ Mark Complete & Earn Points';}
    console.error('NAB complete module error:', err);
    alert('Connection error: '+err.message+'. Please refresh and try again.');
  });
};

// Override stubs
window.nabLcToggleModule=window._nabLcToggleModule;
window.nabLcStartQuiz=window._nabLcStartQuiz;
window.nabLcSelectAnswer=window._nabLcSelectAnswer;
window.nabLcNextQuestion=window._nabLcNextQuestion;
window.nabLcMarkComplete=window._nabLcMarkComplete;

// Process queue
(window._nabLcQ||[]).forEach(function(q){
  if(q.t==='toggle')window._nabLcToggleModule(q.mid);
  if(q.t==='quiz')window._nabLcStartQuiz(q.mid);
  if(q.t==='ans')window._nabLcSelectAnswer(q.mid,q.idx);
  if(q.t==='next')window._nabLcNextQuestion(q.mid);
  if(q.t==='done')window._nabLcMarkComplete(q.mid);
});
window._nabLcQ=[];

// Event delegation
document.addEventListener('click',function(e){
  // Handle quiz/video/complete FIRST before toggle
  var quizBtn2=e.target.closest('[data-lc-quiz]');
  if(quizBtn2){window._nabLcStartQuiz(parseInt(quizBtn2.getAttribute('data-lc-quiz')));return;}
  var panelQuizBtn=e.target.closest('[data-panel-quiz-mid]');
  if(panelQuizBtn){
    var pmid=parseInt(panelQuizBtn.getAttribute('data-panel-quiz-mid'));
    var qStart=document.getElementById('nabLcPanelQuizStart');
    var qUI=document.getElementById('nabLcPanelQuizUI');
    if(qStart) qStart.style.display='none';
    if(qUI){ qUI.style.display='block'; }
    window._nabLcStartQuizPanel(pmid);
    return;
  }
  var opt2=e.target.closest('.nab-lc-quiz-opt[data-lc-mid]');
  if(opt2){window._nabLcSelectAnswer(parseInt(opt2.getAttribute('data-lc-mid')),parseInt(opt2.getAttribute('data-lc-idx')));return;}
  // Panel quiz option
  var panelOpt=e.target.closest('.nab-lc-quiz-opt[data-lc-panel-mid]');
  if(panelOpt){window._nabLcSelectAnswerPanel(parseInt(panelOpt.getAttribute('data-lc-panel-mid')),parseInt(panelOpt.getAttribute('data-lc-idx')));return;}
  var nextBtn2=e.target.closest('[data-lc-next]');
  if(nextBtn2&&!nextBtn2.disabled){window._nabLcNextQuestion(parseInt(nextBtn2.getAttribute('data-lc-next')));return;}
  // Panel next button
  var panelNext=e.target.closest('[data-lc-panel-next]');
  if(panelNext&&!panelNext.disabled){window._nabLcNextQuestionPanel(parseInt(panelNext.getAttribute('data-lc-panel-next')));return;}
  var complBtn2=e.target.closest('[data-lc-complete]');
  if(complBtn2){window._nabLcMarkComplete(parseInt(complBtn2.getAttribute('data-lc-complete')));return;}
  var retakeBtn2=e.target.closest('[data-lc-retake]');
  if(retakeBtn2){window._nabLcStartQuiz(parseInt(retakeBtn2.getAttribute('data-lc-retake')));return;}
  var retakePanelBtn=e.target.closest('[data-lc-retake-panel]');
  if(retakePanelBtn){
    var rpmid=parseInt(retakePanelBtn.getAttribute('data-lc-retake-panel'));
    window._nabLcStartQuizPanel(rpmid);
    return;
  }
  // Play video
  var vidEl2=e.target.closest('[data-action="play-lc-video"]');
  if(vidEl2){
    var url2=vidEl2.getAttribute('data-video-url');
    if(url2){var ifr2=document.createElement('iframe');ifr2.src=url2+'?autoplay=1&rel=0';ifr2.setAttribute('frameborder','0');ifr2.setAttribute('allow','accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture;web-share');ifr2.setAttribute('allowfullscreen','');ifr2.style.cssText='width:100%;height:100%;border:0;display:block';vidEl2.innerHTML='';vidEl2.appendChild(ifr2);}
    return;
  }
  // Toggle module LAST
  var header=e.target.closest('[data-toggle-mid]');
  if(header){window._nabLcToggleModule(parseInt(header.getAttribute('data-toggle-mid')));return;}
});
</script>

<?php wp_footer(); ?>
</body>
</html>
