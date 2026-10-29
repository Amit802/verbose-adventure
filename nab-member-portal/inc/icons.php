<?php
/**
 * NAB Member Portal — Icons (v1.9.1)
 *
 * One consistent line-icon set for the whole portal (replaces emoji).
 * Source: Lucide (https://lucide.dev) — ISC licence, see
 * assets/js/vendor/lucide-LICENSE.txt. Only the icons the portal uses
 * are copied here; to add one, paste the inner markup of
 * lucide-static/icons/<name>.svg as a new entry.
 *
 * PHP:  echo nab_portal_icon( 'piggy-bank' );            // 20px
 *       echo nab_portal_icon( 'car', 16, 'my-class' );
 * JS:   window.nabIcon( 'car' ) on pages that call nab_portal_print_icons_js()
 *
 * @package NAB_Member_Portal
 * @since   1.9.1
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function nab_portal_icon_paths() {
    static $icons = null;
    if ( $icons !== null ) return $icons;
    $icons = apply_filters( 'nab_portal_icon_paths', [
        'house' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'file-text' => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'chart-column' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'trending-up' => '<path d="M16 7h6v6"/><path d="m22 7-8.5 8.5-5-5L2 17"/>',
        'trending-down' => '<path d="M16 17h6v-6"/><path d="m22 17-8.5-8.5-5 5L2 7"/>',
        'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'calendar-days' => '<path d="M8 2v3"/><path d="M16 2v3"/><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M8 13h.01"/><path d="M12 13h.01"/><path d="M16 13h.01"/><path d="M8 17h.01"/><path d="M12 17h.01"/><path d="M16 17h.01"/>',
        'message-square-text' => '<path d="M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"/><path d="M7 11h10"/><path d="M7 15h6"/><path d="M7 7h8"/>',
        'life-buoy' => '<circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/><circle cx="12" cy="12" r="4"/>',
        'landmark' => '<path d="M10 18v-7"/><path d="M11.119 2.205a2 2 0 0 1 1.762 0l7.84 3.846A.5.5 0 0 1 20.5 7h-17a.5.5 0 0 1-.22-.949z"/><path d="M14 18v-7"/><path d="M18 18v-7"/><path d="M3 22h18"/><path d="M6 18v-7"/>',
        'car' => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>',
        'credit-card' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/><path d="M6 14h2"/>',
        'graduation-cap' => '<path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/>',
        'book-open' => '<path d="M12 5v16"/><path d="M20.001 19A2 2 0 0022 17V5a2 2 0 00-1.999-2L16 3.002A5 5 0 0012 5a5 5 0 00-4-2H4a2 2 0 00-2 2v12a2 2 0 001.999 2H8a5 5 0 014 2 5 5 0 014-2z"/>',
        'file-pen-line' => '<path d="M14.364 13.634a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506l4.013-4.009a1 1 0 0 0-3.004-3.004z"/><path d="M14.487 7.858A1 1 0 0 1 14 7V2"/><path d="M20 19.645V20a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l2.516 2.516"/><path d="M8 18h1"/>',
        'piggy-bank' => '<path d="M11 17h3v2a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-3a3.16 3.16 0 0 0 2-2h1a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1h-1a5 5 0 0 0-2-4V3a4 4 0 0 0-3.2 1.6l-.3.4H11a6 6 0 0 0-6 6v1a5 5 0 0 0 2 4v3a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1z"/><path d="M16 10h.01"/><path d="M2 8v1a2 2 0 0 0 2 2h1"/>',
        'map' => '<path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path d="M15 5.764v15"/><path d="M9 3.236v15"/>',
        'file-check' => '<path d="M6 22a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.704.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2z"/><path d="M14 2v5a1 1 0 0 0 1 1h5"/><path d="m9 15 2 2 4-4"/>',
        'log-out' => '<path d="m16 17 5-5-5-5"/><path d="M21 12H9"/><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>',
        'menu' => '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
        'search' => '<path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/>',
        'bell' => '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'calendar' => '<path d="M8 2v3"/><path d="M16 2v3"/><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'wallet' => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
        'banknote' => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        'chart-line' => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m19 9-5 5-4-4-3 3"/>',
        'chart-pie' => '<path d="M21 12c.552 0 1.005-.449.95-.998a10 10 0 0 0-8.953-8.951c-.55-.055-.998.398-.998.95v8a1 1 0 0 0 1 1z"/><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>',
        'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'play' => '<path d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'shopping-cart' => '<path d="m2.05 2.05 1.099-.028a1 1 0 0 1 1.008.815l2.69 14.347A1 1 0 0 0 7.83 18H18"/><path d="M4.563 5h16.435a1 1 0 0 1 .981 1.204l-1.026 6.226A2 2 0 0 1 18.962 14H6.25"/><circle cx="18" cy="20" r="2"/><circle cx="8" cy="20" r="2"/>',
        'shopping-bag' => '<path d="M16 10a4 4 0 0 1-8 0"/><path d="M3.103 6.034h17.794"/><path d="M3.4 5.467a2 2 0 0 0-.4 1.2V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.667a2 2 0 0 0-.4-1.2l-2-2.667A2 2 0 0 0 17 2H7a2 2 0 0 0-1.6.8z"/>',
        'heart-pulse' => '<path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/><path d="M3.22 13H9.5l.5-1 2 4.5 2-7 1.5 3.5h5.27"/>',
        'ellipsis' => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        'hand-coins' => '<path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/>',
        'gem' => '<path d="M10.5 3 8 9l4 13 4-13-2.5-6"/><path d="M17 3a2 2 0 0 1 1.6.8l3 4a2 2 0 0 1 .013 2.382l-7.99 10.986a2 2 0 0 1-3.247 0l-7.99-10.986A2 2 0 0 1 2.4 7.8l2.998-3.997A2 2 0 0 1 7 3z"/><path d="M2 9h20"/>',
        'key-round' => '<path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>',
        'scale' => '<path d="M12 3v18"/><path d="m19 8 3 8a5 5 0 0 1-6 0zV7"/><path d="M3 7h1a17 17 0 0 0 8-2 17 17 0 0 0 8 2h1"/><path d="m5 8 3 8a5 5 0 0 1-6 0zV7"/><path d="M7 21h10"/>',
        'arrow-up' => '<path d="m5 12 7-7 7 7"/><path d="M12 19V5"/>',
        'maximize-2' => '<path d="M15 3h6v6"/><path d="m21 3-7 7"/><path d="m3 21 7-7"/><path d="M9 21H3v-6"/>',
        'square-pen' => '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/>',
        'pencil' => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
        'trash-2' => '<path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'briefcase' => '<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>',
        'receipt' => '<path d="M12 17V7"/><path d="M16 8h-6a2 2 0 0 0 0 4h4a2 2 0 0 1 0 4H8"/><path d="M4 3a1 1 0 0 1 1-1 1.3 1.3 0 0 1 .7.2l.933.6a1.3 1.3 0 0 0 1.4 0l.934-.6a1.3 1.3 0 0 1 1.4 0l.933.6a1.3 1.3 0 0 0 1.4 0l.933-.6a1.3 1.3 0 0 1 1.4 0l.934.6a1.3 1.3 0 0 0 1.4 0l.933-.6A1.3 1.3 0 0 1 19 2a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1 1.3 1.3 0 0 1-.7-.2l-.933-.6a1.3 1.3 0 0 0-1.4 0l-.934.6a1.3 1.3 0 0 1-1.4 0l-.933-.6a1.3 1.3 0 0 0-1.4 0l-.933.6a1.3 1.3 0 0 1-1.4 0l-.934-.6a1.3 1.3 0 0 0-1.4 0l-.933.6a1.3 1.3 0 0 1-.7.2 1 1 0 0 1-1-1z"/>',
        'gauge' => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'download' => '<path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/>',
        'circle-dollar-sign' => '<circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/>',
        'route' => '<circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'megaphone' => '<path d="M11 6a13 13 0 0 0 8.4-2.8A1 1 0 0 1 21 4v12a1 1 0 0 1-1.6.8A13 13 0 0 0 11 14H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2z"/><path d="M6 14a12 12 0 0 0 2.4 7.2 2 2 0 0 0 3.2-2.4A8 8 0 0 1 10 14"/><path d="M8 6v8"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'star' => '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>',
        'trophy' => '<path d="M10 14.66V17a1 1 0 0 1-1 1 2 2 0 0 0-2 2v2"/><path d="M14 14.66V17a1 1 0 0 0 1 1 2 2 0 0 1 2 2v2"/><path d="M17.916 10H19.5A2.5 2.5 0 0 0 22 7.5V5a1 1 0 0 0-1-1h-3"/><path d="M4 22h16"/><path d="M6 9a6 6 0 0 0 12 0V3a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1z"/><path d="M6.084 10H4.5A2.5 2.5 0 0 1 2 7.5V5a1 1 0 0 1 1-1h3"/>',
        'pause' => '<rect x="14" y="3" width="5" height="18" rx="1"/><rect x="5" y="3" width="5" height="18" rx="1"/>',
        'circle-x' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        'circle-check' => '<circle cx="12" cy="12" r="10"/><path d="m16 9-5.5 5.5L8 12"/>',
        'triangle-alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    ] );
    return $icons;
}

/**
 * Inline SVG icon. Decorative (aria-hidden) — always pair with visible text
 * or an aria-label on the parent control.
 */
function nab_portal_icon( $name, $size = 20, $class = '' ) {
    $paths = nab_portal_icon_paths();
    if ( ! isset( $paths[ $name ] ) ) return '';
    return sprintf(
        '<svg class="nab-i%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
        $class ? ' ' . esc_attr( $class ) : '', (int) $size, (int) $size, $paths[ $name ]
    );
}

/* ═══════════════════════════════════════════════════════════
   ICON TILES (v1.13.0) — a white icon on a soft gradient tile, like
   app icons. Used for every tool, category and account type.
   PHP: echo nab_portal_icon_tile( 'wallet', 'green', 40 );
   JS:  window.nabTile( 'wallet', 'green', 40 ) (nab_portal_print_icons_js)
   ═══════════════════════════════════════════════════════════ */
function nab_portal_tile_tones() {
    return apply_filters( 'nab_portal_tile_tones', [
        'blue'   => [ '#5AA9FA', '#1C6ED8' ],
        'navy'   => [ '#3F75AE', '#0B3A6B' ],
        'indigo' => [ '#8C97FF', '#4F46E5' ],
        'purple' => [ '#B096FF', '#7044E6' ],
        'pink'   => [ '#FF8FC4', '#DD3E87' ],
        'red'    => [ '#FF7E83', '#DC2F38' ],
        'orange' => [ '#FFAA63', '#F06A0A' ],
        'yellow' => [ '#FFD75E', '#EFA70A' ],
        'green'  => [ '#55D892', '#14A058' ],
        'teal'   => [ '#45DCD2', '#0B9E9C' ],
        'slate'  => [ '#A9B6C8', '#66768C' ],
        'gold'   => [ '#F3D68F', '#B98A23' ],
    ] );
}

function nab_portal_icon_tile( $icon, $tone = 'blue', $size = 40, $class = '' ) {
    if ( ! isset( nab_portal_tile_tones()[ $tone ] ) ) $tone = 'blue';
    $glyph = (int) round( $size * 0.5 );
    $svg   = str_replace( 'stroke-width="1.75"', 'stroke-width="2"', nab_portal_icon( $icon, $glyph ) );
    return '<span class="nab-tile nab-tile-' . esc_attr( $tone ) . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" style="--tile:' . (int) $size . 'px" aria-hidden="true">' . $svg . '</span>';
}

/**
 * NABi — the NAB AI Assistant's mark (v1.13.0): a white "N" monogram with a
 * gold spark on a deep navy tile with a fine gold rim. The colours live in
 * CSS (.nab-nabi), so the same markup works from PHP and JS
 * (window.NabAI.mark( size ) in nab-ai.js builds the identical string).
 */
function nab_portal_nabi_mark( $size = 40, $class = '' ) {
    return '<span class="nab-nabi' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" style="--nabi:' . (int) $size . 'px" aria-hidden="true">'
        . '<svg viewBox="0 0 48 48" width="100%" height="100%" focusable="false">'
        . '<path class="nab-nabi-n" d="M15 33.5V15.5l15.5 18V15.5"/>'
        . '<path class="nab-nabi-spark" d="M37.5 6.5c.45 2.9 2.1 4.55 5 5-2.9.45-4.55 2.1-5 5-.45-2.9-2.1-4.55-5-5 2.9-.45 4.55-2.1 5-5z"/>'
        . '</svg></span>';
}

/** Tile styles. Printed once per page (wp_head on portal pages, and the sidebar as a fallback). */
function nab_portal_tile_css() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    $tones = '';
    foreach ( nab_portal_tile_tones() as $k => $c ) $tones .= '.nab-tile-' . $k . '{--t1:' . $c[0] . ';--t2:' . $c[1] . '}';
    echo '<style id="nab-tile-css">'
        . '.nab-tile{--tile:40px;--t1:#5AA9FA;--t2:#1C6ED8;position:relative;display:inline-flex;align-items:center;justify-content:center;flex:none;'
        . 'width:var(--tile);height:var(--tile);min-width:var(--tile);border-radius:calc(var(--tile) * .3);color:#fff;overflow:hidden;'
        . 'background:linear-gradient(160deg,var(--t1) 0%,var(--t2) 100%);'
        . 'box-shadow:inset 0 1px 0 rgba(255,255,255,.38),inset 0 -1px 0 rgba(0,0,0,.10),0 1px 2px rgba(15,27,45,.16),0 6px 14px -8px var(--t2)}'
        . '.nab-tile::before{content:"";position:absolute;inset:0;border-radius:inherit;background:linear-gradient(180deg,rgba(255,255,255,.26) 0%,rgba(255,255,255,0) 52%);pointer-events:none}'
        . '.nab-tile .nab-i{position:relative;z-index:1;filter:drop-shadow(0 1px 1px rgba(0,0,0,.14))}'
        . $tones
        // NABi mark
        . '.nab-nabi{--nabi:40px;position:relative;display:inline-flex;flex:none;width:var(--nabi);height:var(--nabi);min-width:var(--nabi);border-radius:calc(var(--nabi) * .3);overflow:hidden;'
        . 'background:radial-gradient(120% 90% at 20% 0%,#2C6BB3 0%,#123A6E 45%,#081A33 100%);'
        . 'box-shadow:inset 0 0 0 1.25px rgba(236,200,112,.9),inset 0 1px 0 rgba(255,255,255,.28),0 1px 2px rgba(8,26,51,.25),0 8px 18px -8px rgba(8,26,51,.7)}'
        . '.nab-nabi::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.18),rgba(255,255,255,0) 50%);pointer-events:none}'
        . '.nab-nabi svg{position:relative;z-index:1;display:block}'
        . '.nab-nabi-n{fill:none;stroke:#fff;stroke-width:4.4;stroke-linecap:round;stroke-linejoin:round}'
        . '.nab-nabi-spark{fill:#F0CF7A}'
        . '.nab-nabi-word i{font-style:normal;color:#E9C46A}'
        // sidebar: tiles sit on the dark blue menu, so they get a fine light ring
        . '.nab-nav-item .nab-nav-tile{margin-right:3px;box-shadow:inset 0 1px 0 rgba(255,255,255,.38),0 0 0 1px rgba(255,255,255,.14),0 2px 6px rgba(0,0,0,.18)}'
        . '.nab-nav-item .nab-nav-tile .nab-i{opacity:1}'
        . '.nab-nav-item .nab-nabi{margin-right:3px;box-shadow:inset 0 0 0 1.25px rgba(236,200,112,.9),0 0 0 1px rgba(255,255,255,.14),0 2px 6px rgba(0,0,0,.25)}'
        . '</style>';
}
add_action( 'wp_head', function() {
    if ( function_exists( 'nab_portal_current_tool' ) && nab_portal_current_tool() ) nab_portal_tile_css();
}, 20 );

/** Exposes window.nabIcon(name, size) for scripts that build UI in the browser. */
function nab_portal_print_icons_js( $names ) {
    $paths = array_intersect_key( nab_portal_icon_paths(), array_flip( (array) $names ) );
    ?>
<script>
window.nabIconPaths = <?php echo wp_json_encode( $paths ); ?>;
window.nabTile = function( n, tone, s ) {
  s = s || 40;
  var svg = window.nabIcon( n, Math.round( s * 0.5 ) ).replace( 'stroke-width="1.75"', 'stroke-width="2"' );
  return '<span class="nab-tile nab-tile-' + String( tone || 'blue' ).replace( /[^a-z]/g, '' ) + '" style="--tile:' + ( s | 0 ) + 'px" aria-hidden="true">' + svg + '</span>';
};
window.nabIcon = function( n, s ) {
  var p = window.nabIconPaths[ n ]; if ( ! p ) return '';
  s = s || 20;
  return '<svg class="nab-i" width="' + s + '" height="' + s + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + p + '</svg>';
};
</script>
<?php
}
