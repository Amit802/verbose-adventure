<?php
/** Team discussions: suggestions, process ideas and member feedback, with votes and comments. v0.3: design with sample data. */
if ( ! defined( 'ABSPATH' ) ) exit;
$topics = [
  [ 14, 'Add a short video on how to use the Lending Finder', 'Suggestion', 'Jessica K.', '8 comments', 'Planned', 'blue', 'A lot of members email asking where the loan offers are. A 1 minute video in the welcome email and on the LFP page could save us a lot of tickets.' ],
  [ 9, 'New wording for refund replies', 'Process', 'Mark T.', '5 comments', 'Discussing', 'warn', 'Our current refund reply sounds a bit cold. I wrote a softer version, let me know what you think before we add it to the templates.' ],
  [ 6, 'Many members can\'t find the Dispute Center', 'Member feedback', 'Aisha M.', '3 comments', 'Open', 'warn', 'Three members this week asked how to dispute something. Maybe a button on the dashboard home?' ],
  [ 4, 'Weekend coverage for urgent tickets', 'Team', 'Matt', '2 comments', 'Done', 'good', 'Starting next week, one person checks urgent tickets on Saturday morning. Rota is in the team sheet.' ],
];
?>
<section class="screen" id="s-discussions">
  <div class="head head-row">
    <div>
      <div class="eyebrow">Team discussions</div>
      <div class="title">Ideas and suggestions</div>
      <p class="sub">Share what would make our work easier or help members more. Vote on the ideas you agree with.</p>
    </div>
    <button class="btn primary" type="button" id="newTopic"><?php echo nab_staff_icon( 'plus', 16 ); ?>New topic</button>
  </div>

  <div class="card new-topic" id="newTopicBox" hidden>
    <h2>Start a topic</h2>
    <label class="f">Title<input class="in" placeholder="What's it about?"></label>
    <label class="f">Type<select class="in"><option>Suggestion</option><option>Process</option><option>Member feedback</option><option>Question</option><option>Team</option></select></label>
    <label class="f">Details<textarea class="in" style="min-height:110px" placeholder="Explain the idea or the problem"></textarea></label>
    <div class="row"><button class="btn primary" type="button">Post topic</button><button class="btn" type="button" id="cancelTopic">Cancel</button></div>
  </div>

  <div class="chips" style="margin-bottom:14px">
    <button class="chip on" type="button">All</button><button class="chip" type="button">Suggestions</button><button class="chip" type="button">Process</button><button class="chip" type="button">Member feedback</button><button class="chip" type="button">Questions</button>
  </div>

  <div class="topics">
  <?php foreach ( $topics as $i => $t ) : ?>
    <div class="card topic">
      <button class="vote<?php echo $i === 0 ? ' on' : ''; ?>" type="button" aria-label="Vote"><?php echo nab_staff_icon( 'up', 16 ); ?><b><?php echo (int) $t[0]; ?></b></button>
      <div class="topic-body">
        <div class="row" style="gap:8px;margin-bottom:4px"><b class="topic-title"><?php echo esc_html( $t[1] ); ?></b><span class="badge <?php echo esc_attr( $t[6] ); ?>"><?php echo esc_html( $t[5] ); ?></span></div>
        <p><?php echo esc_html( $t[7] ); ?></p>
        <div class="topic-meta"><span class="tag"><?php echo esc_html( $t[2] ); ?></span><span><?php echo esc_html( $t[3] ); ?></span><span><?php echo esc_html( $t[4] ); ?></span></div>
        <?php if ( $i === 0 ) : ?>
        <div class="comments">
          <div class="msg"><div class="av sm">MT</div><div class="bubble"><div class="meta"><b>Mark T.</b><span>Yesterday</span></div>Agree, I answer this one almost every day.</div></div>
          <div class="msg"><div class="av sm">MA</div><div class="bubble"><div class="meta"><b>Matt</b><span>Today</span></div>Love it. Let's do it next month, Amit can add it to the welcome email.</div></div>
          <div class="row"><input class="in sm" style="flex:1" placeholder="Write a comment"><button class="btn small" type="button">Comment</button></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</section>
