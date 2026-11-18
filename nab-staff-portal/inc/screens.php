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
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function nab_staff_screens() {
    static $screens = null;
    if ( $screens !== null ) return $screens;
    $screens = apply_filters( 'nab_staff_screens', [
        'reply'     => [ 'label' => 'Reply Assistant', 'section' => 'Customer Care', 'icon' => 'message', 'file' => 'reply',     'cap' => 'use' ],
        'lookup'    => [ 'label' => 'Member Lookup',   'section' => 'Customer Care', 'icon' => 'search',  'file' => 'lookup',    'cap' => 'use' ],
        'documents' => [ 'label' => 'Documents',       'section' => 'Customer Care', 'icon' => 'file',    'file' => 'documents', 'cap' => 'use' ],
        'freshdesk' => [ 'label' => 'Freshdesk tickets', 'section' => 'Shortcuts', 'icon' => 'ticket', 'cap' => 'use', 'link' => 'https://nabsolutions.freshdesk.com/a/tickets' ],
        'access'    => [ 'label' => 'Member Access',   'section' => 'Shortcuts', 'icon' => 'user', 'cap' => 'manage', 'link' => admin_url( 'users.php?page=nab-member-access' ) ],
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
        'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    ];
    return '<svg class="i" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" aria-hidden="true">' . ( $paths[ $name ] ?? '' ) . '</svg>';
}
