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
		flush_rewrite_rules();
	}
}
