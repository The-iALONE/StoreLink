<?php
/**
 * REST API response helper.
 *
 * @package PricePilot
 */

namespace PricePilot\REST;

use WP_Error;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Standardized REST responses.
 */
class ApiResponse {

	/**
	 * Success response.
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	public static function success( mixed $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Error response from WP_Error or message.
	 *
	 * @param WP_Error|string $error  Error object or message.
	 * @param string          $code   Error code.
	 * @param int             $status HTTP status.
	 * @return WP_REST_Response
	 */
	public static function error( WP_Error|string $error, string $code = 'error', int $status = 400 ): WP_REST_Response {
		if ( $error instanceof WP_Error ) {
			$code    = $error->get_error_code();
			$message = $error->get_error_message();
			$data    = $error->get_error_data();
			$status  = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : $status;

			return new WP_REST_Response(
				array(
					'code'    => $code,
					'message' => $message,
					'data'    => $data,
				),
				$status
			);
		}

		return new WP_REST_Response(
			array(
				'code'    => $code,
				'message' => (string) $error,
				'data'    => array( 'status' => $status ),
			),
			$status
		);
	}
}
