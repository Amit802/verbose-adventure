<?php
/** One ticket: conversation, AI reply, internal notes and member details. v0.3: design with sample data. */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="screen" id="s-ticket">
  <div class="crumb"><a href="<?php echo esc_url( nab_staff_url( 'tickets' ) ); ?>">Tickets</a> <span>/</span> #1042</div>
  <div class="head head-row" style="margin-bottom:14px">
    <div>
      <div class="title" style="font-size:22px">I want to cancel and get a refund</div>
      <div class="row" style="margin-top:6px"><span class="badge warn">Open</span><span class="tag">Cancellation</span><span class="tag">Refund request</span><span class="tag warn">Upset customer</span><span class="muted" style="font-size:12.5px">From the member portal · 2 hours ago</span></div>
    </div>
    <div class="row"><button class="btn" type="button">Mark resolved</button></div>
  </div>

  <div class="tgrid">
    <div>
      <div class="card thread">
        <div class="msg">
          <div class="av sm">ST</div>
          <div class="bubble"><div class="meta"><b>Sarah Thompson</b><span>Today, 9:14 AM</span></div>Hi, I want to cancel my membership today and I want my money back for this week. I haven't used anything. Thanks, Sarah</div>
        </div>
        <div class="msg note-msg">
          <div class="av sm" style="background:#FFF4E5;color:#8A4B00">MT</div>
          <div class="bubble"><div class="meta"><b>Mark T.</b><span>Internal note · 9:40 AM</span></div>She called yesterday too and sounded frustrated about the weekly payment. Maybe offer a pause first.</div>
        </div>
      </div>

      <div class="card composer">
        <div class="tabs"><button class="tab on" type="button">Reply to member</button><button class="tab" type="button">Internal note</button></div>
        <div class="ai-box" id="aiBox">
          <div class="ai-head"><?php echo nab_staff_icon( 'spark', 16 ); ?><b>AI suggestion</b><span>Based on Cancellation and Refund Policy and the note from Mark</span></div>
          <p class="ai-sum">Sarah wants to cancel and a refund for this week. Our policy is 30 days' notice and no refunds, and Mark suggests offering a pause.</p>
          <div class="row"><button class="btn small primary" type="button" id="useAi">Use this reply</button><button class="btn small" type="button" data-tone="short">Shorter</button><button class="btn small" type="button" data-tone="friendly">Friendlier</button><button class="btn small" type="button" data-tone="formal">More formal</button></div>
        </div>
        <textarea class="in" id="ticketReply" placeholder="Write your reply, or use the AI suggestion above"></textarea>
        <div class="row" style="justify-content:space-between;margin-top:12px">
          <div class="row"><select class="in sm" style="width:auto"><option>Status after sending: Waiting on member</option><option>Resolved</option><option>Open</option></select></div>
          <button class="btn primary" type="button">Send reply</button>
        </div>
        <div class="safe"><?php echo nab_staff_icon( 'clock', 14 ); ?>Sarah gets your reply by email and sees it in her portal under Support.</div>
      </div>
    </div>

    <aside class="tside">
      <div class="card">
        <div class="ph" style="margin-bottom:12px"><div class="av">ST</div><div><b>Sarah Thompson</b><span>sarah.thompson@example.com</span></div></div>
        <dl class="kv">
          <dt>Member ID</dt><dd>NAB-1364</dd>
          <dt>Membership</dt><dd><span class="badge good">Active</span></dd>
          <dt>Member since</dt><dd>Oct 2, 2026</dd>
          <dt>Last login</dt><dd>3 days ago</dd>
          <dt>Credit score</dt><dd>612</dd>
        </dl>
        <a class="link" style="display:inline-block;margin-top:12px" href="<?php echo esc_url( nab_staff_url( 'lookup' ) ); ?>">Open full profile</a>
      </div>
      <div class="card">
        <h2 style="margin-bottom:12px">Ticket details</h2>
        <label class="f">Assigned to<select class="in sm"><option>Unassigned</option><option>You</option><option>Mark T.</option><option>Aisha M.</option><option>Jessica K.</option></select></label>
        <label class="f">Priority<select class="in sm"><option>High</option><option>Normal</option><option>Low</option></select></label>
        <label class="f" style="margin-bottom:0">Topic<select class="in sm"><option>Cancellation</option><option>Billing</option><option>Loans</option><option>Disputes</option><option>Collection Assistance</option><option>Login</option></select></label>
      </div>
      <div class="card">
        <h2 style="margin-bottom:6px">Earlier tickets</h2>
        <ul class="list">
          <li><div>Can't log in to the portal<small>Oct 28</small></div><span class="badge good">Resolved</span></li>
        </ul>
      </div>
    </aside>
  </div>
</section>
