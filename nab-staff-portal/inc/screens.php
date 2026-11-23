<?php
/**
 * The list of staff portal screens. Adding a screen = one entry here + a file
 * in templates/screens/. The sidebar, the page address (/staff/{slug}/) and
 * the permission check all come from this list.
 *
 *   label    sidebar and page title
 *   section  sidebar group
 *   icon     key in nab_staff_icon()
 *   file     templates/screens/{file}.php
 *   cap      'use' or 'manage' (see inc/roles.php)
 *   link     optional: an outside link instead of a screen (no file)
 *   hidden   optional: not shown in the sidebar (e.g. a single ticket page)
 *   parent   optional: sidebar item to highlight for a hidden screen
 *   badge    optional: small count next to the sidebar item
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function nab_staff_screens() {
    static $screens = null;
    if ( $screens !== null ) return $screens;
    $screens = apply_filters( 'nab_staff_screens', [
        'home'        => [ 'label' => 'Home',              'section' => 'Workspace',     'icon' => 'home',    'file' => 'home',        'cap' => 'use' ],
        'reply'       => [ 'label' => 'Reply Assistant',   'section' => 'Customer Care', 'icon' => 'message', 'file' => 'reply',       'cap' => 'use' ],
        'lookup'      => [ 'label' => 'Member Lookup',     'section' => 'Customer Care', 'icon' => 'search',  'file' => 'lookup',      'cap' => 'use' ],
        'documents'   => [ 'label' => 'Documents',         'section' => 'Customer Care', 'icon' => 'file',    'file' => 'documents',   'cap' => 'use' ],
        'discussions' => [ 'label' => 'Team Discussions',  'section' => 'Team',          'icon' => 'chat',    'file' => 'discussions', 'cap' => 'use' ],
        'team'        => [ 'label' => 'People',            'section' => 'Team',          'icon' => 'users',   'file' => 'team',        'cap' => 'manage' ],
        'freshdesk'   => [ 'label' => 'Freshdesk tickets', 'section' => 'Shortcuts',     'icon' => 'ticket',  'cap' => 'use', 'link' => 'https://nabsolutions.freshdesk.com/a/tickets' ],
        'access'      => [ 'label' => 'Member Access',     'section' => 'Shortcuts',     'icon' => 'user',    'cap' => 'manage', 'link' => admin_url( 'users.php?page=nab-member-access' ) ],
    ] );
    return $screens;
}

/** Address of a screen. */
function nab_staff_url( $slug = '' ) {
    return home_url( '/staff/' . ( $slug ? $slug . '/' : '' ) );
}

/** Small line icons (same family as the member portal). */
function nab_staff_icon( $name, $size = 18 ) {
    $paths = [
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8"/><path d="M8 13h5"/>',
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'file'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h6"/>',
        'ticket'  => '<path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 0 0 4v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 0 0-4z"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'home'    => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9v11h14V9"/><path d="M10 20v-6h4v6"/>',
        'chat'    => '<path d="M17 8h2a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-1v3l-3-3h-4a2 2 0 0 1-2-2v-1"/><path d="M3 4h11a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2H8l-3 3v-3H3a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>',
        'up'      => '<path d="m6 15 6-6 6 6"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'spark'   => '<path d="m12 3-1.9 5.8L4 10l5 3.6L7.6 20 12 16.5 16.4 20 15 13.6 20 10l-6.1-1.2z"/>',
        'plus'    => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'note'    => '<path d="M4 4h16v12H8l-4 4z"/>',
        'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M22 20c0-2.6-1.6-4.4-4-5.1"/>',
        'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    ];
    return '<svg class="i" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" aria-hidden="true">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}
