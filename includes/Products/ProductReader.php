<?php
/**
 * Product reader.
 *
 * @package PricePilot
 */

namespace PricePilot\Products;

use PricePilot\Core\Currency;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Reads product data for API responses.
 */
class ProductReader {

	/**
	 * Convert WC_Product to array representation.
	 *
	 * @param WC_Product $product Product object.
	 * @return array<string, mixed>
	 */
	public function to_array( WC_Product $product ): array {
		$type        = $product->get_type();
		$parent_id   = $product->get_parent_id();
		$parent_name = '';

		if ( $parent_id ) {
			$parent = wc_get_product( $parent_id );
			if ( $parent ) {
				$parent_name = $parent->get_name();
			}
		}

		$categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
		$is_bulk_supported = in_array( $type, array( 'simple', 'variation' ), true );

		return Currency::row_to_display(
			array(
			'id'                => $product->get_id(),
			'name'              => $product->get_name(),
			'sku'               => $product->get_sku(),
			'global_unique_id'  => method_exists( $product, 'get_global_unique_id' ) ? $product->get_global_unique_id() : '',
			'type'              => $type,
			'status'            => $product->get_status(),
			'regular_price'     => $product->get_regular_price( 'edit' ),
			'sale_price'        => $product->get_sale_price( 'edit' ),
			'price'             => $product->get_price( 'edit' ),
			'on_sale'           => $product->is_on_sale(),
			'stock_quantity'    => $product->get_stock_quantity(),
			'stock_status'      => $product->get_stock_status(),
			'manage_stock'      => $product->get_manage_stock(),
			'categories'        => is_array( $categories ) ? $categories : array(),
			'parent_id'         => $parent_id,
			'parent_name'       => $parent_name,
			'bulk_supported'    => $is_bulk_supported,
			'bulk_skip_reason'  => $is_bulk_supported ? '' : $this->get_skip_reason( $type ),
			'edit_url'          => get_edit_post_link( $product->get_id(), 'raw' ),
			)
		);
	}

	/**
	 * Get human-readable skip reason for unsupported types.
	 *
	 * @param string $type Product type.
	 * @return string
	 */
	private function get_skip_reason( string $type ): string {
		if ( 'variable' === $type ) {
			return __( 'Variable parent — edit variations individually.', 'pricepilot' );
		}

		return __( 'Product type not supported for bulk pricing in V1.', 'pricepilot' );
	}

	/**
	 * Load product by ID.
	 *
	 * @param int $product_id Product ID.
	 * @return WC_Product|null
	 */
	public function get_product( int $product_id ): ?WC_Product {
		$product = wc_get_product( $product_id );
		return $product instanceof WC_Product ? $product : null;
	}
}
