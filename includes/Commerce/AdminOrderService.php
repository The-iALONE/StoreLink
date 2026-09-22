<?php
/**
 * WooCommerce order operations for store admins.
 *
 * @package StoreLink
 */

namespace StoreLink\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Lists and updates orders through WooCommerce APIs.
 */
class AdminOrderService {

	public const PER_PAGE = 5;

	/**
	 * @return array{items: array<int, array<string, mixed>>, page: int, pages: int}
	 */
	public function list_page( int $page = 1 ): array {
		$page = max( 1, $page );

		$orders = wc_get_orders(
			array(
				'limit'    => self::PER_PAGE,
				'page'     => $page,
				'paginate' => true,
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		$items = array();
		foreach ( $orders->orders as $order ) {
			if ( $order instanceof \WC_Order ) {
				$items[] = $this->summary( $order );
			}
		}

		$total = (int) $orders->total;
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );

		return array(
			'items' => $items,
			'page'  => $page,
			'pages' => $pages,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( int $order_id ): ?array {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		$lines = array();
		foreach ( $order->get_items() as $item ) {
			$lines[] = sprintf( '%s × %s', $item->get_name(), $item->get_quantity() );
		}

		$summary = $this->summary( $order );
		$summary['lines']   = $lines;
		$summary['phone']   = $order->get_billing_phone();
		$summary['address'] = $order->get_billing_address_1();
		$summary['payment'] = $order->get_checkout_payment_url();

		return $summary;
	}

	public function update_status( int $order_id, string $status ): \WP_Error|\WC_Order {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'not_found', __( 'Order not found.', 'storelink' ) );
		}

		$allowed = array( 'processing', 'completed', 'cancelled' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new \WP_Error( 'bad_status', __( 'This status cannot be set from the bot.', 'storelink' ) );
		}

		$order->update_status( $status, 'StoreLink Telegram admin' );
		return $order;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function summary( \WC_Order $order ): array {
		return array(
			'id'     => $order->get_id(),
			'number' => $order->get_order_number(),
			'status' => wc_get_order_status_name( $order->get_status() ),
			'total'  => wp_strip_all_tags( html_entity_decode( $order->get_formatted_order_total() ) ),
			'name'   => $order->get_formatted_billing_full_name(),
		);
	}
}
