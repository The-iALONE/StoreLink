<?php
/**
 * Plugin deactivation.
 *
 * @package PricePilot
 */

namespace PricePilot\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin deactivation.
 */
class Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		delete_transient( 'pricepilot_dashboard_stats' );
		flush_rewrite_rules();
	}
}
