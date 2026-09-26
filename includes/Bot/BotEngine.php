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
use StoreLink\Commerce\CheckoutService;
use StoreLink\Commerce\OrderService;
use StoreLink\Commerce\PriceFormat;
use StoreLink\Database\Repositories\CustomerRepository;
use StoreLink\Database\Repositories\SessionRepository;
use StoreLink\Messengers\GatewayInterface;
use StoreLink\Messengers\IncomingUpdate;
use StoreLink\Messengers\OutgoingMessage;
use StoreLink\Tracking\ProviderRegistry;
use StoreLink\Tracking\TrackingService;

defined( 'ABSPATH' ) || exit;

/**
 * Messenger-agnostic shopping conversation.
 */
class BotEngine {

	public function __construct(
		private CatalogService $catalog = new CatalogService(),
		private OrderService $orders = new OrderService(),
		private CheckoutService $checkout = new CheckoutService(),
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
		SettingsStore::remember_admin_chat( $update->user_id, $update->chat_id, $update->platform );
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
			$session['checkout']['cat_id'] = 0;
			return $this->catalog_page( $update, $session, 1, '' );
		}

		if ( $this->is_cmd( $text, __( 'Categories', 'storelink' ), '/categories' ) || 'cats' === $data || str_starts_with( $data, 'cats:' ) ) {
			$parent = str_starts_with( $data, 'cats:' ) ? (int) substr( $data, 5 ) : 0;
			return $this->category_list( $update, $session, $parent );
		}

		if ( str_starts_with( $data, 'cat:' ) ) {
			$session['checkout']['cat_id'] = absint( substr( $data, 4 ) );
			$session['search_q'] = '';
			$session['state']    = 'menu';
			return $this->catalog_page( $update, $session, 1, '' );
		}

		if ( $this->is_cmd( $text, __( 'Cart', 'storelink' ), '/cart' ) || 'cart' === $data ) {
			return $this->show_cart( $update, $session );
		}

		if ( $this->is_cmd( $text, __( 'My orders', 'storelink' ), '/orders' ) || 'myorders' === $data ) {
			return $this->my_orders( $update, $session );
		}

		if ( str_starts_with( $data, 'mf:' ) ) {
			$session['checkout']['my_filter'] = AdminOrderService::normalize_status( substr( $data, 3 ) );
			return $this->my_orders( $update, $session );
		}

		if ( str_starts_with( $data, 'mo:' ) ) {
			return $this->my_order_detail( $update, (int) substr( $data, 3 ) );
		}

		if ( $this->is_cmd( $text, __( 'Search', 'storelink' ), '/search' ) || 'search' === $data ) {
			$session['state'] = 'search';
			return $this->message( $update, __( 'Send a product name to search.', 'storelink' ), array( $this->back_row() ) );
		}

		if ( $this->is_cmd( $text, __( 'Orders', 'storelink' ), '/admin', 'Orders', 'سفارش‌ها', 'سفارش ها', 'سفارشها' ) || 'admin' === $data ) {
			return $this->admin_list( $update, $session, 1 );
		}

		if ( str_starts_with( $data, 'af:' ) ) {
			$session['checkout']['admin_filter'] = AdminOrderService::normalize_status( substr( $data, 3 ) );
			return $this->admin_list( $update, $session, 1 );
		}

		if ( str_starts_with( $data, 'ap:' ) ) {
			return $this->admin_list( $update, $session, (int) substr( $data, 3 ) );
		}

		if ( str_starts_with( $data, 'ao:' ) ) {
			return $this->admin_detail( $update, (int) substr( $data, 3 ) );
		}

		if ( str_starts_with( $data, 'trk:' ) ) {
			return $this->admin_track_start( $update, $session, (int) substr( $data, 4 ) );
		}
		if ( str_starts_with( $data, 'trc:' ) ) {
			return $this->admin_track_carrier( $update, $session, substr( $data, 4 ) );
		}

		if ( str_starts_with( $data, 'as:' ) ) {
			return $this->admin_status( $update, substr( $data, 3 ) );
		}
		if ( str_starts_with( $data, 'sf:' ) ) {
			return $this->admin_send_file( $update, (int) substr( $data, 3 ) );
		}
		if ( str_starts_with( $data, 'sk:' ) ) {
			return $this->admin_stock( $update, substr( $data, 3 ) );
		}
		if ( str_starts_with( $data, 'sq:' ) ) {
			return $this->admin_stock_qty( $update, substr( $data, 3 ) );
		}
		if ( str_starts_with( $data, 'stq:' ) ) {
			return $this->admin_stock_ask( $update, $session, (int) substr( $data, 4 ) );
		}

		if ( str_starts_with( $data, 'page:' ) ) {
			return $this->catalog_page( $update, $session, (int) substr( $data, 5 ), (string) ( $session['search_q'] ?? '' ) );
		}
		if ( str_starts_with( $data, 'p:' ) ) {
			return $this->product( $update, $session, (int) substr( $data, 2 ) );
		}
		if ( str_starts_with( $data, 'vopt:' ) ) {
			return $this->pick_variation_option( $update, $session, (int) substr( $data, 5 ) );
		}
		if ( str_starts_with( $data, 'add:' ) ) {
			return $this->add_to_cart( $update, $session, (int) substr( $data, 4 ) );
		}
		if ( str_starts_with( $data, 'cq:' ) ) {
			return $this->cart_qty_ask( $update, $session, (int) substr( $data, 3 ) );
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
			if ( SettingsStore::skip_free_download_checkout() && $this->checkout->cart_is_free_downloadable( $session['cart'] ?? array() ) ) {
				$session['checkout'] = array(
					'name'    => sanitize_text_field( $update->display_name ),
					'phone'   => sanitize_text_field( $update->phone ),
					'address' => '',
					'country' => $this->checkout->country(),
					'coupon'  => '',
				);
				$session['state'] = 'checkout_confirm';
				return $this->place_order( $update, $session );
			}
			$session['state']    = 'checkout_name';
			$session['checkout'] = array();
			return $this->message( $update, __( 'Please send your full name.', 'storelink' ), array( $this->back_row() ) );
		}

		if ( 'search' === ( $session['state'] ?? '' ) && 'message' === $update->type && '' !== $text ) {
			$session['search_q'] = $text;
			$session['checkout']['cat_id'] = 0;
			$session['state']    = 'menu';
			return $this->catalog_page( $update, $session, 1, $text );
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
			$session['checkout']['country'] = $this->checkout->country();
			if ( ! $this->checkout->cart_needs_shipping( $session['cart'] ?? array() ) ) {
				$session['checkout']['address'] = '';
				return $this->ask_coupon( $update, $session );
			}
			$session['state'] = 'checkout_address';
			return $this->message( $update, __( 'Please send your delivery address.', 'storelink' ), array( $this->back_row() ) );
		}

		if ( 'checkout_address' === $session['state'] && 'message' === $update->type && '' !== $text ) {
			$session['checkout']['address'] = sanitize_textarea_field( $text );
			$session['checkout']['country'] = $this->checkout->country();
			return $this->after_address( $update, $session );
		}

		if ( str_starts_with( $data, 'st:' ) ) {
			return $this->pick_state( $update, $session, substr( $data, 3 ) );
		}

		if ( 'checkout_city' === $session['state'] && 'message' === $update->type && '' !== $text ) {
			$session['checkout']['city'] = sanitize_text_field( $text );
			return $this->ask_shipping_or_coupon( $update, $session );
		}

		if ( str_starts_with( $data, 'shp:' ) ) {
			return $this->pick_shipping( $update, $session, (int) substr( $data, 4 ) );
		}

		if ( 'cpnskip' === $data ) {
			$session['checkout']['coupon'] = '';
			$session['state']              = 'checkout_confirm';
			return $this->confirm_checkout( $update, $session );
		}

		if ( 'checkout_coupon' === $session['state'] && 'message' === $update->type && '' !== $text ) {
			$valid = $this->checkout->validate_coupon( $text );
			if ( is_wp_error( $valid ) ) {
				return $this->message( $update, $valid->get_error_message(), array( $this->coupon_buttons() ) );
			}
			$session['checkout']['coupon'] = $valid;
			$session['state']              = 'checkout_confirm';
			return $this->confirm_checkout( $update, $session );
		}

		if ( 'place' === $data && 'checkout_confirm' === $session['state'] ) {
			return $this->place_order( $update, $session );
		}

		if ( 'track_number' === ( $session['state'] ?? '' ) && 'message' === $update->type && '' !== $text ) {
			return $this->admin_track_save( $update, $session, $text );
		}

		if ( 'admin_stock_qty' === ( $session['state'] ?? '' ) && 'message' === $update->type && '' !== $text ) {
			return $this->admin_stock_set( $update, $session, $text );
		}

		if ( 'cart_qty' === ( $session['state'] ?? '' ) && 'message' === $update->type && '' !== $text ) {
			return $this->cart_qty_set( $update, $session, $text );
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
			array( 'text' => __( 'Products', 'storelink' ), 'data' => 'catalog' ),
			array( 'text' => __( 'Categories', 'storelink' ), 'data' => 'cats' ),
		);
		$row2 = array(
			array( 'text' => __( 'Cart', 'storelink' ), 'data' => 'cart' ),
			array( 'text' => __( 'Search', 'storelink' ), 'data' => 'search' ),
		);
		$row3 = array(
			array( 'text' => __( 'My orders', 'storelink' ), 'data' => 'myorders' ),
		);
		$rows = array( $row1, $row2, $row3 );
		if ( SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			$rows[] = array( array( 'text' => __( 'Orders', 'storelink' ), 'data' => 'admin' ) );
		}

		$welcome = __( 'Welcome to the store. Choose an option:', 'storelink' );

		return new OutgoingMessage(
			$update->chat_id,
			$welcome,
			$rows,
			'',
			$update->callback_query_id,
			'reply'
		);
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function catalog_page( IncomingUpdate $update, array &$session, int $page, string $search ): OutgoingMessage {
		$cat_id = '' !== $search ? 0 : absint( $session['checkout']['cat_id'] ?? 0 );
		$list   = $this->catalog->list_page( $page, $search, $cat_id );
		$buttons = array();

		if ( $cat_id > 0 && 1 === $page && '' === $search ) {
			foreach ( $this->catalog->list_categories( $cat_id ) as $category ) {
				$buttons[] = array(
					array(
						'text' => $category['name'],
						'data' => 'cat:' . $category['id'],
					),
				);
			}
		}

		if ( empty( $list['items'] ) && empty( $buttons ) ) {
			$empty = array();
			if ( $cat_id > 0 ) {
				$empty[] = array( array( 'text' => __( 'All products', 'storelink' ), 'data' => 'catalog' ) );
			}
			$empty[] = $this->back_row();
			return $this->message( $update, __( 'No products found.', 'storelink' ), $empty );
		}

		foreach ( $list['items'] as $item ) {
			$buttons[] = array(
				array(
					'text' => $this->product_list_button( $item ),
					'data' => 'p:' . $item['id'],
				),
			);
		}

		$nav = array();
		if ( $list['page'] > 1 ) {
			$nav[] = array( 'text' => __( 'Previous', 'storelink' ), 'data' => 'page:' . ( $list['page'] - 1 ) );
		}
		if ( $list['page'] < $list['pages'] && ! empty( $list['items'] ) ) {
			$nav[] = array( 'text' => __( 'Next', 'storelink' ), 'data' => 'page:' . ( $list['page'] + 1 ) );
		}
		if ( $nav ) {
			$buttons[] = $nav;
		}
		if ( $cat_id > 0 ) {
			$buttons[] = array( array( 'text' => __( 'All products', 'storelink' ), 'data' => 'catalog' ) );
			$buttons[] = array( array( 'text' => __( 'Categories', 'storelink' ), 'data' => 'cats' ) );
		}
		$buttons[] = $this->back_row();

		$cat_name = $cat_id > 0 ? $this->catalog->category_name( $cat_id ) : '';
		if ( '' !== $cat_name ) {
			$text = sprintf(
				/* translators: 1: category name 2: page 3: total pages */
				__( '%1$s (page %2$d of %3$d)', 'storelink' ),
				$cat_name,
				$list['page'],
				$list['pages']
			);
		} else {
			$text = sprintf(
				/* translators: 1: page 2: total pages */
				__( 'Products (page %1$d of %2$d)', 'storelink' ),
				$list['page'],
				$list['pages']
			);
		}

		return $this->message( $update, $text, $buttons );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function category_list( IncomingUpdate $update, array &$session, int $parent ): OutgoingMessage {
		$session['state']    = 'menu';
		$session['search_q'] = '';
		$categories          = $this->catalog->list_categories( $parent );
		if ( empty( $categories ) ) {
			return $this->message( $update, __( 'No categories found.', 'storelink' ), array( $this->back_row() ) );
		}

		$buttons = array();
		foreach ( $categories as $category ) {
			$buttons[] = array(
				array(
					'text' => $category['name'],
					'data' => 'cat:' . $category['id'],
				),
			);
		}
		$buttons[] = array( array( 'text' => __( 'All products', 'storelink' ), 'data' => 'catalog' ) );
		$buttons[] = $this->back_row();

		return $this->message( $update, __( 'Choose a category:', 'storelink' ), $buttons );
	}

	private function product( IncomingUpdate $update, array &$session, int $product_id ): OutgoingMessage {
		$item = $this->catalog->get( $product_id );
		if ( ! $item ) {
			return $this->message( $update, __( 'Product not found.', 'storelink' ), array( $this->back_row() ) );
		}

		if ( 'variable' === $item['type'] ) {
			$session['checkout']['vary'] = array(
				'id'       => $product_id,
				'selected' => array(),
			);
			$session['state'] = 'vary';
			return $this->variation_step( $update, $session );
		}

		$stock = $item['in_stock'] ? __( 'In stock', 'storelink' ) : __( 'Out of stock', 'storelink' );
		$price = (string) ( $item['price_now'] ?? $item['price_html'] );
		$kind  = TrackingService::kind_label( ! empty( $item['virtual'] ), ! empty( $item['downloadable'] ) );
		$text  = sprintf( "%s\n%s\n%s\n%s", $item['name'], $price, $stock, $kind );
		if ( SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			$text .= "\n" . sprintf( __( 'Stock: %s', 'storelink' ), (string) (int) ( $item['stock'] ?? 0 ) );
		}
		if ( ! empty( $item['downloadable'] ) ) {
			$text .= "\n" . ( (float) $item['price'] > 0
				? __( 'Downloadable file after payment.', 'storelink' )
				: __( 'Free download after checkout.', 'storelink' ) );
		}
		$rows  = array();

		if ( $item['purchasable'] || (float) $item['price'] <= 0 ) {
			$rows[] = array( array( 'text' => __( 'Add to cart', 'storelink' ), 'data' => 'add:' . $item['id'] ) );
		}
		if ( SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			$rows[] = array(
				array( 'text' => __( 'In stock', 'storelink' ), 'data' => 'sk:' . $item['id'] . ':in' ),
				array( 'text' => __( 'Out of stock', 'storelink' ), 'data' => 'sk:' . $item['id'] . ':out' ),
			);
			$rows[] = array(
				array( 'text' => '−', 'data' => 'sq:' . $item['id'] . ':-1' ),
				array( 'text' => '+', 'data' => 'sq:' . $item['id'] . ':1' ),
			);
			$rows[] = array(
				array( 'text' => __( 'Set stock', 'storelink' ), 'data' => 'stq:' . $item['id'] ),
			);
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
	private function variation_step( IncomingUpdate $update, array &$session ): OutgoingMessage {
		$vary = $session['checkout']['vary'] ?? array();
		$id   = (int) ( $vary['id'] ?? 0 );
		$sel  = is_array( $vary['selected'] ?? null ) ? $vary['selected'] : array();
		$next = $this->catalog->next_attribute( $id, $sel );

		if ( ! $next ) {
			$variation = $this->catalog->match_variation( $id, $sel );
			unset( $session['checkout']['vary'] );
			$session['state'] = 'menu';
			if ( ! $variation ) {
				return $this->message( $update, __( 'This combination is not available.', 'storelink' ), array( $this->back_row() ) );
			}
			if ( SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
				return $this->product( $update, $session, $variation->get_id() );
			}
			return $this->add_to_cart( $update, $session, $variation->get_id() );
		}

		$session['checkout']['vary']['options'] = array_column( $next['options'], 'slug' );
		$buttons = array();
		foreach ( $next['options'] as $index => $option ) {
			$buttons[] = array(
				array(
					'text' => $option['name'],
					'data' => 'vopt:' . $index,
				),
			);
		}
		$buttons[] = $this->back_row();

		return $this->message(
			$update,
			sprintf(
				/* translators: %s: attribute label */
				__( 'Choose %s:', 'storelink' ),
				$next['label']
			),
			$buttons
		);
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function pick_variation_option( IncomingUpdate $update, array &$session, int $index ): OutgoingMessage {
		$vary = $session['checkout']['vary'] ?? array();
		$id   = (int) ( $vary['id'] ?? 0 );
		if ( $id < 1 ) {
			return $this->message( $update, __( 'Product not found.', 'storelink' ), array( $this->back_row() ) );
		}

		$options = $vary['options'] ?? array();
		$slug    = (string) ( $options[ $index ] ?? '' );
		$sel     = is_array( $vary['selected'] ?? null ) ? $vary['selected'] : array();
		$next    = $this->catalog->next_attribute( $id, $sel );
		if ( '' === $slug || ! $next ) {
			return $this->variation_step( $update, $session );
		}

		$session['checkout']['vary']['selected'][ $next['key'] ] = $slug;
		return $this->variation_step( $update, $session );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function add_to_cart( IncomingUpdate $update, array &$session, int $product_id ): OutgoingMessage {
		$item = $this->catalog->get( $product_id );
		if ( ! $item ) {
			return $this->message( $update, __( 'This product cannot be purchased.', 'storelink' ), array( $this->back_row() ) );
		}

		if ( 'variable' === $item['type'] ) {
			return $this->product( $update, $session, $product_id );
		}

		if ( empty( $item['purchasable'] ) && (float) $item['price'] > 0 ) {
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
	private function cart_qty_ask( IncomingUpdate $update, array &$session, int $product_id ): OutgoingMessage {
		$found = false;
		foreach ( $session['cart'] as $line ) {
			if ( (int) $line['id'] === $product_id ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			return $this->show_cart( $update, $session );
		}

		$session['state']                    = 'cart_qty';
		$session['checkout']['cart_line_id'] = $product_id;
		$item = $this->catalog->get( $product_id );
		$name = $item ? (string) $item['name'] : '';

		return $this->message(
			$update,
			sprintf(
				/* translators: %s: product name */
				__( 'Send the quantity for %s as a number.', 'storelink' ),
				$name
			),
			array( $this->back_row() )
		);
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function cart_qty_set( IncomingUpdate $update, array &$session, string $text ): OutgoingMessage {
		$qty = $this->parse_nonneg_int( $text );
		if ( null === $qty ) {
			return $this->message( $update, __( 'Send the quantity as a number.', 'storelink' ), array( $this->back_row() ) );
		}

		$product_id = (int) ( $session['checkout']['cart_line_id'] ?? 0 );
		$session['state'] = 'menu';
		unset( $session['checkout']['cart_line_id'] );

		$notice = '';
		$product = wc_get_product( $product_id );
		if ( $product instanceof \WC_Product && $product->managing_stock() ) {
			$max = max( 0, (int) $product->get_stock_quantity() );
			if ( $qty > $max ) {
				$qty    = $max;
				$notice = sprintf( __( 'Maximum stock: %s', 'storelink' ), (string) $max );
			}
		}

		foreach ( $session['cart'] as $index => $line ) {
			if ( (int) $line['id'] !== $product_id ) {
				continue;
			}
			if ( $qty < 1 ) {
				unset( $session['cart'][ $index ] );
			} else {
				$session['cart'][ $index ]['qty'] = $qty;
			}
			break;
		}
		$session['cart'] = array_values( $session['cart'] );

		return $this->show_cart( $update, $session, $notice );
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
	private function show_cart( IncomingUpdate $update, array $session, string $notice = '' ): OutgoingMessage {
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
			$line_html = PriceFormat::amount( $total );
			$kind      = TrackingService::kind_label( ! empty( $item['virtual'] ), ! empty( $item['downloadable'] ) );
			$lines[]   = sprintf( '%s × %d — %s — %s', $item['name'], $qty, $kind, $line_html );
			$buttons[] = array(
				array( 'text' => '−', 'data' => 'dec:' . $item['id'] ),
				array( 'text' => (string) $qty, 'data' => 'cq:' . $item['id'] ),
				array( 'text' => '+', 'data' => 'inc:' . $item['id'] ),
			);
			$buttons[] = array(
				array(
					'text' => __( 'Remove', 'storelink' ),
					'data' => 'rm:' . $item['id'],
				),
			);
		}

		$lines[]   = sprintf( __( 'Total: %s', 'storelink' ), PriceFormat::amount( $sum ) );
		$buttons[] = array( array( 'text' => __( 'Checkout', 'storelink' ), 'data' => 'checkout' ) );
		$buttons[] = array( array( 'text' => __( 'Empty cart', 'storelink' ), 'data' => 'clr' ) );
		$buttons[] = $this->back_row();

		$text = implode( "\n", $lines );
		if ( '' !== $notice ) {
			$text = $notice . "\n" . $text;
		}

		return $this->message( $update, $text, $buttons );
	}

	private function my_orders( IncomingUpdate $update, array &$session ): OutgoingMessage {
		$filter = AdminOrderService::normalize_status( (string) ( $session['checkout']['my_filter'] ?? '' ) );
		$items  = $this->orders->list_for_customer( $update->platform, $update->chat_id, $filter );
		if ( empty( $items ) ) {
			$buttons   = $this->status_filter_rows( 'mf' );
			$buttons[] = $this->back_row();
			return $this->message( $update, __( 'You have no orders yet.', 'storelink' ), $buttons );
		}

		$lines   = array( __( 'Your orders:', 'storelink' ) );
		$buttons = $this->status_filter_rows( 'mf' );
		foreach ( $items as $item ) {
			$lines[] = sprintf(
				'#%s — %s — %s — %s',
				$item['number'],
				$item['status'],
				sprintf( __( '%d items', 'storelink' ), (int) $item['qty'] ),
				$item['total']
			);
			$buttons[] = array(
				array(
					'text' => sprintf( __( 'Order #%s', 'storelink' ), $item['number'] ),
					'data' => 'mo:' . $item['id'],
				),
			);
		}
		$buttons[] = $this->back_row();

		return $this->message( $update, implode( "\n", $lines ), $buttons );
	}

	private function my_order_detail( IncomingUpdate $update, int $order_id ): OutgoingMessage {
		$detail = $this->orders->get_for_customer( $order_id, $update->platform, $update->chat_id );
		if ( ! $detail ) {
			return $this->message( $update, __( 'Order not found.', 'storelink' ), array( $this->back_row() ) );
		}

		$parts = array(
			sprintf( '#%s', $detail['number'] ),
			$detail['status'],
			sprintf( __( '%d items', 'storelink' ), (int) $detail['qty'] ),
			$detail['total'],
			implode( "\n", $detail['lines'] ),
			$detail['address'],
			$detail['phone'],
		);
		if ( ! empty( $detail['shipping'] ) ) {
			$parts[] = implode( "\n", $detail['shipping'] );
		}
		if ( ! empty( $detail['coupons'] ) ) {
			$parts[] = sprintf( __( 'Coupon: %s', 'storelink' ), implode( ', ', $detail['coupons'] ) );
		}
		if ( ! empty( $detail['tracking']['has'] ) && ! empty( $detail['tracking']['lines'] ) ) {
			$parts[] = implode( "\n", $detail['tracking']['lines'] );
		}

		$buttons = array();
		if ( ! empty( $detail['site_url'] ) ) {
			$buttons[] = array(
				array(
					'text' => __( 'View on site', 'storelink' ),
					'url'  => $detail['site_url'],
				),
			);
		}
		if ( ! empty( $detail['needs_payment'] ) && ! empty( $detail['payment_url'] ) ) {
			$buttons[] = array(
				array(
					'text' => __( 'Pay now', 'storelink' ),
					'url'  => $detail['payment_url'],
				),
			);
		}
		$track_url = (string) ( $detail['tracking']['url'] ?? '' );
		if ( self::public_https_url( $track_url ) ) {
			$buttons[] = array(
				array(
					'text' => __( 'Track shipment', 'storelink' ),
					'url'  => $track_url,
				),
			);
		}
		foreach ( $detail['downloads'] ?? array() as $file ) {
			if ( empty( $file['url'] ) ) {
				continue;
			}
			$buttons[] = array(
				array(
					'text' => sprintf( __( 'Download: %s', 'storelink' ), $file['name'] ),
					'url'  => $file['url'],
				),
			);
		}
		$buttons[] = array( array( 'text' => __( 'My orders', 'storelink' ), 'data' => 'myorders' ) );
		$buttons[] = $this->back_row();

		return $this->message( $update, implode( "\n", array_filter( $parts ) ), $buttons );
	}

	private function admin_list( IncomingUpdate $update, array &$session, int $page ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$filter  = AdminOrderService::normalize_status( (string) ( $session['checkout']['admin_filter'] ?? '' ) );
		$list    = $this->admin_orders->list_page( $page, $filter );
		$buttons = $this->status_filter_rows( 'af' );

		if ( empty( $list['items'] ) ) {
			$buttons[] = $this->back_row();
			return $this->message( $update, __( 'No orders found.', 'storelink' ), $buttons );
		}

		foreach ( $list['items'] as $item ) {
			$buttons[] = array(
				array(
					'text' => sprintf( '#%s — %s — %s', $item['number'], $item['total'], $item['status'] ),
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
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$detail = $this->admin_orders->get( $order_id );
		if ( ! $detail ) {
			return $this->message( $update, __( 'Order not found.', 'storelink' ), array( $this->back_row() ) );
		}

		$text = implode(
			"\n",
			array_values(
				array_filter(
					array(
						'#' . $detail['number'],
						$detail['name'],
						$detail['phone'],
						$detail['address'],
						$detail['total'],
						$detail['status'],
						implode( "\n", $detail['lines'] ),
						! empty( $detail['tracking']['has'] ) ? implode( "\n", $detail['tracking']['lines'] ?? array() ) : '',
					)
				)
			)
		);

		$buttons = array();
		if ( ! empty( $detail['tracking']['needs_shipment'] ) ) {
			$buttons[] = array(
				array( 'text' => __( 'Set tracking', 'storelink' ), 'data' => 'trk:' . $order_id ),
			);
		}
		$buttons[] = array(
			array( 'text' => __( 'Pending payment', 'storelink' ), 'data' => 'as:' . $order_id . ':pending' ),
			array( 'text' => __( 'On hold', 'storelink' ), 'data' => 'as:' . $order_id . ':on-hold' ),
		);
		$buttons[] = array(
			array( 'text' => __( 'Processing', 'storelink' ), 'data' => 'as:' . $order_id . ':processing' ),
			array( 'text' => __( 'Completed', 'storelink' ), 'data' => 'as:' . $order_id . ':completed' ),
		);
		$buttons[] = array(
			array( 'text' => __( 'Cancelled', 'storelink' ), 'data' => 'as:' . $order_id . ':cancelled' ),
			array( 'text' => __( 'Failed', 'storelink' ), 'data' => 'as:' . $order_id . ':failed' ),
		);
		$buttons[] = array(
			array( 'text' => __( 'Refunded', 'storelink' ), 'data' => 'as:' . $order_id . ':refunded' ),
		);
		if ( ! empty( $detail['has_download'] ) ) {
			$buttons[] = array(
				array( 'text' => __( 'Send file', 'storelink' ), 'data' => 'sf:' . $order_id ),
			);
		}
		if ( self::public_https_url( (string) ( $detail['edit_url'] ?? '' ) ) ) {
			$buttons[] = array(
				array(
					'text' => __( 'Open in WooCommerce', 'storelink' ),
					'url'  => $detail['edit_url'],
				),
			);
		}
		$track_url = (string) ( $detail['tracking']['url'] ?? '' );
		if ( self::public_https_url( $track_url ) ) {
			$buttons[] = array(
				array(
					'text' => __( 'Track shipment', 'storelink' ),
					'url'  => $track_url,
				),
			);
		}
		$buttons[] = array(
			array( 'text' => __( 'Orders', 'storelink' ), 'data' => 'admin' ),
		);

		return $this->message( $update, $text, $buttons );
	}

	private function admin_status( IncomingUpdate $update, string $payload ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
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
	private function admin_track_start( IncomingUpdate $update, array &$session, int $order_id ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$order = $order_id > 0 ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof \WC_Order ) {
			return $this->message( $update, __( 'Order not found.', 'storelink' ), array( $this->back_row() ) );
		}
		if ( ! ( new TrackingService() )->order_needs_shipment( $order ) ) {
			return $this->message( $update, __( 'This order has no physical shipment.', 'storelink' ), array( $this->back_row() ) );
		}

		$session['checkout']['track_order'] = $order_id;
		$session['state']                   = 'track_carrier';

		$buttons = array();
		foreach ( ProviderRegistry::instance()->all() as $provider ) {
			$buttons[] = array(
				array(
					'text' => $provider->label(),
					'data' => 'trc:' . $order_id . ':' . $provider->id(),
				),
			);
		}
		$buttons[] = array( array( 'text' => __( 'Orders', 'storelink' ), 'data' => 'admin' ) );

		return $this->message( $update, __( 'Choose a shipping carrier.', 'storelink' ), $buttons );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function admin_track_carrier( IncomingUpdate $update, array &$session, string $payload ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$parts    = explode( ':', $payload, 2 );
		$order_id = (int) ( $parts[0] ?? 0 );
		$carrier  = sanitize_key( (string) ( $parts[1] ?? '' ) );
		if ( $order_id < 1 || ! ProviderRegistry::instance()->get( $carrier ) ) {
			return $this->message( $update, __( 'Unknown shipping provider.', 'storelink' ), array( $this->back_row() ) );
		}

		$session['checkout']['track_order']   = $order_id;
		$session['checkout']['track_carrier'] = $carrier;
		$session['state']                     = 'track_number';

		return $this->message( $update, __( 'Send the tracking number.', 'storelink' ), array( $this->back_row() ) );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function admin_track_save( IncomingUpdate $update, array &$session, string $number ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			$session['state'] = 'menu';
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$order_id = (int) ( $session['checkout']['track_order'] ?? 0 );
		$carrier  = sanitize_key( (string) ( $session['checkout']['track_carrier'] ?? '' ) );
		$result   = ( new TrackingService() )->save( $order_id, $carrier, $number );
		if ( is_wp_error( $result ) ) {
			return $this->message( $update, $result->get_error_message(), array( $this->back_row() ) );
		}

		$session['state'] = 'menu';
		unset( $session['checkout']['track_order'], $session['checkout']['track_carrier'] );

		return $this->admin_detail( $update, $order_id );
	}

	private function admin_send_file( IncomingUpdate $update, int $order_id ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return $this->message( $update, __( 'Order not found.', 'storelink' ), array( $this->back_row() ) );
		}

		delete_transient( 'storelink_cs_' . $order->get_id() . '_' . $order->get_status() );
		( new OrderNotifier() )->notify_customer( $order );
		return $this->message( $update, __( 'Download sent to the customer.', 'storelink' ), array( array( array( 'text' => __( 'Orders', 'storelink' ), 'data' => 'ao:' . $order_id ) ) ) );
	}

	private function admin_stock( IncomingUpdate $update, string $payload ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$parts  = explode( ':', $payload );
		$id     = (int) ( $parts[0] ?? 0 );
		$status = 'out' === ( $parts[1] ?? '' ) ? 'outofstock' : 'instock';
		$result = $this->catalog->set_stock_status( $id, $status );
		if ( is_wp_error( $result ) ) {
			return $this->message( $update, $result->get_error_message(), array( $this->back_row() ) );
		}

		$session = $this->sessions->get( $update->platform, $update->chat_id );
		return $this->product( $update, $session, $id );
	}

	private function admin_stock_qty( IncomingUpdate $update, string $payload ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$parts = explode( ':', $payload );
		$id    = (int) ( $parts[0] ?? 0 );
		$delta = (int) ( $parts[1] ?? 0 );
		$result = $this->catalog->adjust_stock( $id, $delta );
		if ( is_wp_error( $result ) ) {
			return $this->message( $update, $result->get_error_message(), array( $this->back_row() ) );
		}

		$session = $this->sessions->get( $update->platform, $update->chat_id );
		return $this->product( $update, $session, $id );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function admin_stock_ask( IncomingUpdate $update, array &$session, int $product_id ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$item = $this->catalog->get( $product_id );
		if ( ! $item || 'variable' === ( $item['type'] ?? '' ) ) {
			return $this->message( $update, __( 'Product not found.', 'storelink' ), array( $this->back_row() ) );
		}

		$session['state']                       = 'admin_stock_qty';
		$session['checkout']['stock_product'] = $product_id;

		return $this->message( $update, __( 'Send the stock quantity as a number.', 'storelink' ), array( $this->back_row() ) );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function admin_stock_set( IncomingUpdate $update, array &$session, string $text ): OutgoingMessage {
		if ( ! SettingsStore::is_admin_user( $update->user_id, $update->platform ) ) {
			$session['state'] = 'menu';
			return $this->message( $update, __( 'You are not allowed to manage orders.', 'storelink' ), array( $this->back_row() ) );
		}

		$qty = $this->parse_nonneg_int( $text );
		if ( null === $qty ) {
			return $this->message( $update, __( 'Send the stock quantity as a number.', 'storelink' ), array( $this->back_row() ) );
		}

		$id     = (int) ( $session['checkout']['stock_product'] ?? 0 );
		$result = $this->catalog->set_stock_quantity( $id, $qty );
		if ( is_wp_error( $result ) ) {
			return $this->message( $update, $result->get_error_message(), array( $this->back_row() ) );
		}

		$session['state'] = 'menu';
		unset( $session['checkout']['stock_product'] );

		return $this->product( $update, $session, $id );
	}

	private function parse_nonneg_int( string $text ): ?int {
		$map = array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		);
		$digits = strtr( trim( $text ), $map );
		$digits = preg_replace( '/\s+/', '', $digits );
		if ( ! is_string( $digits ) || ! preg_match( '/^\d+$/', $digits ) ) {
			return null;
		}

		return (int) $digits;
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function confirm_checkout( IncomingUpdate $update, array $session ): OutgoingMessage {
		$c      = $session['checkout'];
		$states = $this->checkout->states();
		$state  = (string) ( $c['state'] ?? '' );
		$state  = $states[ $state ] ?? $state;
		$coupon = (string) ( $c['coupon'] ?? '' );
		$ship   = $c['shipping'] ?? array();
		$ship_l = is_array( $ship ) ? (string) ( $ship['label'] ?? '' ) : '';
		$ship_c = is_array( $ship ) ? (string) ( $ship['cost'] ?? '' ) : '';

		$lines = array(
			__( 'Confirm your order:', 'storelink' ),
			$c['name'] ?? '',
			$c['phone'] ?? '',
			$c['address'] ?? '',
			$state,
			$c['city'] ?? '',
			$coupon ? sprintf( __( 'Coupon: %s', 'storelink' ), $coupon ) : '',
			$ship_l ? sprintf( '%s — %s', $ship_l, $ship_c ) : '',
		);

		$sum = 0.0;
		foreach ( $session['cart'] ?? array() as $row ) {
			$item = $this->catalog->get( (int) ( $row['id'] ?? 0 ) );
			if ( ! $item ) {
				continue;
			}
			$qty  = max( 1, (int) ( $row['qty'] ?? 1 ) );
			$unit = (float) $item['price'];
			$sum += $unit * $qty;
			$lines[] = sprintf(
				'%s - %d - %s',
				$item['name'],
				$qty,
				PriceFormat::amount( $unit )
			);
		}

		if ( is_array( $ship ) ) {
			$sum += (float) ( $ship['cost_raw'] ?? 0 );
		}

		$lines[] = sprintf( __( 'Grand total: %s', 'storelink' ), PriceFormat::amount( $sum ) );

		$text = implode( "\n", array_filter( $lines ) );

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

		$buttons = array();
		$files   = is_array( $result['downloads'] ?? null ) ? $result['downloads'] : array();
		if ( $files ) {
			$text .= "\n" . __( 'Your downloads:', 'storelink' );
			foreach ( $files as $file ) {
				if ( empty( $file['url'] ) ) {
					continue;
				}
				$buttons[] = array(
					array(
						'text' => sprintf( __( 'Download: %s', 'storelink' ), $file['name'] ),
						'url'  => $file['url'],
					),
				);
			}
		} elseif ( ! empty( $result['needs_payment'] ) && ! empty( $result['payment_url'] ) ) {
			$buttons[] = array(
				array(
					'text' => __( 'Pay now', 'storelink' ),
					'url'  => $result['payment_url'],
				),
			);
		}
		$buttons[] = $this->back_row();

		return $this->message( $update, $text, $buttons );
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

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function after_address( IncomingUpdate $update, array &$session ): OutgoingMessage {
		if ( $this->checkout->cart_needs_shipping( $session['cart'] ?? array() ) ) {
			return $this->ask_state( $update, $session );
		}

		return $this->ask_coupon( $update, $session );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function ask_state( IncomingUpdate $update, array &$session ): OutgoingMessage {
		$states = $this->checkout->states();
		if ( empty( $states ) ) {
			$session['checkout']['state'] = '';
			$session['state']             = 'checkout_city';
			return $this->message( $update, __( 'Please send your city.', 'storelink' ), array( $this->back_row() ) );
		}

		$session['state'] = 'checkout_state';
		$buttons          = array();
		$row              = array();
		foreach ( $states as $code => $label ) {
			$row[] = array(
				'text' => (string) $label,
				'data' => 'st:' . $code,
			);
			if ( count( $row ) === 2 ) {
				$buttons[] = $row;
				$row       = array();
			}
		}
		if ( $row ) {
			$buttons[] = $row;
		}
		$buttons[] = $this->back_row();

		return $this->message( $update, __( 'Choose your province:', 'storelink' ), $buttons );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function pick_state( IncomingUpdate $update, array &$session, string $code ): OutgoingMessage {
		$session['checkout']['state'] = sanitize_text_field( $code );
		$session['state']             = 'checkout_city';
		return $this->message( $update, __( 'Please send your city.', 'storelink' ), array( $this->back_row() ) );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function ask_shipping_or_coupon( IncomingUpdate $update, array &$session ): OutgoingMessage {
		$dest = array(
			'country' => (string) ( $session['checkout']['country'] ?? $this->checkout->country() ),
			'state'   => (string) ( $session['checkout']['state'] ?? '' ),
			'city'    => (string) ( $session['checkout']['city'] ?? '' ),
		);
		$rates = $this->checkout->shipping_rates(
			$session['cart'] ?? array(),
			$dest,
			(string) ( $session['checkout']['coupon'] ?? '' )
		);

		if ( empty( $rates ) ) {
			$session['checkout']['shipping'] = array();
			return $this->ask_coupon( $update, $session );
		}

		$session['checkout']['rates'] = $rates;
		$session['state']             = 'checkout_ship';
		$buttons                      = array();
		foreach ( $rates as $index => $rate ) {
			$buttons[] = array(
				array(
					'text' => $rate['label'] . ' — ' . $rate['cost'],
					'data' => 'shp:' . $index,
				),
			);
		}
		$buttons[] = $this->back_row();

		return $this->message( $update, __( 'Choose a shipping method:', 'storelink' ), $buttons );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function pick_shipping( IncomingUpdate $update, array &$session, int $index ): OutgoingMessage {
		$rates = $session['checkout']['rates'] ?? array();
		$rate  = $rates[ $index ] ?? null;
		if ( ! is_array( $rate ) ) {
			return $this->ask_shipping_or_coupon( $update, $session );
		}

		$session['checkout']['shipping'] = $rate;
		return $this->ask_coupon( $update, $session );
	}

	/**
	 * @param array<string, mixed> $session Session.
	 */
	private function ask_coupon( IncomingUpdate $update, array &$session ): OutgoingMessage {
		$session['state'] = 'checkout_coupon';
		return $this->message(
			$update,
			__( 'Send a WooCommerce coupon code, or skip.', 'storelink' ),
			array( $this->coupon_buttons() )
		);
	}

	/**
	 * @param array<string, mixed> $item Catalog row.
	 */
	private function product_list_button( array $item ): string {
		$name  = (string) ( $item['name'] ?? '' );
		$price = (string) ( $item['price_now'] ?? '' );
		$max   = 64;
		$sep   = $price ? ' — ' : '';
		$room  = $max - mb_strlen( $sep . $price );
		if ( $room < 8 ) {
			return $price !== '' ? $price : mb_substr( $name, 0, $max );
		}
		if ( mb_strlen( $name ) > $room ) {
			$name = mb_substr( $name, 0, max( 1, $room - 1 ) ) . '…';
		}

		return $price !== '' ? $name . $sep . $price : $name;
	}

	/**
	 * @return array<int, array{text:string, data:string}>
	 */
	private function coupon_buttons(): array {
		return array(
			array( 'text' => __( 'Skip coupon', 'storelink' ), 'data' => 'cpnskip' ),
			array( 'text' => __( 'Main menu', 'storelink' ), 'data' => 'menu' ),
		);
	}

	/**
	 * @return array<int, array<int, array{text:string, data:string}>>
	 */
	private function status_filter_rows( string $prefix ): array {
		return array(
			array(
				array( 'text' => __( 'All', 'storelink' ), 'data' => $prefix . ':all' ),
				array( 'text' => __( 'Pending payment', 'storelink' ), 'data' => $prefix . ':pending' ),
				array( 'text' => __( 'On hold', 'storelink' ), 'data' => $prefix . ':on-hold' ),
			),
			array(
				array( 'text' => __( 'Processing', 'storelink' ), 'data' => $prefix . ':processing' ),
				array( 'text' => __( 'Completed', 'storelink' ), 'data' => $prefix . ':completed' ),
			),
			array(
				array( 'text' => __( 'Cancelled', 'storelink' ), 'data' => $prefix . ':cancelled' ),
				array( 'text' => __( 'Failed', 'storelink' ), 'data' => $prefix . ':failed' ),
				array( 'text' => __( 'Refunded', 'storelink' ), 'data' => $prefix . ':refunded' ),
			),
		);
	}

	private static function public_https_url( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
			return false;
		}

		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		return '' !== $host && ! in_array( $host, array( 'localhost', '127.0.0.1' ), true );
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
