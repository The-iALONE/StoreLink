<?php
/**
 * Uninstall handler.
 *
 * @package PricePilot
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$remove_data = apply_filters( 'pricepilot_remove_data_on_uninstall', true );

if ( ! $remove_data ) {
	return;
}

$table_operations = $wpdb->prefix . 'pricepilot_operations';
$table_items      = $wpdb->prefix . 'pricepilot_operation_items';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table_items}" );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table_operations}" );

delete_option( 'pricepilot_db_version' );
delete_transient( 'pricepilot_dashboard_stats' );
