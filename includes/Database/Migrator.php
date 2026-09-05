<?php
/**
 * Database migrations.
 *
 * @package PricePilot
 */

namespace PricePilot\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Runs schema migrations.
 */
class Migrator {

	/**
	 * Run pending migrations.
	 *
	 * @return void
	 */
	public static function migrate(): void {
		$installed = get_option( 'pricepilot_db_version', '' );

		if ( version_compare( $installed, PRICEPILOT_DB_VERSION, '>=' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( Schema::get_schema() );

		update_option( 'pricepilot_db_version', PRICEPILOT_DB_VERSION );
	}
}
