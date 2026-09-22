<?php
/**
 * Uninstall handler.
 *
 * @package StoreLink
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$remove_data = apply_filters( 'storelink_remove_data_on_uninstall', true );

if ( ! $remove_data ) {
	return;
}

$customers = $wpdb->prefix . 'storelink_customers';
$sessions  = $wpdb->prefix . 'storelink_sessions';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$sessions}" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$customers}" );

$legacy_ops   = $wpdb->prefix . 'pricepilot_operations';
$legacy_items = $wpdb->prefix . 'pricepilot_operation_items';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$legacy_items}" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$legacy_ops}" );

delete_option( 'storelink_db_version' );
delete_option( 'storelink_settings' );
delete_option( 'pricepilot_db_version' );
