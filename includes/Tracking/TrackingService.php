<?php
/**
 * Tracking numbers on WooCommerce orders.
 *
 * @package StoreLink
 */

namespace StoreLink\Tracking;

use StoreLink\Bot\OrderNotifier;
use StoreLink\Core\Log;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes shipment meta. Does not scrape carrier sites.
 */
class TrackingService {

	public const META_CARRIER = '_storelink_tracking_carrier';
	public const META_NUMBER  = '_storelink_tracking_number';
	public const META_URL     = '_storelink_tracking_url';
	public const META_STATUS  = '_storelink_tracking_status';
	public const META_HISTORY = '_storelink_tracking_history';

	public const HISTORY_MAX = 40;

	public const CRON_HOOK = 'storelink_refresh_tracking';

	/**
	 * @return array<string, mixed>
	 */
	public function get( \WC_Order $order ): array {
		$carrier = sanitize_key( (string) $order->get_meta( self::META_CARRIER ) );
		$number  = sanitize_text_field( (string) $order->get_meta( self::META_NUMBER ) );
		$provider = ProviderRegistry::instance()->get( $carrier );

		$url = (string) $order->get_meta( self::META_URL );
		if ( $provider && '' !== $number ) {
			$url = $provider->tracking_url( $number );
		}

		$history = $order->get_meta( self::META_HISTORY );
		$history = is_array( $history ) ? $history : array();

		return array(
			'carrier'      => $carrier,
			'carrier_label'=> $provider ? $provider->label() : '',
			'number'       => $number,
			'url'          => $url,
			'status'       => sanitize_text_field( (string) $order->get_meta( self::META_STATUS ) ),
			'history'      => $history,
			'has'          => '' !== $number,
		);
	}

	/**
	 * True when at least one line is not virtual. Downloadable-only products still ship.
	 */
	public function order_needs_shipment( \WC_Order $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( $product instanceof \WC_Product && $product->needs_shipping() ) {
				return true;
			}
		}

		return false;
	}

	public static function kind_label( bool $virtual, bool $downloadable ): string {
		if ( $virtual && $downloadable ) {
			return __( 'Virtual and downloadable', 'storelink' );
		}
		if ( $virtual ) {
			return __( 'Virtual', 'storelink' );
		}
		if ( $downloadable ) {
			return __( 'Physical, with a downloadable file', 'storelink' );
		}

		return __( 'Physical', 'storelink' );
	}

	public static function product_kind_label( ?\WC_Product $product ): string {
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}

		return self::kind_label( $product->is_virtual(), $product->is_downloadable() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function format_for_chat( \WC_Order $order ): array {
		if ( ! $this->order_needs_shipment( $order ) ) {
			return array(
				'has'             => false,
				'needs_shipment'  => false,
				'lines'           => array(),
				'url'             => '',
			);
		}

		$data                    = $this->get( $order );
		$data['needs_shipment'] = true;
		if ( ! $data['has'] ) {
			$data['lines'] = array();
			return $data;
		}

		$lines = array(
			sprintf( __( 'Carrier: %s', 'storelink' ), $data['carrier_label'] ?: $data['carrier'] ),
			sprintf( __( 'Tracking number: %s', 'storelink' ), $data['number'] ),
		);
		if ( '' !== $data['status'] ) {
			$lines[] = sprintf( __( 'Shipment: %s', 'storelink' ), $data['status'] );
		}
		if ( '' !== $data['url'] ) {
			$lines[] = $data['url'];
		}
		$data['lines'] = $lines;

		return $data;
	}

	/**
	 * @return \WP_Error|true
	 */
	public function save( int $order_id, string $carrier, string $number, bool $notify = true ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'not_found', __( 'Order not found.', 'storelink' ) );
		}

		if ( ! $this->order_needs_shipment( $order ) ) {
			return new \WP_Error( 'no_shipment', __( 'This order has no physical shipment.', 'storelink' ) );
		}

		$carrier = sanitize_key( $carrier );
		$number  = $this->sanitize_number( $number );
		if ( '' === $number ) {
			return new \WP_Error( 'empty', __( 'Enter a tracking number.', 'storelink' ) );
		}

		$provider = ProviderRegistry::instance()->get( $carrier );
		if ( ! $provider ) {
			return new \WP_Error( 'carrier', __( 'Unknown shipping provider.', 'storelink' ) );
		}

		$old_carrier = sanitize_key( (string) $order->get_meta( self::META_CARRIER ) );
		$old_number  = sanitize_text_field( (string) $order->get_meta( self::META_NUMBER ) );
		$changed     = $old_carrier !== $carrier || $old_number !== $number;

		$status = __( 'Recorded', 'storelink' );
		$url    = $provider->tracking_url( $number );

		$order->update_meta_data( self::META_CARRIER, $carrier );
		$order->update_meta_data( self::META_NUMBER, $number );
		$order->update_meta_data( self::META_URL, $url );
		$order->update_meta_data( self::META_STATUS, $status );

		if ( $changed ) {
			$history   = $order->get_meta( self::META_HISTORY );
			$history   = is_array( $history ) ? $history : array();
			$history[] = array(
				'at'     => current_time( 'mysql' ),
				'status' => $status,
				'note'   => $carrier . ' ' . $number,
			);
			if ( count( $history ) > self::HISTORY_MAX ) {
				$history = array_slice( $history, -1 * self::HISTORY_MAX );
			}
			$order->update_meta_data( self::META_HISTORY, $history );
		}

		$order->save();

		if ( $changed && $notify ) {
			( new OrderNotifier() )->notify_tracking( $order );
		}

		return true;
	}

	public function refresh_due(): void {
		$registry = ProviderRegistry::instance();
		$live     = array();
		foreach ( $registry->all() as $provider ) {
			if ( $provider->can_refresh() ) {
				$live[] = $provider->id();
			}
		}

		if ( ! $live ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 50,
				'status'     => array( 'processing', 'completed', 'on-hold' ),
				'meta_query' => array(
					array(
						'key'     => self::META_NUMBER,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}
			$carrier  = sanitize_key( (string) $order->get_meta( self::META_CARRIER ) );
			$provider = $registry->get( $carrier );
			if ( ! $provider || ! $provider->can_refresh() ) {
				continue;
			}

			Log::warning( 'tracking refresh skipped: no live API for ' . $carrier );
		}
	}

	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	public static function clear_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	private function sanitize_number( string $number ): string {
		$number = sanitize_text_field( $number );
		$number = preg_replace( '/\s+/', '', $number );
		return is_string( $number ) ? $number : '';
	}
}
