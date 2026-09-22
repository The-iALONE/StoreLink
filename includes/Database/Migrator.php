<?php
/**
 * Database migrations.
 *
 * @package StoreLink
 */

namespace StoreLink\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Runs schema migrations.
 */
class Migrator {

	public static function migrate(): void {
		$installed = get_option( 'storelink_db_version', '' );

		if ( version_compare( (string) $installed, STORELINK_DB_VERSION, '>=' ) ) {
			return;
		}

		self::drop_legacy_tables();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( Schema::get_schema() );

		update_option( 'storelink_db_version', STORELINK_DB_VERSION );
		delete_option( 'pricepilot_db_version' );
	}

	private static function drop_legacy_tables(): void {
		global $wpdb;

		$ops   = $wpdb->prefix . 'pricepilot_operations';
		$items = $wpdb->prefix . 'pricepilot_operation_items';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$items}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$ops}" );
	}
}
