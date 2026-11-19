<?php
/** Member Lookup screen. v0.1: design with sample data. */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="screen" id="s-lookup">
        <div class="head">
          <div class="eyebrow">Member lookup</div>
          <div class="title">Everything about a member, in one place</div>
          <p class="sub">Search by email or Member ID to see their membership, access, disputes and requests.</p>
        </div>
        <div class="search"><input class="in" value="sarah.thompson@example.com"><button class="btn primary">Search</button></div>
        <div class="profile">
          <div class="card">
            <div class="ph"><div class="av">ST</div><div><b>Sarah Thompson</b><span>sarah.thompson@example.com</span></div></div>
            <dl class="kv">
              <dt>Member ID</dt><dd>NAB-1364</dd>
              <dt>Phone</dt><dd>(403) 555-0148</dd>
              <dt>Member since</dt><dd>Oct 2, 2026</dd>
              <dt>Membership</dt><dd><span class="badge good">Active</span></dd>
              <dt>Portal access</dt><dd>Open</dd>
              <dt>Last login</dt><dd>3 days ago</dd>
              <dt>Credit score</dt><dd>612 (up 18)</dd>
            </dl>
            <div class="row" style="margin-top:18px">
              <button class="btn small">Resend login email</button>
              <button class="btn small">Cancel access</button>
              <button class="btn small">Open in Member Access</button>
            </div>
          </div>
          <div class="card">
            <div class="sec">Dispute letters</div>
            <ul class="list">
              <li><div>Late payment, Equifax<small>Sent Oct 21 · 2 accounts</small></div><span class="badge blue">Waiting for reply</span></li>
              <li><div>Not my account, TransUnion<small>Created Nov 3</small></div><span class="badge warn">Ready to send</span></li>
            </ul>
            <div class="sec">Collection Assistance</div>
            <ul class="list">
              <li><div>Collection agency · $909.00<small>Sent Nov 13 · Reference #228</small></div><span class="badge warn">Received</span></li>
            </ul>
            <div class="sec">Freshdesk tickets</div>
            <ul class="list">
              <li><div>Can't log in to the portal<small>Oct 28</small></div><span class="badge good">Resolved</span></li>
              <li><div>Question about the Lending Finder Program<small>Nov 9</small></div><span class="badge blue">Open</span></li>
            </ul>
          </div>
        </div>
      </section>
