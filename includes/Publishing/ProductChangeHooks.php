<?php
/**
 * Fires when WooCommerce product price or stock changes.
 *
 * @package StoreLink
 */

namespace StoreLink\Publishing;

defined( 'ABSPATH' ) || exit;

/**
 * Extension hook for channel/AI modules. No publishers run in this version.
 */
class ProductChangeHooks {

	public function on_product_updated( int $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$this->emit( $product, 'updated' );
	}

	public function on_stock_changed( $product ): void {
		if ( $product instanceof \WC_Product ) {
			$this->emit( $product, 'stock' );
		}
	}

	private function emit( \WC_Product $product, string $reason ): void {
		/**
		 * Product price or stock changed. Channel publishers may listen later.
		 *
		 * @param \WC_Product $product Product.
		 * @param string      $reason  Change reason.
		 */
		do_action( 'storelink_product_changed', $product, $reason );
	}
}
