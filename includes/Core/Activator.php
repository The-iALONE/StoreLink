<?php
/**
 * Plugin activation.
 *
 * @package StoreLink
 */

namespace StoreLink\Core;

use StoreLink\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation.
 */
class Activator {

	public static function activate(): void {
		if ( ! Requirements::check() ) {
			deactivate_plugins( plugin_basename( STORELINK_PLUGIN_FILE ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: required PHP version */
						__( 'StoreLink requires PHP %s or higher.', 'storelink' ),
						Requirements::MIN_PHP
					)
				)
			);
		}

		Migrator::migrate();
		\StoreLink\Tracking\TrackingService::schedule_cron();
		flush_rewrite_rules();
	}
}
