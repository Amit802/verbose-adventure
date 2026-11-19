<?php
/** Home: today at a glance. v0.3: design with sample data. */
if ( ! defined( 'ABSPATH' ) ) exit;
$first = wp_get_current_user()->first_name ?: 'there';
?>
<section class="screen" id="s-home">
  <div class="head">
    <div class="eyebrow"><?php echo esc_html( wp_date( 'l, F j' ) ); ?></div>
    <div class="title">Good to see you, <?php echo esc_html( $first ); ?></div>
    <p class="sub">Here's what's waiting for the team today.</p>
  </div>

  <div class="stats">
    <a class="stat" href="<?php echo esc_url( nab_staff_url( 'tickets' ) ); ?>"><span>Open tickets</span><b>12</b><small>4 new today</small></a>
    <a class="stat" href="<?php echo esc_url( nab_staff_url( 'tickets' ) ); ?>"><span>Assigned to you</span><b>5</b><small>2 waiting on you</small></a>
    <a class="stat warn" href="<?php echo esc_url( nab_staff_url( 'tickets' ) ); ?>"><span>Urgent</span><b>2</b><small>Older than 24 hours</small></a>
    <div class="stat"><span>Average first reply</span><b>3h 20m</b><small>Down from 6h last week</small></div>
  </div>

  <div class="grid2" style="margin-top:16px">
    <div class="card">
      <div class="card-head"><h2>Waiting on you</h2><a class="link" href="<?php echo esc_url( nab_staff_url( 'tickets' ) ); ?>">All tickets</a></div>
      <ul class="tlist">
        <li><a href="<?php echo esc_url( nab_staff_url( 'ticket' ) ); ?>"><span class="prio high"></span><div><b>I want to cancel and get a refund</b><small>Sarah Thompson · Cancellation · 2 hours ago</small></div><span class="badge warn">Open</span></a></li>
        <li><a href="<?php echo esc_url( nab_staff_url( 'ticket' ) ); ?>"><span class="prio high"></span><div><b>A collection agency keeps calling me</b><small>Daniel Roy · Collection Assistance · 5 hours ago</small></div><span class="badge warn">Open</span></a></li>
        <li><a href="<?php echo esc_url( nab_staff_url( 'ticket' ) ); ?>"><span class="prio"></span><div><b>Where do I find loan offers?</b><small>Priya Sharma · Loans · Yesterday</small></div><span class="badge blue">Replied</span></a></li>
      </ul>
    </div>
    <div class="card">
      <div class="card-head"><h2>From the team</h2><a class="link" href="<?php echo esc_url( nab_staff_url( 'discussions' ) ); ?>">All discussions</a></div>
      <ul class="tlist">
        <li><a href="<?php echo esc_url( nab_staff_url( 'discussions' ) ); ?>"><span class="votes">14</span><div><b>Add a short video on how to use the Lending Finder</b><small>Suggestion · Jessica K. · 8 comments</small></div></a></li>
        <li><a href="<?php echo esc_url( nab_staff_url( 'discussions' ) ); ?>"><span class="votes">9</span><div><b>New wording for refund replies</b><small>Process · Mark T. · 5 comments</small></div></a></li>
        <li><a href="<?php echo esc_url( nab_staff_url( 'discussions' ) ); ?>"><span class="votes">6</span><div><b>Many members can't find the Dispute Center</b><small>Member feedback · Aisha M. · 3 comments</small></div></a></li>
      </ul>
      <div class="tip"><?php echo nab_staff_icon( 'spark', 16 ); ?><span><b>This week's top question:</b> "How do I get loan offers?" came up 23 times. A short guide in the welcome email could cut these down.</span></div>
    </div>
  </div>
</section>
