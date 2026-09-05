<?php
/**
 * Product filters.
 *
 * @package PricePilot
 */

namespace PricePilot\Products\Filters\Filters;

use PricePilot\Core\Currency;
use PricePilot\Products\Filters\FilterInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Search by product name.
 */
class SearchFilter implements FilterInterface {
	public function key(): string {
		return 'search';
	}

	public function apply( array $query_args, mixed $value ): array {
		$term = (string) $value;

		if ( '' === $term ) {
			return $query_args;
		}

		if ( function_exists( 'WC' ) && class_exists( '\WC_Data_Store' ) ) {
			$data_store = \WC_Data_Store::load( 'product' );
			$ids        = $data_store->search_products( $term, '', true, false );

			$query_args['include'] = ! empty( $ids ) ? array_map( 'absint', $ids ) : array( 0 );
			return $query_args;
		}

		$query_args['s'] = $term;
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		return sanitize_text_field( (string) $value );
	}
}

/**
 * Filter by SKU.
 */
class SkuFilter implements FilterInterface {
	public function key(): string {
		return 'sku';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['sku'] = (string) $value;
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		return sanitize_text_field( (string) $value );
	}
}

/**
 * Filter by category slug or ID.
 */
class CategoryFilter implements FilterInterface {
	public function key(): string {
		return 'category';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['category'] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array( (string) $value );
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}
		return sanitize_text_field( (string) $value );
	}
}

/**
 * Filter by tag slug or ID.
 */
class TagFilter implements FilterInterface {
	public function key(): string {
		return 'tag';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['tag'] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array( (string) $value );
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}
		return sanitize_text_field( (string) $value );
	}
}

/**
 * Minimum price filter.
 */
class PriceMinFilter implements FilterInterface {
	public function key(): string {
		return 'price_min';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['pricepilot_price_min'] = Currency::to_storage( (float) $value );
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		return max( 0, (float) $value );
	}
}

/**
 * Maximum price filter.
 */
class PriceMaxFilter implements FilterInterface {
	public function key(): string {
		return 'price_max';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['pricepilot_price_max'] = Currency::to_storage( (float) $value );
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		return max( 0, (float) $value );
	}
}

/**
 * Minimum stock filter.
 */
class StockMinFilter implements FilterInterface {
	public function key(): string {
		return 'stock_min';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['pricepilot_stock_min'] = (int) $value;
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		return max( 0, (int) $value );
	}
}

/**
 * Maximum stock filter.
 */
class StockMaxFilter implements FilterInterface {
	public function key(): string {
		return 'stock_max';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['pricepilot_stock_max'] = (int) $value;
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		return max( 0, (int) $value );
	}
}

/**
 * Stock status filter.
 */
class StockStatusFilter implements FilterInterface {
	public function key(): string {
		return 'stock_status';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['stock_status'] = (string) $value;
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		$allowed = array( 'instock', 'outofstock', 'onbackorder' );
		$value   = sanitize_text_field( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : 'instock';
	}
}

/**
 * Product type filter.
 */
class ProductTypeFilter implements FilterInterface {
	public function key(): string {
		return 'product_type';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['type'] = is_array( $value ) ? $value : array( (string) $value );
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		$allowed = array( 'simple', 'variable', 'variation', 'grouped', 'external' );
		if ( is_array( $value ) ) {
			return array_values( array_intersect( array_map( 'sanitize_text_field', $value ), $allowed ) );
		}
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, $allowed, true ) ? array( $value ) : array( 'simple', 'variation' );
	}
}

/**
 * Post status filter.
 */
class PostStatusFilter implements FilterInterface {
	public function key(): string {
		return 'post_status';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['status'] = (string) $value;
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		$allowed = array( 'publish', 'draft', 'pending', 'private' );
		$value   = sanitize_text_field( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : 'publish';
	}
}

/**
 * On sale filter.
 */
class OnSaleFilter implements FilterInterface {
	public function key(): string {
		return 'on_sale';
	}

	public function apply( array $query_args, mixed $value ): array {
		$query_args['pricepilot_on_sale'] = (bool) $value;
		return $query_args;
	}

	public function sanitize( mixed $value ): mixed {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}
}
