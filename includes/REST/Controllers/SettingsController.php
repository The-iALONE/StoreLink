<?php
/**
 * Settings REST controller.
 *
 * @package PricePilot
 */

namespace PricePilot\REST\Controllers;

use PricePilot\Core\Locale;
use PricePilot\REST\ApiResponse;
use PricePilot\Security\Permissions;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings endpoints.
 */
class SettingsController {

	private const NAMESPACE = 'pricepilot/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings/locale',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_locale' ),
					'permission_callback' => array( Permissions::class, 'rest_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_locale' ),
					'permission_callback' => array( Permissions::class, 'rest_permission' ),
				),
			)
		);
	}

	/**
	 * Get current locale settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_locale() {
		return ApiResponse::success(
			array(
				'locale'    => Locale::get_user_locale(),
				'is_rtl'    => Locale::is_rtl(),
				'available' => Locale::get_available(),
				'default'   => Locale::DEFAULT_LOCALE,
			)
		);
	}

	/**
	 * Update user locale.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function set_locale( WP_REST_Request $request ) {
		$locale = sanitize_text_field( (string) $request->get_param( 'locale' ) );

		if ( ! Locale::set_user_locale( $locale ) ) {
			return ApiResponse::error( __( 'Invalid language selected.', 'pricepilot' ), 'validation_error', 400 );
		}

		return ApiResponse::success(
			array(
				'locale' => Locale::get_user_locale(),
				'is_rtl' => Locale::is_rtl(),
			)
		);
	}
}
