<?php
/**
 * Customer repository.
 *
 * @package StoreLink
 */

namespace StoreLink\Database\Repositories;

use StoreLink\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Persists messenger customers.
 */
class CustomerRepository {

	/**
	 * @param array<string, mixed> $data Customer fields.
	 * @return int
	 */
	public function upsert( array $data ): int {
		global $wpdb;

		$table   = Schema::customers_table();
		$now     = current_time( 'mysql' );
		$existing = $this->find( (string) $data['platform'], (string) $data['external_user_id'] );

		$row = array(
			'platform'         => sanitize_key( (string) $data['platform'] ),
			'external_user_id' => sanitize_text_field( (string) $data['external_user_id'] ),
			'chat_id'          => sanitize_text_field( (string) $data['chat_id'] ),
			'display_name'     => sanitize_text_field( (string) ( $data['display_name'] ?? '' ) ),
			'phone'            => sanitize_text_field( (string) ( $data['phone'] ?? '' ) ),
			'updated_at'       => $now,
		);

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) );
			return (int) $existing['id'];
		}

		$row['created_at'] = $now;
		$wpdb->insert( $table, $row );

		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( string $platform, string $external_user_id ): ?array {
		global $wpdb;

		$table = Schema::customers_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE platform = %s AND external_user_id = %s LIMIT 1",
				sanitize_key( $platform ),
				sanitize_text_field( $external_user_id )
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}
}
