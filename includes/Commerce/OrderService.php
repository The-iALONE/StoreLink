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
	 * @param array<string, mixed>               $customer Checkout fields.
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

			if ( $product instanceof \WC_Product_Variable ) {
				$order->delete( true );
				return new \WP_Error( 'variation', __( 'Choose product options before checkout.', 'storelink' ) );
			}

			if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() || 'outofstock' === $product->get_stock_status() ) {
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

		$country = sanitize_text_field( (string) ( $customer['country'] ?? '' ) );
		if ( '' === $country ) {
			$country = ( new CheckoutService() )->country();
		}

		$address = array(
			'first_name' => sanitize_text_field( $customer['name'] ?? '' ),
			'email'      => $this->guest_email( $platform, $chat_id, (string) ( $customer['email'] ?? '' ) ),
			'phone'      => sanitize_text_field( $customer['phone'] ?? '' ),
			'address_1'  => sanitize_textarea_field( $customer['address'] ?? '' ),
			'city'       => sanitize_text_field( $customer['city'] ?? '' ),
			'state'      => sanitize_text_field( $customer['state'] ?? '' ),
			'country'    => $country,
		);

		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );
		$order->update_meta_data( '_storelink_platform', sanitize_key( $platform ) );
		$order->update_meta_data( '_storelink_chat_id', sanitize_text_field( $chat_id ) );
		$order->update_meta_data( '_storelink_external_user_id', sanitize_text_field( $external_user_id ) );

		$coupon = sanitize_text_field( (string) ( $customer['coupon'] ?? '' ) );
		if ( '' !== $coupon ) {
			$result = $order->apply_coupon( $coupon );
			if ( is_wp_error( $result ) ) {
				$order->delete( true );
				return $result;
			}
		}

		$shipping = $customer['shipping'] ?? array();
		if ( is_array( $shipping ) && ! empty( $shipping['method_id'] ) ) {
			$item = new \WC_Order_Item_Shipping();
			$item->set_method_title( sanitize_text_field( (string) ( $shipping['label'] ?? '' ) ) );
			$item->set_method_id( sanitize_text_field( (string) $shipping['method_id'] ) );
			$item->set_instance_id( absint( $shipping['instance_id'] ?? 0 ) );
			$item->set_total( (float) ( $shipping['cost_raw'] ?? 0 ) );
			$order->add_item( $item );
		}

		$order->calculate_totals();
		$order->save();

		if ( (float) $order->get_total() <= 0 ) {
			$order->payment_complete();
			$order = wc_get_order( $order->get_id() );
			if ( $order instanceof \WC_Order && self::is_digital_only( $order ) && ! $order->has_status( 'processing' ) ) {
				$order->update_status( 'processing', 'StoreLink virtual order' );
			}
		}

		if ( function_exists( 'wc_downloadable_product_permissions' ) && $order->has_status( 'completed' ) ) {
			wc_downloadable_product_permissions( $order->get_id(), true );
		} elseif ( $order->has_downloadable_item() ) {
			self::ensure_download_permissions( $order );
		}

		( new \StoreLink\Bot\OrderNotifier() )->on_new_order( $order->get_id() );

		$order = wc_get_order( $order->get_id() );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'not_found', __( 'Order not found.', 'storelink' ) );
		}

		return array(
			'order_id'       => $order->get_id(),
			'number'         => $order->get_order_number(),
			'payment_url'    => $order->get_checkout_payment_url(),
			'total'          => PriceFormat::from_html( $order->get_formatted_order_total() ),
			'needs_payment'  => $order->needs_payment(),
			'downloads'      => self::download_items( $order ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_customer( string $platform, string $chat_id, string $status = '' ): array {
		$args = array(
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
		);

		$status = AdminOrderService::normalize_status( $status );
		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		$orders = wc_get_orders( $args );

		$items = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$items[] = $this->customer_summary( $order );
		}

		return $items;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_for_customer( int $order_id, string $platform, string $chat_id ): ?array {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		if ( sanitize_key( $platform ) !== (string) $order->get_meta( '_storelink_platform' ) ) {
			return null;
		}
		if ( sanitize_text_field( $chat_id ) !== (string) $order->get_meta( '_storelink_chat_id' ) ) {
			return null;
		}

		$lines = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$kind    = \StoreLink\Tracking\TrackingService::product_kind_label( $product instanceof \WC_Product ? $product : null );
			$lines[] = '' !== $kind
				? sprintf( '%s × %s — %s', $item->get_name(), $item->get_quantity(), $kind )
				: sprintf( '%s × %s', $item->get_name(), $item->get_quantity() );
		}

		$shipping = array();
		foreach ( $order->get_items( 'shipping' ) as $ship ) {
			$shipping[] = sprintf(
				'%s — %s',
				$ship->get_name(),
				PriceFormat::amount( (float) $ship->get_total() )
			);
		}

		$coupons = $order->get_coupon_codes();
		$summary = $this->customer_summary( $order );
		$summary['lines']      = $lines;
		$summary['phone']      = $order->get_billing_phone();
		$summary['address']    = trim( $order->get_billing_address_1() . ' ' . $order->get_billing_city() );
		$summary['state']      = $order->get_billing_state();
		$summary['shipping']   = $shipping;
		$summary['coupons']    = $coupons;
		$summary['date']       = $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';
		$summary['site_url']   = $order->get_checkout_order_received_url();
		$summary['needs_payment'] = $order->needs_payment();
		$summary['downloads']     = self::download_items( $order );
		$summary['tracking']      = ( new \StoreLink\Tracking\TrackingService() )->format_for_chat( $order );

		return $summary;
	}

	public static function is_digital_only( \WC_Order $order ): bool {
		$found = false;
		foreach ( $order->get_items() as $line ) {
			$product = $line->get_product();
			if ( ! $product instanceof \WC_Product ) {
				return false;
			}
			if ( ! $product->is_downloadable() && ! $product->is_virtual() ) {
				return false;
			}
			$found = true;
		}

		return $found;
	}

	/**
	 * Files for messenger delivery (processing / completed only).
	 *
	 * @return array<int, array{name:string, url:string, path:string}>
	 */
	public static function download_items( \WC_Order $order ): array {
		$status = $order->get_status();
		if ( ! in_array( $status, array( 'processing', 'completed' ), true ) ) {
			return array();
		}

		if ( $order->has_downloadable_item() ) {
			self::ensure_download_permissions( $order );
			$fresh = wc_get_order( $order->get_id() );
			if ( $fresh instanceof \WC_Order ) {
				$order = $fresh;
			}
		}

		$items = array();
		foreach ( $order->get_items() as $line ) {
			$product = $line->get_product();
			if ( ! $product instanceof \WC_Product || ! $product->is_downloadable() ) {
				continue;
			}
			foreach ( $product->get_downloads() as $file ) {
				$raw  = (string) $file->get_file();
				$name = (string) $file->get_name();
				$path = self::local_download_path( $raw );
				$url  = self::public_download_url( $raw );
				if ( '' === $path && '' === $url ) {
					continue;
				}
				$items[] = array(
					'name' => '' !== $name ? $name : $product->get_name(),
					'url'  => $url,
					'path' => $path,
				);
			}
		}

		if ( $items ) {
			return $items;
		}

		foreach ( $order->get_downloadable_items() as $item ) {
			$url = (string) ( $item['download_url'] ?? '' );
			if ( '' === $url ) {
				continue;
			}
			$name = (string) ( $item['download_name'] ?? '' );
			if ( '' === $name ) {
				$name = (string) ( $item['product_name'] ?? __( 'Download', 'storelink' ) );
			}
			$items[] = array(
				'name' => $name,
				'url'  => $url,
				'path' => '',
			);
		}

		return $items;
	}

	private static function local_download_path( string $file ): string {
		if ( '' === $file ) {
			return '';
		}

		$uploads = wp_get_upload_dir();
		$base    = (string) ( $uploads['basedir'] ?? '' );
		$url     = (string) ( $uploads['baseurl'] ?? '' );

		$candidates = array();
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $file ) ) {
			$candidates[] = $file;
		}
		if ( '' !== $url && str_starts_with( $file, $url ) ) {
			$candidates[] = $base . substr( $file, strlen( $url ) );
		}

		foreach ( $candidates as $candidate ) {
			if ( self::inside_uploads( $candidate, $base ) ) {
				return (string) realpath( $candidate );
			}
		}

		return '';
	}

	private static function inside_uploads( string $candidate, string $base ): bool {
		if ( '' === $base || ! is_file( $candidate ) || ! is_readable( $candidate ) ) {
			return false;
		}

		$real_base = realpath( $base );
		$real_file = realpath( $candidate );
		if ( false === $real_base || false === $real_file ) {
			return false;
		}

		$real_base = rtrim( str_replace( '\\', '/', $real_base ), '/' ) . '/';
		$real_file = str_replace( '\\', '/', $real_file );

		return str_starts_with( $real_file, $real_base );
	}

	private static function public_download_url( string $file ): string {
		if ( ! preg_match( '#^https://#i', $file ) ) {
			return '';
		}
		$host = strtolower( (string) wp_parse_url( $file, PHP_URL_HOST ) );
		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			return '';
		}
		return $file;
	}

	private static function ensure_download_permissions( \WC_Order $order ): void {
		if ( ! function_exists( 'wc_downloadable_file_permission' ) ) {
			return;
		}

		$data_store = \WC_Data_Store::load( 'customer-download' );
		$existing   = $data_store->get_downloads( array( 'order_id' => $order->get_id() ) );
		if ( ! empty( $existing ) ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product instanceof \WC_Product || ! $product->is_downloadable() ) {
				continue;
			}
			foreach ( array_keys( $product->get_downloads() ) as $download_id ) {
				wc_downloadable_file_permission( $download_id, $product, $order, $item->get_quantity(), $item );
			}
		}
	}

	private function guest_email( string $platform, string $chat_id, string $email ): string {
		$email = sanitize_email( $email );
		if ( is_email( $email ) ) {
			return $email;
		}

		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' === $host || ! str_contains( $host, '.' ) ) {
			$host = 'storelink.local';
		}

		$local = 'sl' . substr( md5( $platform . ':' . $chat_id ), 0, 10 );
		return $local . '@' . $host;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function customer_summary( \WC_Order $order ): array {
		$names = array();
		$qty = 0;
		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name();
			$qty    += (int) $item->get_quantity();
		}

		return array(
			'id'            => $order->get_id(),
			'number'        => $order->get_order_number(),
			'status'        => wc_get_order_status_name( $order->get_status() ),
			'total'         => PriceFormat::from_html( $order->get_formatted_order_total() ),
			'date'          => $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '',
			'qty'           => $qty,
			'items_label'   => implode( '، ', array_slice( $names, 0, 3 ) ),
			'payment_url'   => $order->get_checkout_payment_url(),
			'site_url'      => $order->get_checkout_order_received_url(),
			'needs_payment' => $order->needs_payment(),
		);
	}
}
