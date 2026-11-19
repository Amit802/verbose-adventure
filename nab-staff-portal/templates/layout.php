<?php
/**
 * Page shell: sidebar, top bar and the current screen.
 * The sidebar is built from nab_staff_screens(); items the person can't use are hidden.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$current = $GLOBALS['nab_staff_screen'] ?? 'home';
$screens = nab_staff_screens();
$screen  = $screens[ $current ];
$user    = wp_get_current_user();
$name    = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
$parts   = preg_split( '/\s+/', $name );
$initial = strtoupper( substr( $parts[0] ?? '', 0, 1 ) . substr( $parts[1] ?? '', 0, 1 ) ) ?: 'NS';
$role    = nab_staff_can( 'manage' ) ? 'Care Manager' : 'Customer Care';

$sections = [];
foreach ( $screens as $slug => $s ) {
    if ( ! empty( $s['hidden'] ) || ! nab_staff_can( $s['cap'] ?? 'use' ) ) continue;
    $sections[ $s['section'] ][ $slug ] = $s;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $screen['label'] ); ?> | NAB Staff Portal</title>
<link rel="stylesheet" href="<?php echo esc_url( NAB_STAFF_URL . 'assets/css/staff.css?v=' . NAB_STAFF_VERSION ); ?>">
</head>
<body>
<div class="app<?php echo nab_staff_can( 'manage' ) ? ' can-manage' : ''; ?>">
  <aside class="side">
    <div class="brand"><div class="brand-mark">NAB</div><div><b>NAB Solutions</b><span>Staff Portal</span></div></div>
    <nav class="nav" aria-label="Staff portal">
      <?php foreach ( $sections as $label => $items ) : ?>
      <div class="nav-label"><?php echo esc_html( $label ); ?></div>
        <?php foreach ( $items as $slug => $s ) :
          $is_link = ! empty( $s['link'] );
          $href    = $is_link ? $s['link'] : nab_staff_url( $slug === 'home' ? '' : $slug );
          $on      = $slug === $current || $slug === ( $screen['parent'] ?? '' ); ?>
      <a href="<?php echo esc_url( $href ); ?>"<?php echo $on ? ' class="on" aria-current="page"' : ''; ?><?php echo $is_link ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo nab_staff_icon( $s['icon'] ); ?><?php echo esc_html( $s['label'] ); ?><?php if ( ! empty( $s['badge'] ) ) : ?><span class="count"><?php echo esc_html( $s['badge'] ); ?></span><?php endif; ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <div class="av"><?php echo esc_html( $initial ); ?></div>
      <div style="flex:1;min-width:0"><b><?php echo esc_html( $name ); ?></b><span><?php echo esc_html( $role ); ?></span></div>
      <a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" title="Log out" aria-label="Log out" style="color:#fff;opacity:.8;display:flex"><?php echo nab_staff_icon( 'logout' ); ?></a>
    </div>
  </aside>

  <main class="main">
    <header class="top"><h1><?php echo esc_html( $screen['label'] ); ?></h1><span class="pill">Preview, sample data</span></header>
    <div class="content">
      <?php include NAB_STAFF_DIR . 'templates/screens/' . basename( $screen['file'] ) . '.php'; ?>
    </div>
  </main>
</div>
<script src="<?php echo esc_url( NAB_STAFF_URL . 'assets/js/staff.js?v=' . NAB_STAFF_VERSION ); ?>"></script>
</body>
</html>
