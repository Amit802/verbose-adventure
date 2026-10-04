<?php
/**
 * Template Name: NAB Support Center
 * Freshdesk-powered ticket submission and tracking.
 * Credentials managed from WP Admin → Dashboard page ACF → Support Center tab.
 *
 * @package NAB_Member_Portal
 * @since   1.6.3
 */

if ( ! defined( 'ABSPATH' ) ) exit;
// Prevent caching of this page
if ( ! headers_sent() ) {
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    header( 'Pragma: no-cache' );
}
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( get_permalink() ) ); exit; }

$user       = wp_get_current_user();
$uid        = $user->ID;
$first_name = get_user_meta( $uid, 'nab_first_name', true ) ?: $user->first_name ?: 'Member';
$last_name  = get_user_meta( $uid, 'nab_last_name',  true ) ?: $user->last_name  ?: '';
$email      = $user->user_email;

$dash_pages = get_pages(['meta_key'=>'_wp_page_template','meta_value'=>'nab-dashboard','number'=>1]);
$dash_id    = !empty($dash_pages) ? $dash_pages[0]->ID : 0;
$fd_key     = $dash_id ? get_field('nab_freshdesk_key',    $dash_id) : '';
$fd_domain  = $dash_id ? get_field('nab_freshdesk_domain', $dash_id) : '';
$has_fd     = !empty($fd_key) && !empty($fd_domain);

$notifications = function_exists('nab_get_user_notifications') ? nab_get_user_notifications($uid) : [];

nab_head_open('Support Center — NAB Member Portal');
?>
<style>
.nab-support-wrap{max-width:860px;margin:0 auto;padding-bottom:40px}
.nab-support-hero{background:linear-gradient(135deg,#0D5C9B,#0a4a7c);border-radius:16px;padding:24px 28px;color:#fff;display:flex;align-items:center;gap:20px;margin-bottom:24px;flex-wrap:wrap}
.nab-support-hero-icon{font-size:44px;flex-shrink:0}
.nab-support-hero-title{font-size:20px;font-weight:800;margin:0 0 4px}
.nab-support-hero-sub{font-size:13px;opacity:.85;margin:0;line-height:1.5}

/* Tabs */
.nab-support-tabs{display:flex;gap:4px;border-bottom:2px solid #e2e8f0;margin-bottom:24px}
.nab-support-tab{background:none;border:none;padding:11px 22px;font-size:14px;color:#64748b;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;font-weight:500;transition:.15s}
.nab-support-tab.active,.nab-support-tab:hover{color:#0D5C9B;border-bottom-color:#0D5C9B}
.nab-support-pane{display:none}
.nab-support-pane.active{display:block}

/* Form */
.nab-support-card{background:#fff;border-radius:16px;padding:28px 32px;box-shadow:0 2px 16px rgba(0,0,0,.07);margin-bottom:20px}
.nab-support-card h3{margin:0 0 20px;color:#1e293b;font-size:17px}
.nab-sf-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.nab-sf-row{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
.nab-sf-row label{font-size:12px;font-weight:700;color:#475569}
.nab-sf-row.full{grid-column:1/-1}
.nab-sf-input{width:100%;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;transition:.15s;box-sizing:border-box;font-family:inherit}
.nab-sf-input:focus{outline:none;border-color:#0D5C9B}
.nab-sf-textarea{min-height:120px;resize:vertical}
.nab-sf-select{appearance:none;background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2394a3b8' stroke-width='1.5' fill='none'/%3E%3C/svg%3E") no-repeat right 14px center}
.nab-submit-btn{background:#0D5C9B;color:#fff;border:none;border-radius:10px;padding:13px 32px;font-size:14px;font-weight:700;cursor:pointer;transition:.15s}
.nab-submit-btn:hover{background:#0a4a7c}
.nab-submit-btn:disabled{background:#cbd5e1;cursor:not-allowed}

/* Success */
.nab-ticket-success{background:#dcfce7;border:1.5px solid #16a34a;border-radius:12px;padding:24px;text-align:center;display:none}
.nab-ticket-success-icon{font-size:48px;margin-bottom:12px}
.nab-ticket-success-title{font-size:18px;font-weight:800;color:#166534;margin-bottom:6px}
.nab-ticket-success-sub{font-size:13px;color:#15803d}
.nab-ticket-id{background:#fff;border-radius:8px;padding:8px 16px;display:inline-block;margin:12px 0;font-weight:700;color:#0D5C9B;font-size:15px}

/* Ticket list */
.nab-ticket-item{background:#fff;border-radius:12px;padding:18px 22px;box-shadow:0 1px 8px rgba(0,0,0,.06);margin-bottom:12px;border-left:4px solid #e2e8f0;transition:.15s;cursor:pointer}
.nab-ticket-item:hover{box-shadow:0 4px 20px rgba(0,0,0,.1);border-left-color:#0D5C9B}
.nab-ticket-item.open{border-left-color:#0D5C9B}
.nab-ticket-item.pending{border-left-color:#f59e0b}
.nab-ticket-item.resolved{border-left-color:#16a34a}
.nab-ticket-item.closed{border-left-color:#94a3b8}
.nab-ticket-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.nab-ticket-subject{font-size:14px;font-weight:700;color:#1e293b;margin-bottom:4px}
.nab-ticket-meta{font-size:12px;color:#94a3b8}
.nab-ticket-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;flex-shrink:0;white-space:nowrap}
.nab-badge-open{background:#dbeafe;color:#1e40af}
.nab-badge-pending{background:#fef9c3;color:#854d0e}
.nab-badge-resolved{background:#dcfce7;color:#166534}
.nab-badge-closed{background:#f1f5f9;color:#64748b}
.nab-tickets-empty{text-align:center;padding:48px 20px;color:#94a3b8}
.nab-tickets-empty-icon{font-size:48px;margin-bottom:12px}
.nab-tickets-loading{text-align:center;padding:32px;color:#94a3b8;font-size:14px}

/* Alert */
.nab-alert-error{background:#fee2e2;border-left:4px solid #c62828;padding:12px 16px;border-radius:8px;font-size:13px;color:#c62828;margin-bottom:16px;display:none}

@media(max-width:640px){
  .nab-sf-grid{grid-template-columns:1fr}
  .nab-support-hero{flex-direction:column;text-align:center}
}
</style>
</head>
<body <?php body_class('nab-portal-body'); ?>>
<?php wp_body_open(); ?>

<div class="nab-portal-wrap">
  <?php nab_render_sidebar('support'); ?>

  <main class="nab-main">
    <header class="nab-topbar">
      <div class="nab-topbar-title">🎫 Support Center</div>
      <div class="nab-topbar-right">
        <?php if(function_exists('nab_render_notification_bell')) nab_render_notification_bell(); ?>
      </div>
    </header>

    <div class="nab-content">
      <div class="nab-support-wrap">

        <!-- Hero -->
        <div class="nab-support-hero">
          <div class="nab-support-hero-icon">🎫</div>
          <div>
            <div class="nab-support-hero-title">How can we help you?</div>
            <p class="nab-support-hero-sub">Submit a support request and our team will get back to you as soon as possible. You can also track your existing tickets here.</p>
          </div>
        </div>

        <!-- Tabs -->
        <div class="nab-support-tabs">
          <button class="nab-support-tab active" data-tab="new-ticket" type="button">➕ New Ticket</button>
          <button class="nab-support-tab" data-tab="my-tickets" type="button">📋 My Tickets</button>
        </div>

        <!-- NEW TICKET TAB -->
        <div class="nab-support-pane active" id="tab-new-ticket">
          <div class="nab-support-card">
            <h3>Submit a Support Request</h3>
            <div class="nab-alert-error" id="nabSfError"></div>
            <div class="nab-ticket-success" id="nabSfSuccess">
              <div class="nab-ticket-success-icon">✅</div>
              <div class="nab-ticket-success-title">Ticket Submitted Successfully!</div>
              <div class="nab-ticket-id" id="nabSfTicketId"></div>
              <div class="nab-ticket-success-sub">Our team will respond within 1-2 business days. You can track this ticket in the My Tickets tab.</div>
            </div>
            <form id="nabSupportForm">
              <div class="nab-sf-grid">
                <div class="nab-sf-row">
                  <label>First Name</label>
                  <input type="text" class="nab-sf-input" id="nabSfFirst" value="<?php echo esc_attr($first_name); ?>" required>
                </div>
                <div class="nab-sf-row">
                  <label>Last Name</label>
                  <input type="text" class="nab-sf-input" id="nabSfLast" value="<?php echo esc_attr($last_name); ?>" required>
                </div>
                <div class="nab-sf-row">
                  <label>Email Address</label>
                  <input type="email" class="nab-sf-input" id="nabSfEmail" value="<?php echo esc_attr($email); ?>" required>
                </div>
                <div class="nab-sf-row">
                  <label>Category</label>
                  <select class="nab-sf-input nab-sf-select" id="nabSfCategory" required>
                    <option value="">Select a category</option>
                    <option value="Cancellation">Cancellation</option>
                    <option value="Refund">Refund</option>
                    <option value="Technical Concern">Technical Concern</option>
                    <option value="Billing Issue">Billing Issue</option>
                    <option value="Dispute Query">Dispute Query</option>
                    <option value="Credit Report Question">Credit Report Question</option>
                    <option value="Membership Access">Membership Access</option>
                    <option value="General Inquiry">General Inquiry</option>
                  </select>
                </div>
              </div>
              <div class="nab-sf-row">
                <label>Subject</label>
                <input type="text" class="nab-sf-input" id="nabSfSubject" placeholder="Brief summary of your issue" required>
              </div>
              <div class="nab-sf-row">
                <label>Message</label>
                <textarea class="nab-sf-input nab-sf-textarea" id="nabSfMessage" placeholder="Please describe your issue in detail..." required></textarea>
              </div>
              <button type="submit" class="nab-submit-btn" id="nabSfSubmit">Submit Ticket</button>
            </form>
          </div>
        </div>

        <!-- MY TICKETS TAB -->
        <div class="nab-support-pane" id="tab-my-tickets">
          <div class="nab-support-card">
            <h3>My Support Tickets</h3>
            <div id="nabTicketsList">
              <div class="nab-tickets-loading">Loading your tickets…</div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<script>
(function(){
var AJAX    = '<?php echo admin_url("admin-ajax.php"); ?>';
var NONCE   = '<?php echo wp_create_nonce("nab_freshdesk_action"); ?>';
// Refresh nonce dynamically in case page was cached
fetch('<?php echo admin_url("admin-ajax.php"); ?>?action=nab_get_nonce&type=nab_freshdesk_action')
  .then(function(r){return r.json();})
  .then(function(d){if(d.success && d.data.nonce) NONCE = d.data.nonce;})
  .catch(function(){});
var HAS_FD  = <?php echo $has_fd ? 'true' : 'false'; ?>;

// Clear any stale validation errors on page load
window.addEventListener('load', function(){
  var err = document.getElementById('nabSfError');
  if(err){ err.style.display='none'; err.textContent=''; }
});

// Tab switching
document.querySelectorAll('.nab-support-tab').forEach(function(btn){
  btn.addEventListener('click', function(){
    document.querySelectorAll('.nab-support-tab').forEach(function(b){b.classList.remove('active');});
    document.querySelectorAll('.nab-support-pane').forEach(function(p){p.classList.remove('active');});
    btn.classList.add('active');
    var pane = document.getElementById('tab-'+btn.dataset.tab);
    if(pane){ pane.classList.add('active'); }
    if(btn.dataset.tab === 'my-tickets') loadTickets();
  });
});

// Submit form
document.getElementById('nabSupportForm').addEventListener('submit', function(e){
  e.preventDefault();
  var btn    = document.getElementById('nabSfSubmit');
  var err    = document.getElementById('nabSfError');
  var suc    = document.getElementById('nabSfSuccess');
  var first  = document.getElementById('nabSfFirst').value.trim();
  var last   = document.getElementById('nabSfLast').value.trim();
  var email  = document.getElementById('nabSfEmail').value.trim();
  var cat    = document.getElementById('nabSfCategory').value;
  var subj   = document.getElementById('nabSfSubject').value.trim();
  var msg    = document.getElementById('nabSfMessage').value.trim();

  err.style.display='none';
  if(!first||!last||!email||!cat||!subj||!msg){
    err.textContent='Please fill in all fields.';
    err.style.display='block';
    return;
  }

  btn.disabled=true;btn.textContent='Submitting…';

  fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=nab_freshdesk_submit&nonce='+NONCE+
      '&first='+encodeURIComponent(first)+
      '&last='+encodeURIComponent(last)+
      '&email='+encodeURIComponent(email)+
      '&category='+encodeURIComponent(cat)+
      '&subject='+encodeURIComponent(subj)+
      '&message='+encodeURIComponent(msg)
  })
  .then(function(r){return r.json();})
  .then(function(data){
    if(data.success){
      document.getElementById('nabSupportForm').style.display='none';
      document.getElementById('nabSfTicketId').textContent='Ticket #'+data.data.ticket_id;
      suc.style.display='block';
      if(typeof nab_add_auto_notification==='function'){
        // handled server side
      }
    } else {
      err.textContent = data.data.message || 'Something went wrong. Please try again.';
      err.style.display='block';
      btn.disabled=false;btn.textContent='Submit Ticket';
    }
  })
  .catch(function(){
    err.textContent='Connection error. Please try again.';
    err.style.display='block';
    btn.disabled=false;btn.textContent='Submit Ticket';
  });
});

// Load tickets
function loadTickets(){
  var list = document.getElementById('nabTicketsList');
  list.innerHTML='<div class="nab-tickets-loading">⏳ Loading your tickets…</div>';
  fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=nab_freshdesk_tickets&nonce='+NONCE
  })
  .then(function(r){return r.json();})
  .then(function(data){
    if(data.success && data.data.tickets && data.data.tickets.length){
      var html='';
      data.data.tickets.forEach(function(t){
        var statusMap={2:'open',3:'pending',4:'resolved',5:'closed'};
        var labelMap={2:'Open',3:'Pending / Waiting',4:'Resolved',5:'Closed'};
        var badgeMap={2:'nab-badge-open',3:'nab-badge-pending',4:'nab-badge-resolved',5:'nab-badge-closed'};
        var st=t.status||2;
        var date=new Date(t.created_at).toLocaleDateString('en-CA',{year:'numeric',month:'short',day:'numeric'});
        var fd_url = 'https://nabsolutions.freshdesk.com/a/tickets/'+t.id;
        html+='<div class="nab-ticket-item '+statusMap[st]+'">';
        html+='<div class="nab-ticket-row">';
        html+='<div><div class="nab-ticket-subject">'+escH(t.subject)+'</div>';
        html+='<div class="nab-ticket-meta">Ticket #'+t.id+' &nbsp;·&nbsp; '+date+'</div></div>';
        html+='<a href="'+fd_url+'" target="_blank" class="nab-ticket-badge '+badgeMap[st]+'" style="text-decoration:none;">'+labelMap[st]+'</a>';
        html+='</div></div>';
      });
      list.innerHTML=html;
    } else if(data.success){
      list.innerHTML='<div class="nab-tickets-empty"><div class="nab-tickets-empty-icon">🎫</div><div style="font-weight:700;color:#64748b;margin-bottom:6px">No tickets yet</div><div style="font-size:13px">Submit a request using the New Ticket tab and we\'ll get back to you shortly.</div></div>';
    } else {
      list.innerHTML='<div class="nab-tickets-empty"><div class="nab-tickets-empty-icon">⚠️</div><div style="font-weight:700;color:#64748b;margin-bottom:6px">Could not load tickets</div><div style="font-size:13px">'+( data.data.message||'Please try again shortly.')+'</div></div>';
    }
  })
  .catch(function(){
    list.innerHTML='<div class="nab-tickets-empty"><div style="font-size:13px;color:#94a3b8">Connection error. Please refresh the page.</div></div>';
  });
}

function escH(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
})();
</script>

<?php nab_portal_footer_js(); ?>
<?php wp_footer(); ?>
</body>
</html>
