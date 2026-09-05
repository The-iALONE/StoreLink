<?php
/**
 * History REST controller.
 *
 * @package PricePilot
 */

namespace PricePilot\REST\Controllers;

use PricePilot\Database\Repositories\OperationRepository;
use PricePilot\REST\ApiResponse;
use PricePilot\REST\ParamSanitizer;
use PricePilot\Security\Permissions;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Operation history endpoints.
 */
class HistoryController {

	private const NAMESPACE = 'pricepilot/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_history' ),
				'permission_callback' => array( Permissions::class, 'rest_permission' ),
				'args'                => array(
					'page'     => array( 'default' => 1, 'sanitize_callback' => array( ParamSanitizer::class, 'absint' ) ),
					'per_page' => array( 'default' => 20, 'sanitize_callback' => array( ParamSanitizer::class, 'absint' ) ),
				),
			)
		);
	}

	/**
	 * List operation history.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_history( WP_REST_Request $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );

		$result = ( new OperationRepository() )->paginate( $page, $per_page );
		$items  = array();

		foreach ( $result['items'] as $row ) {
			$user      = get_userdata( (int) $row->user_id );
			$items[] = array(
				'id'              => (int) $row->id,
				'user'            => $user ? $user->display_name : __( 'Unknown', 'pricepilot' ),
				'operation_type'  => $row->operation_type,
				'operation_value' => $row->operation_value,
				'target_field'    => $row->target_field,
				'status'          => $row->status,
				'product_count'   => (int) $row->product_count,
				'success_count'   => (int) $row->success_count,
				'error_count'     => (int) $row->error_count,
				'created_at'      => $row->created_at,
				'undone_at'       => $row->undone_at,
				'can_undo'        => in_array( $row->status, array( 'applied', 'partial' ), true ) && empty( $row->undone_at ),
			);
		}

		return ApiResponse::success(
			array(
				'items'    => $items,
				'total'    => $result['total'],
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}
}
