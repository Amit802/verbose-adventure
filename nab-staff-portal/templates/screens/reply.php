<?php
/** Reply Assistant: paste an email, get a summary and a reply based on our documents. */
if ( ! defined( 'ABSPATH' ) ) exit;
$has_docs = (bool) nab_staff_docs( true );
$ai_ready = nab_staff_ai_config()['key'] !== '';
?>
<section class="screen" id="s-reply">
  <div class="head">
    <div class="eyebrow">Reply assistant</div>
    <div class="title">Answer a customer email</div>
    <p class="sub">Paste the customer's email and get a reply based on our policies. You always check it before sending.</p>
  </div>
  <?php if ( ! $ai_ready ) : ?>
  <div class="notice-bar bad">The AI isn't set up yet. Add the OpenAI key in the Dashboard page settings (OpenAI / AI Features), the same one Nabby uses.</div>
  <?php elseif ( ! $has_docs ) : ?>
  <div class="notice-bar"><?php echo nab_staff_can( 'manage' ) ? 'No documents are switched on yet, so replies will be general. Add our policies in Documents first.' : 'No documents have been added yet, so replies will be general. Ask your manager to add our policies in Documents.'; ?></div>
  <?php endif; ?>
  <div class="grid2">
    <div class="card">
      <h2>Customer's email</h2>
      <p class="hint">Copy it from Freshdesk or Gmail and paste it here.</p>
      <form id="replyForm" autocomplete="off">
        <label class="f">Member's email <span style="font-weight:500;color:var(--text-3)">(optional, adds their account details)</span>
          <input class="in" type="email" name="member" id="memberEmail" placeholder="member@email.com">
        </label>
        <div id="memberCard"></div>
        <label class="f">Their message
          <textarea class="in" name="message" id="custMsg" placeholder="Paste the email here" required></textarea>
        </label>
        <div class="row" style="margin-top:4px">
          <button class="btn primary" type="submit" id="suggest"><?php echo nab_staff_icon( 'spark', 16 ); ?>Suggest a reply</button>
          <button class="btn" type="button" id="clear">Clear</button>
        </div>
      </form>
      <div class="safe"><svg class="i" viewBox="0 0 24 24" style="width:15px;height:15px"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>Card, bank and SIN numbers are hidden before anything goes to the AI.</div>
    </div>

    <div class="card">
      <h2>Suggested reply</h2>
      <p class="hint">Check it, change anything you like, then copy and send.</p>
      <div class="empty" id="rEmpty">Paste an email and click "Suggest a reply".</div>
      <div class="loading" id="rLoad"><span class="dot"></span><span class="dot"></span><span class="dot"></span> Reading the email and our policies</div>
      <div class="err-box" id="rErr" hidden></div>
      <div id="rOut" hidden>
        <div class="summary"><div><b>What they want</b><p id="rSummary"></p></div></div>
        <div class="tags" id="rTags"></div>
        <div class="notice-bar" id="rNotCovered" hidden>Our documents don't cover this, so the reply says the team will look into it. Check with your manager before sending.</div>
        <div class="row" style="margin-bottom:10px" id="tones">
          <button class="btn small on" type="button" data-tone="standard">Standard</button>
          <button class="btn small" type="button" data-tone="short">Shorter</button>
          <button class="btn small" type="button" data-tone="friendly">Friendlier</button>
          <button class="btn small" type="button" data-tone="formal">More formal</button>
        </div>
        <textarea class="in reply" id="replyText" spellcheck="true"></textarea>
        <div class="sources" id="rSources"></div>
        <div class="row" style="margin-top:14px">
          <button class="btn primary" type="button" id="copy"><svg class="i" viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>Copy reply</button>
          <button class="btn" type="button" id="again">Try again</button>
          <span class="copied" id="copied">Copied. Paste it into Freshdesk.</span>
        </div>
      </div>
    </div>
  </div>
</section>
