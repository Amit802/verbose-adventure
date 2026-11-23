<?php
/** People: managers add staff, change their role, resend the invite or remove access. */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<section class="screen" id="s-team">
  <div class="head">
    <div class="eyebrow">People</div>
    <div class="title">Who can use the staff portal</div>
    <p class="sub">Add someone and they get an email to set their password. Customer Care can use everything except People and changing documents. Care Managers can do both.</p>
  </div>

  <form class="card" id="teamAdd" autocomplete="off">
    <h2>Add a person</h2>
    <div class="team-form">
      <label class="f">First name<input class="in" name="first" maxlength="60" required></label>
      <label class="f">Last name<input class="in" name="last" maxlength="60"></label>
      <label class="f">Email<input class="in" type="email" name="email" maxlength="100" placeholder="Their work email" required></label>
      <label class="f">Role<select class="in" name="role"><?php foreach ( nab_staff_team_roles() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="row"><button class="btn primary" type="submit"><?php echo nab_staff_icon( 'plus', 16 ); ?>Add and send invite</button><span class="form-msg" id="teamMsg" role="status"></span></div>
    <p class="note">Use a different email from any member account. If someone leaves, click Remove. They're logged out straight away and can't log in again.</p>
  </form>

  <div class="card">
    <h2>Team</h2>
    <table id="teamTable">
      <thead><tr><th>Name</th><th>Role</th><th>Last login</th><th style="text-align:right"></th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</section>
<script>window.NABSTAFF_TEAM=<?php echo wp_json_encode( [ 'people' => nab_staff_team(), 'roles' => nab_staff_team_roles() ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;</script>
