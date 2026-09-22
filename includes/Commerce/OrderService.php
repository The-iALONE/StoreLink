<?php
/**
 * WooCommerce order creator.
 *
 * @package StoreLink
 */

namespace StoreLink\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Creates WooCommerce orders from messenger carts.
 */
class OrderService {

	/**
	 * @param array<int, array{id:int, qty:int}> $items Cart lines.
	 * @param array<string, string>              $customer Checkout fields.
	 * @return array{order_id:int, number:string, payment_url:string, total:string}|\WP_Error
	 */
	public function create( array $items, array $customer, string $platform, string $chat_id, string $external_user_id ) {
		if ( empty( $items ) ) {
			return new \WP_Error( 'empty_cart', __( 'Your cart is empty.', 'storelink' ) );
		}

		$order = wc_create_order();
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		foreach ( $items as $line ) {
			$product = wc_get_product( (int) $line['id'] );
			$qty     = max( 1, (int) $line['qty'] );

			if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				$order->delete( true );
				return new \WP_Error(
					'unavailable',
					sprintf(
						/* translators: %s: product name */
						__( 'Product unavailable: %s', 'storelink' ),
						$product ? $product->get_name() : (string) $line['id']
					)
				);
			}

			if ( $product->managing_stock() && $product->get_stock_quantity() < $qty ) {
				$order->delete( true );
				return new \WP_Error( 'out_of_stock', __( 'Not enough stock for this order.', 'storelink' ) );
			}

			$order->add_product( $product, $qty );
		}

		$country = '';
		if ( function_exists( 'WC' ) && WC() && WC()->countries ) {
			$country = (string) WC()->countries->get_base_country();
		}

		$address = array(
			'first_name' => sanitize_text_field( $customer['name'] ?? '' ),
			'phone'      => sanitize_text_field( $customer['phone'] ?? '' ),
			'address_1'  => sanitize_textarea_field( $customer['address'] ?? '' ),
			'country'    => $country,
		);

		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );
		$order->update_meta_data( '_storelink_platform', sanitize_key( $platform ) );
		$order->update_meta_data( '_storelink_chat_id', sanitize_text_field( $chat_id ) );
		$order->update_meta_data( '_storelink_external_user_id', sanitize_text_field( $external_user_id ) );
		$order->calculate_totals();
		$order->save();
		( new \StoreLink\Bot\OrderNotifier() )->on_new_order( $order->get_id() );

		return array(
			'order_id'    => $order->get_id(),
			'number'      => $order->get_order_number(),
			'payment_url' => $order->get_checkout_payment_url(),
			'total'       => wp_strip_all_tags( html_entity_decode( $order->get_formatted_order_total() ) ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_customer( string $platform, string $chat_id ): array {
		$orders = wc_get_orders(
			array(
				'limit'      => 8,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_query' => array(
					array(
						'key'   => '_storelink_platform',
						'value' => sanitize_key( $platform ),
					),
					array(
						'key'   => '_storelink_chat_id',
						'value' => sanitize_text_field( $chat_id ),
					),
				),
			)
		);

		$items = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$items[] = array(
				'id'     => $order->get_id(),
				'number' => $order->get_order_number(),
				'status' => wc_get_order_status_name( $order->get_status() ),
				'total'  => wp_strip_all_tags( html_entity_decode( $order->get_formatted_order_total() ) ),
			);
		}

		return $items;
	}
}
