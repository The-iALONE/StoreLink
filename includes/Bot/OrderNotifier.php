<?php
/**
 * Notify Telegram admins about new WooCommerce orders.
 *
 * @package StoreLink
 */

namespace StoreLink\Bot;

use StoreLink\Admin\SettingsStore;
use StoreLink\Commerce\AdminOrderService;
use StoreLink\Messengers\GatewayRegistry;
use StoreLink\Messengers\OutgoingMessage;

defined( 'ABSPATH' ) || exit;

/**
 * Sends a summary to stored admin chats.
 */
class OrderNotifier {

	public function on_new_order( $order ): void {
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

		if ( ! SettingsStore::is_enabled( 'telegram' ) ) {
			return;
		}

		set_transient( 'storelink_tg_n_' . $order_id, 1, 10 * MINUTE_IN_SECONDS );

		$detail = ( new AdminOrderService() )->get( $order_id );
		if ( ! $detail ) {
			return;
		}

		$gateway = GatewayRegistry::instance()->get( 'telegram' );
		if ( ! $gateway ) {
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

		foreach ( SettingsStore::admin_chat_ids() as $chat_id ) {
			$gateway->send( new OutgoingMessage( $chat_id, $text, $buttons ) );
		}
	}
}
