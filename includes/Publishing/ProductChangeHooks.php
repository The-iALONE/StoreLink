<?php
/**
 * Fires when WooCommerce product price or stock changes.
 *
 * @package StoreLink
 */

namespace StoreLink\Publishing;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes or edits channel posts from the M7 product-changed hook.
 */
class ProductChangeHooks {

	public function on_product_updated( int $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( $product instanceof \WC_Product ) {
			$this->emit( $product, 'updated' );
		}
	}

	public function on_stock_changed( $product ): void {
		if ( $product instanceof \WC_Product ) {
			$this->emit( $product, 'stock' );
		}
	}

	private function emit( \WC_Product $product, string $reason ): void {
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent instanceof \WC_Product ) {
				$product = $parent;
			}
		}

		/**
		 * Product price or stock changed.
		 *
		 * @param \WC_Product $product Product.
		 * @param string      $reason  Change reason.
		 */
		do_action( 'storelink_product_changed', $product, $reason );

		$key = 'storelink_ch_' . $product->get_id();
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 30 );

		if ( ( new ChannelPublishQueue() )->sync( $product ) ) {
			delete_transient( $key );
		}
	}
}
