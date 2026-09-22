<?php
/**
 * Database schema definitions.
 *
 * @package StoreLink
 */

namespace StoreLink\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Custom table schema.
 */
class Schema {

	public static function customers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'storelink_customers';
	}

	public static function sessions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'storelink_sessions';
	}

	public static function get_schema(): string {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$customers       = self::customers_table();
		$sessions        = self::sessions_table();

		return "CREATE TABLE {$customers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			platform varchar(40) NOT NULL DEFAULT '',
			external_user_id varchar(64) NOT NULL DEFAULT '',
			chat_id varchar(64) NOT NULL DEFAULT '',
			wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			display_name varchar(190) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY platform_user (platform, external_user_id),
			KEY chat_id (chat_id)
		) {$charset_collate};

		CREATE TABLE {$sessions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			platform varchar(40) NOT NULL DEFAULT '',
			chat_id varchar(64) NOT NULL DEFAULT '',
			state varchar(40) NOT NULL DEFAULT 'menu',
			cart longtext NULL,
			checkout longtext NULL,
			last_update_id bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY platform_chat (platform, chat_id)
		) {$charset_collate};";
	}
}
