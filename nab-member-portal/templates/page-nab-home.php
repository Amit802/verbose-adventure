<?php
/**
 * Template Name: NAB Member Home
 * Landing page for members.nabsolutions.ca
 * If logged in → redirect to dashboard
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// If already logged in, go straight to dashboard
if ( is_user_logged_in() ) {
    wp_redirect( home_url( '/dashboard/' ) );
    exit;
}

$login_url = wp_login_url( home_url( '/dashboard/' ) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>NAB Solutions Member Portal</title>
<?php wp_head(); ?>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',Arial,sans-serif;background:#f8fafc;color:#1e293b}

/* ── HEADER ── */
.nab-home-header{background:#0D5C9B;padding:0 32px;height:64px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,.2)}
.nab-home-logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.nab-home-logo-icon{width:40px;height:40px;background:#FF6B35;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px}
.nab-home-logo-text{line-height:1.1}
.nab-home-logo-text strong{color:#FF6B35;font-size:16px;font-weight:800;display:block}
.nab-home-logo-text span{color:#fff;font-size:11px;opacity:.8}
.nab-home-header-btn{background:#FF6B35;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;transition:.2s}
.nab-home-header-btn:hover{background:#e55a26;color:#fff}

/* ── HERO ── */
.nab-home-hero{background:linear-gradient(135deg,#0D5C9B 0%,#0a4a7c 60%,#083d6b 100%);padding:80px 32px;text-align:center;color:#fff;position:relative;overflow:hidden}
.nab-home-hero::before{content:'';position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(circle at 30% 50%,rgba(255,107,53,.1) 0%,transparent 60%);pointer-events:none}
.nab-home-badge{display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);color:#fff;padding:6px 16px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:20px;letter-spacing:.5px}
.nab-home-hero h1{font-size:clamp(28px,5vw,48px);font-weight:800;line-height:1.2;margin-bottom:16px;max-width:700px;margin-left:auto;margin-right:auto}
.nab-home-hero p{font-size:16px;opacity:.85;max-width:560px;margin:0 auto 36px;line-height:1.7}
.nab-home-hero-btn{display:inline-block;background:#FF6B35;color:#fff;padding:16px 40px;border-radius:12px;font-size:17px;font-weight:800;text-decoration:none;transition:.2s;box-shadow:0 4px 20px rgba(255,107,53,.4)}
.nab-home-hero-btn:hover{background:#e55a26;transform:translateY(-2px);color:#fff}
.nab-home-hero-sub{margin-top:16px;font-size:13px;opacity:.65}

/* ── TOOLS ── */
.nab-home-tools{padding:64px 32px;background:#fff}
.nab-home-section-label{text-align:center;color:#FF6B35;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:12px}
.nab-home-section-title{text-align:center;font-size:clamp(22px,4vw,32px);font-weight:800;color:#0D5C9B;margin-bottom:8px}
.nab-home-section-sub{text-align:center;color:#64748b;font-size:15px;margin-bottom:48px}
.nab-home-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:1000px;margin:0 auto}
.nab-home-card{background:#f8fafc;border:1.5px solid #e8f0fb;border-radius:16px;padding:24px;transition:.2s;cursor:default}
.nab-home-card:hover{border-color:#0D5C9B;box-shadow:0 4px 20px rgba(13,92,155,.1);transform:translateY(-2px)}
.nab-home-card-icon{font-size:32px;margin-bottom:12px}
.nab-home-card-title{font-size:15px;font-weight:700;color:#0D5C9B;margin-bottom:6px}
.nab-home-card-desc{font-size:13px;color:#64748b;line-height:1.6}

/* ── CTA ── */
.nab-home-cta{background:linear-gradient(135deg,#0D5C9B,#0a4a7c);padding:64px 32px;text-align:center;color:#fff}
.nab-home-cta h2{font-size:clamp(22px,4vw,32px);font-weight:800;margin-bottom:12px}
.nab-home-cta p{opacity:.85;font-size:15px;margin-bottom:32px}
.nab-home-cta-btn{display:inline-block;background:#FF6B35;color:#fff;padding:16px 40px;border-radius:12px;font-size:17px;font-weight:800;text-decoration:none;transition:.2s}
.nab-home-cta-btn:hover{background:#e55a26;transform:translateY(-2px);color:#fff}

/* ── FOOTER ── */
.nab-home-footer{background:#0a3d6b;padding:32px;color:#fff}
.nab-home-footer-inner{max-width:1000px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px}
.nab-home-footer-logo strong{color:#FF6B35;font-size:16px;font-weight:800;display:block}
.nab-home-footer-logo span{color:rgba(255,255,255,.6);font-size:12px}
.nab-home-footer-info{text-align:center;font-size:12px;color:rgba(255,255,255,.6);line-height:1.8}
.nab-home-footer-contact{text-align:right;font-size:12px;color:rgba(255,255,255,.6);line-height:1.8}
.nab-home-footer-bottom{text-align:center;padding-top:20px;margin-top:20px;border-top:1px solid rgba(255,255,255,.1);font-size:11px;color:rgba(255,255,255,.4)}

@media(max-width:768px){
  .nab-home-grid{grid-template-columns:repeat(2,1fr)}
  .nab-home-footer-inner{flex-direction:column;text-align:center}
  .nab-home-footer-contact{text-align:center}
}
@media(max-width:480px){
  .nab-home-grid{grid-template-columns:1fr}
  .nab-home-hero{padding:60px 20px}
}
</style>
</head>
<body>

<!-- HEADER -->
<header class="nab-home-header">
  <a href="<?php echo home_url('/'); ?>" class="nab-home-logo">
    <div class="nab-home-logo-icon">⚙️</div>
    <div class="nab-home-logo-text">
      <strong>NAB Solutions</strong>
      <span>Member Portal</span>
    </div>
  </a>
  <a href="<?php echo esc_url($login_url); ?>" class="nab-home-header-btn">🔐 Member Login</a>
</header>

<!-- HERO -->
<section class="nab-home-hero">
  <div class="nab-home-badge">✨ FOR OUR MEMBERS</div>
  <h1>Welcome to NAB Solutions Member Portal</h1>
  <p>Access your credit tools, education modules and financial resources — all in one place. Your financial journey starts here.</p>
  <a href="<?php echo esc_url($login_url); ?>" class="nab-home-hero-btn">🔐 Login to Your Portal</a>
  <p class="nab-home-hero-sub">Already a member? Click above to access your dashboard.</p>
</section>

<!-- TOOLS GRID -->
<section class="nab-home-tools">
  <div class="nab-home-section-label">What's Inside</div>
  <h2 class="nab-home-section-title">Everything You Need to Build Better Credit</h2>
  <p class="nab-home-section-sub">Your portal gives you access to all the tools and resources you need on your financial journey.</p>

  <div class="nab-home-grid">
    <div class="nab-home-card">
      <div class="nab-home-card-icon">📚</div>
      <div class="nab-home-card-title">Learning Center</div>
      <div class="nab-home-card-desc">Step-by-step credit education modules with videos and quizzes to improve your financial literacy.</div>
    </div>
    <div class="nab-home-card">
      <div class="nab-home-card-icon">📊</div>
      <div class="nab-home-card-title">Utilization Checker</div>
      <div class="nab-home-card-desc">Calculate your credit utilization ratio instantly and get actionable tips to improve your score.</div>
    </div>
    <div class="nab-home-card">
      <div class="nab-home-card-icon">📈</div>
      <div class="nab-home-card-title">Score Simulator</div>
      <div class="nab-home-card-desc">See how financial decisions could impact your credit score before you make them.</div>
    </div>
    <div class="nab-home-card">
      <div class="nab-home-card-icon">🛡️</div>
      <div class="nab-home-card-title">Dispute Center</div>
      <div class="nab-home-card-desc">Download dispute letter templates and submit credit bureau disputes directly from the portal.</div>
    </div>
    <div class="nab-home-card">
      <div class="nab-home-card-icon">🏦</div>
      <div class="nab-home-card-title">Lending Finder Program</div>
      <div class="nab-home-card-desc">Get matched with trusted Canadian lenders. Soft credit check only — no impact to your score.</div>
    </div>
    <div class="nab-home-card">
      <div class="nab-home-card-icon">🤖</div>
      <div class="nab-home-card-title">AI Credit Assistant</div>
      <div class="nab-home-card-desc">Get instant answers to your credit questions 24/7 from our AI-powered credit assistant.</div>
    </div>
    <div class="nab-home-card">
      <div class="nab-home-card-icon">📄</div>
      <div class="nab-home-card-title">Credit Report Access</div>
      <div class="nab-home-card-desc">Access your free credit report from Equifax, TransUnion, Borrowell and CreditKarma Canada.</div>
    </div>
    <div class="nab-home-card">
      <div class="nab-home-card-icon">📅</div>
      <div class="nab-home-card-title">Book a Specialist</div>
      <div class="nab-home-card-desc">Schedule a one-on-one session with a NAB Solutions credit specialist at your convenience.</div>
    </div>
    <div class="nab-home-card">
      <div class="nab-home-card-icon">🎧</div>
      <div class="nab-home-card-title">Support Center</div>
      <div class="nab-home-card-desc">Submit and track support tickets directly with the NAB Solutions team from your portal.</div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="nab-home-cta">
  <h2>Ready to Take Control of Your Credit?</h2>
  <p>Login now and start your financial journey with NAB Solutions today.</p>
  <a href="<?php echo esc_url($login_url); ?>" class="nab-home-cta-btn">Login to Your Portal →</a>
</section>

<!-- FOOTER -->
<footer class="nab-home-footer">
  <div class="nab-home-footer-inner">
    <div class="nab-home-footer-logo">
      <strong>NAB Solutions</strong>
      <span>Member Portal</span>
    </div>
    <div class="nab-home-footer-info">
      NAB Solutions Ltd.<br>
      Suite 290, 6815-8 Street NE<br>
      Calgary, Alberta T2E 7H7, Canada
    </div>
    <div class="nab-home-footer-contact">
      admin@nabsolutions.ca<br>
      1-855-542-6078<br>
      nabsolutions.ca
    </div>
  </div>
  <div class="nab-home-footer-bottom">
    © 2026 NAB Solutions Ltd. All Rights Reserved.
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
