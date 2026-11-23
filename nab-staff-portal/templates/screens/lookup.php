<?php
/** Member Lookup: search, then one member's details, disputes, requests and Freshdesk tickets. */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="screen" id="s-lookup">
  <div class="head">
    <div class="eyebrow">Member lookup</div>
    <div class="title">Everything about a member, in one place</div>
    <p class="sub">Search by email, name or Member ID to see their membership, access, disputes and requests.</p>
  </div>
  <form class="search" id="lookupForm" autocomplete="off">
    <input class="in" name="q" id="lookupQ" placeholder="Email, name or Member ID" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ) ); ?>">
    <button class="btn primary" type="submit">Search</button>
  </form>
  <div class="err-box" id="lkErr" hidden></div>
  <div class="card results" id="lkResults" hidden></div>

  <div class="profile" id="lkProfile" hidden>
    <div class="card">
      <div class="ph"><div class="av" id="pInit"></div><div><b id="pName"></b><span id="pEmail"></span></div></div>
      <dl class="kv">
        <dt>Member ID</dt><dd id="pId"></dd>
        <dt>Phone</dt><dd id="pPhone"></dd>
        <dt>Member since</dt><dd id="pSince"></dd>
        <dt>Membership</dt><dd><span class="badge" id="pMembership"></span></dd>
        <dt>Portal access</dt><dd id="pAccess"></dd>
        <dt>Last login</dt><dd id="pLogin"></dd>
        <dt>Credit score</dt><dd id="pScore"></dd>
      </dl>
      <div class="row" style="margin-top:18px">
        <button class="btn small" type="button" id="pResend">Resend login email</button>
        <a class="btn small" id="pReply" href="#">Answer their email</a>
        <a class="btn small" id="pAccessLink" href="#" target="_blank" rel="noopener" hidden>Open in Member Access</a>
      </div>
      <p class="ok-note" id="pMsg" hidden></p>
    </div>
    <div class="card">
      <div class="sec">Dispute letters</div>
      <ul class="list" id="pDisputes"></ul>
      <div class="sec">Collection Assistance</div>
      <ul class="list" id="pCollections"></ul>
      <div class="sec">Freshdesk tickets</div>
      <ul class="list" id="pTickets"><li class="muted">Loading…</li></ul>
    </div>
  </div>
</section>
