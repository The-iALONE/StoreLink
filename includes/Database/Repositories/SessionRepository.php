<?php
/**
 * Session repository.
 *
 * @package StoreLink
 */

namespace StoreLink\Database\Repositories;

use StoreLink\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Conversation session storage.
 */
class SessionRepository {

	/**
	 * @return array<string, mixed>
	 */
	public function get( string $platform, string $chat_id ): array {
		global $wpdb;

		$table = Schema::sessions_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE platform = %s AND chat_id = %s LIMIT 1",
				sanitize_key( $platform ),
				sanitize_text_field( $chat_id )
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return array(
				'platform'       => $platform,
				'chat_id'        => $chat_id,
				'state'          => 'menu',
				'cart'           => array(),
				'checkout'       => array(),
				'last_update_id' => 0,
			);
		}

		$row['cart']     = json_decode( (string) ( $row['cart'] ?? '[]' ), true ) ?: array();
		$row['checkout'] = json_decode( (string) ( $row['checkout'] ?? '{}' ), true ) ?: array();

		return $row;
	}

	/**
	 * @param array<string, mixed> $session Session payload.
	 */
	public function save( array $session ): void {
		global $wpdb;

		$table = Schema::sessions_table();
		$now   = current_time( 'mysql' );
		$data  = array(
			'platform'       => sanitize_key( (string) $session['platform'] ),
			'chat_id'        => sanitize_text_field( (string) $session['chat_id'] ),
			'state'          => sanitize_key( (string) ( $session['state'] ?? 'menu' ) ),
			'cart'           => wp_json_encode( $session['cart'] ?? array() ),
			'checkout'       => wp_json_encode( $session['checkout'] ?? array() ),
			'last_update_id' => absint( $session['last_update_id'] ?? 0 ),
			'updated_at'     => $now,
		);

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE platform = %s AND chat_id = %s",
				$data['platform'],
				$data['chat_id']
			)
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
			return;
		}

		$wpdb->insert( $table, $data );
	}
}
