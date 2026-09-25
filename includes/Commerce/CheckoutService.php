<?php
/**
 * Checkout helpers: coupons and shipping quotes.
 *
 * @package StoreLink
 */

namespace StoreLink\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Uses WooCommerce shipping zones and coupons, not custom tables.
 */
class CheckoutService {

	public function country(): string {
		$country = 'IR';
		if ( function_exists( 'WC' ) && WC() && WC()->countries ) {
			$base = (string) WC()->countries->get_base_country();
			if ( '' !== $base ) {
				$country = $base;
			}
		}

		return $country;
	}

	/**
	 * @return array<string, string> State code => label.
	 */
	public function states(): array {
		if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->countries ) {
			return array();
		}

		$states = WC()->countries->get_states( $this->country() );
		return is_array( $states ) ? $states : array();
	}

	public function cart_needs_shipping( array $items ): bool {
		foreach ( $items as $line ) {
			$product = wc_get_product( (int) ( $line['id'] ?? 0 ) );
			if ( $product instanceof \WC_Product && $product->needs_shipping() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<int, array{id?:int, qty?:int}> $items Cart lines.
	 */
	public function cart_is_free_downloadable( array $items ): bool {
		if ( empty( $items ) ) {
			return false;
		}

		foreach ( $items as $line ) {
			$product = wc_get_product( (int) ( $line['id'] ?? 0 ) );
			if ( ! $product instanceof \WC_Product || ! $product->is_downloadable() ) {
				return false;
			}
			if ( (float) $product->get_price() > 0 ) {
				return false;
			}
		}

		return true;
	}

	public function validate_coupon( string $code ): \WP_Error|string {
		$code = wc_format_coupon_code( $code );
		if ( '' === $code ) {
			return new \WP_Error( 'coupon', __( 'Enter a coupon code or skip.', 'storelink' ) );
		}

		$coupon = new \WC_Coupon( $code );
		if ( ! $coupon->get_id() ) {
			return new \WP_Error( 'coupon', __( 'This coupon is not valid.', 'storelink' ) );
		}

		return $code;
	}

	/**
	 * @param array<int, array{id:int, qty:int}> $items Cart lines.
	 * @param array{country:string, state:string, city:string} $dest Destination.
	 * @return array<int, array{id:string, label:string, cost:string, cost_raw:float, method_id:string, instance_id:int}>
	 */
	public function shipping_rates( array $items, array $dest, string $coupon = '' ): array {
		if ( ! function_exists( 'wc_load_cart' ) ) {
			return array();
		}

		if ( is_null( WC()->cart ) ) {
			wc_load_cart();
		}

		WC()->cart->empty_cart( true );
		foreach ( $items as $line ) {
			WC()->cart->add_to_cart( (int) $line['id'], max( 1, (int) $line['qty'] ) );
		}

		if ( '' !== $coupon ) {
			WC()->cart->apply_coupon( $coupon );
		}

		if ( WC()->customer ) {
			WC()->customer->set_shipping_country( $dest['country'] ?? $this->country() );
			WC()->customer->set_shipping_state( $dest['state'] ?? '' );
			WC()->customer->set_shipping_city( $dest['city'] ?? '' );
			WC()->customer->set_billing_country( $dest['country'] ?? $this->country() );
			WC()->customer->set_billing_state( $dest['state'] ?? '' );
			WC()->customer->set_billing_city( $dest['city'] ?? '' );
		}

		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		$rates = array();
		foreach ( WC()->shipping()->get_packages() as $package ) {
			if ( empty( $package['rates'] ) || ! is_array( $package['rates'] ) ) {
				continue;
			}
			foreach ( $package['rates'] as $rate_id => $rate ) {
				if ( ! $rate instanceof \WC_Shipping_Rate ) {
					continue;
				}
				$cost    = (float) $rate->get_cost();
				$rates[] = array(
					'id'          => (string) $rate_id,
					'label'       => $rate->get_label(),
					'cost'        => PriceFormat::amount( $cost ),
					'cost_raw'    => $cost,
					'method_id'   => $rate->get_method_id(),
					'instance_id' => (int) $rate->get_instance_id(),
				);
			}
		}

		WC()->cart->empty_cart( true );

		return $rates;
	}
}
