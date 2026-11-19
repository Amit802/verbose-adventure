<?php
/**
 * Plugin Name:  NAB Staff Portal
 * Plugin URI:   https://members.nabsolutions.ca/staff/
 * Description:  Private workspace for the NAB Customer Care team at /staff/: tickets, AI reply assistant, member lookup, team documents and discussions. Adds the NAB Staff and NAB Staff Manager roles.
 * Version:      0.3.1
 * Author:       Amit
 * License:      Private, all rights reserved
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain:  nab-staff
 *
 * Works alongside the NAB Member Portal plugin on members.nabsolutions.ca.
 * Read DEVELOPER.md before changing anything.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NAB_STAFF_VERSION', '0.3.1' );
define( 'NAB_STAFF_FILE', __FILE__ );
define( 'NAB_STAFF_DIR', plugin_dir_path( __FILE__ ) );
define( 'NAB_STAFF_URL', plugin_dir_url( __FILE__ ) );

require_once NAB_STAFF_DIR . 'inc/roles.php';
require_once NAB_STAFF_DIR . 'inc/screens.php';
require_once NAB_STAFF_DIR . 'inc/router.php';

register_activation_hook( __FILE__, function () {
    nab_staff_setup_roles();
    nab_staff_add_rewrites();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    // Roles are kept on purpose, so staff accounts keep their role if the plugin is switched back on.
    flush_rewrite_rules();
} );
