<?php
/** Home: today at a glance. v0.3: design with sample data. */
if ( ! defined( 'ABSPATH' ) ) exit;
$first = wp_get_current_user()->first_name ?: 'there';
?>
<section class="screen" id="s-home">
  <div class="head">
    <div class="eyebrow"><?php echo esc_html( wp_date( 'l, F j' ) ); ?></div>
    <div class="title">Good to see you, <?php echo esc_html( $first ); ?></div>
    <p class="sub">What would you like to do?</p>
  </div>

  <div class="quick">
    <a class="qcard" href="<?php echo esc_url( nab_staff_url( 'reply' ) ); ?>"><?php echo nab_staff_icon( 'message', 22 ); ?><b>Answer an email</b><span>Paste a member's email and get a reply based on our policies</span></a>
    <a class="qcard" href="<?php echo esc_url( nab_staff_url( 'lookup' ) ); ?>"><?php echo nab_staff_icon( 'search', 22 ); ?><b>Look up a member</b><span>Membership, access dates, disputes and requests in one place</span></a>
    <a class="qcard" href="https://nabsolutions.freshdesk.com/a/tickets" target="_blank" rel="noopener"><?php echo nab_staff_icon( 'ticket', 22 ); ?><b>Open Freshdesk</b><span>See and answer member tickets</span></a>
  </div>

  <div class="grid2" style="margin-top:16px">
    <div class="card">
      <div class="card-head"><h2>Recently updated documents</h2><a class="link" href="<?php echo esc_url( nab_staff_url( 'documents' ) ); ?>">All documents</a></div>
      <ul class="tlist">
        <li><a href="<?php echo esc_url( nab_staff_url( 'documents' ) ); ?>"><span class="ficon doc">DOC</span><div><b>Reply Templates</b><small>Updated Nov 15 by Jessica K.</small></div></a></li>
        <li><a href="<?php echo esc_url( nab_staff_url( 'documents' ) ); ?>"><span class="ficon pdf">PDF</span><div><b>Cancellation and Refund Policy</b><small>Updated Nov 12</small></div></a></li>
        <li><a href="<?php echo esc_url( nab_staff_url( 'documents' ) ); ?>"><span class="ficon doc">DOC</span><div><b>PAD and Billing Procedures</b><small>Updated Nov 10</small></div></a></li>
      </ul>
    </div>
    <div class="card">
      <div class="card-head"><h2>From the team</h2><a class="link" href="<?php echo esc_url( nab_staff_url( 'discussions' ) ); ?>">All discussions</a></div>
      <ul class="tlist">
        <li><a href="<?php echo esc_url( nab_staff_url( 'discussions' ) ); ?>"><span class="votes">14</span><div><b>Add a short video on how to use the Lending Finder</b><small>Suggestion · Jessica K. · 8 comments</small></div></a></li>
        <li><a href="<?php echo esc_url( nab_staff_url( 'discussions' ) ); ?>"><span class="votes">9</span><div><b>New wording for refund replies</b><small>Process · Mark T. · 5 comments</small></div></a></li>
        <li><a href="<?php echo esc_url( nab_staff_url( 'discussions' ) ); ?>"><span class="votes">6</span><div><b>Many members can't find the Dispute Center</b><small>Member feedback · Aisha M. · 3 comments</small></div></a></li>
      </ul>
    </div>
  </div>
</section>
