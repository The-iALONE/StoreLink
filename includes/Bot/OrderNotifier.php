<?php
/**
 * Notify messenger admins about new WooCommerce orders and customers about status changes.
 *
 * @package StoreLink
 */

namespace StoreLink\Bot;

use StoreLink\Admin\SettingsStore;
use StoreLink\Commerce\AdminOrderService;
use StoreLink\Commerce\OrderService;
use StoreLink\Messengers\GatewayRegistry;
use StoreLink\Messengers\OutgoingMessage;
use StoreLink\Tracking\TrackingService;

defined( 'ABSPATH' ) || exit;

/**
 * Sends a summary to stored admin chats and status updates to the buyer chat.
 */
class OrderNotifier {

	public function on_new_order( $order ): void {
		if ( ! SettingsStore::notify_admin_new_order() ) {
			return;
		}

		$order_id = $order instanceof \WC_Order ? $order->get_id() : (int) $order;
		if ( $order_id < 1 ) {
			return;
		}

		if ( get_transient( 'storelink_tg_n_' . $order_id ) ) {
			return;
		}

		$wc_order = wc_get_order( $order_id );
		if ( ! $wc_order instanceof \WC_Order || $wc_order->get_item_count() < 1 ) {
			return;
		}

		set_transient( 'storelink_tg_n_' . $order_id, 1, 10 * MINUTE_IN_SECONDS );

		$detail = ( new AdminOrderService() )->get( $order_id );
		if ( ! $detail ) {
			return;
		}

		$text = sprintf(
			"%s\n#%s\n%s\n%s\n%s",
			__( 'New order', 'storelink' ),
			$detail['number'],
			$detail['name'],
			$detail['total'],
			$detail['status']
		);

		$buttons = array(
			array(
				array(
					'text' => __( 'Order details', 'storelink' ),
					'data' => 'ao:' . $detail['id'],
				),
			),
		);

		$this->send_to_admins( $text, $buttons );
	}

	/**
	 * @param int               $order_id   Order id.
	 * @param string            $old_status Previous WC status slug.
	 * @param string            $new_status New WC status slug.
	 * @param \WC_Order|mixed   $order      Order object when provided.
	 */
	public function on_status_changed( $order_id, $old_status, $new_status, $order = null ): void {
		$order_id   = (int) $order_id;
		$old_status = sanitize_key( (string) $old_status );
		$new_status = sanitize_key( (string) $new_status );

		if ( $order_id < 1 || '' === $new_status || $old_status === $new_status ) {
			return;
		}

		if ( '' === $old_status || 'new' === $old_status ) {
			return;
		}

		$wc_order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $wc_order instanceof \WC_Order ) {
			return;
		}

		if ( 'completed' === $new_status && 'pending' === $old_status && OrderService::is_digital_only( $wc_order ) ) {
			$wc_order->update_status( 'processing', 'StoreLink virtual order' );
			return;
		}

		if ( SettingsStore::notify_admin_status() ) {
			$this->notify_admin_status( $wc_order );
		}

		$this->notify_customer( $wc_order, $old_status, $new_status );
	}

	public function notify_customer( \WC_Order $wc_order, string $old_status = '', string $new_status = '' ): void {
		$order_id = $wc_order->get_id();
		$chat_id  = (string) $wc_order->get_meta( '_storelink_chat_id' );
		$platform = sanitize_key( (string) $wc_order->get_meta( '_storelink_platform' ) );
		if ( '' === $chat_id || '' === $platform ) {
			return;
		}

		$status = '' !== $new_status ? sanitize_key( $new_status ) : $wc_order->get_status();
		$key    = 'storelink_cs_' . $order_id . '_' . $status;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 20 );

		$gateway = GatewayRegistry::instance()->get( $platform );
		if ( ! $gateway ) {
			return;
		}

		$use_refunded = 'refunded' === $status && SettingsStore::notify_customer_refunded();
		$use_shipped  = 'completed' === $status
			&& '' !== $old_status
			&& 'completed' !== $old_status
			&& SettingsStore::notify_customer_shipped()
			&& ( new TrackingService() )->order_needs_shipment( $wc_order );

		$downloads = OrderService::download_items( $wc_order );
		$send_body = $use_refunded || $use_shipped || SettingsStore::notify_customer_status();
		if ( ! $send_body && ! $downloads ) {
			return;
		}

		$switched = false;
		if ( function_exists( 'switch_to_locale' ) ) {
			$switched = switch_to_locale( get_locale() );
		}

		if ( $use_refunded ) {
			$text = sprintf(
				__( 'Order #%s was refunded.', 'storelink' ),
				$wc_order->get_order_number()
			);
		} elseif ( $use_shipped ) {
			$text = sprintf(
				__( 'Order #%s was shipped.', 'storelink' ),
				$wc_order->get_order_number()
			);
		} elseif ( $send_body ) {
			$text = sprintf(
				__( 'Order #%s is now: %s', 'storelink' ),
				$wc_order->get_order_number(),
				wc_get_order_status_name( $status )
			);
		} else {
			$text = sprintf(
				__( 'Order #%s', 'storelink' ),
				$wc_order->get_order_number()
			);
		}

		$tracking = ( new TrackingService() )->format_for_chat( $wc_order );
		if ( $send_body && ! empty( $tracking['has'] ) && ! empty( $tracking['lines'] ) ) {
			$text .= "\n" . implode( "\n", $tracking['lines'] );
		}

		$buttons = array();
		if ( $wc_order->needs_payment() ) {
			$pay = (string) $wc_order->get_checkout_payment_url();
			if ( 'https' === strtolower( (string) wp_parse_url( $pay, PHP_URL_SCHEME ) ) ) {
				$host = strtolower( (string) wp_parse_url( $pay, PHP_URL_HOST ) );
				if ( ! in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
					$buttons[] = array(
						array(
							'text' => __( 'Pay now', 'storelink' ),
							'url'  => $pay,
						),
					);
				}
			}
		}

		if ( $downloads ) {
			$text .= "\n" . __( 'Your downloads:', 'storelink' );
			foreach ( $downloads as $file ) {
				if ( '' === ( $file['url'] ?? '' ) ) {
					continue;
				}
				$buttons[] = array(
					array(
						'text' => sprintf( __( 'Download: %s', 'storelink' ), $file['name'] ),
						'url'  => $file['url'],
					),
				);
			}
		}

		$track_url = (string) ( $tracking['url'] ?? '' );
		if ( $send_body && '' !== $track_url && 'https' === strtolower( (string) wp_parse_url( $track_url, PHP_URL_SCHEME ) ) ) {
			$buttons[] = array(
				array(
					'text' => __( 'Track shipment', 'storelink' ),
					'url'  => $track_url,
				),
			);
		}

		$gateway->send( new OutgoingMessage( $chat_id, $text, $buttons ) );

		if ( $downloads && method_exists( $gateway, 'send_document' ) ) {
			foreach ( $downloads as $file ) {
				$path = (string) ( $file['path'] ?? '' );
				if ( '' === $path ) {
					continue;
				}
				$gateway->send_document( $chat_id, $path, (string) $file['name'], (string) $file['name'] );
			}
		}

		if ( $switched && function_exists( 'restore_previous_locale' ) ) {
			restore_previous_locale();
		}
	}

	public function notify_tracking( \WC_Order $wc_order ): void {
		if ( ! SettingsStore::notify_customer_tracking() ) {
			return;
		}

		$platform = sanitize_key( (string) $wc_order->get_meta( '_storelink_platform' ) );
		if ( '' === $platform || ! SettingsStore::is_enabled( $platform ) ) {
			return;
		}

		$chat_id = (string) $wc_order->get_meta( '_storelink_chat_id' );
		if ( '' === $chat_id ) {
			return;
		}

		$formatted = ( new TrackingService() )->format_for_chat( $wc_order );
		if ( empty( $formatted['has'] ) ) {
			return;
		}

		$key = 'storelink_tr_' . $wc_order->get_id() . '_' . md5( (string) $formatted['carrier'] . (string) $formatted['number'] );
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 20 );

		$gateway = GatewayRegistry::instance()->get( $platform );
		if ( ! $gateway ) {
			return;
		}

		$text = sprintf(
			__( 'Order #%s tracking update', 'storelink' ),
			$wc_order->get_order_number()
		);
		$text .= "\n" . implode( "\n", $formatted['lines'] ?? array() );

		$buttons = array();
		$url     = (string) ( $formatted['url'] ?? '' );
		if ( '' !== $url && 'https' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
			$buttons[] = array(
				array(
					'text' => __( 'Track shipment', 'storelink' ),
					'url'  => $url,
				),
			);
		}

		$gateway->send( new OutgoingMessage( $chat_id, $text, $buttons ) );
	}

	private function notify_admin_status( \WC_Order $wc_order ): void {
		$detail = ( new AdminOrderService() )->get( $wc_order->get_id() );
		if ( ! $detail ) {
			return;
		}

		$text = sprintf(
			"%s\n#%s\n%s\n%s\n%s",
			__( 'Order status changed', 'storelink' ),
			$detail['number'],
			$detail['name'],
			$detail['total'],
			$detail['status']
		);

		$buttons = array(
			array(
				array(
					'text' => __( 'Order details', 'storelink' ),
					'data' => 'ao:' . $detail['id'],
				),
			),
		);

		$this->send_to_admins( $text, $buttons );
	}

	/**
	 * @param array<int, array<int, array<string, string>>> $buttons
	 */
	private function send_to_admins( string $text, array $buttons ): void {
		foreach ( SettingsStore::platforms() as $platform ) {
			if ( ! SettingsStore::is_enabled( $platform ) ) {
				continue;
			}

			$gateway = GatewayRegistry::instance()->get( $platform );
			if ( ! $gateway ) {
				continue;
			}

			foreach ( SettingsStore::admin_chat_ids( $platform ) as $chat_id ) {
				$gateway->send( new OutgoingMessage( $chat_id, $text, $buttons ) );
			}
		}
	}
}
