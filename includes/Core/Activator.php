<?php
/**
 * Plugin activation.
 *
 * @package PricePilot
 */

namespace PricePilot\Core;

use PricePilot\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation.
 */
class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( ! Requirements::check() ) {
			deactivate_plugins( plugin_basename( PRICEPILOT_PLUGIN_FILE ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: required PHP version */
						__( 'PricePilot requires PHP %s or higher.', 'pricepilot' ),
						Requirements::MIN_PHP
					)
				)
			);
		}

		Migrator::migrate();
		flush_rewrite_rules();
	}
}
