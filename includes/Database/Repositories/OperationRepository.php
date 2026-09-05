<?php
/**
 * Operation repository.
 *
 * @package PricePilot
 */

namespace PricePilot\Database\Repositories;

use PricePilot\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for operations table.
 */
class OperationRepository {

	/**
	 * Insert a new operation.
	 *
	 * @param array<string, mixed> $data Operation data.
	 * @return int Operation ID.
	 */
	public function create( array $data ): int {
		global $wpdb;

		$wpdb->insert(
			Schema::operations_table(),
			array(
				'user_id'         => (int) ( $data['user_id'] ?? get_current_user_id() ),
				'operation_type'  => sanitize_text_field( (string) ( $data['operation_type'] ?? '' ) ),
				'operation_value' => sanitize_text_field( (string) ( $data['operation_value'] ?? '' ) ),
				'target_field'    => sanitize_text_field( (string) ( $data['target_field'] ?? 'regular' ) ),
				'status'          => sanitize_text_field( (string) ( $data['status'] ?? 'previewed' ) ),
				'product_count'   => (int) ( $data['product_count'] ?? 0 ),
				'success_count'   => (int) ( $data['success_count'] ?? 0 ),
				'error_count'     => (int) ( $data['error_count'] ?? 0 ),
				'created_at'      => $data['created_at'] ?? current_time( 'mysql' ),
				'meta'            => isset( $data['meta'] ) ? wp_json_encode( $data['meta'] ) : null,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update operation by ID.
	 *
	 * @param int                  $id   Operation ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$fields = array();
		$format = array();

		$allowed = array(
			'status'        => '%s',
			'success_count' => '%d',
			'error_count'   => '%d',
			'undone_at'     => '%s',
			'product_count' => '%d',
		);

		foreach ( $allowed as $key => $type ) {
			if ( array_key_exists( $key, $data ) ) {
				$fields[ $key ] = $data[ $key ];
				$format[]       = $type;
			}
		}

		if ( empty( $fields ) ) {
			return false;
		}

		return false !== $wpdb->update(
			Schema::operations_table(),
			$fields,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Get operation by ID.
	 *
	 * @param int $id Operation ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		$table = Schema::operations_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		return $row ?: null;
	}

	/**
	 * Get last applied operation that can be undone.
	 *
	 * @return object|null
	 */
	public function get_last_undoable(): ?object {
		global $wpdb;

		$table = Schema::operations_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			"SELECT * FROM {$table} WHERE status IN ('applied','partial') AND undone_at IS NULL ORDER BY id DESC LIMIT 1"
		);

		return $row ?: null;
	}

	/**
	 * Paginated history list.
	 *
	 * @param int $page     Page number.
	 * @param int $per_page Items per page.
	 * @return array{items: array<int, object>, total: int}
	 */
	public function paginate( int $page, int $per_page ): array {
		global $wpdb;

		$table  = Schema::operations_table();
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		return array(
			'items' => $items ?: array(),
			'total' => $total,
		);
	}
}
