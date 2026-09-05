<?php
/**
 * Security helpers.
 *
 * @package PricePilot
 */

namespace PricePilot\Security;

use PricePilot\Core\Plugin;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Permission and validation helpers.
 */
class Permissions {

	/**
	 * REST permission callback.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( Plugin::capability() );
	}

	/**
	 * Verify REST nonce from request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function verify_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		return (bool) wp_verify_nonce( sanitize_text_field( (string) $nonce ), 'wp_rest' );
	}

	/**
	 * Combined permission check for REST endpoints.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function rest_permission( WP_REST_Request $request ): bool {
		return self::can_manage() && self::verify_nonce( $request );
	}
}
