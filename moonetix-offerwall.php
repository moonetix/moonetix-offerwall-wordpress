<?php
/**
 * Plugin Name:       Moonetix Offerwall
 * Plugin URI:        https://docs.moonetix.com/wordpress
 * Description:       Show the Moonetix offerwall to logged-in users and credit their rewards automatically: built-in balance, myCRED, GamiPress, or TeraWallet.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Moonetix
 * Author URI:        https://moonetix.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       moonetix-offerwall
 *
 * @package Moonetix_Offerwall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MOONETIX_OFFERWALL_VERSION', '1.0.0' );
define( 'MOONETIX_OFFERWALL_DB_VERSION', '2' );
define( 'MOONETIX_OFFERWALL_FILE', __FILE__ );
define( 'MOONETIX_OFFERWALL_DIR', plugin_dir_path( __FILE__ ) );
define( 'MOONETIX_OFFERWALL_URL', plugin_dir_url( __FILE__ ) );
define( 'MOONETIX_OFFERWALL_WALL_BASE', 'https://wall.moonetix.com/' );

require_once MOONETIX_OFFERWALL_DIR . 'includes/class-moonetix-offerwall-settings.php';
require_once MOONETIX_OFFERWALL_DIR . 'includes/class-moonetix-offerwall-ledger.php';
require_once MOONETIX_OFFERWALL_DIR . 'includes/class-moonetix-offerwall-postback.php';
require_once MOONETIX_OFFERWALL_DIR . 'includes/class-moonetix-offerwall-frontend.php';
require_once MOONETIX_OFFERWALL_DIR . 'includes/class-moonetix-offerwall-admin.php';

register_activation_hook( __FILE__, array( 'Moonetix_Offerwall_Ledger', 'install' ) );
register_activation_hook( __FILE__, array( 'Moonetix_Offerwall_Admin', 'activate' ) );

add_action( 'plugins_loaded', array( 'Moonetix_Offerwall_Ledger', 'maybe_upgrade' ) );
add_action( 'rest_api_init', array( 'Moonetix_Offerwall_Postback', 'register_routes' ) );
add_action( 'init', array( 'Moonetix_Offerwall_Frontend', 'init' ) );
add_filter( 'wp_privacy_personal_data_exporters', array( 'Moonetix_Offerwall_Ledger', 'register_exporter' ) );
add_filter( 'wp_privacy_personal_data_erasers', array( 'Moonetix_Offerwall_Ledger', 'register_eraser' ) );

if ( is_admin() ) {
	Moonetix_Offerwall_Admin::init();
}
