<?php
/**
 * Product query wrapper.
 *
 * @package PricePilot
 */

namespace PricePilot\Products;

use PricePilot\Products\Filters\FilterRegistry;
use WC_Product;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Paginated WooCommerce product queries with filters.
 */
class ProductQuery {

	/**
	 * Filter registry.
	 *
	 * @var FilterRegistry
	 */
	private FilterRegistry $registry;

	/**
	 * Constructor.
	 *
	 * @param FilterRegistry|null $registry Optional registry.
	 */
	public function __construct( ?FilterRegistry $registry = null ) {
		$this->registry = $registry ?? new FilterRegistry();
		$this->register_query_hooks();
	}

	/**
	 * Register WP_Query meta hooks for custom filters.
	 *
	 * @return void
	 */
	private function register_query_hooks(): void {
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( $this, 'extend_product_query' ), 10, 2 );
	}

	/**
	 * Extend product query for custom price/stock/sale filters.
	 *
	 * @param array<string, mixed> $query Query vars.
	 * @param array<string, mixed> $args  wc_get_products args.
	 * @return array<string, mixed>
	 */
	public function extend_product_query( array $query, array $args ): array {
		if ( isset( $args['pricepilot_price_min'] ) || isset( $args['pricepilot_price_max'] ) ) {
			$query['meta_query']   = $query['meta_query'] ?? array();
			$query['meta_query'][] = array(
				'key'     => '_price',
				'value'   => array(
					$args['pricepilot_price_min'] ?? 0,
					$args['pricepilot_price_max'] ?? PHP_FLOAT_MAX,
				),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		if ( isset( $args['pricepilot_stock_min'] ) || isset( $args['pricepilot_stock_max'] ) ) {
			$query['meta_query']   = $query['meta_query'] ?? array();
			$query['meta_query'][] = array(
				'key'     => '_stock',
				'value'   => array(
					$args['pricepilot_stock_min'] ?? 0,
					$args['pricepilot_stock_max'] ?? PHP_INT_MAX,
				),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		if ( ! empty( $args['pricepilot_on_sale'] ) ) {
			$on_sale_ids        = wc_get_product_ids_on_sale();
			$query['post__in']  = ! empty( $query['post__in'] )
				? array_intersect( (array) $query['post__in'], $on_sale_ids )
				: $on_sale_ids;
			if ( empty( $query['post__in'] ) ) {
				$query['post__in'] = array( 0 );
			}
		}

		return $query;
	}

	/**
	 * Query products with filters and pagination.
	 *
	 * @param array<string, mixed> $params Request params.
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public function query( array $params ): array {
		$page     = max( 1, (int) ( $params['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $params['per_page'] ?? 20 ) ) );

		$query_args = array(
			'limit'   => $per_page,
			'page'    => $page,
			'paginate'=> true,
			'return'  => 'objects',
			'orderby' => sanitize_text_field( (string) ( $params['orderby'] ?? 'date' ) ),
			'order'   => strtoupper( sanitize_text_field( (string) ( $params['order'] ?? 'DESC' ) ) ) === 'ASC' ? 'ASC' : 'DESC',
		);

		if ( empty( $params['product_type'] ) ) {
			$params['product_type'] = array( 'simple', 'variation' );
		}

		$query_args = $this->registry->apply_all( $query_args, $params );

		$result = wc_get_products( $query_args );

		$reader = new ProductReader();
		$items  = array();

		foreach ( $result->products as $product ) {
			if ( $product instanceof WC_Product ) {
				$items[] = $reader->to_array( $product );
			}
		}

		return array(
			'items'    => $items,
			'total'    => (int) $result->total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Get filter registry.
	 *
	 * @return FilterRegistry
	 */
	public function get_registry(): FilterRegistry {
		return $this->registry;
	}
}
