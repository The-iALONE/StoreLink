<?php
/**
 * Plugin deactivation.
 *
 * @package StoreLink
 */

namespace StoreLink\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation.
 */
class Deactivator {

	public static function deactivate(): void {
		\StoreLink\Tracking\TrackingService::clear_cron();
		flush_rewrite_rules();
	}
}
