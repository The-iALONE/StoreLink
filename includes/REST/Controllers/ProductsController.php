<?php
/**
 * Products REST controller.
 *
 * @package PricePilot
 */

namespace PricePilot\REST\Controllers;

use PricePilot\Products\ProductQuery;
use PricePilot\REST\ApiResponse;
use PricePilot\REST\ParamSanitizer;
use PricePilot\Security\Permissions;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Product listing endpoints.
 */
class ProductsController {

	/**
	 * Namespace.
	 */
	private const NAMESPACE = 'pricepilot/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_products' ),
				'permission_callback' => array( Permissions::class, 'rest_permission' ),
				'args'                => $this->get_collection_params(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/filters',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_filters' ),
				'permission_callback' => array( Permissions::class, 'rest_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/categories',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_categories' ),
				'permission_callback' => array( Permissions::class, 'rest_permission' ),
			)
		);
	}

	/**
	 * List products with filters.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_products( WP_REST_Request $request ) {
		$query  = new ProductQuery();
		$params = $request->get_params();
		$result = $query->query( $params );

		return ApiResponse::success( $result );
	}

	/**
	 * List available filters.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_filters() {
		$query = new ProductQuery();
		return ApiResponse::success( $query->get_registry()->get_definitions() );
	}

	/**
	 * List WooCommerce product categories for filters.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return ApiResponse::success( array() );
		}

		$items = array();

		foreach ( $terms as $term ) {
			$items[] = array(
				'id'     => (int) $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'parent' => (int) $term->parent,
				'count'  => (int) $term->count,
			);
		}

		return ApiResponse::success( $items );
	}

	/**
	 * Collection query parameters.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_collection_params(): array {
		return array(
			'page'         => array( 'default' => 1, 'sanitize_callback' => array( ParamSanitizer::class, 'absint' ) ),
			'per_page'     => array( 'default' => 20, 'sanitize_callback' => array( ParamSanitizer::class, 'absint' ) ),
			'search'       => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'sku'          => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'category'     => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'tag'          => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'price_min'    => array( 'sanitize_callback' => array( ParamSanitizer::class, 'optional_float' ) ),
			'price_max'    => array( 'sanitize_callback' => array( ParamSanitizer::class, 'optional_float' ) ),
			'stock_min'    => array( 'sanitize_callback' => array( ParamSanitizer::class, 'optional_absint' ) ),
			'stock_max'    => array( 'sanitize_callback' => array( ParamSanitizer::class, 'optional_absint' ) ),
			'stock_status' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'product_type' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'post_status'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'on_sale'      => array( 'sanitize_callback' => 'rest_sanitize_boolean' ),
		);
	}
}
