<?php
/**
 * Tickets inbox. Tickets live in our portal (not Freshdesk): members raise them
 * from the member portal (Support Center, Collection Assistance), and staff can
 * create one for a phone call or email. v0.3: design with sample data.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$t = nab_staff_url( 'ticket' );
$rows = [
  [ '#1042', 'I want to cancel and get a refund', 'Sarah Thompson', 'Cancellation', 'Open', 'warn', 'High', 'Unassigned', '2h ago' ],
  [ '#1041', 'A collection agency keeps calling me', 'Daniel Roy', 'Collection Assistance', 'Open', 'warn', 'High', 'You', '5h ago' ],
  [ '#1040', 'Can\'t log in to the portal', 'Emily Tremblay', 'Login', 'Waiting on member', 'blue', 'Normal', 'Mark T.', 'Yesterday' ],
  [ '#1039', 'Where do I find loan offers?', 'Priya Sharma', 'Loans', 'Replied', 'blue', 'Normal', 'You', 'Yesterday' ],
  [ '#1038', 'My PAD payment came out twice', 'Michael Brown', 'Billing', 'Open', 'warn', 'High', 'Aisha M.', '2 days ago' ],
  [ '#1037', 'How long does a dispute take?', 'David Chen', 'Disputes', 'Resolved', 'good', 'Low', 'Jessica K.', '3 days ago' ],
];
?>
<section class="screen" id="s-tickets">
  <div class="head head-row">
    <div>
      <div class="eyebrow">Tickets</div>
      <div class="title">Member requests</div>
      <p class="sub">Every question from the member portal lands here. Open a ticket to reply with help from the AI.</p>
    </div>
    <button class="btn primary" type="button"><?php echo nab_staff_icon( 'plus', 16 ); ?>New ticket</button>
  </div>

  <div class="card" style="padding:0">
    <div class="filters">
      <div class="chips" role="tablist">
        <button class="chip on" type="button">All <span>12</span></button>
        <button class="chip" type="button">Mine <span>5</span></button>
        <button class="chip" type="button">Unassigned <span>3</span></button>
        <button class="chip" type="button">Urgent <span>2</span></button>
        <button class="chip" type="button">Resolved</button>
      </div>
      <div class="filter-right">
        <select class="in sm"><option>All topics</option><option>Cancellation</option><option>Billing</option><option>Loans</option><option>Disputes</option><option>Collection Assistance</option><option>Login</option></select>
        <input class="in sm" placeholder="Search tickets or members">
      </div>
    </div>
    <table class="ttable">
      <thead><tr><th>Ticket</th><th>Member</th><th>Topic</th><th>Status</th><th>Priority</th><th>Assigned</th><th>Updated</th></tr></thead>
      <tbody>
      <?php foreach ( $rows as $r ) : ?>
        <tr onclick="location.href='<?php echo esc_url( $t ); ?>'">
          <td><a href="<?php echo esc_url( $t ); ?>"><span class="tid"><?php echo esc_html( $r[0] ); ?></span><b><?php echo esc_html( $r[1] ); ?></b></a></td>
          <td><?php echo esc_html( $r[2] ); ?></td>
          <td><span class="tag"><?php echo esc_html( $r[3] ); ?></span></td>
          <td><span class="badge <?php echo esc_attr( $r[5] ); ?>"><?php echo esc_html( $r[4] ); ?></span></td>
          <td><span class="prio-t <?php echo esc_attr( strtolower( $r[6] ) ); ?>"><?php echo esc_html( $r[6] ); ?></span></td>
          <td><?php echo esc_html( $r[7] ); ?></td>
          <td class="muted"><?php echo esc_html( $r[8] ); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
