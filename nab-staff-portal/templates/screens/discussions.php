<?php
/** Team discussions: topics with votes and comments. Rendered by staff.js from the data below. */
if ( ! defined( 'ABSPATH' ) ) exit;
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

  <form class="card new-topic" id="newTopicBox" hidden autocomplete="off">
    <h2>Start a topic</h2>
    <label class="f">Title<input class="in" name="title" maxlength="150" placeholder="What's it about?" required></label>
    <label class="f">Type<select class="in" name="type"><?php foreach ( nab_staff_topic_types() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
    <label class="f">Details<textarea class="in" name="body" maxlength="5000" style="min-height:110px" placeholder="Explain the idea or the problem"></textarea></label>
    <div class="row"><button class="btn primary" type="submit">Post topic</button><button class="btn" type="button" id="cancelTopic">Cancel</button><span class="form-msg" id="topicMsg"></span></div>
  </form>

  <div class="chips" id="topicFilter" style="margin-bottom:14px">
    <button class="chip on" type="button" data-type="">All</button>
    <?php foreach ( nab_staff_topic_types() as $k => $l ) : ?><button class="chip" type="button" data-type="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></button><?php endforeach; ?>
  </div>

  <div class="topics" id="topics"></div>
  <p class="empty-note" id="topicsEmpty" hidden>No topics yet. Start the first one.</p>
</section>
<script>window.NABSTAFF_TOPICS=<?php echo wp_json_encode( [ 'topics' => nab_staff_topics(), 'statuses' => array_map( function ( $s ) { return $s[0]; }, nab_staff_topic_statuses() ) ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;</script>
