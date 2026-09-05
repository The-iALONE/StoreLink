<?php
/**
 * Bulk pricing REST controller.
 *
 * @package PricePilot
 */

namespace PricePilot\REST\Controllers;

use PricePilot\Pricing\BulkApplyService;
use PricePilot\Pricing\BulkPreviewService;
use PricePilot\Pricing\OperationTypes;
use PricePilot\Pricing\UndoService;
use PricePilot\REST\ApiResponse;
use PricePilot\Security\Permissions;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Bulk preview, apply, and undo endpoints.
 */
class BulkController {

	private const NAMESPACE = 'pricepilot/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/bulk/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( Permissions::class, 'rest_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/bulk/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'apply' ),
				'permission_callback' => array( Permissions::class, 'rest_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/bulk/undo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'undo' ),
				'permission_callback' => array( Permissions::class, 'rest_permission' ),
			)
		);
	}

	/**
	 * Preview bulk price changes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function preview( WP_REST_Request $request ) {
		$product_ids    = array_map( 'absint', (array) $request->get_param( 'product_ids' ) );
		$operation_type = sanitize_text_field( (string) $request->get_param( 'operation_type' ) );
		$value          = (float) $request->get_param( 'value' );
		$target_field   = sanitize_text_field( (string) ( $request->get_param( 'target_field' ) ?: 'regular' ) );

		if ( empty( $product_ids ) ) {
			return ApiResponse::error( __( 'No products selected.', 'pricepilot' ), 'validation_error', 400 );
		}

		if ( ! in_array( $operation_type, OperationTypes::all(), true ) ) {
			return ApiResponse::error( __( 'Invalid operation type.', 'pricepilot' ), 'validation_error', 400 );
		}

		if ( ! in_array( $target_field, OperationTypes::target_fields(), true ) ) {
			return ApiResponse::error( __( 'Invalid target field.', 'pricepilot' ), 'validation_error', 400 );
		}

		$service = new BulkPreviewService();
		$result  = $service->preview( $product_ids, $operation_type, $value, $target_field );

		return ApiResponse::success( $result );
	}

	/**
	 * Apply previewed bulk changes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function apply( WP_REST_Request $request ) {
		$token = sanitize_text_field( (string) $request->get_param( 'preview_token' ) );

		if ( empty( $token ) ) {
			return ApiResponse::error( __( 'Preview token is required.', 'pricepilot' ), 'validation_error', 400 );
		}

		$rate_key = 'pricepilot_apply_' . get_current_user_id();
		if ( get_transient( $rate_key ) ) {
			return ApiResponse::error( __( 'Please wait before applying another bulk operation.', 'pricepilot' ), 'rate_limited', 429 );
		}

		set_transient( $rate_key, 1, 5 );

		$service = new BulkApplyService();
		$result  = $service->apply( $token );

		if ( is_wp_error( $result ) ) {
			return ApiResponse::error( $result );
		}

		return ApiResponse::success( $result );
	}

	/**
	 * Undo last bulk operation.
	 *
	 * @return \WP_REST_Response
	 */
	public function undo() {
		$service = new UndoService();
		$result  = $service->undo_last();

		if ( is_wp_error( $result ) ) {
			return ApiResponse::error( $result );
		}

		return ApiResponse::success( $result );
	}
}
