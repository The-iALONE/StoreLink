<?php
/**
 * Conversation engine.
 *
 * @package StoreLink
 */

namespace StoreLink\Bot;

use StoreLink\Admin\SettingsStore;
use StoreLink\Commerce\AdminOrderService;
use StoreLink\Commerce\CatalogService;
use StoreLink\Commerce\OrderService;
use StoreLink\Database\Repositories\CustomerRepository;
use StoreLink\Database\Repositories\SessionRepository;
use StoreLink\Messengers\GatewayInterface;
use StoreLink\Messengers\IncomingUpdate;
use StoreLink\Messengers\OutgoingMessage;

defined( 'ABSPATH' ) || exit;

/**
 * Messenger-agnostic shopping conversation.
 */
class BotEngine {

	public function __construct(
		private CatalogService $catalog = new CatalogService(),
		private OrderService $orders = new OrderService(),
		private AdminOrderService $admin_orders = new AdminOrderService(),
		private SessionRepository $sessions = new SessionRepository(),
		private CustomerRepository $customers = new CustomerRepository()
	) {}

	public function handle( IncomingUpdate $update, GatewayInterface $gateway ): void {
		if ( ! $this->allow_request( $update ) ) {
			return;
		}

		$session = $this->sessions->get( $update->platform, $update->chat_id );
		if ( (int) $update->update_id > 0 && (int) $update->update_id <= (int) $session['last_update_id'] ) {
			return;
		}

		$session['last_update_id'] = (int) $update->update_id;
		SettingsStore::remember_admin_chat( $update->user_id, $update->chat_id );
		$this->customers->upsert(
			array(
				'platform'         => $update->platform,
				'external_user_id' => $update->user_id,
				'chat_id'          => $update->chat_id,
				'display_name'     => $update->display_name,
				'phone'            => $update->phone,
			)
		);

		$reply = $this->route( $update, $session );
		$this->sessions->save( $session );

		if ( $reply ) {
			$gateway->send( $reply );
		}
	}

	/**
	 * @param array<string, mixed> $session Session (by ref).
	 */
	private function route( IncomingUpdate $update, array &$session ): ?OutgoingMessage {
		$text = trim( $update->text );
		$data = $update->callback_data;

		if ( '/start' === $text || 'menu' === $data ) {
			$session['state'] = 'menu';
			return $this->menu( $update );
		}

		if ( $this->is_cmd( $text, __( 'Products', 'storelink' ), '/products' ) || 'catalog' === $data ) {
			$session['state']    = 'menu';
			$session['search_q'] = '';
			return $this->catalog_page( $update, 1, '' );
		}

		if ( $this->is_cmd( $text, __( 'Cart', 'storelink' ), '/cart' ) || 'cart' === $data ) {
			return $this->show_cart( $update, $session );
		}

		if ( $this->is_cmd( $text, __( 'My orders', 'storelink' ), '/orders' ) || 'myorders' === $data ) {
			return $this->my_orders( $update );
		}

		if ( $this->is_cmd( $text, __( 'Search', 'storelink' ), '/search' ) || 'search' === $data ) {
			$session['state'] = 'search';
			return $this->message( $update, __( 'Send a product name to search.', 'storelink' ), array( $this->back_row() ) );
		}

		if ( $this->is_cmd( $text, __( 'Orders', 'storelink' ), '/admin' ) || 'admin' === $data || str_starts_with( $data, 'ap:' ) ) {
			return $this->admin_list( $update, str_starts_with( $data, 'ap:' ) ? (int) substr( $data, 3 ) : 1 );
		}

		if ( str_starts_with( $data, 'ao:' ) ) {
			return $this->admin_detail( $update, (int) substr( $data, 3 ) );
		}

		if ( str_starts_with( $data, 'as:' ) ) {
			return $this->admin_status( $update, substr( $data, 3 ) );
		}

		if ( str_starts_with( $data, 'page:' ) ) {
			return $this->catalog_page( $update, (int) substr( $data, 5 ), (string) ( $session['search_q'] ?? '' ) );
		}
		if ( str_starts_with( $data, 'p:' ) ) {
			return $this->product( $update, (int) substr( $data, 2 ) );
		}
		if ( str_starts_with( $data, 'add:' ) ) {
			return $this->add_to_cart( $update, $session, (int) substr( $data, 4 ) );
		}
		if ( str_starts_with( $data, 'inc:' ) ) {
			return $this->change_qty( $update, $session, (int) substr( $data, 4 ), 1 );
		}
		if ( str_starts_with( $data, 'dec:' ) ) {
			return $this->change_qty( $update, $session, (int) substr( $data, 4 ), -1 );
		}
		if ( 'clr' === $data ) {
			$session['cart'] = array();
			return $this->show_cart( $update, $session );
		}
		if ( str_starts_with( $data, 'rm:' ) ) {
			return $this->remove_line( $update, $session, (int) substr( $data, 3 ) );
		}
		if ( 'checkout' === $data ) {
			$session['state']    = 'checkout_name';
			$session['checkout'] = array();
			return $this->message( $update, __( 'Please send your full name.', 'storelink' ), array( $this->back_row() ) );
		}

		if ( 'search' === ( $session['state'] ?? '' ) && 'message' === $update->type && '' !== $text ) {
			$session['search_q'] = $text;
			$session['state']    = 'menu';
			return $this->catalog_page( $update, 1, $text );
		}

		if ( 'checkout_name' === $session['state'] && 'message' === $update->type && '' !== $text ) {
			$session['checkout']['name'] = sanitize_text_field( $text );
			$session['state']            = 'checkout_phone';
			return new OutgoingMessage(
				$update->chat_id,
				__( 'Please send your phone number.', 'storelink' ),
				array(
					array( array( 'text' => __( 'Share phone number', 'storelink' ) ) ),
				),
				'',
				$update->callback_query_id,
				'contact'
			);
		}

		if ( 'checkout_phone' === $session['state'] && 'message' === $update->type ) {
			$phone = $update->phone !== '' ? $update->phone : $text;
			if ( '' === $phone ) {
				return new OutgoingMessage(
					$update->chat_id,
					__( 'Please send your phone number.', 'storelink' ),
					array(
						array( array( 'text' => __( 'Share phone number', 'storelink' ) ) ),
					),
					'',
					$update->callback_query_id,
					'contact'
				);
			}
			$session['checkout']['phone'] = sanitize_text_field( $phone );
			$session['state']             = 'checkout_address';
			return $this->message( $update, __( 'Please send your delivery address.', 'storelink' ), array( $this->back_row() ) );
		}

		if ( 'checkout_address' === $session['state'] && 'message' === $update->type && '' !== $text ) {
			$session['checkout']['address'] = sanitize_textarea_field( $text );
			$session['state']               = 'checkout_confirm';
			return $this->confirm_checkout( $update, $session );
		}

		if ( 'place' === $data && 'checkout_confirm' === $session['state'] ) {
			return $this->place_order( $update, $session );
		}

		return $this->menu( $update );
	}

	private function is_cmd( string $text, string ...$aliases ): bool {
		foreach ( $aliases as $alias ) {
			if ( $text === $alias ) {
				return true;
			}
		}

		return false;
	}

	private function menu( IncomingUpdate $update ): OutgoingMessage {
		$row1 = array(
			array( 'text' => __( 'Products', 'storelink' ) ),
			array( 'text' => __( 'Cart', 'storelink' ) ),
		);
		$row2 = array(
			array( 'text' => __( 'Search', 'storelink' ) ),
			array( 'text' => __( 'My orders', 'storelink' ) ),
		);
		$rows = array( $row1, $row2 );
		if ( SettingsStore::is_admin_user( $update->user_id ) ) {
			$rows[] = array( array( 'text' => __( 'Orders', 'storelink' ) ) );
		}

		return new OutgoingMessage(
			$update->chat_id,
			__( 'Welcome to the store. Choose an option:', 'storelink' ),
			$rows,
			'',
			$update->callback_query_id,
			'reply'
		);
	}

	private function catalog_page( IncomingUpdate $update, int $page, string $search ): OutgoingMessage {
		$list    = $this->catalog->list_page( $page, $search );
		$buttons = array();

		if ( empty( $list['items'] ) ) {
			return $this->message( $update, __( 'No products found.', 'storelink' ), array( $this->back_row() ) );
		}

		foreach ( $list['items'] as $item ) {
			$buttons[] = array(
				array(
					'text' => $item['name'] . ' — ' . $item['price_html'],
					'data' => 'p:' . $item['id'],
				),
			);
		}

		$nav = array();
		if ( $list['page'] > 1 ) {
			$nav[] = array( 'text' => __( 'Previous', 'storelink' ), 'data' => 'page:' . ( $list['page'] - 1 ) );
		}
		if ( $list['page'] < $list['pages'] ) {
			$nav[] = array( 'text' => __( 'Next', 'storelink' ), 'data' => 'page:' . ( $list['page'] + 1 ) );
		}
		if ( $nav ) {
			$buttons[] = $nav;
		}
		$buttons[] = $this->back_row();

		$text = sprintf(
			/* translators: 1: page 2: total pages */
			__( 'Products (page %1$d of %2$d)', 'storelink' ),
			$list['page'],
			$list['pages']
		);

		return $this->message( $update, $text, $buttons );
	}

	private function product( IncomingUpdate $update, int $product_id ): OutgoingMessage {
		$item = $this->catalog->get( $product_id );
		if ( ! $item ) {
			return $this->message( $update, __( 'Product not found.', 'storelink' ), array( $this->back_row() ) );
		}

		$stock = $item['in_stock'] ? __( 'In stock', 'storelink' ) : __( 'Out of stock', 'storelink' );
		$text  = sprintf( "%s\n%s\n%s", $item['name'], $item['price_html'], $stock );
		$rows  = array();

		if ( $item['purchasable'] ) {
			$rows[] = array( array( 'text' => __( 'Add to cart', 'storelink' ), 'data' => 'add:' . $item['id'] ) );
		}
		$rows[] = array(
			array( 'text' => __( 'Products', 'storelink' ), 'data' => 'catalog' ),
			array( 'text' => __( 'Cart', 'storelink' ), 'data' => 'cart' ),
		);

		return new OutgoingMessage( $update->chat_id, $text, $rows, (string) $item['image'], $update->callback_query_id );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function add_to_cart( IncomingUpdate $update, array &$session, int $product_id ): OutgoingMessage {
		$item = $this->catalog->get( $product_id );
		if ( ! $item || empty( $item['purchasable'] ) ) {
			return $this->message( $update, __( 'This product cannot be purchased.', 'storelink' ), array( $this->back_row() ) );
		}

		$found = false;
		foreach ( $session['cart'] as &$line ) {
			if ( (int) $line['id'] === $product_id ) {
				$line['qty'] = (int) $line['qty'] + 1;
				$found       = true;
				break;
			}
		}
		unset( $line );

		if ( ! $found ) {
			$session['cart'][] = array( 'id' => $product_id, 'qty' => 1 );
		}

		return $this->show_cart( $update, $session );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function change_qty( IncomingUpdate $update, array &$session, int $product_id, int $delta ): OutgoingMessage {
		foreach ( $session['cart'] as $index => $line ) {
			if ( (int) $line['id'] !== $product_id ) {
				continue;
			}
			$qty = (int) $line['qty'] + $delta;
			if ( $qty < 1 ) {
				unset( $session['cart'][ $index ] );
			} else {
				$session['cart'][ $index ]['qty'] = $qty;
			}
			break;
		}
		$session['cart'] = array_values( $session['cart'] );

		return $this->show_cart( $update, $session );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function remove_line( IncomingUpdate $update, array &$session, int $product_id ): OutgoingMessage {
		$session['cart'] = array_values(
			array_filter(
				$session['cart'],
				static fn( $line ) => (int) $line['id'] !== $product_id
			)
		);

		return $this->show_cart( $update, $session );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function show_cart( IncomingUpdate $update, array $session ): OutgoingMessage {
		if ( empty( $session['cart'] ) ) {
			return $this->message( $update, __( 'Your cart is empty.', 'storelink' ), array( $this->back_row() ) );
		}

		$lines   = array();
		$buttons = array();
		$sum     = 0.0;

		foreach ( $session['cart'] as $line ) {
			$item = $this->catalog->get( (int) $line['id'] );
			if ( ! $item ) {
				continue;
			}
			$qty   = (int) $line['qty'];
			$unit  = (float) $item['price'];
			$total = $unit * $qty;
			$sum  += $total;
			$line_html = wp_strip_all_tags( html_entity_decode( wc_price( $total ) ) );
			$lines[]   = sprintf( '%s × %d — %s', $item['name'], $qty, $line_html );
			$buttons[] = array(
				array( 'text' => '−', 'data' => 'dec:' . $item['id'] ),
				array( 'text' => (string) $qty, 'data' => 'cart' ),
				array( 'text' => '+', 'data' => 'inc:' . $item['id'] ),
			);
			$buttons[] = array(
				array(
					'text' => sprintf( __( 'Remove %s', 'storelink' ), $item['name'] ),
					'data' => 'rm:' . $item['id'],
				),
			);
		}

		$lines[]   = sprintf( __( 'Total: %s', 'storelink' ), wp_strip_all_tags( html_entity_decode( wc_price( $sum ) ) ) );
		$buttons[] = array( array( 'text' => __( 'Checkout', 'storelink' ), 'data' => 'checkout' ) );
		$buttons[] = array( array( 'text' => __( 'Empty cart', 'storelink' ), 'data' => 'clr' ) );
		$buttons[] = $this->back_row();

		return $this->message( $update, implode( "\n", $lines ), $buttons );
	}

	private function my_orders( IncomingUpdate $update ): OutgoingMessage {
		$items = $this->orders->list_for_customer( $update->platform, $update->chat_id );
		if ( empty( $items ) ) {
			return $this->message( $update, __( 'You have no orders yet.', 'storelink' ), array( $this->back_row() ) );
		}

		$lines = array( __( 'Your orders:', 'storelink' ) );
		foreach ( $items as $item ) {
			$lines[] = sprintf( '#%s — %s — %s', $item['number'], $item['total'], $item['status'] );
		}

		return $this->message( $update, implode( "\n", $lines ), array( $this->back_row() ) );
	}

	private function admin_list( IncomingUpdate $update, int $page ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$list    = $this->admin_orders->list_page( $page );
		$buttons = array();

		if ( empty( $list['items'] ) ) {
			return $this->message( $update, __( 'No orders found.', 'storelink' ), array( $this->back_row() ) );
		}

		foreach ( $list['items'] as $item ) {
			$buttons[] = array(
				array(
					'text' => sprintf( '#%s %s %s', $item['number'], $item['total'], $item['status'] ),
					'data' => 'ao:' . $item['id'],
				),
			);
		}

		$nav = array();
		if ( $list['page'] > 1 ) {
			$nav[] = array( 'text' => __( 'Previous', 'storelink' ), 'data' => 'ap:' . ( $list['page'] - 1 ) );
		}
		if ( $list['page'] < $list['pages'] ) {
			$nav[] = array( 'text' => __( 'Next', 'storelink' ), 'data' => 'ap:' . ( $list['page'] + 1 ) );
		}
		if ( $nav ) {
			$buttons[] = $nav;
		}
		$buttons[] = $this->back_row();

		return $this->message( $update, __( 'Store orders:', 'storelink' ), $buttons );
	}

	private function admin_detail( IncomingUpdate $update, int $order_id ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$detail = $this->admin_orders->get( $order_id );
		if ( ! $detail ) {
			return $this->message( $update, __( 'Order not found.', 'storelink' ), array( $this->back_row() ) );
		}

		$text = sprintf(
			"#%s\n%s\n%s\n%s\n%s\n%s\n%s",
			$detail['number'],
			$detail['name'],
			$detail['phone'],
			$detail['address'],
			$detail['total'],
			$detail['status'],
			implode( "\n", $detail['lines'] )
		);

		$buttons = array(
			array(
				array( 'text' => __( 'Processing', 'storelink' ), 'data' => 'as:' . $order_id . ':processing' ),
			),
			array(
				array( 'text' => __( 'Completed', 'storelink' ), 'data' => 'as:' . $order_id . ':completed' ),
			),
			array(
				array( 'text' => __( 'Cancelled', 'storelink' ), 'data' => 'as:' . $order_id . ':cancelled' ),
			),
			array(
				array( 'text' => __( 'Orders', 'storelink' ), 'data' => 'admin' ),
			),
		);

		return $this->message( $update, $text, $buttons );
	}

	private function admin_status( IncomingUpdate $update, string $payload ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$parts = explode( ':', $payload );
		$id    = (int) ( $parts[0] ?? 0 );
		$status = sanitize_key( (string) ( $parts[1] ?? '' ) );
		$result = $this->admin_orders->update_status( $id, $status );

		if ( is_wp_error( $result ) ) {
			return $this->message( $update, $result->get_error_message(), array( $this->back_row() ) );
		}

		return $this->admin_detail( $update, $id );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function confirm_checkout( IncomingUpdate $update, array $session ): OutgoingMessage {
		$c    = $session['checkout'];
		$text = sprintf(
			"%s\n%s\n%s\n%s",
			__( 'Confirm your order:', 'storelink' ),
			$c['name'] ?? '',
			$c['phone'] ?? '',
			$c['address'] ?? ''
		);

		return $this->message(
			$update,
			$text,
			array(
				array( array( 'text' => __( 'Place order', 'storelink' ), 'data' => 'place' ) ),
				$this->back_row(),
			)
		);
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function place_order( IncomingUpdate $update, array &$session ): OutgoingMessage {
		$result = $this->orders->create(
			$session['cart'],
			$session['checkout'],
			$update->platform,
			$update->chat_id,
			$update->user_id
		);

		if ( is_wp_error( $result ) ) {
			return $this->message( $update, $result->get_error_message(), array( $this->back_row() ) );
		}

		$session['cart']     = array();
		$session['checkout'] = array();
		$session['state']    = 'menu';

		$text = sprintf(
			/* translators: 1: order number 2: total */
			__( 'Order #%1$s created. Total: %2$s', 'storelink' ),
			$result['number'],
			$result['total']
		);

		return $this->message(
			$update,
			$text,
			array(
				array( array( 'text' => __( 'Pay now', 'storelink' ), 'url' => $result['payment_url'] ) ),
				$this->back_row(),
			)
		);
	}

	/**
	 * @param array<int, array<int, array<string, string>>> $buttons Buttons.
	 */
	private function message( IncomingUpdate $update, string $text, array $buttons ): OutgoingMessage {
		return new OutgoingMessage( $update->chat_id, $text, $buttons, '', $update->callback_query_id );
	}

	/**
	 * @return array<int, array{text:string, data:string}>
	 */
	private function back_row(): array {
		return array( array( 'text' => __( 'Main menu', 'storelink' ), 'data' => 'menu' ) );
	}

	private function allow_request( IncomingUpdate $update ): bool {
		$key   = 'storelink_rl_' . md5( $update->platform . ':' . $update->chat_id );
		$count = (int) get_transient( $key );
		if ( $count > 40 ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}
}
