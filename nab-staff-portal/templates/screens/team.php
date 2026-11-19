<?php
/** Team & Assignment (managers): who is online, workload, and how new tickets are shared out. v0.4: design with sample data. */
if ( ! defined( 'ABSPATH' ) ) exit;
$team = [
  [ 'JK', 'Jessica K.', 'Online', 'good', 5, 2, 'All topics', '2h 10m' ],
  [ 'MT', 'Mark T.', 'Online', 'good', 4, 0, 'Billing, Cancellation', '3h 05m' ],
  [ 'AM', 'Aisha M.', 'Online', 'good', 6, 1, 'Disputes, Collection Assistance', '4h 40m' ],
  [ 'RP', 'Rachel P.', 'Away', 'warn', 2, 0, 'All topics', '2h 50m' ],
];
?>
<section class="screen" id="s-team">
  <div class="head">
    <div class="eyebrow">Team & assignment</div>
    <div class="title">Who's handling what</div>
    <p class="sub">See each person's workload and choose how new tickets are shared out.</p>
  </div>

  <div class="card" style="padding:0;margin-bottom:16px">
    <table class="ttable">
      <thead><tr><th>Team member</th><th>Status</th><th>Open tickets</th><th>Overdue</th><th>Topics</th><th>Avg first reply</th></tr></thead>
      <tbody>
      <?php foreach ( $team as $m ) : ?>
        <tr style="cursor:default">
          <td><span class="who"><span class="av xs"><?php echo esc_html( $m[0] ); ?></span><b><?php echo esc_html( $m[1] ); ?></b></span></td>
          <td><span class="badge <?php echo esc_attr( $m[3] ); ?>"><?php echo esc_html( $m[2] ); ?></span></td>
          <td><div class="load"><span style="width:<?php echo (int) min( 100, $m[4] * 12.5 ); ?>%"></span></div><small class="muted"><?php echo (int) $m[4]; ?> of 8</small></td>
          <td class="<?php echo $m[5] ? 'late' : 'muted'; ?>"><?php echo (int) $m[5]; ?></td>
          <td class="muted"><?php echo esc_html( $m[6] ); ?></td>
          <td><?php echo esc_html( $m[7] ); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="grid2">
    <div class="card">
      <h2>How new tickets are assigned</h2>
      <p class="hint">Pick one. You can change it any time.</p>
      <label class="opt"><input type="radio" name="mode" checked><span><b>Share them out automatically</b><small>New tickets go to whoever is online, one after another. People who are away or have hit their limit are skipped.</small></span></label>
      <label class="opt"><input type="radio" name="mode"><span><b>Team picks them up</b><small>New tickets wait in Unassigned until someone clicks "Take it".</small></span></label>
      <label class="opt"><input type="radio" name="mode"><span><b>Manager assigns</b><small>New tickets wait for a manager to assign them.</small></span></label>
    </div>
    <div class="card">
      <h2>Rules</h2>
      <p class="hint">These apply whichever way tickets are assigned.</p>
      <label class="f">Most open tickets per person<input class="in" value="8"></label>
      <label class="f">Mark a ticket overdue after<select class="in"><option>24 hours without a reply</option><option>12 hours without a reply</option><option>48 hours without a reply</option></select></label>
      <label class="f" style="margin-bottom:0">Send topics to the right people<select class="in"><option>Yes, use each person's topics</option><option>No, any topic to anyone</option></select></label>
    </div>
  </div>
</section>
