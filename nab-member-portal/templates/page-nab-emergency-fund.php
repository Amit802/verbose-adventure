<?php
/**
 * Template Name: NAB Emergency Fund Planner
 * Member enters a monthly savings amount + savings goal, tracks progress
 * with a progress bar, gets milestone messages at 25/50/75/100%, and
 * sees a short static tips section.
 *
 * Client spec (Matt, v1.7.4): "The member enters their monthly savings
 * amount and sets a savings goal. The tool calculates how long it will
 * take to reach the goal and shows a progress bar. When they hit 25, 50,
 * 75 and 100 percent they get a congratulations message. Also includes
 * a short tips section with savings advice."
 *
 * @package NAB_Member_Portal
 * @since   1.7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user    = wp_get_current_user();
$uid     = $user->ID;
$monthly = (float) ( get_user_meta( $uid, 'nab_ef_monthly', true ) ?: 0 );
$goal    = (float) ( get_user_meta( $uid, 'nab_ef_goal',    true ) ?: 0 );
$saved   = (float) ( get_user_meta( $uid, 'nab_ef_saved',   true ) ?: 0 );

$percent = $goal > 0 ? min( 100, round( ( $saved / $goal ) * 100 ) ) : 0;
$months  = ( $monthly > 0 && $goal > $saved ) ? ceil( ( $goal - $saved ) / $monthly ) : 0;

nab_head_open( 'Emergency Fund Planner — NAB Member Portal' );
?>
<style>
.nab-ef-wrap{max-width:820px;margin:0 auto;padding-bottom:40px}
.nab-ef-hero{background:linear-gradient(135deg,#0d9488,#0f766e);border-radius:16px;padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:24px;flex-wrap:wrap}
.nab-ef-hero-icon{font-size:44px;flex-shrink:0}
.nab-ef-hero-title{font-size:20px;font-weight:800;margin:0 0 4px}
.nab-ef-hero-sub{font-size:13px;opacity:.85;margin:0;line-height:1.5}

.nab-ef-card{background:#fff;border-radius:16px;padding:28px 32px;box-shadow:0 2px 16px rgba(0,0,0,.07);margin-bottom:20px}
.nab-ef-card h3{margin:0 0 20px;color:#1e293b;font-size:17px}
.nab-ef-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px}
@media(max-width:640px){.nab-ef-grid{grid-template-columns:1fr}}
.nab-ef-row{display:flex;flex-direction:column;gap:6px}
.nab-ef-row label{font-size:12px;font-weight:700;color:#475569}
.nab-ef-input{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;transition:.15s;box-sizing:border-box;font-family:inherit}
.nab-ef-input:focus{outline:none;border-color:#0d9488}
.nab-ef-submit{background:#0d9488;color:#fff;border:none;border-radius:10px;padding:13px 32px;font-size:14px;font-weight:700;cursor:pointer;transition:.15s}
.nab-ef-submit:hover{background:#0f766e}
.nab-ef-submit:disabled{opacity:.6;cursor:not-allowed}

.nab-ef-progress-wrap{margin-top:8px}
.nab-ef-progress-labels{display:flex;justify-content:space-between;font-size:13px;color:#475569;margin-bottom:8px;font-weight:600}
.nab-ef-progress-track{background:#f1f5f9;border-radius:999px;height:22px;overflow:hidden;position:relative}
.nab-ef-progress-fill{background:linear-gradient(90deg,#0d9488,#14b8a6);height:100%;border-radius:999px;transition:width .4s ease;display:flex;align-items:center;justify-content:flex-end;padding-right:8px}
.nab-ef-progress-fill span{color:#fff;font-size:11px;font-weight:800}
.nab-ef-milestones{display:flex;justify-content:space-between;margin-top:6px;font-size:10.5px;color:#94a3b8;font-weight:700}
.nab-ef-milestones span.hit{color:#0d9488}

.nab-ef-congrats{margin-top:18px;background:#f0fdfa;border:1.5px solid #99f6e4;border-radius:12px;padding:16px 18px;font-size:13.5px;color:#0f766e;font-weight:600;display:none}
.nab-ef-congrats.show{display:block}

.nab-ef-stats{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px}
@media(max-width:480px){.nab-ef-stats{grid-template-columns:1fr}}
.nab-ef-stat{background:#f8fafc;border-radius:10px;padding:14px 16px}
.nab-ef-stat-label{font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.nab-ef-stat-value{font-size:20px;font-weight:800;color:#0f172a;margin-top:4px}

.nab-ef-tips{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
.nab-ef-tips li{display:flex;gap:10px;font-size:13.5px;color:#334155;line-height:1.5}
.nab-ef-tips li::before{content:"💡";flex-shrink:0}
</style>
</head>
<body <?php body_class( 'nab-portal-body' ); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar( 'ef' ); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title">💰 Emergency Fund Planner</div>
      <div class="nab-topbar-right">
        <?php if ( function_exists( 'nab_render_notification_bell' ) ) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="nab-ef-wrap">

        <div class="nab-ef-hero">
          <div class="nab-ef-hero-icon">💰</div>
          <div>
            <div class="nab-ef-hero-title">Build your emergency fund</div>
            <div class="nab-ef-hero-sub">Set a savings goal, track your progress, and see how long it'll take to get there — a strong emergency fund is your buffer against the unexpected.</div>
          </div>
        </div>

        <div class="nab-ef-card">
          <h3>Your Savings Plan</h3>
          <div class="nab-ef-grid">
            <div class="nab-ef-row">
              <label for="efMonthly">Monthly Savings Amount ($)</label>
              <input type="number" min="0" step="0.01" id="efMonthly" class="nab-ef-input" value="<?php echo esc_attr( $monthly ?: '' ); ?>" placeholder="e.g. 150">
            </div>
            <div class="nab-ef-row">
              <label for="efGoal">Savings Goal ($)</label>
              <input type="number" min="0" step="0.01" id="efGoal" class="nab-ef-input" value="<?php echo esc_attr( $goal ?: '' ); ?>" placeholder="e.g. 3000">
            </div>
            <div class="nab-ef-row">
              <label for="efSaved">Current Amount Saved ($)</label>
              <input type="number" min="0" step="0.01" id="efSaved" class="nab-ef-input" value="<?php echo esc_attr( $saved ?: '' ); ?>" placeholder="e.g. 500">
            </div>
          </div>
          <button class="nab-ef-submit" id="efSaveBtn" onclick="nabEfSave()">Save & Update Progress</button>

          <div class="nab-ef-progress-wrap" style="margin-top:24px">
            <div class="nab-ef-progress-labels">
              <span id="efPercentLabel"><?php echo esc_html( $percent ); ?>% of goal</span>
              <span id="efSavedLabel">$<?php echo esc_html( number_format( $saved, 2 ) ); ?> / $<?php echo esc_html( number_format( $goal, 2 ) ); ?></span>
            </div>
            <div class="nab-ef-progress-track">
              <div class="nab-ef-progress-fill" id="efProgressFill" style="width:<?php echo esc_attr( $percent ); ?>%">
                <span id="efProgressFillLabel"><?php echo $percent > 8 ? esc_html( $percent ) . '%' : ''; ?></span>
              </div>
            </div>
            <div class="nab-ef-milestones">
              <span id="efM25"  class="<?php echo $percent >= 25  ? 'hit' : ''; ?>">25%</span>
              <span id="efM50"  class="<?php echo $percent >= 50  ? 'hit' : ''; ?>">50%</span>
              <span id="efM75"  class="<?php echo $percent >= 75  ? 'hit' : ''; ?>">75%</span>
              <span id="efM100" class="<?php echo $percent >= 100 ? 'hit' : ''; ?>">100%</span>
            </div>
          </div>

          <div class="nab-ef-congrats<?php echo $percent >= 25 ? ' show' : ''; ?>" id="efCongrats">
            <span id="efCongratsText"><?php echo esc_html( nab_ef_milestone_message( $percent ) ); ?></span>
          </div>

          <div class="nab-ef-stats">
            <div class="nab-ef-stat">
              <div class="nab-ef-stat-label">Remaining to Save</div>
              <div class="nab-ef-stat-value" id="efRemaining">$<?php echo esc_html( number_format( max( 0, $goal - $saved ), 2 ) ); ?></div>
            </div>
            <div class="nab-ef-stat">
              <div class="nab-ef-stat-label">Estimated Time to Goal</div>
              <div class="nab-ef-stat-value" id="efMonths"><?php echo $months > 0 ? esc_html( $months ) . ' month' . ( $months == 1 ? '' : 's' ) : ( $percent >= 100 ? 'Goal reached!' : '—' ); ?></div>
            </div>
          </div>
        </div>

        <div class="nab-ef-card">
          <h3>💡 Savings Tips</h3>
          <ul class="nab-ef-tips">
            <li>Automate it — set up a recurring transfer to your emergency fund right after payday, so saving happens before you get a chance to spend it.</li>
            <li>Start small and stay consistent. Even $10–$20 a week adds up faster than you'd expect.</li>
            <li>Keep your emergency fund in a separate, easy-to-access account — separate from your everyday spending money, but not locked away.</li>
            <li>Revisit your goal every few months. As your expenses or income change, your target amount should too.</li>
            <li>Aim for 3–6 months of essential expenses as a long-term target, but any amount saved is real progress.</li>
          </ul>
        </div>

      </div>
    </div>
  </main>
</div>

<script>
function nabEfMilestoneMsg(pct){
  if(pct>=100) return "🎉 Congratulations! You've reached your savings goal!";
  if(pct>=75)  return "🎉 75% complete! You're almost at your goal — keep going.";
  if(pct>=50)  return "🎉 Halfway there! You've saved 50% of your goal.";
  if(pct>=25)  return "🎉 You've hit 25% of your goal! Great start — keep the momentum going.";
  return "";
}
function nabEfSave(){
  if(typeof nabPortal==='undefined'){ alert('Session error. Please refresh the page.'); return; }
  var btn = document.getElementById('efSaveBtn');
  var monthly = parseFloat(document.getElementById('efMonthly').value) || 0;
  var goal    = parseFloat(document.getElementById('efGoal').value) || 0;
  var saved   = parseFloat(document.getElementById('efSaved').value) || 0;

  btn.disabled = true; btn.textContent = 'Saving…';

  fetch(nabPortal.ajax, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=nab_save_emergency_fund&nonce=' + nabPortal.nonce +
          '&monthly=' + encodeURIComponent(monthly) +
          '&goal=' + encodeURIComponent(goal) +
          '&saved=' + encodeURIComponent(saved)
  })
  .then(function(r){ return r.json(); })
  .then(function(data){
    btn.disabled = false; btn.textContent = 'Save & Update Progress';
    if(!data.success){ alert('Could not save — please try again.'); return; }

    var pct = data.data.percent;
    var months = data.data.months;

    document.getElementById('efPercentLabel').textContent = pct + '% of goal';
    document.getElementById('efSavedLabel').textContent = '$' + saved.toFixed(2) + ' / $' + goal.toFixed(2);
    var fill = document.getElementById('efProgressFill');
    fill.style.width = pct + '%';
    document.getElementById('efProgressFillLabel').textContent = pct > 8 ? pct + '%' : '';

    ['25','50','75','100'].forEach(function(m){
      var el = document.getElementById('efM' + m);
      if(pct >= parseInt(m)) el.classList.add('hit'); else el.classList.remove('hit');
    });

    var congrats = document.getElementById('efCongrats');
    var msg = nabEfMilestoneMsg(pct);
    if(msg){
      document.getElementById('efCongratsText').textContent = msg;
      congrats.classList.add('show');
    } else {
      congrats.classList.remove('show');
    }

    document.getElementById('efRemaining').textContent = '$' + Math.max(0, goal - saved).toFixed(2);
    document.getElementById('efMonths').textContent = months > 0 ? (months + ' month' + (months == 1 ? '' : 's')) : (pct >= 100 ? 'Goal reached!' : '—');
  })
  .catch(function(){
    btn.disabled = false; btn.textContent = 'Save & Update Progress';
    alert('Connection error. Please try again.');
  });
}
</script>

<?php nab_footer_scripts(); ?>
<?php wp_footer(); ?>
</body>
</html>
