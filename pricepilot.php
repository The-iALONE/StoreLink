<?php
/**
 * Plugin Name:       PricePilot
 * Plugin URI:        https://pricepilot.ir
 * Description:       Smart WooCommerce pricing and product operations dashboard.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.3
 * Author:            PricePilot
 * Author URI:        https://pricepilot.ir
 * Text Domain:       pricepilot
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.1
 *
 * @package PricePilot
 */

defined( 'ABSPATH' ) || exit;

define( 'PRICEPILOT_VERSION', '1.0.0' );
define( 'PRICEPILOT_PLUGIN_FILE', __FILE__ );
define( 'PRICEPILOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRICEPILOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PRICEPILOT_DB_VERSION', '1.0.0' );

if ( file_exists( PRICEPILOT_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once PRICEPILOT_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once PRICEPILOT_PLUGIN_DIR . 'includes/Core/Requirements.php';

if ( ! PricePilot\Core\Requirements::check() ) {
	return;
}

require_once PRICEPILOT_PLUGIN_DIR . 'includes/Core/Activator.php';
require_once PRICEPILOT_PLUGIN_DIR . 'includes/Core/Deactivator.php';

register_activation_hook( __FILE__, array( 'PricePilot\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PricePilot\Core\Deactivator', 'deactivate' ) );

require_once PRICEPILOT_PLUGIN_DIR . 'includes/Core/Plugin.php';

PricePilot\Core\Plugin::instance()->run();
