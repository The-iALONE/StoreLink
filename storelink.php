<?php
/**
 * Plugin Name:       StoreLink
 * Plugin URI:        https://github.com/The-iALONE/StoreLink
 * Description:       Connect WooCommerce to messengers. Telegram bot for catalog, cart, and orders.
 * Version:           2.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.3
 * Author:            StoreLink
 * Author URI:        https://github.com/The-iALONE/StoreLink
 * Text Domain:       storelink
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.1
 *
 * @package StoreLink
 */

defined( 'ABSPATH' ) || exit;

define( 'STORELINK_VERSION', '2.0.0' );
define( 'STORELINK_PLUGIN_FILE', __FILE__ );
define( 'STORELINK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'STORELINK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STORELINK_DB_VERSION', '2.0.0' );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'StoreLink\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file     = STORELINK_PLUGIN_DIR . 'includes/' . $relative . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

require_once STORELINK_PLUGIN_DIR . 'includes/Core/Requirements.php';

if ( ! StoreLink\Core\Requirements::check() ) {
	return;
}

require_once STORELINK_PLUGIN_DIR . 'includes/Core/Activator.php';
require_once STORELINK_PLUGIN_DIR . 'includes/Core/Deactivator.php';

register_activation_hook( __FILE__, array( 'StoreLink\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'StoreLink\Core\Deactivator', 'deactivate' ) );

StoreLink\Core\Plugin::instance()->run();
