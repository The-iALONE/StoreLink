<?php
/**
 * Database schema definitions.
 *
 * @package PricePilot
 */

namespace PricePilot\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Custom table schema.
 */
class Schema {

	/**
	 * Operations table name.
	 *
	 * @return string
	 */
	public static function operations_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pricepilot_operations';
	}

	/**
	 * Operation items table name.
	 *
	 * @return string
	 */
	public static function operation_items_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pricepilot_operation_items';
	}

	/**
	 * SQL for creating tables.
	 *
	 * @return string
	 */
	public static function get_schema(): string {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$operations      = self::operations_table();
		$items           = self::operation_items_table();

		$sql = "CREATE TABLE {$operations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			operation_type varchar(50) NOT NULL DEFAULT '',
			operation_value varchar(100) NOT NULL DEFAULT '',
			target_field varchar(30) NOT NULL DEFAULT 'regular',
			status varchar(20) NOT NULL DEFAULT 'previewed',
			product_count int(11) NOT NULL DEFAULT 0,
			success_count int(11) NOT NULL DEFAULT 0,
			error_count int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			undone_at datetime DEFAULT NULL,
			meta longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};

		CREATE TABLE {$items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			operation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_type varchar(20) NOT NULL DEFAULT '',
			sku varchar(100) NOT NULL DEFAULT '',
			product_name text NOT NULL,
			old_regular decimal(19,4) DEFAULT NULL,
			new_regular decimal(19,4) DEFAULT NULL,
			old_sale decimal(19,4) DEFAULT NULL,
			new_sale decimal(19,4) DEFAULT NULL,
			change_label varchar(50) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'success',
			error_message text DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY operation_id (operation_id),
			KEY product_id (product_id)
		) {$charset_collate};";

		return $sql;
	}
}
