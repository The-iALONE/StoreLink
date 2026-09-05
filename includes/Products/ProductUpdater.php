<?php
/**
 * Product updater via WooCommerce APIs.
 *
 * @package PricePilot
 */

namespace PricePilot\Products;

use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Updates product prices and stock through WC_Product.
 */
class ProductUpdater {

	/**
	 * Apply price changes to a product.
	 *
	 * @param WC_Product           $product Product.
	 * @param array<string, mixed> $prices  Keys: regular_price, sale_price (optional null to clear).
	 * @return true|\WP_Error
	 */
	public function update_prices( WC_Product $product, array $prices ) {
		if ( array_key_exists( 'regular_price', $prices ) ) {
			$product->set_regular_price( wc_format_decimal( $prices['regular_price'] ) );
		}

		if ( array_key_exists( 'sale_price', $prices ) ) {
			$sale = $prices['sale_price'];
			if ( null === $sale || '' === $sale ) {
				$product->set_sale_price( '' );
			} else {
				$product->set_sale_price( wc_format_decimal( $sale ) );
			}
		}

		$regular = $product->get_regular_price( 'edit' );
		$sale    = $product->get_sale_price( 'edit' );

		if ( '' !== $sale && (float) $sale > (float) $regular ) {
			return new \WP_Error(
				'sale_gt_regular',
				__( 'Sale price cannot be greater than regular price.', 'pricepilot' )
			);
		}

		$product->save();

		return true;
	}

	/**
	 * Update stock quantity.
	 *
	 * @param WC_Product $product  Product.
	 * @param int|null   $quantity Stock quantity.
	 * @return true|\WP_Error
	 */
	public function update_stock( WC_Product $product, ?int $quantity ) {
		if ( null === $quantity ) {
			return true;
		}

		$product->set_manage_stock( true );
		$product->set_stock_quantity( $quantity );
		$product->save();

		return true;
	}
}
