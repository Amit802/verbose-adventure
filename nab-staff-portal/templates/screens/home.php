<?php
/** Home: shortcuts, recently updated documents and the team's top topics. */
if ( ! defined( 'ABSPATH' ) ) exit;
$first  = wp_get_current_user()->first_name ?: 'there';
$docs   = array_slice( nab_staff_docs(), 0, 3 );
$topics = array_slice( array_filter( nab_staff_topics(), function ( $t ) { return $t['status'] !== 'done'; } ), 0, 3 );
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
      <?php if ( ! $docs ) : ?>
        <p class="empty-note">No documents yet. <?php echo nab_staff_can( 'manage' ) ? 'Add our policies and reply templates so the Reply Assistant can use them.' : 'Your manager will add our policies and templates here.'; ?></p>
      <?php else : ?>
      <ul class="tlist">
        <?php foreach ( $docs as $p ) : $d = nab_staff_doc_view( $p ); ?>
        <li><a href="<?php echo esc_url( nab_staff_url( 'documents' ) ); ?>"><span class="ficon <?php echo $d['ext'] === 'PDF' ? 'pdf' : 'doc'; ?>"><?php echo esc_html( $d['ext'] === 'DOCX' ? 'DOC' : $d['ext'] ); ?></span><div><b><?php echo esc_html( $d['title'] ); ?></b><small>Updated <?php echo esc_html( $d['updated'] ); ?> by <?php echo esc_html( $d['by'] ); ?></small></div></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h2>From the team</h2><a class="link" href="<?php echo esc_url( nab_staff_url( 'discussions' ) ); ?>">All discussions</a></div>
      <?php if ( ! $topics ) : ?>
        <p class="empty-note">No open topics. Have an idea that would help the team or our members? <a class="link" href="<?php echo esc_url( nab_staff_url( 'discussions' ) ); ?>">Start a topic</a></p>
      <?php else : ?>
      <ul class="tlist">
        <?php foreach ( $topics as $t ) : ?>
        <li><a href="<?php echo esc_url( nab_staff_url( 'discussions' ) . '#topic-' . $t['id'] ); ?>"><span class="votes"><?php echo (int) $t['votes']; ?></span><div><b><?php echo esc_html( $t['title'] ); ?></b><small><?php echo esc_html( $t['typeLabel'] . ' · ' . $t['author'] . ' · ' . count( $t['comments'] ) . ( count( $t['comments'] ) === 1 ? ' comment' : ' comments' ) ); ?></small></div></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</section>
