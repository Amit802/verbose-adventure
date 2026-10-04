<?php
/**
 * Template Name: NAB Membership Plans
 * Pricing cards for Basic / Standard / Premium + annual toggle + FAQ.
 *
 * @package NAB_Member_Portal
 * @since   1.6.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Auth check ────────────────────────────────────────────
if ( ! is_user_logged_in() ) {
    wp_redirect( wp_login_url( get_permalink() ) ); exit;
}

$tier = nab_get_member_tier();

// IDs pulled from ACF on Dashboard page — no hardcoding
$plans = [
    'basic' => [
        'id'      => nab_mp_plan_id('basic'),
        'name'    => 'Basic',
        'price'   => nab_mp_price_label('basic'),
        'color'   => '#455a64',
        'icon'    => '🌱',
        'tagline' => 'Perfect for getting started',
        'features'=> [ 'Credit Score Dashboard', '3 Dispute Templates / month', 'Basic Education Modules', 'Financial Roadmap', 'Email Support' ],
        'missing' => [ 'AI Credit Assistant', 'Loan Matching Tool', 'Priority Support' ],
    ],
    'standard' => [
        'id'      => nab_mp_plan_id('standard'),
        'name'    => 'Standard',
        'price'   => nab_mp_price_label('standard'),
        'color'   => '#0D5C9B',
        'icon'    => '⚡',
        'tagline' => 'Most popular — best value',
        'badge'   => 'Most Popular',
        'features'=> [ 'Everything in Basic', 'Unlimited Dispute Templates', 'All Education Modules', 'Points, Levels & Badges', 'Loan Matching Tool', 'Rent Reporting Display', 'Priority Support' ],
        'missing' => [ 'AI Credit Assistant', 'Dedicated Account Manager' ],
    ],
    'premium' => [
        'id'      => nab_mp_plan_id('premium'),
        'name'    => 'Premium',
        'price'   => nab_mp_price_label('premium'),
        'color'   => '#b8860b',
        'icon'    => '👑',
        'tagline' => 'Everything, plus AI coaching',
        'features'=> [ 'Everything in Standard', 'AI NAB Assistant (Chatbot)', 'AI Credit Decision Explainer', 'Dedicated Account Manager', 'Emergency Fund Planner', 'Phone & Chat Support', 'Early Access to New Features' ],
        'missing' => [],
    ],
];

nab_head_open( 'Membership Plans — NAB Member Portal' );
?>
<style>
body{background:#F0F4FA!important}
.nab-plans-page{max-width:1060px;margin:0 auto;padding:40px 24px}
.nab-plans-hero{text-align:center;padding:0 0 40px}
.nab-plans-hero h1{font-size:32px;color:#0D5C9B;margin:0 0 10px}
.nab-plans-hero p{font-size:16px;color:#64748b;margin:0 0 24px}
.nab-toggle-row{display:flex;align-items:center;gap:10px;justify-content:center;font-size:14px}
.nab-toggle-lbl{cursor:pointer;color:#64748b;padding:5px 0}
.nab-toggle-lbl.active{color:#0D5C9B;font-weight:700;border-bottom:2px solid #0D5C9B}
.nab-save-tag{background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700}
.nab-plans-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:40px}
.nab-plan-card{background:#fff;border-radius:14px;padding:32px 26px;box-shadow:0 2px 16px rgba(0,0,0,.07);position:relative;border-top:4px solid var(--pc,#0D5C9B);transition:.2s}
.nab-plan-card:hover{transform:translateY(-3px);box-shadow:0 6px 32px rgba(0,0,0,.1)}
.nab-plan-card.featured{border-top-width:6px;transform:scale(1.03)}
.nab-plan-card.featured:hover{transform:scale(1.03) translateY(-3px)}
.nab-plan-top-badge{position:absolute;top:-13px;left:50%;transform:translateX(-50%);background:#0D5C9B;color:#fff;padding:3px 14px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;white-space:nowrap}
.nab-plan-top-badge.current{background:#166534}
.nab-plan-icon{font-size:34px;margin-bottom:10px}
.nab-plan-card h2{font-size:20px;color:#1e293b;margin:0 0 4px}
.nab-plan-card .tagline{font-size:13px;color:#64748b;margin:0 0 18px}
.nab-price-row{display:flex;align-items:baseline;gap:2px;margin-bottom:4px}
.nab-price-cur{font-size:18px;color:#0D5C9B;font-weight:700}
.nab-price-amt{font-size:44px;font-weight:800;color:#0D5C9B;line-height:1}
.nab-price-per{font-size:13px;color:#94a3b8;margin-left:4px}
.nab-annual-note{font-size:11px;color:#64748b;margin-bottom:18px}
.nab-plan-features{list-style:none;padding:0;margin:16px 0 24px;border-top:1px solid #f1f5f9;padding-top:16px}
.nab-plan-features li{padding:7px 0;font-size:13px;border-bottom:1px solid #f8fafc;display:flex;gap:8px}
.nab-feat-yes{color:#1e293b}
.nab-feat-no{color:#cbd5e1}
.nab-plan-cta .nab-btn-plan{display:block;text-align:center;padding:13px;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none;transition:.2s}
.nab-plan-cta .nab-btn-plan.primary{background:#0D5C9B;color:#fff}
.nab-plan-cta .nab-btn-plan.primary:hover{background:#0a4a7c}
.nab-plan-cta .nab-btn-plan.outline{border:2px solid #0D5C9B;color:#0D5C9B}
.nab-trust-row{display:flex;justify-content:center;gap:28px;flex-wrap:wrap;padding:20px;background:#e8f0fb;border-radius:10px;margin-bottom:48px;font-size:13px;color:#0D5C9B;font-weight:600}
.nab-faq-section h2{text-align:center;color:#0D5C9B;margin:0 0 28px}
.nab-faq-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.nab-faq-item{background:#fff;border-radius:10px;padding:20px;box-shadow:0 1px 6px rgba(0,0,0,.06)}
.nab-faq-item h4{margin:0 0 6px;color:#1e293b;font-size:14px}
.nab-faq-item p{margin:0;color:#64748b;font-size:13px;line-height:1.6}
@media(max-width:768px){
  .nab-plans-grid{grid-template-columns:1fr}
  .nab-faq-grid{grid-template-columns:1fr}
  .nab-plan-card.featured{transform:none}
}
.nab-hidden{display:none!important}
</style>
</head>
<body <?php body_class('nab-portal-page'); ?>>

<div class="nab-plans-page">

    <div class="nab-plans-hero">
        <h1>Choose Your Plan</h1>
        <p>Join thousands of Canadians repairing their credit with NAB Solutions</p>
        <div class="nab-toggle-row">
            <span class="nab-toggle-lbl active" data-period="monthly">Monthly</span>
            <span style="color:#cbd5e1">/</span>
            <span class="nab-toggle-lbl" data-period="annual">Annual <span class="nab-save-tag">Save 20%</span></span>
        </div>
    </div>

    <div class="nab-plans-grid">
    <?php foreach ( $plans as $key => $plan ):
        $is_current = ($tier === $key);
        $reg_url    = $plan['id'] ? get_permalink($plan['id']) : nab_mp_plans_url();
        // Extract numeric price from label e.g. "$19/month" -> 19
        $price_num  = (int) preg_replace('/[^0-9]/', '', $plan['price']);
        $ann_total  = round($price_num * 12 * 0.8);
        $ann_mo     = round($ann_total / 12);
    ?>
        <div class="nab-plan-card <?php echo isset($plan['badge']) ? 'featured' : ''; ?>"
             style="--pc:<?php echo esc_attr($plan['color']); ?>">

            <?php if ($is_current): ?>
                <div class="nab-plan-top-badge current">Your Plan</div>
            <?php elseif (isset($plan['badge'])): ?>
                <div class="nab-plan-top-badge"><?php echo esc_html($plan['badge']); ?></div>
            <?php endif; ?>

            <div class="nab-plan-icon"><?php echo $plan['icon']; ?></div>
            <h2><?php echo esc_html($plan['name']); ?></h2>
            <p class="tagline"><?php echo esc_html($plan['tagline']); ?></p>

            <div class="nab-price-row">
                <span class="nab-price-cur">$</span>
                <span class="nab-price-amt nab-mo-price"><?php echo esc_html($price_num); ?></span>
                <span class="nab-price-amt nab-ann-price nab-hidden"><?php echo esc_html($ann_mo); ?></span>
                <span class="nab-price-per">/ month</span>
            </div>
            <div class="nab-annual-note nab-ann-note nab-hidden">Billed $<?php echo esc_html($ann_total); ?>/year</div>

            <ul class="nab-plan-features">
                <?php foreach ($plan['features'] as $f): ?>
                    <li class="nab-feat-yes">✓ <?php echo esc_html($f); ?></li>
                <?php endforeach; ?>
                <?php foreach ($plan['missing'] as $f): ?>
                    <li class="nab-feat-no">✗ <?php echo esc_html($f); ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="nab-plan-cta">
                <?php if ($is_current): ?>
                    <a href="<?php echo esc_url(home_url('/account/')); ?>" class="nab-btn-plan outline">Manage Plan</a>
                <?php else: ?>
                    <a href="<?php echo esc_url($reg_url); ?>" class="nab-btn-plan primary">
                        <?php echo ($tier && !$is_current) ? 'Upgrade to '.$plan['name'] : 'Get Started'; ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <div class="nab-trust-row">
        <span>🔒 Secure Payments via Stripe</span>
        <span>↩️ Cancel Anytime</span>
        <span>⏸ Pause Anytime</span>
        <span>🇨🇦 Made for Canadians</span>
        <span>💰 7-Day Money-Back Guarantee</span>
    </div>

    <div class="nab-faq-section">
        <h2>Frequently Asked Questions</h2>
        <div class="nab-faq-grid">
            <?php
            $faqs = [
                ['Can I cancel anytime?','Yes. Cancel from your account page anytime. Access continues until the end of your billing period — no penalty.'],
                ['What payments are accepted?','All major credit/debit cards (Visa, Mastercard, Amex) via Stripe. PAD available for annual plans.'],
                ['Can I upgrade or downgrade?','Yes. Upgrades activate instantly. Downgrades take effect at your next billing date.'],
                ['Is my data safe?','All data is encrypted and stored on Canadian servers. We never share your information with third parties.'],
                ['Is there a free trial?','We offer a 7-day money-back guarantee. Not happy? Email us and we\'ll refund in full.'],
                ['What happens to my data if I cancel?','Your data is retained for 90 days after cancellation so you can rejoin without starting over.'],
            ];
            foreach ($faqs as $faq): ?>
            <div class="nab-faq-item">
                <h4><?php echo esc_html($faq[0]); ?></h4>
                <p><?php echo esc_html($faq[1]); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
(function(){
    var mos=document.querySelectorAll('.nab-mo-price'),
        anns=document.querySelectorAll('.nab-ann-price'),
        notes=document.querySelectorAll('.nab-ann-note'),
        btns=document.querySelectorAll('.nab-toggle-lbl');
    btns.forEach(function(btn){
        btn.addEventListener('click',function(){
            btns.forEach(function(b){b.classList.remove('active');});
            btn.classList.add('active');
            if(btn.dataset.period==='annual'){
                mos.forEach(function(el){el.classList.add('nab-hidden');});
                anns.forEach(function(el){el.classList.remove('nab-hidden');});
                notes.forEach(function(el){el.classList.remove('nab-hidden');});
            } else {
                mos.forEach(function(el){el.classList.remove('nab-hidden');});
                anns.forEach(function(el){el.classList.add('nab-hidden');});
                notes.forEach(function(el){el.classList.add('nab-hidden');});
            }
        });
    });
})();
</script>

<?php wp_footer(); ?>
</body>
</html>
