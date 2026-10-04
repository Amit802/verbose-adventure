<?php
/**
 * Template Name: NAB Member Dashboard
 * v1.6 — Full member profile, MemberPress integration, LoanConnect tools, notifications
 *
 * v1.7.9: Added the same no-cache directive already used on the Learning
 * Center page (see page-nab-learning.php). Members were seeing their
 * credit score revert to an old value on reload after saving a new one —
 * confirmed to be LiteSpeed page-caching this page for logged-in users
 * despite the litespeed_is_not_cacheable exclusion filter in
 * memberpress-setup.php. Rather than rely only on that filter (which
 * this LiteSpeed install clearly isn't honouring reliably), the dashboard
 * now also sets an explicit no-cache directive the same way the Learning
 * Center already does successfully — so members never have to ask an
 * admin to manually purge cache after saving their score.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
}
do_action( 'litespeed_control_set_nocache', 'Dashboard shows live, user-specific data (credit score, EF progress, etc.)' );

$user        = wp_get_current_user();
$uid         = $user->ID;
$first_name  = trim( $user->first_name ) ?: explode( ' ', trim( $user->display_name ) )[0];
$last_name   = trim( $user->last_name );

// ── ACF Member info ───────────────────────────────────
$member_id      = get_user_meta( $uid, 'nab_member_id', true ) ?: ( get_field('nab_member_id') ?: 'NAB-'.str_pad($uid,4,'0',STR_PAD_LEFT) );
$suspension_msg = get_field( 'nab_suspension_message' );

// ── MemberPress data (real subscription info) ─────────
$mp_data        = function_exists('nab_mp_get_member_data') ? nab_mp_get_member_data($uid) : null;
$mp_active      = false; // MemberPress removed - using custom membership system
// Override with custom user meta status
$_nab_status    = get_user_meta( $uid, 'nab_membership_status', true ) ?: 'active';
$_nab_resume    = get_user_meta( $uid, 'nab_mp_resume_date', true ) ?: null;
if ( $mp_data ) {
    $mp_data['status']          = $_nab_status;
    $mp_data['is_paused']       = ( $_nab_status === 'paused' );
    $mp_data['has_subscription'] = true;
    if ( $_nab_resume ) $mp_data['resume_date'] = date( 'M j, Y', strtotime( $_nab_resume ) );
}

// Determine member status from MemberPress if available, else ACF fallback
if ( $mp_data && $mp_data['has_subscription'] ) {
    $member_status  = $mp_data['status'] === 'active' ? 'Active'
                    : ($mp_data['status'] === 'paused'    ? 'Paused'
                    : ($mp_data['status'] === 'cancelled' ? 'Cancelled' : ucfirst($mp_data['status'])));
    $member_since   = $mp_data['started_at'];
    $payment_status = $mp_data['plan_name'] ?: 'Active';
    $plan_price     = $mp_data['plan_price'];
    $next_billing   = $mp_data['next_billing_date'];
    $is_paused      = $mp_data['is_paused'];
    $resume_date    = $mp_data['resume_date'];
    $recent_txns    = $mp_data['recent_txns'];
    $is_suspended   = ($member_status === 'Cancelled');
} else {
    // ACF fallback for sites not yet using MemberPress
    $member_status  = get_user_meta( $uid, 'nab_member_status', true ) ?: ( get_field('nab_member_status') ?: 'Active' );
    // Use nab_member_since meta, fallback to WP user registration date
    $member_since_raw = get_user_meta( $uid, 'nab_member_since', true ) ?: get_field('nab_member_since');
    if ( empty( $member_since_raw ) ) {
        $user_obj = get_userdata( $uid );
        $member_since_raw = $user_obj ? date( 'd M Y', strtotime( $user_obj->user_registered ) ) : '';
    }
    $member_since   = $member_since_raw;
    $payment_status = get_field('nab_payment_status') ?: 'Secured';
    $plan_price     = null;
    $next_billing   = null;
    $is_paused      = false;
    $resume_date    = null;
    $recent_txns    = [];
    $is_suspended   = ($member_status === 'Suspended');
}
// v1.9.0: always the member's own registration date (see inc/dashboard-data.php)
$member_since = nab_get_member_since( $uid );

// ── ACF Nav links ─────────────────────────────────────
$link_report  = get_field( 'nab_link_report' );
$link_booking = get_field( 'nab_link_booking' );
$url_book     = nab_resolve_url( $link_booking );
$link_dispute = get_field( 'nab_link_dispute' );
$link_chatbot = get_field( 'nab_link_chatbot' );
$link_util    = get_field( 'nab_link_utilization' );
$link_sim     = get_field( 'nab_link_simulator' );
$link_blog    = get_field( 'nab_link_blog' );
$link_diy     = get_field( 'nab_link_diy' );
$link_ef      = get_field( 'nab_link_emergency_fund' );
$link_loan    = get_field( 'nab_link_loan' );
$link_cards   = get_field( 'nab_link_card_match' );
$link_pad     = get_field( 'nab_link_pad' );
$link_roadmap = get_field( 'nab_link_roadmap' );

// Financial Roadmap progress — computed live, see nab_get_roadmap_progress() in helpers.php
$roadmap_progress = function_exists( 'nab_get_roadmap_progress' ) ? nab_get_roadmap_progress( $uid ) : [ 'done' => 0, 'total' => 0 ];

$equifax_url     = get_field( 'nab_equifax_url' )     ?: 'https://www.equifax.ca';
$transunion_url  = get_field( 'nab_transunion_url' )  ?: 'https://www.transunion.ca';
$borrowell_url   = get_field( 'nab_borrowell_url' )   ?: 'https://www.borrowell.com';
$creditkarma_url = get_field( 'nab_creditkarma_url' ) ?: 'https://www.creditkarma.ca';

$saved_score  = get_user_meta( $uid, 'nab_credit_score', true );

// ── Education Modules ────────────────────────────────────
$did = nab_get_dash_page_id();
$edu_modules = [
    1 => [
        'title'    => 'What is Credit, Really?',
        'duration' => '8 Min',
        'category' => 'Credit Basics',
        'url'      => ($did && get_field('nab_vid1_url',$did)) ? get_field('nab_vid1_url',$did) : 'https://www.youtube.com/embed/9wjyyDtHZu4',
        'thumb'    => $did && get_field('nab_vid1_thumb',$did) ? get_field('nab_vid1_thumb',$did) : NAB_URL.'assets/img/modules/module-1.png',
        'overview' => 'Credit is more than just a number; it is a formal transactional relationship built on documented trust. This module explores how credit works in Canada, why interest exists, and how your financial reputation is tracked.',
        'concepts' => ['The Price of Speed: Interest is the fee you pay for the convenience of using someone else\'s money now.','The Lender\'s Perspective: Every time you borrow, the lender is making a bet on your reliability.','Credit Bureaus as Historians: In Canada, Equifax and TransUnion collect data to build your financial file.','The Credit Score: A three-digit number (300–900) summarizing your financial reputation.'],
        'steps'    => ['Check Both Reports Annually — Equifax and TransUnion do not share data.','Monitor for Accuracy — Review both reports once a year.','Don\'t Fear Checking Your Own Score — it is a soft inquiry with zero impact.'],
    ],
    2 => [
        'title'    => 'The Trust Recipe: How Credit Scores Are Calculated',
        'duration' => '10 Min',
        'category' => 'Credit Scores',
        'url'      => ($did && get_field('nab_vid2_url',$did)) ? get_field('nab_vid2_url',$did) : 'https://www.youtube.com/embed/aAMOEmWziak',
        'thumb'    => $did && get_field('nab_vid2_thumb',$did) ? get_field('nab_vid2_thumb',$did) : NAB_URL.'assets/img/modules/module-2.png',
        'overview' => 'Your credit score is not a mystery — it is a "trust recipe" built from your everyday financial habits. This module breaks down the five main ingredients used to calculate your score in Canada.',
        'concepts' => ['Payment History (35%): The largest piece — measures your consistency in paying bills on time.','Credit Utilization (30%): Keep balances below 30% of your available credit limit.','Length of Credit History (15%): The age of your oldest and newest accounts matters.','Credit Mix & Inquiries (10% each): Different loan types and application frequency make up the final 20%.'],
        'steps'    => ['Prioritize On-Time Payments — set up autopay.','Manage Statement Closings — pay down balances before your statement closes.','Preserve Old Accounts — keep your oldest card open even if rarely used.','Space Out Applications — avoid a rush of new credit applications.'],
    ],
    3 => [
        'title'    => 'The Escalating Cost of Bad Advice',
        'duration' => '9 Min',
        'category' => 'Credit Education',
        'url'      => ($did && get_field('nab_vid3_url',$did)) ? get_field('nab_vid3_url',$did) : 'https://www.youtube.com/embed/KNhx-HFivNA',
        'thumb'    => $did && get_field('nab_vid3_thumb',$did) ? get_field('nab_vid3_thumb',$did) : NAB_URL.'assets/img/modules/module-3.png',
        'overview' => 'This module replaces financial guesswork with algorithmic facts by tackling the three most dangerous credit myths that can lower scores and drain wealth.',
        'concepts' => ['Hard vs. Soft Inquiries: Applying for new debt triggers a hard inquiry; checking your own score is a harmless soft inquiry.','The 15% History Anchor: Closing old accounts deletes history and spikes your risk profile.','The Utilization Fraction: Closing a card shrinks your total available credit and raises your utilization %.','Payment History Binary: The formula simply asks — did you make at least the minimum payment on time?','The Interest Myth: Carrying a balance earns zero algorithmic favor — interest is tracked in a separate column.'],
        'steps'    => ['Monitor Freely — check your own score frequently.','Preserve Old Accounts — keep them open with at least one small purchase per year.','Avoid Unnecessary Interest — pay your statement balance in full every month.','Stop relying on myths — use mathematical facts to manage your credit profile.'],
    ],
    4 => [
        'title'    => 'Free Canadian Credit Reports: The Complete Guide',
        'duration' => '12 Min',
        'category' => 'Credit Reports',
        'url'      => ($did && get_field('nab_vid4_url',$did)) ? get_field('nab_vid4_url',$did) : 'https://www.youtube.com/embed/5Sc4L2lIOxo',
        'thumb'    => $did && get_field('nab_vid4_thumb',$did) ? get_field('nab_vid4_thumb',$did) : NAB_URL.'assets/img/modules/module-4.png',
        'overview' => 'Every Canadian is legally entitled to a free copy of their credit report from both Equifax and TransUnion. This module provides the exact step-by-step process to secure these documents at no cost.',
        'concepts' => ['Legal Entitlement: You have a legal right to access your credit disclosure from both major bureaus for free.','Zero Score Impact: Pulling your own report is a soft inquiry — no penalty.','Security Prerequisites: You will need your SIN, address history, and answers to security questions.','Consumer Disclosure vs. Monitoring: On TransUnion, look for "Consumer Disclosure" not "Credit Monitoring" to avoid fees.','Dispute Rights: Both bureaus provide free online dispute forms if you find errors.'],
        'steps'    => ['Pull Official Baselines from equifax.ca and transunion.ca.','Audit Every Line for incorrect addresses, unfamiliar accounts, or wrong late payment records.','Staggered Strategy — check one bureau in January and the other in July.','Correct to Boost — dispute inaccuracies immediately; corrections can boost your score in 30 days.'],
    ],
    5 => [
        'title'    => 'Securing Your Free Canadian Credit Reports',
        'duration' => '7 Min',
        'category' => 'Credit Reports',
        'url'      => ($did && get_field('nab_vid5_url',$did)) ? get_field('nab_vid5_url',$did) : 'https://www.youtube.com/embed/8_ns3DHBVII',
        'thumb'    => $did && get_field('nab_vid5_thumb',$did) ? get_field('nab_vid5_thumb',$did) : NAB_URL.'assets/img/modules/module-5.png',
        'overview' => 'This module guides you through the process of securing official, comprehensive digital copies of your reports from Equifax and TransUnion while avoiding aggressive marketing traps and industry-specific terminology to access your free legal disclosures.',
        'concepts' => ['Preparation is Critical: Credit bureaus use strict security timers — have your documents ready before you start.','Identity Verification: You will be asked time-sensitive questions about exact account balances or loan opening dates.','The Upsell Trap: Bureau websites prioritize paid services — look past these to find the free options.','Consumer Disclosure: On TransUnion, look specifically for the phrase "consumer disclosure".','Delayed Review: Raw credit reports use industry shorthand — organize them first, then decode with guidance.'],
        'steps'    => ['Gather Documents — have your legal name, address, birth date, and a recent statement ready.','Access Equifax at consumer.equifax.ca — select "create a free My Equifax account".','Access TransUnion at transunion.ca — locate the "consumer disclosure" link.','Download and Organize — save both reports as PDFs in a dedicated folder.','Resist Immediate Analysis — wait for proper guidance to decode the data.'],
    ],
    6 => [
        'title'    => 'The Credit Inquiry Guide: Understanding Hard and Soft Pulls',
        'duration' => '11 Min',
        'category' => 'Credit Inquiries',
        'url'      => ($did && get_field('nab_vid6_url',$did)) ? get_field('nab_vid6_url',$did) : 'https://www.youtube.com/embed/idthInO6S98',
        'thumb'    => $did && get_field('nab_vid6_thumb',$did) ? get_field('nab_vid6_thumb',$did) : NAB_URL.'assets/img/modules/module-6.png',
        'overview' => 'Every time your credit file is accessed, it leaves a digital footprint that can either be harmless or a silent drain on your financial standing. This module teaches you to audit every inquiry on your report.',
        'concepts' => ['Inquiry Retention: Credit bureaus log up to 3 years of credit checking history.','Soft Inquiries (Green): Routine checks — invisible to lenders, zero score impact.','Hard Inquiries (Orange): Triggered when you actively apply for new credit — slightly lowers your score.','Penalty Decay: The score penalty from a hard inquiry completely fades within 12 months.','Rate Shopping Exception: Multiple inquiries for the same loan type within 14–45 days count as one hit.','Fraud Detection (Red): Any unrecognized hard inquiry is a warning sign of identity theft.'],
        'steps'    => ['Perform a Color-Coded Audit: green for soft, orange for authorized hard, red for unrecognized inquiries.','Cluster Applications: Consolidate loan shopping into a tight two-week window.','Space Out Credit Cards: Multiple card applications do not qualify for rate shopping exceptions.','Dispute Fraud immediately if you find unauthorized inquiries on your report.'],
    ],
    7 => [
        'title'    => 'Building Better Financial Habits',
        'duration' => '13 Min',
        'category' => 'Financial Habits',
        'url'      => ($did && get_field('nab_vid7_url',$did)) ? get_field('nab_vid7_url',$did) : 'https://www.youtube.com/embed/eZW2-mcXqz4',
        'thumb'    => $did && get_field('nab_vid7_thumb',$did) ? get_field('nab_vid7_thumb',$did) : NAB_URL.'assets/img/modules/module-7.png',
        'overview' => 'This module shifts the focus from unattainable perfection to building resilience through realistic, adaptable, and consistent daily habits that protect your credit profile.',
        'concepts' => ['The Diagnostic Budget: A budget is a diagnostic tool — not a pass/fail test.','The 50/30/20 Framework: 50% needs, 30% wants, 20% savings — adjust as needed for Canadian living costs.','Payment History Core: Consistent on-time payments demonstrate reliability over time.','Active Autopay Management: Autopay requires active engagement — missing alerts can lead to failed payments.','Rent Payment Reporting: Report eligible rent payments to credit bureaus to turn existing habits into credit history.','The Savings Shock Absorber: An emergency fund absorbs financial shocks without damaging your credit.'],
        'steps'    => ['Shift Mindsets — focus on long-term stability through adaptable habits.','Monitor Accounts Regularly — actively engage with automated systems.','Document Existing Habits — use NAB\'\2 rent reporting tool.','Scale Savings Strategically — start with 1 month of essential expenses, then build to 3–6 months.'],
    ],
];
$completed_modules = json_decode( get_user_meta($uid,'nab_modules_completed',true) ?: '[]', true );
if(!is_array($completed_modules)) $completed_modules = [];
$total_modules     = count($edu_modules);
$completed_count   = count($completed_modules);
$progress_pct      = $total_modules > 0 ? round(($completed_count/$total_modules)*100) : 0;
$next_module_id    = 1;
foreach($edu_modules as $mid => $m){ if(!in_array($mid,$completed_modules)){ $next_module_id=$mid; break; } }
$featured_module   = $edu_modules[$next_module_id] ?? $edu_modules[1];
$score_source = get_user_meta( $uid, 'nab_score_source', true );

// ── v1.9.0 Visual dashboard data ─────────────────────────
$dash_data   = nab_dash_get_chart_data( $uid );
$nav_links   = nab_get_nav_links();
$cf_months   = [];
$cf_cursor   = new DateTime( current_time( 'Y-m-01' ), wp_timezone() );
for ( $i = 0; $i < 24; $i++ ) {
    $cf_months[ $cf_cursor->format( 'Y-m' ) ] = wp_date( 'F Y', $cf_cursor->getTimestamp() + 43200 );
    $cf_cursor->modify( '-1 month' );
}
$can_self_score = ! ( $saved_score && $score_source === 'account' );

$hour     = (int) current_time( 'G' );
$greeting = $hour < 12 ? 'Morning' : ( $hour < 17 ? 'Afternoon' : 'Evening' );

$notifications = function_exists( 'nab_get_user_notifications' ) ? nab_get_user_notifications( $uid ) : [];
$notif_count   = count( $notifications );

$blog_cat_slug = get_field( 'nab_blog_category_slug' ) ?: 'credit-education';
$diy_cat_slug  = get_field( 'nab_diy_category_slug' )  ?: 'diy-guide';
$blog_posts    = function_exists( 'nab_get_blog_posts' ) ? nab_get_blog_posts( $blog_cat_slug, 20 ) : [];

// v1.6.8: DIY Guide blog posts now merged into the Educational Blog tab
// (per client request). The DIY tab itself became "DIY Dispute Letter"
// and now shows downloadable templates instead of blog posts — see below.
$diy_guide_posts = function_exists( 'nab_get_diy_posts' ) ? nab_get_diy_posts( $diy_cat_slug, 20 ) : [];
if ( $diy_guide_posts ) {
    $blog_posts = array_merge( $blog_posts, $diy_guide_posts );
    $seen = [];
    $blog_posts = array_values( array_filter( $blog_posts, function( $p ) use ( &$seen ) {
        if ( isset( $seen[ $p->ID ] ) ) return false;
        $seen[ $p->ID ] = true;
        return true;
    } ) );
    usort( $blog_posts, function( $a, $b ) { return strtotime( $b->post_date ) <=> strtotime( $a->post_date ); } );
}

// UM removed v1.6.2 -- profile now native
$profile_sc = '';

// Auto-open tab from URL param (e.g. from sidebar on other pages)
$auto_tab = '';
if ( isset( $_GET['nab_tab'] ) ) {
    $allowed_tabs = ['overview','profile','blog','diy'];
    $req_tab = sanitize_key( $_GET['nab_tab'] );
    if ( in_array( $req_tab, $allowed_tabs, true ) ) {
        $auto_tab = $req_tab;
    }
}

nab_head_open( 'Dashboard — NAB Member Portal' );
?>
<style>
/* ══ LAYOUT ══════════════════════════════════════════════ */
.nab-top-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px}
.nab-welcome-card,.nab-score-card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.nab-welcome-card{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
.nab-welcome-greeting{font-size:13px;color:#64748b;margin-bottom:4px}
.nab-welcome-name{font-size:26px;font-weight:800;color:#1e293b;line-height:1.1;margin-bottom:12px}
.nab-welcome-meta{display:flex;flex-wrap:wrap;gap:8px}
.nab-meta-pill{font-size:11px;background:#f1f5f9;color:#475569;padding:4px 10px;border-radius:20px;display:flex;align-items:center;gap:5px}
.ndot{width:7px;height:7px;background:#22c55e;border-radius:50%;display:inline-block}
.nab-welcome-right{text-align:right;flex-shrink:0}
.nab-member-since-label{font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px}
.nab-member-id{font-size:20px;font-weight:800;color:#0D5C9B;margin-bottom:14px;font-family:monospace}
.nab-quick-btns{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
.nab-qbtn{font-size:12px;font-weight:600;padding:7px 14px;border-radius:8px;text-decoration:none;background:#f1f5f9;color:#374151;transition:.15s;border:1px solid #e2e8f0;cursor:pointer}
.nab-qbtn:hover{background:#e2e8f0;color:#1e293b}
.nab-qbtn-orange{background:#F97316;color:#fff!important;border-color:#F97316}
.nab-qbtn-orange:hover{background:#ea6c0a}

/* ══ SCORE ═══════════════════════════════════════════════ */
.nab-score-title{font-size:14px;font-weight:700;color:#1e293b;margin-bottom:12px}
.nab-score-input-wrap{display:flex;gap:8px;margin-bottom:6px}
.nab-score-input{flex:1;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;transition:.15s}
.nab-score-input:focus{border-color:#0D5C9B}
.nab-btn-check{background:#0D5C9B;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;transition:.15s}
.nab-btn-check:hover{background:#0a4a7c}
.nab-score-error{font-size:12px;color:#ef4444;margin-bottom:6px;display:none}
.nab-score-self-label{font-size:11px;color:#f97316;margin-bottom:6px;display:none}
.nab-score-gauge{position:relative;display:flex;justify-content:center;margin:10px 0 4px}
.nab-score-number{position:absolute;bottom:0;left:50%;transform:translateX(-50%);font-size:28px;font-weight:800;color:#1e293b}
.nab-score-label{text-align:center;font-size:13px;font-weight:700;margin-bottom:4px}
.nab-score-msg{text-align:center;font-size:11px;color:#64748b;margin-bottom:10px;line-height:1.4}
.nab-score-range{display:flex;justify-content:space-between;font-size:9px;color:#94a3b8;margin-top:4px}
.nab-ri{display:flex;flex-direction:column;align-items:center;gap:1px}
.nab-ri span{font-weight:700;font-size:10px}
.nab-score-providers{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}
.nab-provider-chip{display:flex;align-items:center;gap:5px;padding:4px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:20px;font-size:11px;font-weight:600;color:#374151;text-decoration:none;transition:.15s}
.nab-provider-chip:hover{background:#f1f5f9}
.nab-pdot{width:7px;height:7px;border-radius:50%;flex-shrink:0}

/* ══ TABS ════════════════════════════════════════════════ */
.nab-tab-bar{display:flex;gap:4px;background:#fff;border-radius:12px;padding:6px;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:24px;flex-wrap:wrap}
.nab-tab-btn{padding:8px 18px;border-radius:8px;border:none;background:transparent;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;transition:.15s;white-space:nowrap}
.nab-tab-btn:hover{background:#f1f5f9;color:#1e293b}
.nab-tab-btn.active{background:#0D5C9B;color:#fff}
.nab-tab-panel{display:none}
.nab-tab-panel.active{display:block}

/* ══ QUICK ACCESS CARDS ══════════════════════════════════ */
.nab-section-heading{font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.07em;margin-bottom:14px}
.nab-cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:16px;margin-bottom:28px}
.nab-feat-card{display:flex;align-items:center;gap:14px;background:#fff;border-radius:14px;padding:16px;text-decoration:none;border:1.5px solid transparent;transition:.18s;position:relative;border-top:3px solid var(--ncard-accent);cursor:pointer}
.nab-feat-card:hover{border-color:var(--ncard-accent);box-shadow:0 4px 16px rgba(0,0,0,.08);transform:translateY(-2px)}
.nab-card-disabled{opacity:.55;cursor:not-allowed;pointer-events:none}
.nab-card-icon{width:42px;height:42px;border-radius:10px;background:var(--ncard-iconbg);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.nab-card-body{flex:1;min-width:0}
.nab-card-title{font-size:13px;font-weight:700;color:#1e293b;margin-bottom:3px}
.nab-card-desc{font-size:11px;color:#64748b;line-height:1.4}
.nab-card-arrow{font-size:16px;color:#cbd5e1;transition:.15s}
.nab-feat-card:hover .nab-card-arrow{color:var(--ncard-accent);transform:translateX(3px)}
.nab-badge{font-size:9px;font-weight:700;letter-spacing:.05em;padding:2px 7px;border-radius:20px;text-transform:uppercase;position:absolute;top:10px;right:10px}
.nab-badge-soon{background:#e2e8f0;color:#64748b}
.nab-badge-live{background:#dbeafe;color:#1d4ed8}

/* ══ SPLIT-PANEL READER (Blog & DIY) ═════════════════════
   Left: scrollable post list  |  Right: full article view  */
.nab-reader{display:grid;grid-template-columns:300px 1fr;gap:0;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);min-height:520px}

/* Left list */
.nab-reader-list{border-right:1px solid #f1f5f9;overflow-y:auto;max-height:700px}
.nab-reader-list-header{padding:16px 18px 12px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid #f1f5f9;position:sticky;top:0;background:#fff;z-index:1;display:flex;justify-content:space-between;align-items:center}
.nab-reader-list-header a{font-size:11px;font-weight:600;color:#0D5C9B;text-decoration:none;text-transform:none;letter-spacing:0}
.nab-reader-list-header a:hover{text-decoration:underline}
.nab-reader-row{display:flex;gap:12px;padding:14px 16px;cursor:pointer;border-bottom:1px solid #f8fafc;transition:.15s;align-items:flex-start}
.nab-reader-row:hover{background:#f8fafc}
.nab-reader-row.active{background:#eff6ff;border-left:3px solid #0D5C9B}
.nab-reader-row-thumb{width:56px;height:56px;border-radius:8px;object-fit:cover;flex-shrink:0;background:#f1f5f9}
.nab-reader-row-thumb-ph{width:56px;height:56px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:22px}
.nab-reader-row-thumb-ph.blog-ph{background:linear-gradient(135deg,#fef3c7,#fde68a)}
.nab-reader-row-thumb-ph.diy-ph{background:linear-gradient(135deg,#dcfce7,#bbf7d0)}
.nab-reader-row-body{flex:1;min-width:0}
.nab-reader-row-title{font-size:12px;font-weight:700;color:#1e293b;line-height:1.4;margin-bottom:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.nab-reader-row-meta{font-size:10px;color:#94a3b8}
.nab-reader-row.active .nab-reader-row-title{color:#0D5C9B}

/* Right article */
.nab-reader-article{overflow-y:auto;max-height:700px;padding:32px 36px}
.nab-reader-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#cbd5e1;text-align:center;padding:40px}
.nab-reader-placeholder-icon{font-size:48px;margin-bottom:16px;opacity:.5}
.nab-reader-placeholder-text{font-size:13px;color:#94a3b8;line-height:1.6}
.nab-article-back{display:none;margin-bottom:16px}
.nab-article-cat{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#0D5C9B;margin-bottom:8px}
.nab-article-title{font-size:22px;font-weight:800;color:#1e293b;line-height:1.3;margin-bottom:12px}
.nab-article-meta{font-size:12px;color:#94a3b8;margin-bottom:20px;display:flex;gap:12px;flex-wrap:wrap}
.nab-article-thumb{width:100%;max-height:280px;object-fit:cover;border-radius:12px;margin-bottom:24px;display:block}
.nab-article-body{font-size:14px;color:#374151;line-height:1.8}
.nab-article-body h1,.nab-article-body h2,.nab-article-body h3{color:#1e293b;font-weight:700;margin:24px 0 10px;line-height:1.3}
.nab-article-body h2{font-size:18px}
.nab-article-body h3{font-size:16px}
.nab-article-body p{margin:0 0 14px}
.nab-article-body ul,.nab-article-body ol{margin:0 0 14px;padding-left:20px}
.nab-article-body li{margin-bottom:6px}
.nab-article-body img{max-width:100%;border-radius:8px;margin:12px 0}
.nab-article-body a{color:#0D5C9B}
.nab-article-body blockquote{border-left:3px solid #0D5C9B;margin:16px 0;padding:10px 16px;background:#eff6ff;border-radius:0 8px 8px 0;font-style:italic}
.nab-article-loading{display:flex;align-items:center;justify-content:center;height:200px;color:#94a3b8;font-size:13px;gap:10px}
.nab-spinner{width:18px;height:18px;border:2px solid #e2e8f0;border-top-color:#0D5C9B;border-radius:50%;animation:nabSpin .7s linear infinite;flex-shrink:0}
@keyframes nabSpin{to{transform:rotate(360deg)}}

/* ══ PROFILE TAB ═════════════════════════════════════════ */
.nab-profile-wrap{background:#fff;border-radius:16px;padding:28px 32px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
/* UM styles removed v1.6.2 */

/* ══ EDUCATION MODULES ══════════════════════════════════ */
.nab-edu-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:24px}
.nab-edu-card{background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 1px 8px rgba(0,0,0,.07);transition:.2s;cursor:pointer;border:2px solid transparent;position:relative}
.nab-edu-card:hover{transform:translateY(-3px);box-shadow:0 6px 24px rgba(0,0,0,.1);border-color:#0D5C9B}
.nab-edu-card.completed{border-color:#16a34a;opacity:.85}
.nab-edu-thumb{width:100%;height:140px;object-fit:cover;background:linear-gradient(135deg,#0D5C9B,#0a4a7c);display:flex;align-items:center;justify-content:center;font-size:48px;color:rgba(255,255,255,.3)}
.nab-edu-thumb img{width:100%;height:100%;object-fit:cover}
.nab-edu-body{padding:14px 16px}
.nab-edu-cat{font-size:10px;font-weight:700;color:#0D5C9B;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px}
.nab-edu-title{font-size:13px;font-weight:700;color:#1e293b;line-height:1.4;margin-bottom:8px}
.nab-edu-meta{display:flex;align-items:center;justify-content:space-between}
.nab-edu-dur{font-size:11px;color:#94a3b8}
.nab-edu-status{font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px}
.nab-edu-status.done{background:#dcfce7;color:#166534}
.nab-edu-status.todo{background:#dbeafe;color:#1e40af}
.nab-edu-status.next{background:#fef9c3;color:#854d0e}
.nab-edu-progress-bar{height:4px;background:#f1f5f9;border-radius:2px;margin-top:8px}
.nab-edu-progress-fill{height:100%;background:#0D5C9B;border-radius:2px;transition:.4s}

/* Featured video */
.nab-featured-video{background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 1px 8px rgba(0,0,0,.07);margin-bottom:24px}
.nab-featured-inner{display:grid;grid-template-columns:1fr 1fr;gap:0}
.nab-video-player-wrap{position:relative;background:#000;aspect-ratio:16/9;overflow:hidden}
.nab-video-player-wrap iframe{width:100%;height:100%;border:0}
.nab-video-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(135deg,#0D5C9B,#0a4a7c);color:rgba(255,255,255,.7);font-size:13px;text-align:center;gap:12px;cursor:pointer}
.nab-video-placeholder img{width:100%;height:100%;object-fit:cover}
.nab-play-btn{width:56px;height:56px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:22px;backdrop-filter:blur(4px);border:2px solid rgba(255,255,255,.4)}
.nab-featured-info{padding:24px}
.nab-featured-label{font-size:10px;font-weight:700;color:#0D5C9B;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}
.nab-featured-title{font-size:17px;font-weight:800;color:#1e293b;line-height:1.3;margin-bottom:10px}
.nab-featured-desc{font-size:12px;color:#64748b;line-height:1.6;margin-bottom:14px}
.nab-featured-meta{display:flex;gap:14px;margin-bottom:16px}
.nab-featured-meta-item{font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:4px}
.nab-featured-progress{margin-bottom:14px}
.nab-featured-progress-bar{height:5px;background:#f1f5f9;border-radius:3px;margin-top:6px}
.nab-featured-progress-fill{height:100%;background:#0D5C9B;border-radius:3px;transition:.4s}
.nab-btn-continue{background:#0D5C9B;color:#fff;border:none;border-radius:8px;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;transition:.15s;text-decoration:none;display:inline-block}
.nab-btn-continue:hover{background:#0a4a7c;color:#fff}
.nab-btn-view-all{background:#f1f5f9;color:#374151;border:none;border-radius:8px;padding:10px 18px;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:.15s;margin-left:8px}
.nab-btn-view-all:hover{background:#e2e8f0}

/* Learning progress widget */
.nab-learn-progress{background:#fff;border-radius:16px;padding:20px;box-shadow:0 1px 8px rgba(0,0,0,.07);margin-bottom:20px}
.nab-learn-progress-title{font-size:13px;font-weight:700;color:#1e293b;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between}
.nab-learn-progress-title a{font-size:11px;color:#0D5C9B;font-weight:600;text-decoration:none}
.nab-progress-circle{position:relative;width:70px;height:70px;flex-shrink:0}
.nab-progress-circle svg{transform:rotate(-90deg)}
.nab-progress-pct{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:14px;font-weight:800;color:#0D5C9B}
.nab-learn-next{background:#f8fafc;border-radius:10px;padding:12px 14px;margin-top:12px}
.nab-learn-next-label{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px}
.nab-learn-next-title{font-size:12px;font-weight:700;color:#1e293b;margin-bottom:4px}
.nab-learn-next-sub{font-size:11px;color:#64748b}

/* Quiz */
.nab-quiz-wrap{background:#fff;border-radius:16px;padding:28px;box-shadow:0 1px 8px rgba(0,0,0,.07)}
.nab-quiz-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.nab-quiz-title{font-size:16px;font-weight:800;color:#1e293b}
.nab-quiz-progress{font-size:12px;color:#64748b}
.nab-quiz-bar{height:4px;background:#f1f5f9;border-radius:2px;margin-bottom:24px}
.nab-quiz-bar-fill{height:100%;background:#0D5C9B;border-radius:2px;transition:.3s}
.nab-quiz-q{font-size:15px;font-weight:700;color:#1e293b;margin-bottom:6px;line-height:1.4}
.nab-quiz-hint{font-size:12px;color:#94a3b8;margin-bottom:20px;font-style:italic}
.nab-quiz-opts{display:flex;flex-direction:column;gap:10px;margin-bottom:24px}
.nab-quiz-opt{background:#f8fafc;border:2px solid #e2e8f0;border-radius:10px;padding:13px 16px;font-size:13px;font-weight:500;color:#374151;cursor:pointer;transition:.15s;text-align:left;display:flex;align-items:center;gap:10px}
.nab-quiz-opt:hover{border-color:#0D5C9B;background:#f0f7ff}
.nab-quiz-opt.selected{border-color:#0D5C9B;background:#dbeafe;font-weight:700}
.nab-quiz-opt.correct{border-color:#16a34a;background:#dcfce7;color:#166534}
.nab-quiz-opt.wrong{border-color:#dc2626;background:#fee2e2;color:#991b1b}
.nab-quiz-opt-letter{width:24px;height:24px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}
.nab-quiz-opt.correct .nab-quiz-opt-letter{background:#16a34a;color:#fff}
.nab-quiz-opt.wrong .nab-quiz-opt-letter{background:#dc2626;color:#fff}
.nab-quiz-opt.selected .nab-quiz-opt-letter{background:#0D5C9B;color:#fff}
.nab-quiz-feedback{padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;display:none;line-height:1.5}
.nab-quiz-feedback.correct{background:#dcfce7;color:#166534;border-left:4px solid #16a34a}
.nab-quiz-feedback.wrong{background:#fee2e2;color:#991b1b;border-left:4px solid #dc2626}
.nab-quiz-nav{display:flex;justify-content:space-between;align-items:center}
.nab-quiz-btn{background:#0D5C9B;color:#fff;border:none;border-radius:8px;padding:11px 24px;font-size:13px;font-weight:700;cursor:pointer;transition:.15s}
.nab-quiz-btn:hover{background:#0a4a7c}
.nab-quiz-btn:disabled{background:#cbd5e1;cursor:not-allowed}
.nab-quiz-result{text-align:center;padding:32px 20px}
.nab-quiz-result-icon{font-size:56px;margin-bottom:12px}
.nab-quiz-result-score{font-size:32px;font-weight:900;color:#0D5C9B;margin-bottom:4px}
.nab-quiz-result-label{font-size:14px;color:#64748b;margin-bottom:20px}
.nab-quiz-result-msg{font-size:13px;color:#374151;margin-bottom:24px;line-height:1.6}

/* Ask AI floating button */
.nab-ask-ai-btn{position:fixed;bottom:28px;right:28px;background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff;border:none;border-radius:50px;padding:13px 20px;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 4px 20px rgba(124,58,237,.4);z-index:999;display:flex;align-items:center;gap:8px;transition:.2s;text-decoration:none}
.nab-ask-ai-btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(124,58,237,.5);color:#fff}
.nab-ask-ai-btn-dot{width:8px;height:8px;background:#22c55e;border-radius:50%;animation:nabPulse 2s infinite}
@keyframes nabPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(.85)}}

/* Dashboard layout update */
.nab-dash-two-col{display:grid;grid-template-columns:1fr 320px;gap:20px;margin-bottom:20px}
@media(max-width:900px){.nab-dash-two-col{grid-template-columns:1fr}.nab-featured-inner{grid-template-columns:1fr}}

/* ══ SEARCH BAR ══════════════════════════════════════════ */
.nab-search-wrap{position:relative;max-width:360px;flex:1;min-width:0}
.nab-search-input{width:100%;padding:10px 16px 10px 40px;border:1.5px solid #e2e8f0;border-radius:50px;font-size:13px;background:#f8fafc;outline:none;transition:.2s;box-sizing:border-box;font-family:inherit}
.nab-search-input:focus{border-color:#0D5C9B;background:#fff;box-shadow:0 0 0 3px rgba(13,92,155,.1)}
.nab-search-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:14px;pointer-events:none}
.nab-search-results{position:absolute;top:calc(100% + 6px);left:0;right:0;background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.12);border:1px solid #e2e8f0;z-index:999;display:none;max-height:360px;overflow-y:auto}
.nab-search-results.visible{display:block}
.nab-search-result-item{padding:12px 16px;display:flex;align-items:center;gap:12px;cursor:pointer;transition:.15s;border-bottom:1px solid #f1f5f9}
.nab-search-result-item:last-child{border-bottom:none}
.nab-search-result-item:hover{background:#f0f7ff}
.nab-search-result-icon{width:36px;height:36px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.nab-search-result-title{font-size:13px;font-weight:700;color:#1e293b}
.nab-search-result-desc{font-size:11px;color:#94a3b8;margin-top:1px}
.nab-search-no-results{padding:20px;text-align:center;color:#94a3b8;font-size:13px}

/* ══ MY FINANCIAL JOURNEY ════════════════════════════════ */
.nab-journey-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-bottom:28px}
.nab-journey-card{background:#fff;border-radius:14px;padding:18px;box-shadow:0 1px 8px rgba(0,0,0,.06);border:1.5px solid #e2e8f0;cursor:pointer;transition:.2s;text-decoration:none;display:block}
.nab-journey-card:hover{border-color:#0D5C9B;transform:translateY(-2px);box-shadow:0 4px 20px rgba(0,0,0,.1)}
.nab-journey-card-icon{font-size:28px;margin-bottom:10px}
.nab-journey-card-title{font-size:14px;font-weight:700;color:#1e293b;margin-bottom:4px}
.nab-journey-card-desc{font-size:12px;color:#64748b;line-height:1.5}

/* ══ EMPTY ═══════════════════════════════════════════════ */
.nab-no-posts{padding:40px 24px;background:#fff;border-radius:12px;text-align:center;color:#94a3b8;font-size:13px;line-height:1.7}

/* ══ RESPONSIVE ══════════════════════════════════════════ */
@media(max-width:900px){
  .nab-top-row{grid-template-columns:1fr}
  .nab-reader{grid-template-columns:1fr}
  .nab-reader-list{max-height:none;border-right:none;border-bottom:1px solid #f1f5f9}
  .nab-reader-article{padding:20px}
}
@media(max-width:640px){
  .nab-cards-grid{grid-template-columns:1fr}
  .nab-tab-btn{font-size:12px;padding:7px 12px}
  .nab-reader{border-radius:12px}
  .nab-article-title{font-size:18px}
}
@media(max-width:480px){
  .nab-topbar{padding:12px 16px 12px 56px;flex-wrap:wrap;gap:8px}
  .nab-search-wrap{order:3;max-width:none;flex-basis:100%}
  .nab-profile-wrap{padding:18px 16px}
  table{font-size:11px}
  .nab-content{padding:12px}
}

/* ════════════════════════════════════════════════════════════
   v1.9.0 VISUAL DASHBOARD — Monarch-inspired design layer.
   Scoped to this page only (other portal pages are unchanged).
   ════════════════════════════════════════════════════════════ */
body.nab-portal-body{
  --nd-bg:#F5F6F8;--nd-card:#fff;--nd-line:#E8EBF0;--nd-line2:#F1F3F6;
  --nd-text:#0F1B2D;--nd-muted:#6B7685;--nd-faint:#9AA4B2;
  --nd-blue:#0D5C9B;--nd-blue2:#0A4A7C;--nd-orange:#F97316;--nd-green:#22A06B;--nd-red:#E5484D;
  --nd-radius:16px;--nd-shadow:0 1px 2px rgba(16,24,40,.04),0 1px 3px rgba(16,24,40,.03);
  background:var(--nd-bg)!important;color:var(--nd-text);-webkit-font-smoothing:antialiased
}
body.nab-portal-body [hidden]{display:none!important}
.nab-portal-body .nab-content{padding:28px 32px 40px;max-width:1440px;width:100%;box-sizing:border-box;margin:0 auto}
.nab-portal-body .nab-topbar{background:rgba(255,255,255,.92);backdrop-filter:saturate(1.4) blur(8px);-webkit-backdrop-filter:saturate(1.4) blur(8px);border-bottom:1px solid var(--nd-line);padding:14px 32px;gap:16px}
.nab-portal-body .nab-topbar-title{font-size:17px;font-weight:700;letter-spacing:-.01em;white-space:nowrap}

/* Buttons + pills */
.nd-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;font:inherit;font-size:13px;font-weight:600;line-height:1;padding:10px 14px;border-radius:10px;border:1px solid var(--nd-line);background:#fff;color:var(--nd-text);cursor:pointer;text-decoration:none;transition:background .15s,border-color .15s,box-shadow .15s;white-space:nowrap}
.nd-btn:hover{background:#F8F9FB;border-color:#D9DEE6;color:var(--nd-text)}
.nd-btn-primary{background:var(--nd-blue);border-color:var(--nd-blue);color:#fff}
.nd-btn-primary:hover{background:var(--nd-blue2);border-color:var(--nd-blue2);color:#fff}
.nd-btn-orange{background:var(--nd-orange);border-color:var(--nd-orange);color:#fff!important}
.nd-btn-orange:hover{background:#EA6C0A;border-color:#EA6C0A}
.nd-btn-sm{padding:8px 12px;font-size:12.5px}
.nd-btn-block{width:100%;padding:13px 16px;font-size:14px}
.nd-btn[disabled]{opacity:.6;cursor:wait}
.nd-link{background:none;border:0;padding:0;font:inherit;font-size:12.5px;font-weight:600;color:var(--nd-blue);cursor:pointer;text-decoration:none;white-space:nowrap}
.nd-link:hover{text-decoration:underline}
.nd-pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:#475467;background:#fff;border:1px solid var(--nd-line);padding:5px 10px;border-radius:999px}
.nd-pill-id{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--nd-blue);font-weight:700}

/* Hero */
.nd-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:22px}
.nd-hero-date{font-size:12px;font-weight:600;color:var(--nd-faint);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px}
.nd-hero-title{font-size:30px;line-height:1.15;font-weight:750;letter-spacing:-.025em;margin:0 0 12px;color:var(--nd-text)}
.nd-hero-pills{display:flex;flex-wrap:wrap;gap:8px}
.nd-hero-actions{display:flex;gap:8px;flex-wrap:wrap}

/* KPI tiles */
.nd-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:24px}
.nd-kpi{display:flex;flex-direction:column;align-items:flex-start;gap:4px;text-align:left;font:inherit;background:var(--nd-card);border:1px solid var(--nd-line);border-radius:var(--nd-radius);padding:16px 18px;box-shadow:var(--nd-shadow);cursor:pointer;text-decoration:none;color:inherit;transition:border-color .15s,box-shadow .15s,transform .15s;min-width:0}
.nd-kpi:hover{border-color:#D3D9E2;box-shadow:0 6px 18px rgba(16,24,40,.06);transform:translateY(-1px)}
.nd-kpi-label{font-size:12.5px;font-weight:600;color:var(--nd-muted)}
.nd-kpi-value{font-size:26px;font-weight:750;letter-spacing:-.02em;color:var(--nd-text);line-height:1.15;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
.nd-kpi-sub{font-size:12px;color:var(--nd-faint);font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
.nd-up{color:var(--nd-green)!important}.nd-down{color:var(--nd-red)!important}

/* Tabs → Monarch underline tabs */
.nab-portal-body .nab-tab-bar{background:transparent;box-shadow:none;border-radius:0;padding:0;gap:2px;border-bottom:1px solid var(--nd-line);margin-bottom:24px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none}
.nab-portal-body .nab-tab-bar::-webkit-scrollbar{display:none}
.nab-portal-body .nab-tab-btn{border-radius:0;padding:12px 14px;margin-bottom:-1px;border-bottom:2px solid transparent;color:var(--nd-muted);font-size:13.5px}
.nab-portal-body .nab-tab-btn:hover{background:transparent;color:var(--nd-text)}
.nab-portal-body .nab-tab-btn.active{background:transparent;color:var(--nd-blue);border-bottom-color:var(--nd-blue)}

/* Grid + cards */
.nd-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:20px;margin-bottom:32px}
.nd-span-8{grid-column:span 8}.nd-span-4{grid-column:span 4}.nd-span-7{grid-column:span 7}.nd-span-5{grid-column:span 5}
.nd-stack{display:flex;flex-direction:column;gap:20px;min-width:0}
.nd-card{background:var(--nd-card);border:1px solid var(--nd-line);border-radius:var(--nd-radius);padding:20px 22px;box-shadow:var(--nd-shadow);min-width:0;box-sizing:border-box}
.nd-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px}
.nd-card-title{font-size:16px;font-weight:700;letter-spacing:-.01em;margin:0;color:var(--nd-text)}
.nd-card-sub{font-size:12.5px;color:var(--nd-muted);margin-top:3px}
.nd-seg{display:inline-flex;background:var(--nd-line2);border-radius:10px;padding:3px;gap:2px;flex-shrink:0}
.nd-seg button{border:0;background:transparent;font:inherit;font-size:12px;font-weight:600;color:var(--nd-muted);padding:6px 10px;border-radius:8px;cursor:pointer}
.nd-seg button.on{background:#fff;color:var(--nd-text);box-shadow:0 1px 2px rgba(16,24,40,.08)}

.nd-chart{position:relative;height:240px}
.nd-chart-lg{height:280px}
.nd-chart-sm{height:120px;margin:4px 0 12px}
.nd-empty{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;text-align:center;background:rgba(255,255,255,.92);border-radius:12px;padding:16px}
.nd-empty-inline{position:static;background:#F8F9FB;border:1px dashed #DCE1E8;margin-top:6px}
.nd-empty-ico{font-size:28px}
.nd-empty-txt{font-size:13px;color:var(--nd-muted);max-width:300px;line-height:1.5}

/* Cash flow stats */
.nd-stats{display:flex;gap:28px;flex-wrap:wrap;margin:-2px 0 14px}
.nd-stat-l{font-size:12px;color:var(--nd-muted);font-weight:500;display:flex;align-items:center;gap:6px}
.nd-stat-l i{width:8px;height:8px;border-radius:3px;display:inline-block}
.nd-stat-v{font-size:19px;font-weight:700;letter-spacing:-.01em;margin-top:2px}

/* Spending donut */
.nd-donut-wrap{display:flex;flex-direction:column;align-items:center;gap:16px}
.nd-chart-donut{height:190px;width:190px}
.nd-donut-center{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none}
.nd-donut-center span{font-size:20px;font-weight:750;letter-spacing:-.02em}
.nd-donut-center small{font-size:11.5px;color:var(--nd-muted)}
.nd-legend{list-style:none;margin:0;padding:0;width:100%}
.nd-legend li{display:flex;align-items:center;gap:10px;font-size:13px;padding:7px 0;border-bottom:1px solid var(--nd-line2)}
.nd-legend li:last-child{border-bottom:0}
.nd-legend .dot{width:10px;height:10px;border-radius:3px;flex-shrink:0}
.nd-legend .nm{flex:1;min-width:0;color:#344054;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nd-legend .amt{font-weight:650}
.nd-legend .pc{color:var(--nd-faint);font-size:12px;width:38px;text-align:right}

/* Net worth */
.nd-nw-value{font-size:32px;font-weight:750;letter-spacing:-.025em;line-height:1.1;margin-bottom:12px}
.nd-nw-bar{display:flex;height:8px;border-radius:999px;overflow:hidden;background:var(--nd-line2);margin-bottom:10px}
.nd-nw-bar .a{background:var(--nd-green);width:50%;transition:width .5s}.nd-nw-bar .d{background:var(--nd-red);width:0;transition:width .5s}
.nd-nw-legend{display:flex;gap:18px;flex-wrap:wrap;font-size:12.5px;color:var(--nd-muted)}
.nd-nw-legend i{display:inline-block;width:8px;height:8px;border-radius:3px;margin-right:6px}
.nd-nw-legend b{color:var(--nd-text);margin-left:4px}
.nd-accounts{list-style:none;margin:0;padding:0}
.nd-acc-group{font-size:11px;font-weight:700;color:var(--nd-faint);text-transform:uppercase;letter-spacing:.08em;padding:12px 0 4px;display:flex;justify-content:space-between}
.nd-acc{display:flex;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--nd-line2)}
.nd-acc-ico{width:34px;height:34px;border-radius:10px;background:var(--nd-line2);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.nd-acc-nm{flex:1;min-width:0}
.nd-acc-nm b{display:block;font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nd-acc-nm small{font-size:11.5px;color:var(--nd-faint)}
.nd-acc-bal{font-size:14px;font-weight:650;white-space:nowrap}

/* Utilization */
.nd-util-top{display:flex;align-items:center;gap:14px;margin-bottom:12px}
.nd-util-pct{font-size:30px;font-weight:750;letter-spacing:-.02em}
.nd-badge{font-size:11.5px;font-weight:700;padding:4px 9px;border-radius:999px}
.nd-badge.good{background:#E3F6EC;color:#157F4F}.nd-badge.ok{background:#FFF4D6;color:#8A5A00}.nd-badge.bad{background:#FDE7E8;color:#B42328}
.nd-util-row{margin-bottom:10px}
.nd-util-row .t{display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:5px;gap:8px}
.nd-util-row .t span:first-child{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600;color:#344054}
.nd-util-row .t span:last-child{color:var(--nd-muted);white-space:nowrap}
.nd-track{height:7px;background:var(--nd-line2);border-radius:999px;overflow:hidden}
.nd-track>span{display:block;height:100%;border-radius:999px;transition:width .5s}

/* Goals */
.nd-goals{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.nd-goal{display:flex;flex-direction:column;align-items:center;text-align:center;gap:4px;padding:10px 4px;border-radius:12px;text-decoration:none;color:inherit;transition:background .15s}
.nd-goal:hover{background:#F8F9FB}
.nd-goal-ring{position:relative;width:64px;height:64px}
.nd-goal-ring span{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:750}
.nd-ring{width:64px;height:64px}.nd-ring circle{transition:stroke-dashoffset .6s}
.nd-goal-name{font-size:13px;font-weight:650;margin-top:4px}
.nd-goal-sub{font-size:11.5px;color:var(--nd-faint)}

/* Score card (existing IDs, restyled) */
.nab-portal-body .nab-score-card{padding:20px 22px}
.nab-portal-body .nab-score-input{height:44px;padding:0 14px;border-radius:10px;font-size:15px;border:1px solid var(--nd-line)}
.nab-portal-body .nab-btn-check{height:44px;border-radius:10px;padding:0 18px;font-size:14px}
.nab-portal-body .nab-score-gauge{margin:14px 0 2px}
.nab-portal-body .nab-score-number{font-size:34px;font-weight:800;letter-spacing:-.02em;bottom:2px}
.nab-portal-body .nab-score-label{font-size:14px}

/* Existing sections — align with the new card style */
.nab-portal-body .nab-featured-video,.nab-portal-body .nab-learn-progress,.nab-portal-body .nab-journey-card,.nab-portal-body .nab-feat-card,.nab-portal-body .nab-profile-wrap,.nab-portal-body .nab-reader{border:1px solid var(--nd-line);box-shadow:var(--nd-shadow)}
.nab-portal-body .nab-feat-card{border-top:3px solid var(--ncard-accent)}
.nab-portal-body .nab-section-heading{font-size:16px;font-weight:700;color:var(--nd-text);text-transform:none;letter-spacing:-.01em}

/* Sheets / modal */
.nd-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center}
.nd-modal-backdrop{position:absolute;inset:0;background:rgba(15,27,45,.45);-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px);animation:ndFade .18s ease}
.nd-sheet{position:relative;background:#fff;border-radius:18px;width:min(560px,calc(100vw - 32px));max-height:min(88vh,780px);display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(15,27,45,.25);animation:ndPop .2s ease}
.nd-sheet-grip{display:none}
.nd-sheet-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px 10px}
.nd-sheet-head h3{margin:0;font-size:18px;font-weight:750;letter-spacing:-.01em;color:var(--nd-text)}
.nd-sheet-x{border:0;background:var(--nd-line2);width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:14px;color:var(--nd-muted)}
.nd-sheet-body{overflow-y:auto;padding:4px 22px 24px;-webkit-overflow-scrolling:touch}
.nd-form label{display:block;font-size:12.5px;font-weight:600;color:#475467;margin-bottom:12px}
.nd-form input,.nd-form select{display:block;width:100%;box-sizing:border-box;height:44px;margin-top:6px;padding:0 12px;border:1px solid #D9DEE6;border-radius:10px;font:inherit;font-size:15px;color:var(--nd-text);background:#fff;outline:none;transition:border-color .15s,box-shadow .15s}
.nd-form input:focus,.nd-form select:focus{border-color:var(--nd-blue);box-shadow:0 0 0 3px rgba(13,92,155,.12)}
.nd-money{position:relative;display:block}
.nd-money::before{content:'$';position:absolute;left:12px;top:50%;transform:translateY(-50%);margin-top:3px;color:var(--nd-faint);font-weight:600;font-size:14px;pointer-events:none}
.nd-money input{padding-left:26px}
.nd-row2{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
.nd-subhead{font-size:11px;font-weight:700;color:var(--nd-faint);text-transform:uppercase;letter-spacing:.08em;margin:6px 0 10px}
.nd-cat-dot{display:inline-block;width:8px;height:8px;border-radius:3px;margin-right:6px}
.nd-hint{font-size:12px;color:var(--nd-muted);margin:-2px 0 12px;line-height:1.5}
.nd-form-msg{font-size:13px;font-weight:600;margin:0 0 10px;min-height:0}
.nd-form-msg.ok{color:#157F4F}.nd-form-msg.err{color:#B42328}
.nd-form-actions{display:flex;flex-direction:column;gap:8px}
.nd-list-title{font-size:11px;font-weight:700;color:var(--nd-faint);text-transform:uppercase;letter-spacing:.08em;margin:22px 0 6px}
.nd-list{list-style:none;margin:0;padding:0}
.nd-list li{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--nd-line2);font-size:13.5px}
.nd-list li .main{flex:1;min-width:0}
.nd-list li .main b{display:block;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nd-list li .main small{color:var(--nd-faint);font-size:12px}
.nd-list li .act{display:flex;gap:4px}
.nd-list li .act button{border:0;background:var(--nd-line2);color:#475467;font:inherit;font-size:12px;font-weight:600;padding:6px 10px;border-radius:8px;cursor:pointer}
.nd-list li .act button.del:hover{background:#FDE7E8;color:#B42328}
.nd-list .none{color:var(--nd-faint);font-size:13px}
.nd-quick{display:flex;flex-direction:column;gap:10px;padding-bottom:6px}
.nd-quick button{display:grid;grid-template-columns:44px 1fr;grid-template-rows:auto auto;column-gap:12px;align-items:center;text-align:left;font:inherit;padding:14px;border:1px solid var(--nd-line);border-radius:14px;background:#fff;cursor:pointer;transition:border-color .15s,background .15s}
.nd-quick button:hover{border-color:var(--nd-blue);background:#F6F9FD}
.nd-quick span{grid-row:1/3;width:44px;height:44px;border-radius:12px;background:var(--nd-line2);display:flex;align-items:center;justify-content:center;font-size:20px}
.nd-quick b{font-size:14.5px;color:var(--nd-text)}
.nd-quick small{font-size:12px;color:var(--nd-muted)}
@keyframes ndFade{from{opacity:0}to{opacity:1}}
@keyframes ndPop{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}
@keyframes ndUp{from{transform:translateY(100%)}to{transform:none}}

/* Bottom nav (mobile only) */
.nd-bottomnav{display:none}

/* ── Responsive ───────────────────────────────────────────── */
@media(max-width:1180px){
  .nd-span-8,.nd-span-4,.nd-span-7,.nd-span-5{grid-column:1/-1}
  .nd-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
  .nd-donut-wrap{flex-direction:row;align-items:center}
  .nd-donut-wrap .nd-legend{flex:1}
}
@media(max-width:768px){
  .nab-portal-body .nab-topbar{padding:10px 14px 10px 64px;min-height:60px;flex-wrap:wrap;gap:10px}
  .nab-portal-body .nab-topbar-title{font-size:16px;flex:1}
  .nab-portal-body .nab-topbar-right .nab-status-badge,.nab-portal-body .nab-topbar-right .nab-qbtn{display:none}
  .nab-portal-body .nab-search-wrap{order:3;flex-basis:100%;max-width:none}
  /* scrolls away with the header — the bottom nav has its own Menu button */
  .nab-portal-body .nab-hamburger{position:absolute;top:10px;left:12px}
  .nab-portal-body .nab-content{padding:18px 16px calc(96px + env(safe-area-inset-bottom))}
  .nd-hero{margin-bottom:18px;align-items:stretch}
  .nd-hero-title{font-size:25px}
  .nd-hero-actions{width:100%}
  .nd-hero-actions .nd-btn{flex:1}
  .nd-kpis{gap:10px;margin-bottom:20px}
  .nd-kpi{padding:14px}
  .nd-kpi-value{font-size:21px}
  .nd-grid{gap:14px}
  .nd-stack{gap:14px}
  .nd-card{padding:16px;border-radius:14px}
  .nd-card-head{flex-wrap:wrap}
  .nd-chart{height:210px}
  .nd-chart-lg{height:230px}
  .nd-donut-wrap{flex-direction:column}
  .nd-stats{gap:18px}
  .nd-stat-v{font-size:17px}
  .nd-nw-value{font-size:28px}
  .nab-ask-ai-btn{display:none!important}
  /* bottom sheet */
  .nd-modal{align-items:flex-end}
  .nd-sheet{width:100%;max-height:92vh;border-radius:20px 20px 0 0;animation:ndUp .24s cubic-bezier(.2,.8,.2,1)}
  .nd-sheet-grip{display:block;width:40px;height:5px;border-radius:3px;background:#D9DEE6;margin:8px auto 0}
  .nd-sheet-head{padding:10px 18px 8px}
  .nd-sheet-body{padding:4px 18px calc(24px + env(safe-area-inset-bottom))}
  .nd-form input,.nd-form select{font-size:16px}
  /* bottom nav */
  .nd-bottomnav{display:grid;grid-template-columns:repeat(5,1fr);align-items:end;position:fixed;left:0;right:0;bottom:0;z-index:80;background:rgba(255,255,255,.96);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border-top:1px solid var(--nd-line);padding:6px 6px calc(6px + env(safe-area-inset-bottom))}
  .nd-bottomnav>a,.nd-bottomnav>button{display:flex;flex-direction:column;align-items:center;gap:2px;font:inherit;font-size:10.5px;font-weight:600;color:var(--nd-muted);background:none;border:0;text-decoration:none;padding:4px 0;cursor:pointer}
  .nd-bottomnav>a span,.nd-bottomnav>button span{font-size:19px;line-height:1.2}
  .nd-bottomnav .on{color:var(--nd-blue)}
  .nd-bottomnav .nd-bn-add span{width:50px;height:50px;border-radius:50%;background:var(--nd-blue);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:400;box-shadow:0 6px 16px rgba(13,92,155,.35);margin-top:-22px}
}
@media(max-width:380px){
  .nd-kpi-value{font-size:19px}
  .nd-goals{gap:4px}
}
</style>
</head>
<body <?php body_class( 'nab-portal-body' ); ?>>
<?php wp_body_open(); ?>
<!-- edu stubs removed -->

<script>
// Store auto-tab for external JS to pick up after load
window._nabAutoTab = <?php echo $auto_tab ? '"' . esc_js( $auto_tab ) . '"' : 'null'; ?>;
</script>
<script>
// Data for external nab-dashboard.js
var nabEduData = <?php
$edu_js = [];
foreach($edu_modules as $mid => $mod){
    $edu_js[] = [
        'id'       => $mid,
        'title'    => $mod['title'],
        'url'      => $mod['url'] ?: '',
        'thumb'    => $mod['thumb'] ?: '',
        'dur'      => $mod['duration'],
        'cat'      => $mod['category'],
        'overview' => $mod['overview'] ?? '',
        'concepts' => $mod['concepts'] ?? [],
        'steps'    => $mod['steps'] ?? [],
        'done'     => in_array($mid, $completed_modules),
    ];
}
echo json_encode($edu_js, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
?>;

var nabQuizData = {
  1: { title: "What is Credit, Really?", questions: [
    { q: "What is the social logic that defines credit?", hint: "Think of the everyday feeling of trust when lending money to a reliable friend.", opts: ["A government-mandated savings plan.", "A foundation of trust that allows you to borrow today based on a promise to pay later.", "A legal penalty for those who do not use cash.", "A one-time gift from a bank."], answer: 1 },
    { q: "How is interest conceptually described?", hint: "It is the $5 difference between borrowing $100 today and paying back $105 next month.", opts: ["A storage fee for keeping money in a bank.", "A tax on all digital transactions.", "The price of speed - the cost for the convenience of using someone else money now.", "A reward given to borrowers for taking out loans."], answer: 2 },
    { q: "Why might a lender charge a borrower a higher interest rate?", hint: "Lenders use extra costs to balance out the risk they are taking.", opts: ["To reward the borrower for having a high credit score.", "Because the borrower history suggests a higher risk that they might stop paying.", "Because the borrower has no existing debts.", "To encourage the borrower to spend more money."], answer: 1 },
    { q: "In Canada, which two private companies act as historians by maintaining credit records?", hint: "These organizations are known as credit bureaus.", opts: ["Visa and Mastercard.", "The Bank of Canada and the Government of Canada.", "Equifax and TransUnion.", "NAB Solutions and local credit unions."], answer: 2 },
    { q: "Does checking your own credit score lower your score?", hint: "The idea that checking your own score damages it is a common myth.", opts: ["Yes, it lowers it by 10 points each time.", "No, it is a private background check that has zero impact on your score.", "Only if you check it more than once a month.", "Yes, because it shows lenders you are desperate."], answer: 1 }
  ]},
  2: { title: "The Trust Recipe: How Credit Scores Are Calculated", questions: [
    { q: "What is the single most important factor in calculating a credit score, making up 35% of the total?", hint: "This factor asks the simple question: Do you pay on time?", opts: ["Credit Mix", "Credit Utilization", "Payment History", "Length of Credit History"], answer: 2 },
    { q: "In Canada, what is the range for a credit score and what is generally considered an excellent rating?", hint: "The scale starts at 300 and the green zone begins above 760.", opts: ["0 to 1000; 800+", "300 to 900; 760+", "400 to 800; 700+", "500 to 900; 850+"], answer: 1 },
    { q: "To maintain a healthy credit utilization ratio, lenders prefer to see your balances kept below what percentage?", hint: "Warning lights start to flash for lenders once your tank is filled past this specific percentage.", opts: ["10%", "50%", "30%", "75%"], answer: 2 },
    { q: "Why is it often recommended to keep your oldest credit card account open even if you rarely use it?", hint: "This factor is worth about 15% of your score and looks at the average age of all your accounts.", opts: ["It improves your credit mix", "It prevents new inquiries", "It increases your length of credit history", "It automatically lowers your utilization"], answer: 2 },
    { q: "Which of the following actions is considered a soft inquiry and will NOT lower your credit score?", hint: "There is a common myth that doing this yourself will hurt your rating, but it is actually completely safe.", opts: ["Applying for a new car loan", "Checking your own credit score", "Applying for three credit cards in one month", "Opening a new student loan"], answer: 1 }
  ]},
  3: { title: "The Escalating Cost of Bad Advice", questions: [
    { q: "Does checking your own credit score through a bank app hurt your score?", hint: "Checking your own score is a soft inquiry and is invisible to lenders.", opts: ["Yes, it lowers it every time.", "No, it has no effect on your score.", "Yes, but only if you check it once a year.", "Only if you are using a computer."], answer: 1 },
    { q: "What happens if you close your oldest credit card?", hint: "The length of your credit history makes up about 15% of your total score.", opts: ["Your score will go up instantly.", "It cleans up your file and makes it better.", "Your score may drop because you lose your credit history.", "Nothing happens at all."], answer: 2 },
    { q: "Do you need to leave a small balance on your card and pay interest to build credit?", hint: "The credit scoring formula does not look at interest charges; it only asks if you paid on time.", opts: ["Yes, banks need to see you pay interest.", "No, you only need to make the minimum payment on time.", "Yes, it shows you are a good customer.", "Only if the balance is over $1,000."], answer: 1 },
    { q: "Does using your debit card help build your credit score?", hint: "Debit cards manage your own cash and are not part of the credit reporting system.", opts: ["Yes, because it shows how you spend cash.", "Yes, but only for small purchases.", "No, debit card use is not reported to credit bureaus.", "Only if you use it at a grocery store."], answer: 2 },
    { q: "What is a hard inquiry?", hint: "This happens when you are actively seeking new debt from a lender.", opts: ["When you check your own score for fun.", "When you apply for a new credit card or a car loan.", "When you pay your monthly phone bill.", "When you look at your bank balance."], answer: 1 }
  ]},
  4: { title: "Free Canadian Credit Reports: The Complete Guide", questions: [
    { q: "How much does it cost to get your official credit report directly from Equifax or TransUnion?", hint: "You are legally entitled to a copy of this document at no cost.", opts: ["$20.00", "$50.00", "It is free", "A monthly fee"], answer: 2 },
    { q: "Does checking your own credit report hurt your credit score?", hint: "Pulling your own report is considered a soft inquiry.", opts: ["Yes, it lowers the score", "No, it has no impact", "Only if you check it once a year", "Yes, it is a hard inquiry"], answer: 1 },
    { q: "What information do you need to have ready to get your report online?", hint: "You need basic personal details and your SIN to pass security checks.", opts: ["Your driver licence number and a credit card", "Your birth date, current address, and Social Insurance Number (SIN)", "Your mother maiden name only", "A list of all your monthly grocery bills"], answer: 1 },
    { q: "When using the TransUnion website, which option should you look for to get your free report?", hint: "Avoid buttons for monitoring as they often lead to paid subscriptions.", opts: ["Credit Monitoring", "Monthly Subscription", "Paid Service", "Consumer Disclosure"], answer: 3 },
    { q: "How often should you check your credit reports to help spot errors or identity theft early?", hint: "Reviewing your file regularly allows you to catch mistakes before applying for a loan.", opts: ["Every five years", "Once every ten years", "Once or twice a year", "Never"], answer: 2 }
  ]},
  5: { title: "Securing Your Free Canadian Credit Reports", questions: [
    { q: "What information should you have ready before starting to avoid timing out?", hint: "Think about the basic personal details used to identify you on legal forms.", opts: ["Your favorite color and pet name", "Your legal name, current address, and date of birth", "Your high school graduation date", "A list of your favorite stores"], answer: 1 },
    { q: "Why should you have a physical credit card or loan statement in front of you?", hint: "The credit bureaus will ask very specific questions to prove you are who you say you are.", opts: ["To pay a fee for the credit report", "To use as a bookmark", "To answer exact questions about account balances or opening dates", "To take a picture of it for the website"], answer: 2 },
    { q: "What should you do when Equifax shows you premium paid monthly services?", hint: "Look past the big bright buttons for a smaller link that lets you keep going without paying.", opts: ["Sign up for the most expensive one", "Close your browser immediately", "Find the subtle option to skip or continue for free", "Enter your credit card information"], answer: 2 },
    { q: "What specific phrase must you look for on the TransUnion website to get your free report?", hint: "TransUnion uses this legal term instead of saying free credit report in their main menu.", opts: ["Free Credit Score", "Consumer Disclosure", "Member Login", "Buy Now"], answer: 1 },
    { q: "What should you do after downloading both PDF reports to your computer?", hint: "These reports use shorthand that is easy to misunderstand without help.", opts: ["Print them out and throw them away", "Open them and try to read the industry codes immediately", "Put them in a new folder and wait for guidance to decode them", "Email them to all your friends"], answer: 2 }
  ]},
  6: { title: "The Credit Inquiry Guide: Understanding Hard and Soft Pulls", questions: [
    { q: "What is a soft inquiry on your credit report?", hint: "These are harmless and do not change your credit score.", opts: ["A check for a new credit card", "A routine check like a background check or pre-approved offer", "A mistake on your report", "A check for a new car loan"], answer: 1 },
    { q: "Which type of credit check can lower your credit score?", hint: "These happen when you actively apply for things like a mortgage or a credit card.", opts: ["Soft inquiries", "Checking your own score", "Hard inquiries from applying for new debt", "Background checks"], answer: 2 },
    { q: "How long does it take for the score penalty from a hard inquiry to completely go away?", hint: "While it stays on your report longer, the negative effect on your score fades after the first year.", opts: ["1 month", "6 months", "12 months (1 year)", "3 years"], answer: 2 },
    { q: "If you are shopping for one car loan and visit three different banks in one week, how does the credit score model usually treat those checks?", hint: "The system uses a rate shopping exception for the exact same type of loan within a short window.", opts: ["As three separate penalties", "As one single hit to your score", "As a sign of financial distress", "They are ignored completely"], answer: 1 },
    { q: "What should you do if you see a hard inquiry on your report that you do not recognize?", hint: "An unauthorized check is a warning sign that someone else might be trying to use your credit.", opts: ["Ignore it because it will go away in a year", "Highlight it in green", "Assume it is a soft inquiry", "Circle it in red as it could be a sign of identity theft"], answer: 3 }
  ]},
  7: { title: "Building Better Financial Habits", questions: [
    { q: "What is the most important thing for good financial health?", hint: "It is about being consistent every day, not being perfect.", opts: ["Having a perfect math score", "Never making a single mistake", "Small, steady habits you can keep doing", "Winning the lottery"], answer: 2 },
    { q: "Why should you use a budget?", hint: "Think of it as a tool to help you make better choices with your money.", opts: ["To see where your money is actually going", "Because it is a math test you must pass", "To make yourself feel bad about spending", "To predict the future perfectly"], answer: 0 },
    { q: "What is a risk of using autopay for your bills?", hint: "Even if it is automatic, you still need to keep an eye on your bank balance.", opts: ["It makes you too much money", "You might stop checking your accounts and miss a problem", "It is too difficult to set up", "There are no risks to using it"], answer: 1 },
    { q: "How can reporting your rent help you?", hint: "It uses the rent you are already paying to show you are reliable.", opts: ["It lowers your rent cost", "It gives you free money", "It turns a habit you already have into credit history", "It pays your bills for you"], answer: 2 },
    { q: "How much should you try to save first for emergencies?", hint: "Start small with enough to cover your bills for just one month.", opts: ["10 years of salary", "Nothing at all", "Enough to cover 1 month of basic needs", "Exactly one million dollars"], answer: 2 }
  ]}
};

</script>

<?php if ( $is_suspended ) : ?>
<div class="nab-suspension-screen">
  <div class="nab-suspension-box">
    <div class="nab-susp-icon">🔒</div>
    <h2>Account Temporarily Suspended</h2>
    <p><?php echo wp_kses_post( $suspension_msg ?: 'Please settle your outstanding payment to regain access to the member portal.' ); ?></p>
    <?php
    $nab_supp_email = nab_get_global_setting('nab_support_email','admin@nabsolutions.ca');
    ?>
    <a href="mailto:<?php echo esc_attr($nab_supp_email); ?>" class="nab-btn-orange">Contact Support</a>
  </div>
</div>

<?php else : ?>
<div class="nab-portal-wrap">

  <?php nab_render_sidebar( 'dashboard' ); ?>

  <main class="nab-main">

    <!-- ── TOPBAR ───────────────────────────────────── -->
    <header class="nab-topbar">
      <div class="nab-topbar-title">Member Dashboard</div>
      <div class="nab-search-wrap" id="nabSearchWrap">
        <span class="nab-search-icon">🔍</span>
        <input type="text" class="nab-search-input" id="nabSearchInput" placeholder="Search tools, modules, resources..." autocomplete="off">
        <div class="nab-search-results" id="nabSearchResults"></div>
      </div>
      <div class="nab-topbar-right">
        <?php nab_render_notification_bell(); ?>
        <span class="nab-status-badge nab-status-<?php echo esc_attr( strtolower( $member_status ) ); ?>">
          ● <?php echo esc_html( $member_status ); ?>
        </span>
        <button class="nab-qbtn" style="margin:0" data-tab-open="profile" type="button">👤 Profile</button>
      </div>
    </header>

    <div class="nab-content">

      <!-- ── HERO (v1.9.0, Monarch-style) ───────────── -->
      <section class="nd-hero">
        <div class="nd-hero-main">
          <div class="nd-hero-date"><?php echo esc_html( wp_date( 'l, F j' ) ); ?></div>
          <h1 class="nd-hero-title">Good <?php echo esc_html( strtolower( $greeting ) ); ?>, <?php echo esc_html( $first_name ); ?></h1>
          <div class="nd-hero-pills">
            <span class="nd-pill"><span class="ndot"></span><?php echo esc_html( $member_status ); ?> Member</span>
            <?php if ( $member_since ) : ?>
            <span class="nd-pill" title="The date you registered">📅 Member since <?php echo esc_html( $member_since ); ?></span>
            <?php endif; ?>
            <span class="nd-pill">💳 <?php echo esc_html( $payment_status ); ?></span>
            <span class="nd-pill nd-pill-id">ID <?php echo esc_html( $member_id ); ?></span>
          </div>
        </div>
        <div class="nd-hero-actions">
          <?php if ( $url_book !== '#' ) : ?>
          <a href="<?php echo esc_url( $url_book ); ?>" class="nd-btn nd-btn-orange">📅 Book Now</a>
          <?php endif; ?>
          <button type="button" class="nd-btn nd-btn-primary" data-nd-open="quick">＋ Add data</button>
          <button type="button" class="nd-btn" data-tab-open="profile">👤 Profile</button>
        </div>
      </section>

      <!-- ── KPI TILES (filled by nab-dashboard-charts.js) ── -->
      <section class="nd-kpis" aria-label="Your numbers at a glance">
        <button type="button" class="nd-kpi" data-nd-open="score">
          <span class="nd-kpi-label">Credit score</span>
          <span class="nd-kpi-value" id="ndKpiScore"><?php echo $saved_score ? esc_html( $saved_score ) : '—'; ?></span>
          <span class="nd-kpi-sub" id="ndKpiScoreSub">Log your score</span>
        </button>
        <button type="button" class="nd-kpi" data-nd-open="account">
          <span class="nd-kpi-label">Net worth</span>
          <span class="nd-kpi-value" id="ndKpiNet">—</span>
          <span class="nd-kpi-sub" id="ndKpiNetSub">Add your accounts</span>
        </button>
        <button type="button" class="nd-kpi" data-nd-open="cashflow">
          <span class="nd-kpi-label" id="ndKpiCfLabel">Cash flow</span>
          <span class="nd-kpi-value" id="ndKpiCf">—</span>
          <span class="nd-kpi-sub" id="ndKpiCfSub">Add this month</span>
        </button>
        <a class="nd-kpi" href="<?php echo esc_url( $nav_links['util'] !== '#' ? $nav_links['util'] : '#nd-util' ); ?>">
          <span class="nd-kpi-label">Credit utilization</span>
          <span class="nd-kpi-value" id="ndKpiUtil">—</span>
          <span class="nd-kpi-sub" id="ndKpiUtilSub">Use the Utilization Checker</span>
        </a>
      </section>

      <!-- ══ TAB BAR ════════════════════════════════════ -->
      <div class="nab-tab-bar" id="nabTabBar" role="tablist">
        <button class="nab-tab-btn active" data-tab="overview" data-tab-open="overview" role="tab" type="button">🏠 Overview</button>
        <button class="nab-tab-btn"        data-tab="profile"  data-tab-open="profile"  role="tab" type="button">👤 My Profile</button>
                <button class="nab-tab-btn"        data-tab="blog"     data-tab-open="blog"     role="tab" type="button">📚 Education Blog</button>
        <button class="nab-tab-btn"        data-tab="diy"      data-tab-open="diy"      role="tab" type="button">📄 DIY Dispute Letter</button>
      </div>

      <!-- ══ TAB: OVERVIEW ════════════════════════════════ -->
      <div class="nab-tab-panel active" id="nabTab-overview" role="tabpanel">

        <?php
        // Small progress ring used by the Goals card
        $nd_ring = function( $pct, $color, $id = '' ) {
            $pct = max( 0, min( 100, (int) round( $pct ) ) );
            $c   = 2 * M_PI * 26;
            printf(
                '<svg class="nd-ring" viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="26" fill="none" stroke="#EEF1F5" stroke-width="7"/><circle %s cx="32" cy="32" r="26" fill="none" stroke="%s" stroke-width="7" stroke-linecap="round" stroke-dasharray="%.1f" stroke-dashoffset="%.1f" transform="rotate(-90 32 32)"/></svg>',
                $id ? 'id="' . esc_attr( $id ) . '"' : '', esc_attr( $color ), $c, $c * ( 1 - $pct / 100 )
            );
        };
        ?>
        <div class="nd-grid">

          <!-- Credit score trend -->
          <section class="nd-card nd-span-8">
            <header class="nd-card-head">
              <div>
                <h2 class="nd-card-title">Credit score</h2>
                <div class="nd-card-sub" id="ndScoreSub">Track your score over time</div>
              </div>
              <div class="nd-seg" role="group" aria-label="Score chart range" data-nd-range="score">
                <button type="button" data-range="3">3M</button>
                <button type="button" data-range="6">6M</button>
                <button type="button" data-range="12" class="on">1Y</button>
                <button type="button" data-range="0">All</button>
              </div>
            </header>
            <div class="nd-chart nd-chart-lg"><canvas id="ndScoreChart" aria-label="Credit score over time" role="img"></canvas>
              <div class="nd-empty" id="ndScoreEmpty" hidden>
                <div class="nd-empty-ico">📈</div>
                <div class="nd-empty-txt">Log your credit score to start your trend line.</div>
                <?php if ( $can_self_score ) : ?><button type="button" class="nd-btn nd-btn-primary" data-nd-open="score">＋ Log a score</button><?php endif; ?>
              </div>
            </div>
          </section>

          <!-- Score gauge + quick check (IDs used by nab-dashboard.js) -->
          <section class="nd-card nd-span-4 nab-score-card">
            <header class="nd-card-head">
              <h2 class="nd-card-title">My credit score</h2>
              <?php if ( $can_self_score ) : ?><button type="button" class="nd-link" data-nd-open="score">History</button><?php endif; ?>
            </header>
            <?php if ( ! $can_self_score ) : ?>
            <p style="font-size:12px;color:#64748b;margin:0 0 8px">Score pulled from your credit account.</p>
            <?php else : ?>
            <div class="nab-score-input-wrap">
              <input type="number" id="nabScoreInput" class="nab-score-input" placeholder="Enter score (300–900)" inputmode="numeric"
                min="300" max="900" <?php echo ( $saved_score && $score_source === 'self' ) ? 'value="' . esc_attr( $saved_score ) . '"' : ''; ?>>
              <button id="nabScoreBtn" class="nab-btn-check" type="button">Save</button>
            </div>
            <div class="nab-score-error" id="nabScoreError"></div>
            <div class="nab-score-self-label" id="nabSelfLabel">⚠ Self-reported score</div>
            <?php endif; ?>
            <div id="nabScoreDisplay" style="display:<?php echo $saved_score ? 'block' : 'none'; ?>">
              <div class="nab-score-gauge">
                <svg width="180" height="96" viewBox="0 0 160 86">
                  <path d="M10,80 A70,70 0 0,1 150,80" fill="none" stroke="#EEF1F5" stroke-width="12" stroke-linecap="round"/>
                  <path id="nabGaugeArc" d="M10,80 A70,70 0 0,1 150,80" fill="none" stroke="#0D5C9B" stroke-width="12"
                    stroke-linecap="round" stroke-dasharray="220" stroke-dashoffset="220" style="transition:stroke-dashoffset .6s ease,stroke .4s"/>
                </svg>
                <span class="nab-score-number" id="nabScoreNum"><?php echo $saved_score ? esc_html( $saved_score ) : ''; ?></span>
              </div>
              <div class="nab-score-label" id="nabScoreLabel"></div>
              <div class="nab-score-msg"   id="nabScoreMsg"></div>
              <div class="nab-score-range">
                <div class="nab-ri"><span style="color:#ef4444">●</span><span>300</span><span>Poor</span></div>
                <div class="nab-ri"><span style="color:#f97316">●</span><span>580</span><span>Fair</span></div>
                <div class="nab-ri"><span style="color:#3b82f6">●</span><span>670</span><span>Good</span></div>
                <div class="nab-ri"><span style="color:#22c55e">●</span><span>740</span><span>V.Good</span></div>
                <div class="nab-ri"><span style="color:#16a34a">●</span><span>800</span><span>Excellent</span></div>
              </div>
            </div>
            <div class="nab-score-providers">
              <a class="nab-provider-chip" href="<?php echo esc_url($equifax_url); ?>"     target="_blank" rel="noopener"><span class="nab-pdot" style="background:#e53e3e"></span>Equifax</a>
              <a class="nab-provider-chip" href="<?php echo esc_url($transunion_url); ?>"  target="_blank" rel="noopener"><span class="nab-pdot" style="background:#3182ce"></span>TransUnion</a>
              <a class="nab-provider-chip" href="<?php echo esc_url($borrowell_url); ?>"   target="_blank" rel="noopener"><span class="nab-pdot" style="background:#38a169"></span>Borrowell</a>
              <a class="nab-provider-chip" href="<?php echo esc_url($creditkarma_url); ?>" target="_blank" rel="noopener"><span class="nab-pdot" style="background:#805ad5"></span>CreditKarma</a>
            </div>
          </section>

          <!-- Cash flow -->
          <section class="nd-card nd-span-8">
            <header class="nd-card-head">
              <div>
                <h2 class="nd-card-title">Cash flow</h2>
                <div class="nd-card-sub">Income vs. expenses — last 6 months</div>
              </div>
              <button type="button" class="nd-btn nd-btn-sm" data-nd-open="cashflow">＋ Add month</button>
            </header>
            <div class="nd-stats" id="ndCfStats"></div>
            <div class="nd-chart"><canvas id="ndCashflowChart" aria-label="Income and expenses by month" role="img"></canvas>
              <div class="nd-empty" id="ndCashflowEmpty" hidden>
                <div class="nd-empty-ico">💸</div>
                <div class="nd-empty-txt">Add a month of income and expenses to see your cash flow.</div>
                <button type="button" class="nd-btn nd-btn-primary" data-nd-open="cashflow">＋ Add a month</button>
              </div>
            </div>
          </section>

          <!-- Spending breakdown -->
          <section class="nd-card nd-span-4">
            <header class="nd-card-head">
              <div>
                <h2 class="nd-card-title">Spending</h2>
                <div class="nd-card-sub" id="ndSpendSub">By category</div>
              </div>
            </header>
            <div class="nd-donut-wrap">
              <div class="nd-chart nd-chart-donut"><canvas id="ndSpendChart" aria-label="Spending by category" role="img"></canvas>
                <div class="nd-donut-center"><span id="ndSpendTotal">—</span><small>spent</small></div>
              </div>
              <ul class="nd-legend" id="ndSpendLegend"></ul>
            </div>
            <div class="nd-empty nd-empty-inline" id="ndSpendEmpty" hidden>
              <div class="nd-empty-txt">Your spending categories will appear here.</div>
            </div>
          </section>

          <!-- Net worth -->
          <section class="nd-card nd-span-7">
            <header class="nd-card-head">
              <div>
                <h2 class="nd-card-title">Net worth</h2>
                <div class="nd-card-sub">What you own minus what you owe</div>
              </div>
              <button type="button" class="nd-btn nd-btn-sm" data-nd-open="account">＋ Add account</button>
            </header>
            <div class="nd-nw-top">
              <div class="nd-nw-value" id="ndNwValue">—</div>
              <div class="nd-nw-bar" id="ndNwBar" aria-hidden="true"><span class="a"></span><span class="d"></span></div>
              <div class="nd-nw-legend">
                <span><i style="background:#22A06B"></i>Assets <b id="ndNwAssets">$0</b></span>
                <span><i style="background:#E5484D"></i>Debts <b id="ndNwDebts">$0</b></span>
              </div>
            </div>
            <div class="nd-chart nd-chart-sm"><canvas id="ndNetChart" aria-label="Net worth over time" role="img"></canvas></div>
            <ul class="nd-accounts" id="ndAccountList"></ul>
            <div class="nd-empty nd-empty-inline" id="ndNwEmpty" hidden>
              <div class="nd-empty-txt">Add your bank accounts, savings, loans and cards to see your net worth.</div>
              <button type="button" class="nd-btn nd-btn-primary" data-nd-open="account">＋ Add an account</button>
            </div>
          </section>

          <!-- Utilization + Goals -->
          <div class="nd-span-5 nd-stack">
            <section class="nd-card" id="nd-util">
              <header class="nd-card-head">
                <div>
                  <h2 class="nd-card-title">Credit utilization</h2>
                  <div class="nd-card-sub">Keep it under 30%</div>
                </div>
                <?php if ( $nav_links['util'] !== '#' ) : ?><a class="nd-link" href="<?php echo esc_url( $nav_links['util'] ); ?>">Update →</a><?php endif; ?>
              </header>
              <div id="ndUtilBody"></div>
            </section>

            <section class="nd-card">
              <header class="nd-card-head"><h2 class="nd-card-title">Goals</h2></header>
              <div class="nd-goals">
                <a class="nd-goal" href="<?php echo esc_url( $nav_links['ef'] !== '#' ? $nav_links['ef'] : '#' ); ?>">
                  <div class="nd-goal-ring"><?php $nd_ring( 0, '#22A06B', 'ndEfRing' ); ?><span id="ndEfPct">0%</span></div>
                  <div class="nd-goal-name">Emergency fund</div>
                  <div class="nd-goal-sub" id="ndEfSub">Set a goal</div>
                </a>
                <a class="nd-goal" href="<?php echo esc_url( $nav_links['learning'] ); ?>">
                  <div class="nd-goal-ring"><?php $nd_ring( $progress_pct, '#0D5C9B' ); ?><span><?php echo (int) $progress_pct; ?>%</span></div>
                  <div class="nd-goal-name">Learning</div>
                  <div class="nd-goal-sub"><?php echo (int) $completed_count; ?> of <?php echo (int) $total_modules; ?> lessons</div>
                </a>
                <?php $rm_pct = $roadmap_progress['total'] ? 100 * $roadmap_progress['done'] / $roadmap_progress['total'] : 0; ?>
                <a class="nd-goal" href="<?php echo esc_url( $nav_links['roadmap'] !== '#' ? $nav_links['roadmap'] : '#' ); ?>">
                  <div class="nd-goal-ring"><?php $nd_ring( $rm_pct, '#F97316' ); ?><span><?php echo (int) round( $rm_pct ); ?>%</span></div>
                  <div class="nd-goal-name">Roadmap</div>
                  <div class="nd-goal-sub"><?php echo (int) $roadmap_progress['done']; ?> of <?php echo (int) $roadmap_progress['total']; ?> steps</div>
                </a>
              </div>
            </section>
          </div>

        </div><!-- /nd-grid -->

        <!-- ── Featured Video + Right Panel ─────────── -->
        <div class="nab-dash-two-col">

          <!-- Featured Training Video -->
          <div class="nab-featured-video">
            <div class="nab-featured-inner">
              <div class="nab-video-player-wrap">
                <?php if (!empty($featured_module['url'])) : ?>
                <?php
                // Extract YouTube video ID from embed URL
                $embed_url = $featured_module['url'];
                preg_match('/embed\/([a-zA-Z0-9_-]+)/', $embed_url, $yt_match);
                $yt_id     = $yt_match[1] ?? '';
                $yt_thumb  = $yt_id ? "https://img.youtube.com/vi/{$yt_id}/maxresdefault.jpg" : '';
                $yt_watch  = $yt_id ? "https://www.youtube.com/watch?v={$yt_id}" : $embed_url;
                $mod_thumb = $featured_module['thumb'] ?? '';
                $thumb_src = $mod_thumb ?: $yt_thumb;
                ?>
                <div style="position:relative;width:100%;height:100%;cursor:pointer;background:#000"
                     onclick="this.innerHTML='<iframe src='<?php echo esc_js($embed_url); ?>?autoplay=1&rel=0' frameborder='0' allow='accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture;web-share' allowfullscreen referrerpolicy='strict-origin-when-cross-origin' style='width:100%;height:100%;border:0;display:block'></iframe>'">
                  <?php if($thumb_src): ?>
                  <img src="<?php echo esc_url($thumb_src); ?>" alt="<?php echo esc_attr($featured_module['title']); ?>"
                       style="width:100%;height:100%;object-fit:cover;display:block"
                       onerror="this.style.display='none'">
                  <?php endif; ?>
                  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:64px;height:64px;background:rgba(255,0,0,.9);border-radius:50%;display:flex;align-items:center;justify-content:center">
                    <div style="width:0;height:0;border-top:12px solid transparent;border-bottom:12px solid transparent;border-left:20px solid #fff;margin-left:4px"></div>
                  </div>
                </div>
                <?php elseif (!empty($featured_module['thumb'])) : ?>
                <div class="nab-video-placeholder" data-tab-open="education">
                  <img src="<?php echo esc_url($featured_module['thumb']); ?>" alt="<?php echo esc_attr($featured_module['title']); ?>" style="width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0">
                  <div style="position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:8px">
                    <div class="nab-play-btn">▶</div>
                    <div style="font-size:11px">Click to watch</div>
                  </div>
                </div>
                <?php else : ?>
                <div class="nab-video-placeholder" data-tab-open="education">
                  <div class="nab-play-btn">▶</div>
                  <div>Click to start learning</div>
                </div>
                <?php endif; ?>
              </div>
              <div class="nab-featured-info">
                <div class="nab-featured-label">⭐ Featured Training Video</div>
                <div class="nab-featured-title"><?php echo esc_html($featured_module['title']); ?></div>
                <div class="nab-featured-desc">
                  <?php echo esc_html( $featured_module['overview'] ?? 'Watch this module to improve your credit knowledge and earn points.' ); ?>
                </div>
                <div class="nab-featured-meta">
                  <span class="nab-featured-meta-item">⏱ <?php echo esc_html($featured_module['duration']); ?></span>
                  <span class="nab-featured-meta-item">🎓 <?php echo esc_html($featured_module['category']); ?></span>
                </div>
                <div class="nab-featured-progress">
                  <div style="font-size:11px;color:#64748b;margin-bottom:4px">
                    <?php echo $completed_count; ?> of <?php echo $total_modules; ?> modules completed
                  </div>
                  <div class="nab-featured-progress-bar">
                    <div class="nab-featured-progress-fill" style="width:<?php echo $progress_pct; ?>%"></div>
                  </div>
                </div>
                <a href="<?php echo esc_url(nab_resolve_url(get_field('nab_link_learning', nab_get_dash_page_id()))); ?>" class="nab-btn-continue">▶ Continue Watching</a>
                <a href="<?php echo esc_url(nab_resolve_url(get_field('nab_link_learning', nab_get_dash_page_id()))); ?>" class="nab-btn-view-all">View All Videos</a>
              </div>
            </div>
          </div>

          <!-- Right: Score + Learning Progress stacked -->
          <div>
            <!-- Learning Progress -->
            <div class="nab-learn-progress">
              <div class="nab-learn-progress-title">
                🎓 Learning Progress
                <a href="<?php echo esc_url(nab_resolve_url(get_field('nab_link_learning', nab_get_dash_page_id()))); ?>">View My Learning</a>
              </div>
              <div style="display:flex;align-items:center;gap:16px">
                <div class="nab-progress-circle">
                  <svg width="70" height="70" viewBox="0 0 70 70">
                    <circle cx="35" cy="35" r="28" fill="none" stroke="#f1f5f9" stroke-width="6"/>
                    <circle cx="35" cy="35" r="28" fill="none" stroke="#0D5C9B" stroke-width="6"
                      stroke-linecap="round"
                      stroke-dasharray="<?php echo round(2*3.14159*28); ?>"
                      stroke-dashoffset="<?php echo round(2*3.14159*28 * (1 - $progress_pct/100)); ?>"
                      style="transition:.6s"/>
                  </svg>
                  <div class="nab-progress-pct"><?php echo $progress_pct; ?>%</div>
                </div>
                <div>
                  <div style="font-size:13px;font-weight:700;color:#1e293b">You've completed <?php echo $completed_count; ?> of <?php echo $total_modules; ?> lessons</div>
                  <div style="font-size:11px;color:#64748b;margin-top:2px">Keep going! You're on your way to better credit.</div>
                  <div style="font-size:11px;color:#94a3b8;margin-top:4px"><?php echo $completed_count; ?> / <?php echo $total_modules; ?> Lessons</div>
                </div>
              </div>
              <?php if ($next_module_id <= $total_modules) : ?>
              <div class="nab-learn-next">
                <div class="nab-learn-next-label">Next Lesson</div>
                <div class="nab-learn-next-title"><?php echo esc_html($edu_modules[$next_module_id]['title']); ?></div>
                <div class="nab-learn-next-sub"><?php echo esc_html($edu_modules[$next_module_id]['category']); ?></div>
                <a href="<?php echo esc_url(nab_resolve_url(get_field('nab_link_learning', nab_get_dash_page_id()))); ?>" style="font-size:12px;color:#0D5C9B;font-weight:700;text-decoration:none;margin-top:6px;display:inline-block">▶ Continue Learning →</a>
              </div>
              <?php else : ?>
              <div class="nab-learn-next" style="text-align:center">
                <div style="font-size:24px;margin-bottom:6px">🏆</div>
                <div style="font-size:13px;font-weight:700;color:#166534">All modules complete!</div>
              </div>
              <?php endif; ?>
            </div>
          </div>

        </div><!-- /nab-dash-two-col -->

        <!-- My Financial Journey -->
        <div class="nab-section-heading">My Financial Journey</div>
        <div class="nab-journey-grid">
          <a href="<?php echo esc_url(nab_resolve_url(get_field('nab_link_learning', nab_get_dash_page_id()))); ?>" class="nab-journey-card">
            <div class="nab-journey-card-icon">🎓</div>
            <div class="nab-journey-card-title">Learning Center</div>
            <div class="nab-journey-card-desc">Access the 7-part credit education series</div>
          </a>
          <?php
          $roadmap_url = nab_resolve_url( $link_roadmap );
          $roadmap_dis = ( $roadmap_url === '#' );
          ?>
          <a href="<?php echo $roadmap_dis ? 'javascript:void(0)' : esc_url( $roadmap_url ); ?>" class="nab-journey-card" <?php echo $roadmap_dis ? 'style="cursor:default;opacity:.6" aria-disabled="true"' : ''; ?>>
            <div class="nab-journey-card-icon">🗺</div>
            <div class="nab-journey-card-title">Financial Roadmap</div>
            <div class="nab-journey-card-desc"><?php echo $roadmap_dis ? 'Personalized action plan — link the Roadmap page in Dashboard settings' : esc_html( $roadmap_progress['done'] . ' of ' . $roadmap_progress['total'] . ' steps complete' ); ?></div>
          </a>
          <a href="<?php echo esc_url(nab_resolve_url(get_field('nab_link_simulator', nab_get_dash_page_id()) ?: get_page_link(get_page_by_path('score-simulator')))); ?>" class="nab-journey-card">
            <div class="nab-journey-card-icon">📈</div>
            <div class="nab-journey-card-title">Credit Progress Tracker</div>
            <div class="nab-journey-card-desc">Simulate and track score improvements</div>
          </a>
          <a href="<?php echo esc_url(nab_resolve_url(get_field('nab_link_booking', nab_get_dash_page_id()))); ?>" class="nab-journey-card">
            <div class="nab-journey-card-icon">📅</div>
            <div class="nab-journey-card-title">Next Specialist Session</div>
            <div class="nab-journey-card-desc">Book your next credit specialist session</div>
          </a>
          <a href="#" class="nab-journey-card" style="cursor:default;opacity:.6">
            <div class="nab-journey-card-icon">🎯</div>
            <div class="nab-journey-card-title">Goal Tracker</div>
            <div class="nab-journey-card-desc">Home, Car, Business Funding — coming soon</div>
          </a>
        </div>

        <div class="nab-section-heading">Quick Access</div>
        <div class="nab-cards-grid">
        <?php
        $cards = [
          ['#1565C0','#e8f0fe','📄','Credit Report Access',     'Access your credit report through Equifax, TransUnion, Borrowell, or Credit Karma.',         $link_report,  'live', 'link'],
          ['#F57C00','#fff3e0','📅','Book a Credit Specialist', 'Schedule a 1-on-1 session with our certified credit advisors.',                               $link_booking, '',     'link'],
          ['#dc2626','#fee2e2','🛡️','Credit Dispute Center',   'Upload supporting documents and track your dispute status.',                                   $link_dispute, '',     'link'],
          ['#7c3aed','#ede9fe','🤖','NAB AI Chatbot',           'Exclusive access to our intelligent credit chatbot for NAB members.',                         $link_chatbot, '',     'link'],
          ['#0891b2','#e0f2fe','📊','Utilization Checker',      'Visualize your credit utilization and get guidance based on your inputs.',                    $link_util,    '',     'link'],
          ['#059669','#d1fae5','📈','Credit Score Simulator',   "Simulate 'what-if' scenarios for your credit score range.",                                   $link_sim,     '',     'link'],
          ['#1e40af','#dbeafe','🚗','Auto Loan Matcher',        'Find auto loan offers from Canadian lenders — powered by LoanConnect.',                       $link_loan,    'new',  'link'],
          ['#7c2d92','#f3e8ff','💳','Credit Card Matcher',      'Answer 4 questions and get matched to the best Canadian credit card for your profile.',        $link_cards,   'new',  'link'],
          ['#d97706','#fef3c7','📚','Credit Education Blog',    'Browse articles and tips on credit rebuilding — read right here on your dashboard.',          null,          '',     'blog'],
          ['#be185d','#fce7f3','📄','DIY Dispute Letter',  'Download ready-to-use dispute letter templates — available right here on your dashboard.',         null,          '',     'diy' ],
          ['#0d9488','#ccfbf1','💰','Emergency Fund Planner',  'Set a savings goal and track your progress toward financial security.',                      $link_ef,      '',     'link'],
        ];
        foreach ( $cards as $c ) :
          list( $accent, $iconbg, $emoji, $title, $desc, $link, $badge, $action ) = $c;
          if ( $action === 'blog' || $action === 'diy' ) : ?>
          <div class="nab-feat-card" data-tab-open="<?php echo esc_attr($action); ?>"
               style="--ncard-accent:<?php echo esc_attr($accent); ?>;--ncard-iconbg:<?php echo esc_attr($iconbg); ?>"
               role="button" tabindex="0" onkeydown="if(event.key==='Enter')nabOpenTab('<?php echo esc_attr($action); ?>')">
            <div class="nab-card-icon"><?php echo $emoji; ?></div>
            <div class="nab-card-body">
              <div class="nab-card-title"><?php echo esc_html($title); ?></div>
              <div class="nab-card-desc"><?php echo esc_html($desc); ?></div>
            </div>
            <div class="nab-card-arrow">→</div>
          </div>
          <?php else :
            $url = nab_resolve_url($link); $dis = ($url === '#'); $sb = $dis ? 'soon' : $badge; ?>
          <a class="nab-feat-card<?php echo $dis ? ' nab-card-disabled' : ''; ?>"
             href="<?php echo $dis ? 'javascript:void(0)' : esc_url($url); ?>"
             style="--ncard-accent:<?php echo esc_attr($accent); ?>;--ncard-iconbg:<?php echo esc_attr($iconbg); ?>"
             <?php echo $dis ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
            <?php if ($sb) : ?><span class="nab-badge nab-badge-<?php echo esc_attr($sb); ?>"><?php echo strtoupper($sb); ?></span><?php endif; ?>
            <div class="nab-card-icon"><?php echo $emoji; ?></div>
            <div class="nab-card-body">
              <div class="nab-card-title"><?php echo esc_html($title); ?></div>
              <div class="nab-card-desc"><?php echo esc_html($desc); ?></div>
            </div>
            <div class="nab-card-arrow">→</div>
          </a>
          <?php endif; ?>
        <?php endforeach; ?>
        </div>
      </div><!-- /tab overview -->

      <!-- ══ TAB: MY PROFILE ══════════════════════════════ -->
      <div class="nab-tab-panel" id="nabTab-profile" role="tabpanel">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="nab-profile-grid">

          <!-- Native Profile Form (v1.6.2 - UM removed) -->
          <div class="nab-profile-wrap" style="grid-column:1/-1">
            <div style="font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.07em;margin-bottom:20px">👤 My Profile</div>

            <?php
            // Process save
            $nab_profile_msg = '';
            $nab_profile_err = '';
            if ( isset($_POST['nab_dash_profile_save']) && wp_verify_nonce($_POST['nab_dash_profile_nonce'],'nab_dash_profile') ) {
                $fn = sanitize_text_field($_POST['nab_fn'] ?? '');
                $ln = sanitize_text_field($_POST['nab_ln'] ?? '');
                $ph = sanitize_text_field($_POST['nab_ph'] ?? '');
                $gl = sanitize_text_field($_POST['nab_gl'] ?? '');
                $tr = sanitize_text_field($_POST['nab_tr'] ?? '');
                $pw = $_POST['nab_pw'] ?? '';
                $pw2 = $_POST['nab_pw2'] ?? '';
                if ( !$fn || !$ln ) {
                    $nab_profile_err = 'First and last name are required.';
                } elseif ( $pw && strlen($pw) < 8 ) {
                    $nab_profile_err = 'New password must be at least 8 characters.';
                } elseif ( $pw && $pw !== $pw2 ) {
                    $nab_profile_err = 'Passwords do not match.';
                } else {
                    update_user_meta($uid,'nab_first_name',$fn);
                    update_user_meta($uid,'nab_last_name',$ln);
                    update_user_meta($uid,'nab_phone',$ph);
                    update_user_meta($uid,'nab_credit_goal',$gl);
                    update_user_meta($uid,'nab_track',$tr);
                    wp_update_user(['ID'=>$uid,'first_name'=>$fn,'last_name'=>$ln,'display_name'=>trim("$fn $ln")]);
                    if ( $pw ) wp_set_password($pw,$uid);
                    $nab_profile_msg = 'Profile saved successfully!';
                }
            }
            $pf_first = get_user_meta($uid,'nab_first_name',true) ?: $user->first_name;
            $pf_last  = get_user_meta($uid,'nab_last_name',true)  ?: $user->last_name;
            $pf_phone = get_user_meta($uid,'nab_phone',true);
            $pf_goal  = get_user_meta($uid,'nab_credit_goal',true);
            $pf_track = get_user_meta($uid,'nab_track',true);
            $pf_email = $user->user_email;
            ?>

            <?php if ($nab_profile_msg): ?>
            <div style="background:#dcfce7;color:#166534;border-left:4px solid #166534;padding:11px 16px;border-radius:6px;font-size:13px;font-weight:600;margin-bottom:18px;">
                ✅ <?php echo esc_html($nab_profile_msg); ?>
            </div>
            <?php endif; ?>
            <?php if ($nab_profile_err): ?>
            <div style="background:#fee2e2;color:#c62828;border-left:4px solid #c62828;padding:11px 16px;border-radius:6px;font-size:13px;font-weight:600;margin-bottom:18px;">
                ⚠️ <?php echo esc_html($nab_profile_err); ?>
            </div>
            <?php endif; ?>

            <form method="post" id="nabProfileForm">
                <?php wp_nonce_field('nab_dash_profile','nab_dash_profile_nonce'); ?>
                <input type="hidden" name="nab_dash_profile_save" value="1">

                <div class="nab-profile-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px">First Name <span style="color:#e53e3e">*</span></label>
                        <input type="text" name="nab_fn" value="<?php echo esc_attr($pf_first); ?>" required
                               style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box"
                               onfocus="this.style.borderColor='#0D5C9B'" onblur="this.style.borderColor='#e2e8f0'">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px">Last Name <span style="color:#e53e3e">*</span></label>
                        <input type="text" name="nab_ln" value="<?php echo esc_attr($pf_last); ?>" required
                               style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box"
                               onfocus="this.style.borderColor='#0D5C9B'" onblur="this.style.borderColor='#e2e8f0'">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px">Email Address</label>
                        <input type="email" value="<?php echo esc_attr($pf_email); ?>" disabled
                               style="width:100%;padding:10px 13px;border:1.5px solid #f1f5f9;border-radius:8px;font-size:13px;background:#f8fafc;color:#94a3b8;box-sizing:border-box"
                               title="Email cannot be changed here">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px">Phone Number</label>
                        <input type="tel" name="nab_ph" value="<?php echo esc_attr($pf_phone); ?>"
                               style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box"
                               onfocus="this.style.borderColor='#0D5C9B'" onblur="this.style.borderColor='#e2e8f0'">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px">Credit Goal</label>
                        <select name="nab_gl" style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;background:#fff">
                            <option value="">Select goal</option>
                            <?php foreach(['buy_home'=>'Buy a Home','buy_car'=>'Buy a Car','credit_card'=>'Get a Credit Card','improve'=>'Improve Credit Score','dispute'=>'Dispute Errors'] as $v=>$l): ?>
                            <option value="<?php echo esc_attr($v); ?>"<?php selected($pf_goal,$v); ?>><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px">Education Track</label>
                        <select name="nab_tr" style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;background:#fff">
                            <option value="">Select track</option>
                            <?php foreach(['renter'=>'🏠 Renter','student'=>'🎓 Student','immigrant'=>'✈️ New to Canada'] as $v=>$l): ?>
                            <option value="<?php echo esc_attr($v); ?>"<?php selected($pf_track,$v); ?>><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="border-top:1px solid #f1f5f9;padding-top:16px;margin-bottom:14px">
                    <div style="font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px">Change Password <span style="font-weight:400;text-transform:none;letter-spacing:0">(leave blank to keep current)</span></div>
                    <div class="nab-profile-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px">New Password</label>
                            <input type="password" name="nab_pw" autocomplete="new-password" minlength="8"
                                   style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box"
                                   onfocus="this.style.borderColor='#0D5C9B'" onblur="this.style.borderColor='#e2e8f0'"
                                   placeholder="Min. 8 characters">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px">Confirm Password</label>
                            <input type="password" name="nab_pw2" autocomplete="new-password"
                                   style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box"
                                   onfocus="this.style.borderColor='#0D5C9B'" onblur="this.style.borderColor='#e2e8f0'"
                                   placeholder="Repeat new password">
                        </div>
                    </div>
                </div>

                <button type="submit"
                        style="background:#0D5C9B;color:#fff;border:none;border-radius:8px;padding:12px 28px;font-size:14px;font-weight:700;cursor:pointer;transition:.15s"
                        onmouseover="this.style.background='#0a4a7c'" onmouseout="this.style.background='#0D5C9B'">
                    Save Profile
                </button>
            </form>
          </div>

          <!-- ── Membership Status Card ── -->
          <div style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.06)">
            <div style="font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.07em;margin-bottom:16px">💳 Membership</div>

            <?php if ( $mp_data && $mp_data['has_subscription'] ) : ?>
            <div style="display:flex;flex-direction:column;gap:12px">
              <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:12px;border-bottom:1px solid #f1f5f9">
                <span style="font-size:13px;color:#64748b">Plan</span>
                <span style="font-size:13px;font-weight:700;color:#1e293b"><?php echo esc_html($payment_status); ?></span>
              </div>
              <?php if ($plan_price) : ?>
              <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:12px;border-bottom:1px solid #f1f5f9">
                <span style="font-size:13px;color:#64748b">Amount</span>
                <span style="font-size:13px;font-weight:700;color:#1e293b"><?php echo esc_html($plan_price); ?></span>
              </div>
              <?php endif; ?>
              <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:12px;border-bottom:1px solid #f1f5f9">
                <span style="font-size:13px;color:#64748b">Status</span>
                <span style="font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px;
                  background:<?php echo $is_paused?'#fef9c3':($is_suspended?'#fee2e2':'#dcfce7'); ?>;
                  color:<?php echo $is_paused?'#854d0e':($is_suspended?'#991b1b':'#166534'); ?>">
                  ● <?php echo esc_html($member_status); ?>
                </span>
              </div>
              <?php if ($member_since) : ?>
              <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:12px;border-bottom:1px solid #f1f5f9">
                <span style="font-size:13px;color:#64748b">Member Since</span>
                <span style="font-size:13px;font-weight:600;color:#1e293b"><?php echo esc_html($member_since); ?></span>
              </div>
              <?php endif; ?>
              <?php if ($next_billing && !$is_suspended) : ?>
              <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:12px;border-bottom:1px solid #f1f5f9">
                <span style="font-size:13px;color:#64748b">Next Billing</span>
                <span style="font-size:13px;font-weight:600;color:#1e293b"><?php echo esc_html($next_billing); ?></span>
              </div>
              <?php endif; ?>
              <?php if ($is_paused && $resume_date) : ?>
              <div style="background:#fef9c3;border-radius:8px;padding:10px 12px;font-size:12px;color:#854d0e">
                ⏸ Paused — resumes <?php echo esc_html(date('M j, Y',strtotime($resume_date))); ?>
              </div>
              <?php endif; ?>
            </div>

            <?php if (!$is_suspended) : ?>
            <!-- Pause / Cancel -->
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9">
              <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px">Manage Membership</div>

              <?php if (!$is_paused) : ?>
              <!-- Pause -->
              <div style="margin-bottom:12px">
                <div style="font-size:12px;color:#64748b;margin-bottom:8px">Pause billing temporarily:</div>
                <div style="display:flex;gap:8px">
                  <button data-mp-action="pause" data-plan="2" class="nab-mp-pause-btn" type="button">⏸ Pause 2 Weeks</button>
                  <button data-mp-action="pause" data-plan="4" class="nab-mp-pause-btn" type="button">⏸ Pause 4 Weeks</button>
                </div>
              </div>
              <?php else : ?>
              <div style="font-size:12px;color:#64748b;margin-bottom:12px">Your membership is currently paused and will resume automatically.</div>
              <?php endif; ?>

              <!-- Cancel -->
              <div id="nabCancelSection">
                <button data-show="nabCancelForm" type="button"
                  style="background:none;border:1px solid #fca5a5;color:#ef4444;border-radius:8px;padding:8px 14px;font-size:12px;font-weight:600;cursor:pointer;width:100%;transition:.15s">
                  ❌ Cancel Membership
                </button>
                <div id="nabCancelForm" style="display:none;margin-top:12px">
                  <div style="font-size:12px;color:#64748b;margin-bottom:8px">Please tell us why you're leaving (optional):</div>
                  <textarea id="nabCancelReason" rows="3" placeholder="Your feedback helps us improve…"
                    style="width:100%;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box"></textarea>
                  <div style="display:flex;gap:8px;margin-top:10px">
                    <button data-mp-action="cancel" type="button"
                      style="flex:1;background:#ef4444;color:#fff;border:none;border-radius:8px;padding:10px;font-size:13px;font-weight:700;cursor:pointer">
                      Confirm Cancel
                    </button>
                    <button data-hide="nabCancelForm" type="button" class="nab-btn-cancel-hide">✕ Cancel</button>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <?php else : ?>
            <!-- Plan cards (no active membership) -->
            <?php
            $nab_tiers = [
                'basic'    => [ 'label'=>'Basic',    'features'=>'Credit Score &bull; Dispute Templates &bull; Education' ],
                'standard' => [ 'label'=>'Standard', 'features'=>'Everything in Basic &bull; Loan Tool &bull; Badges' ],
                'premium'  => [ 'label'=>'Premium',  'features'=>'Everything in Standard &bull; AI Assistant &bull; Priority Support' ],
            ];
            foreach( $nab_tiers as $tier => $info ) :
                $pid  = nab_mp_plan_id($tier);
                $purl = $pid ? get_permalink($pid) : nab_mp_plans_url();
                $pr   = nab_mp_price_label($tier);
            ?>
            <div style="background:#f8fafc;border-radius:10px;padding:14px 16px;margin-bottom:10px;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
                <div>
                    <div style="font-size:14px;font-weight:700;color:#1e293b;margin-bottom:3px"><?php echo esc_html($info['label']); ?></div>
                    <div style="font-size:11px;color:#64748b"><?php echo $info['features']; ?></div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:14px;font-weight:800;color:#0D5C9B;margin-bottom:6px"><?php echo esc_html($pr); ?></div>
                    <a href="<?php echo esc_url($purl); ?>" style="background:#0D5C9B;color:#fff;padding:7px 14px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:700">Get Started</a>
                </div>
            </div>
            <?php endforeach; ?>
            <div style="text-align:center;margin-top:8px"><a href="<?php echo esc_url(nab_mp_plans_url()); ?>" style="font-size:12px;color:#0D5C9B;font-weight:600">Compare all plans &rarr;</a></div>
            <?php endif; ?>
          </div>

          <!-- ── Billing History Card ── -->
          <?php if (!empty($recent_txns)) : ?>
          <div style="background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.06)">
            <div style="font-size:13px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.07em;margin-bottom:16px">🧾 Recent Billing</div>
            <table style="width:100%;border-collapse:collapse;font-size:12px">
              <thead>
                <tr style="border-bottom:2px solid #f1f5f9">
                  <th style="text-align:left;padding:6px 0;color:#94a3b8;font-weight:600">Date</th>
                  <th style="text-align:right;padding:6px 0;color:#94a3b8;font-weight:600">Amount</th>
                  <th style="text-align:right;padding:6px 0;color:#94a3b8;font-weight:600">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent_txns as $txn) : ?>
                <tr style="border-bottom:1px solid #f8fafc">
                  <td style="padding:8px 0;color:#374151"><?php echo esc_html($txn['date']); ?></td>
                  <td style="padding:8px 0;text-align:right;font-weight:700;color:#1e293b"><?php echo esc_html($txn['amount']); ?></td>
                  <td style="padding:8px 0;text-align:right">
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;
                      background:<?php echo $txn['status']==='complete'?'#dcfce7':'#f1f5f9'; ?>;
                      color:<?php echo $txn['status']==='complete'?'#166534':'#64748b'; ?>">
                      <?php echo esc_html(ucfirst($txn['status'])); ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>

        </div><!-- /profile-grid -->

        <!-- MP action result message -->
        <div id="nabMpMsg" style="display:none;margin-top:16px;padding:14px 18px;border-radius:10px;font-size:13px;font-weight:600"></div>

        <style>
        .nab-profile-grid{grid-template-columns:1fr 1fr}
        .nab-mp-pause-btn{padding:8px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;color:#374151;transition:.15s;flex:1}
        .nab-mp-pause-btn:hover{background:#fef9c3;border-color:#fde047;color:#854d0e}
        @media(max-width:700px){.nab-profile-grid{grid-template-columns:1fr!important}}
        </style>

        <script>
        function nabMpPause(weeks){
          if(!confirm('Pause your membership for '+weeks+' weeks? Billing will stop and resume automatically.')) return;
          nabMpAction('nab_mp_pause','weeks='+weeks,'⏸ Membership paused for '+weeks+' weeks.');
        }
        function nabMpCancel(){
          var reason=document.getElementById('nabCancelReason').value;
          if(!confirm('Are you sure you want to cancel your membership? This cannot be undone.')) return;
          nabMpAction('nab_mp_cancel','reason='+encodeURIComponent(reason),'❌ Membership cancelled. You retain access until your billing period ends.');
        }
        function nabMpAction(action,extra,successMsg){
          if(typeof nabPortal==='undefined') return;
          fetch(nabPortal.ajax,{
            method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action='+action+'&nonce='+nabPortal.nonce+'&'+extra
          }).then(function(r){return r.json();}).then(function(d){
            var msgEl=document.getElementById('nabMpMsg');
            if(d.success){
              msgEl.style.background='#dcfce7';msgEl.style.color='#166534';
              msgEl.textContent=d.data.message||successMsg;
            } else {
              msgEl.style.background='#fee2e2';msgEl.style.color='#991b1b';
              msgEl.textContent=d.data.message||'An error occurred. Please contact support.';
            }
            msgEl.style.display='block';
          }).catch(function(){
            var msgEl=document.getElementById('nabMpMsg');
            msgEl.style.background='#fee2e2';msgEl.style.color='#991b1b';
            msgEl.textContent='Connection error. Please try again.';
            msgEl.style.display='block';
          });
        }
        </script>

      </div><!-- /tab profile -->

      <!-- ══ TAB: EDUCATION BLOG ══════════════════════════ -->
      <div class="nab-tab-panel" id="nabTab-blog" role="tabpanel">
        <?php if ( $blog_posts ) : ?>
        <div class="nab-reader" id="nabBlogReader">

          <!-- Left: post list -->
          <div class="nab-reader-list">
            <div class="nab-reader-list-header">
              <span>📚 Education Blog</span>
              <?php $bext = nab_resolve_url($link_blog); if ($bext !== '#') : ?>
              <a href="<?php echo esc_url($bext); ?>" target="_blank" rel="noopener">View all →</a>
              <?php endif; ?>
            </div>
            <?php foreach ( $blog_posts as $bp ) :
              $bthumb = get_the_post_thumbnail_url( $bp->ID, 'thumbnail' );
              $bdate  = get_the_date( 'M j, Y', $bp );
              $bcats  = get_the_category( $bp->ID );
              $bcat   = ! empty($bcats) ? $bcats[0]->name : 'Blog';
            ?>
            <div class="nab-reader-row" data-pid="<?php echo esc_attr($bp->ID); ?>" data-load-post="<?php echo (int)$bp->ID; ?>" data-load-tab="blog">
              <?php if ($bthumb) : ?>
              <img class="nab-reader-row-thumb" src="<?php echo esc_url($bthumb); ?>" alt="" loading="lazy">
              <?php else : ?>
              <div class="nab-reader-row-thumb-ph blog-ph">📚</div>
              <?php endif; ?>
              <div class="nab-reader-row-body">
                <div class="nab-reader-row-title"><?php echo esc_html( get_the_title($bp) ); ?></div>
                <div class="nab-reader-row-meta"><?php echo esc_html($bcat); ?> · <?php echo esc_html($bdate); ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Right: article view -->
          <div class="nab-reader-article" id="nabBlogArticle">
            <div class="nab-reader-placeholder">
              <div class="nab-reader-placeholder-icon">📚</div>
              <div class="nab-reader-placeholder-text">Select an article from the list to read it here.</div>
            </div>
          </div>

        </div>
        <?php else : ?>
        <div class="nab-no-posts">
          📚 No blog posts yet.<br>
          <?php if ( current_user_can('publish_posts') ) : ?>
          <small>Publish posts in the "<strong><?php echo esc_html($blog_cat_slug); ?></strong>" category, or change the slug in ACF → Dashboard → Blog Category Slug.</small>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div><!-- /tab blog -->

      <!-- ══ TAB: DIY DISPUTE LETTER ═══════════════════════════
           v1.6.8: replaced the old blog-post reader (DIY Guide) with
           direct downloadable dispute letter templates. Blog posts
           that used to live here have been merged into the Educational
           Blog tab above. Templates are shared with the Dispute Center
           page via nab_get_dispute_templates(). ═══════════════════ -->
      <div class="nab-tab-panel" id="nabTab-diy" role="tabpanel">
        <?php $diy_templates = function_exists('nab_get_dispute_templates') ? nab_get_dispute_templates() : []; ?>
        <?php if ( $diy_templates ) : ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
          <?php foreach ( $diy_templates as $tpl ) : ?>
          <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;display:flex;flex-direction:column">
            <div style="height:4px;background:<?php echo esc_attr($tpl['color']); ?>"></div>
            <div style="padding:16px;flex:1;display:flex;flex-direction:column;gap:8px">
              <?php if ($tpl['badge']) : ?>
              <span style="align-self:flex-start;font-size:11px;font-weight:700;padding:3px 8px;border-radius:999px;background:<?php echo esc_attr($tpl['color']); ?>22;color:<?php echo esc_attr($tpl['color']); ?>"><?php echo esc_html($tpl['badge']); ?></span>
              <?php endif; ?>
              <div style="font-weight:700;font-size:14px;color:#1e293b"><?php echo esc_html($tpl['title']); ?></div>
              <div style="font-size:12.5px;color:#64748b;flex:1"><?php echo esc_html($tpl['desc']); ?></div>
              <a href="<?php echo esc_url($tpl['file']); ?>" target="_blank" rel="noopener" download
                 style="margin-top:8px;text-align:center;padding:9px 12px;border-radius:8px;background:<?php echo esc_attr($tpl['color']); ?>;color:#fff;font-size:13px;font-weight:600;text-decoration:none">
                📄 Download PDF
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div class="nab-no-posts">📄 No dispute letter templates available yet.</div>
        <?php endif; ?>
      </div><!-- /tab diy -->

    </div><!-- /nab-content -->
  </main>
</div><!-- /nab-portal-wrap -->

<!-- ══ v1.9.0 MANUAL ENTRY SHEETS (modal on desktop, bottom sheet on mobile) ══ -->
<div class="nd-modal" id="ndModal" hidden>
  <div class="nd-modal-backdrop" data-nd-close></div>
  <div class="nd-sheet" role="dialog" aria-modal="true" aria-labelledby="ndSheetTitle">
    <div class="nd-sheet-grip" aria-hidden="true"></div>
    <header class="nd-sheet-head">
      <h3 id="ndSheetTitle">Add data</h3>
      <button type="button" class="nd-sheet-x" data-nd-close aria-label="Close">✕</button>
    </header>
    <div class="nd-sheet-body">

      <!-- Quick add chooser -->
      <div class="nd-pane" data-pane="quick" data-title="What would you like to add?">
        <div class="nd-quick">
          <?php if ( $can_self_score ) : ?>
          <button type="button" data-nd-open="score"><span>📈</span><b>Credit score</b><small>Log today's or a past score</small></button>
          <?php endif; ?>
          <button type="button" data-nd-open="cashflow"><span>💸</span><b>Monthly income &amp; expenses</b><small>Powers Cash Flow and Spending</small></button>
          <button type="button" data-nd-open="account"><span>🏦</span><b>Account or debt</b><small>Powers your Net Worth</small></button>
        </div>
      </div>

      <!-- Credit score -->
      <form class="nd-pane nd-form" data-pane="score" data-title="Log a credit score" data-action="nab_dash_score_add" novalidate>
        <div class="nd-row2">
          <label>Score<input type="number" name="score" min="300" max="900" inputmode="numeric" placeholder="e.g. 712" required></label>
          <label>Date<input type="date" name="date" max="<?php echo esc_attr( $dash_data['today'] ); ?>" value="<?php echo esc_attr( $dash_data['today'] ); ?>" required></label>
        </div>
        <p class="nd-hint">Find your score free on Borrowell or Credit Karma. One entry per day — saving the same date again replaces it.</p>
        <div class="nd-form-msg" role="status"></div>
        <button type="submit" class="nd-btn nd-btn-primary nd-btn-block">Save score</button>
        <h4 class="nd-list-title">Your score history</h4>
        <ul class="nd-list" data-list="score"></ul>
      </form>

      <!-- Cash flow month -->
      <form class="nd-pane nd-form" data-pane="cashflow" data-title="Monthly income &amp; expenses" data-action="nab_dash_cashflow_save" novalidate>
        <label>Month
          <select name="month">
            <?php foreach ( $cf_months as $mk => $ml ) : ?><option value="<?php echo esc_attr( $mk ); ?>"><?php echo esc_html( $ml ); ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Total income (after tax)
          <span class="nd-money"><input type="number" name="income" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></span>
        </label>
        <div class="nd-subhead">Expenses</div>
        <div class="nd-row2">
          <?php foreach ( $dash_data['cats'] as $ck => $cat ) : ?>
          <label><span class="nd-cat-dot" style="background:<?php echo esc_attr( $cat['color'] ); ?>"></span><?php echo esc_html( $cat['label'] ); ?>
            <span class="nd-money"><input type="number" name="exp_<?php echo esc_attr( $ck ); ?>" min="0" step="0.01" inputmode="decimal" placeholder="0.00"></span>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="nd-form-msg" role="status"></div>
        <button type="submit" class="nd-btn nd-btn-primary nd-btn-block">Save month</button>
        <h4 class="nd-list-title">Saved months</h4>
        <ul class="nd-list" data-list="cashflow"></ul>
      </form>

      <!-- Account -->
      <form class="nd-pane nd-form" data-pane="account" data-title="Add an account" data-action="nab_dash_account_save" novalidate>
        <input type="hidden" name="id" value="">
        <label>Account name<input type="text" name="name" maxlength="60" placeholder="e.g. TD Chequing, Car loan" required></label>
        <div class="nd-row2">
          <label>Type
            <select name="type">
              <optgroup label="What you own">
                <?php foreach ( $dash_data['types'] as $tk => $t ) if ( $t['group'] === 'asset' ) : ?><option value="<?php echo esc_attr( $tk ); ?>"><?php echo esc_html( $t['label'] ); ?></option><?php endif; ?>
              </optgroup>
              <optgroup label="What you owe">
                <?php foreach ( $dash_data['types'] as $tk => $t ) if ( $t['group'] === 'debt' ) : ?><option value="<?php echo esc_attr( $tk ); ?>"><?php echo esc_html( $t['label'] ); ?></option><?php endif; ?>
              </optgroup>
            </select>
          </label>
          <label>Current balance<span class="nd-money"><input type="number" name="balance" min="0" step="0.01" inputmode="decimal" placeholder="0.00" required></span></label>
        </div>
        <p class="nd-hint">For debts, enter the amount you owe as a positive number.</p>
        <div class="nd-form-msg" role="status"></div>
        <div class="nd-form-actions">
          <button type="submit" class="nd-btn nd-btn-primary nd-btn-block">Save account</button>
          <button type="button" class="nd-btn nd-btn-block" data-nd-reset hidden>Cancel edit</button>
        </div>
        <h4 class="nd-list-title">Your accounts</h4>
        <ul class="nd-list" data-list="account"></ul>
      </form>

    </div>
  </div>
</div>

<!-- ══ v1.9.0 MOBILE BOTTOM NAV ══ -->
<nav class="nd-bottomnav" aria-label="Quick navigation">
  <button type="button" class="on" data-nd-home><span>🏠</span>Home</button>
  <a href="<?php echo esc_url( $nav_links['learning'] !== '#' ? $nav_links['learning'] : $nav_links['dashboard'] ); ?>"><span>🎓</span>Learn</a>
  <button type="button" class="nd-bn-add" data-nd-open="quick" aria-label="Add data"><span>＋</span></button>
  <?php if ( $nav_links['chatbot'] !== '#' ) : ?>
  <a href="<?php echo esc_url( $nav_links['chatbot'] ); ?>"><span>✨</span>Ask AI</a>
  <?php else : ?>
  <button type="button" data-tab-open="profile"><span>👤</span>Profile</button>
  <?php endif; ?>
  <button type="button" data-nd-menu><span>☰</span>Menu</button>
</nav>

<script>
window.nabDashData = <?php echo wp_json_encode( $dash_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); ?>;
</script>
<?php endif; ?>


<!-- Ask AI floating button -->
<?php
$link_chatbot_url = nab_resolve_url( get_field('nab_link_chatbot') );
if ($link_chatbot_url && $link_chatbot_url !== '#') :
?>
<a href="<?php echo esc_url($link_chatbot_url); ?>" class="nab-ask-ai-btn" title="Ask the NAB AI Assistant">
  <span class="nab-ask-ai-btn-dot"></span>
  ✨ Ask AI
</a>
<?php endif; ?>


<?php nab_portal_footer_js(); ?>

<?php wp_footer(); ?>
</body>
</html>
