<?php
/** Reply Assistant screen. v0.1: design with sample data. */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="screen" id="s-reply">
        <div class="head">
          <div class="eyebrow">Reply assistant</div>
          <div class="title">Answer a customer email</div>
          <p class="sub">Paste the customer's email and get a reply based on our policies. You always check it before sending.</p>
        </div>
        <div class="grid2">
          <div class="card">
            <h2>Customer's email</h2>
            <p class="hint">Copy it from Freshdesk or Gmail and paste it here.</p>
            <label class="f">Member's email <span style="font-weight:500;color:var(--text-3)">(optional, adds their account details)</span>
              <input class="in" value="sarah.thompson@example.com">
            </label>
            <label class="f">Their message
              <textarea class="in" id="custMsg">Hi, I want to cancel my membership today and I want my money back for this week. I haven't used anything. Thanks, Sarah</textarea>
            </label>
            <div class="mini"><div class="av" style="width:32px;height:32px;font-size:12px;background:var(--brand-soft);color:var(--brand)">ST</div><div style="flex:1"><b>Sarah Thompson</b><span>NAB-1364 · Member since Oct 2, 2026 · Last login 3 days ago</span></div><span class="badge good">Active</span></div>
            <div class="row" style="margin-top:14px">
              <button class="btn primary" id="suggest"><svg class="i" viewBox="0 0 24 24"><path d="m12 3-1.9 5.8L4 10l5 3.6L7.6 20 12 16.5 16.4 20 15 13.6 20 10l-6.1-1.2z"/></svg>Suggest a reply</button>
              <button class="btn" id="clear">Clear</button>
            </div>
            <div class="safe"><svg class="i" viewBox="0 0 24 24" style="width:15px;height:15px"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Bank, card and SIN numbers are hidden before anything goes to the AI.</div>
          </div>

          <div class="card">
            <h2>Suggested reply</h2>
            <p class="hint">Check it, change anything you like, then copy and send.</p>
            <div class="empty" id="rEmpty">Paste an email and click "Suggest a reply".</div>
            <div class="loading" id="rLoad"><span class="dot"></span><span class="dot"></span><span class="dot"></span> Reading the email and our policies</div>
            <div id="rOut" style="display:none">
              <div class="summary"><div><b>What she wants</b><p>Sarah wants to cancel and is asking for a refund for this week.</p></div></div>
              <div class="tags"><span class="tag">Cancellation</span><span class="tag">Refund request</span><span class="tag warn">Upset customer</span></div>
              <div class="row" style="margin-bottom:10px">
                <button class="btn small on" data-tone="standard">Standard</button>
                <button class="btn small" data-tone="short">Shorter</button>
                <button class="btn small" data-tone="friendly">Friendlier</button>
                <button class="btn small" data-tone="formal">More formal</button>
              </div>
              <div class="reply" id="replyText" contenteditable="true"></div>
              <div class="sources">Based on: <span class="src">Cancellation and Refund Policy.pdf</span><span class="src">Reply Templates.docx</span></div>
              <div class="row" style="margin-top:14px">
                <button class="btn primary" id="copy"><svg class="i" viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>Copy reply</button>
                <button class="btn" id="again">Try again</button>
                <span class="copied" id="copied">Copied. Paste it into Freshdesk.</span>
              </div>
            </div>
          </div>
        </div>
      </section>
