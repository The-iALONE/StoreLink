<?php
/**
 * Operation items repository.
 *
 * @package PricePilot
 */

namespace PricePilot\Database\Repositories;

use PricePilot\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for operation items table.
 */
class OperationItemRepository {

	/**
	 * Insert operation item.
	 *
	 * @param array<string, mixed> $data Item data.
	 * @return int Item ID.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$wpdb->insert(
			Schema::operation_items_table(),
			array(
				'operation_id'  => (int) ( $data['operation_id'] ?? 0 ),
				'product_id'    => (int) ( $data['product_id'] ?? 0 ),
				'product_type'  => sanitize_text_field( (string) ( $data['product_type'] ?? '' ) ),
				'sku'           => sanitize_text_field( (string) ( $data['sku'] ?? '' ) ),
				'product_name'  => sanitize_text_field( (string) ( $data['product_name'] ?? '' ) ),
				'old_regular'   => $data['old_regular'] ?? null,
				'new_regular'   => $data['new_regular'] ?? null,
				'old_sale'      => $data['old_sale'] ?? null,
				'new_sale'      => $data['new_sale'] ?? null,
				'change_label'  => sanitize_text_field( (string) ( $data['change_label'] ?? '' ) ),
				'status'        => sanitize_text_field( (string) ( $data['status'] ?? 'success' ) ),
				'error_message' => isset( $data['error_message'] ) ? sanitize_textarea_field( (string) $data['error_message'] ) : null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Bulk insert items.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @return void
	 */
	public function create_many( array $items ): void {
		foreach ( $items as $item ) {
			$this->create( $item );
		}
	}

	/**
	 * Get items for an operation.
	 *
	 * @param int $operation_id Operation ID.
	 * @return array<int, object>
	 */
	public function get_by_operation( int $operation_id ): array {
		global $wpdb;

		$table = Schema::operation_items_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE operation_id = %d ORDER BY id ASC",
				$operation_id
			)
		);

		return $rows ?: array();
	}
}
