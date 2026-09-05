<?php
/**
 * Dashboard REST controller.
 *
 * @package PricePilot
 */

namespace PricePilot\REST\Controllers;

use PricePilot\Database\Repositories\OperationRepository;
use PricePilot\REST\ApiResponse;
use PricePilot\Security\Permissions;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard statistics endpoint.
 */
class DashboardController {

	private const NAMESPACE = 'pricepilot/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( Permissions::class, 'rest_permission' ),
			)
		);
	}

	/**
	 * Get dashboard KPI stats.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stats() {
		$cached = get_transient( 'pricepilot_dashboard_stats' );

		if ( is_array( $cached ) ) {
			return ApiResponse::success( $cached );
		}

		$stats = $this->compute_stats();
		set_transient( 'pricepilot_dashboard_stats', $stats, 5 * MINUTE_IN_SECONDS );

		return ApiResponse::success( $stats );
	}

	/**
	 * Compute dashboard statistics.
	 *
	 * @return array<string, mixed>
	 */
	private function compute_stats(): array {
		$product_counts = wp_count_posts( 'product' );
		$total_products = isset( $product_counts->publish ) ? (int) $product_counts->publish : 0;

		$out_of_stock = wc_get_products(
			array(
				'limit'        => 1,
				'paginate'     => true,
				'return'       => 'ids',
				'stock_status' => 'outofstock',
				'status'       => 'publish',
			)
		);

		$low_stock_threshold = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		$low_stock           = wc_get_products(
			array(
				'limit'    => 1,
				'paginate' => true,
				'return'   => 'ids',
				'status'   => 'publish',
				'meta_query' => array(
					array(
						'key'     => '_manage_stock',
						'value'   => 'yes',
					),
					array(
						'key'     => '_stock',
						'value'   => $low_stock_threshold,
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		global $wpdb;
		$no_price = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_regular_price'
			WHERE p.post_type IN ('product','product_variation') AND p.post_status = 'publish'
			AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0')"
		);

		$recently_updated = wc_get_products(
			array(
				'limit'   => 5,
				'orderby' => 'modified',
				'order'   => 'DESC',
				'return'  => 'objects',
				'status'  => 'publish',
			)
		);

		$recent_items = array();
		foreach ( $recently_updated as $product ) {
			if ( $product instanceof \WC_Product ) {
				$recent_items[] = array(
					'id'   => $product->get_id(),
					'name' => $product->get_name(),
					'date' => get_post_modified_time( 'Y-m-d H:i', false, $product->get_id() ),
				);
			}
		}

		$last_operation = ( new OperationRepository() )->paginate( 1, 1 );
		$last_bulk      = null;

		if ( ! empty( $last_operation['items'] ) ) {
			$row       = $last_operation['items'][0];
			$user      = get_userdata( (int) $row->user_id );
			$last_bulk = array(
				'id'              => (int) $row->id,
				'user'            => $user ? $user->display_name : '',
				'operation_type'  => $row->operation_type,
				'operation_value' => $row->operation_value,
				'product_count'   => (int) $row->product_count,
				'status'          => $row->status,
				'created_at'      => $row->created_at,
				'can_undo'        => in_array( $row->status, array( 'applied', 'partial' ), true ) && empty( $row->undone_at ),
			);
		}

		return array(
			'total_products'        => $total_products,
			'products_with_sale'    => count( wc_get_product_ids_on_sale() ),
			'out_of_stock'          => (int) ( $out_of_stock->total ?? 0 ),
			'low_stock'             => (int) ( $low_stock->total ?? 0 ),
			'products_without_price'=> $no_price,
			'recently_updated'      => $recent_items,
			'last_bulk_operation'   => $last_bulk,
		);
	}
}
