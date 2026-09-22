<?php
/**
 * Security helpers.
 *
 * @package StoreLink
 */

namespace StoreLink\Security;

use StoreLink\Core\Plugin;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Permission helpers.
 */
class Permissions {

	public static function can_manage(): bool {
		return current_user_can( Plugin::capability() );
	}

	public static function verify_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		return (bool) wp_verify_nonce( sanitize_text_field( (string) $nonce ), 'wp_rest' );
	}

	public static function rest_permission( WP_REST_Request $request ): bool {
		return self::can_manage() && self::verify_nonce( $request );
	}
}
