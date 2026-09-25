<?php
/**
 * Queue manual channel publishes through WooCommerce Action Scheduler.
 *
 * @package StoreLink
 */

namespace StoreLink\Publishing;

use StoreLink\Admin\SettingsStore;
use StoreLink\Commerce\CatalogService;
use StoreLink\Core\Log;

defined( 'ABSPATH' ) || exit;

/**
 * One async action per published simple or variable product.
 */
class ChannelPublishQueue {

	public const HOOK  = 'storelink_channel_publish_product';
	public const GROUP = 'storelink';

	public function run( int $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || $product->is_type( 'variation' ) ) {
			return;
		}

		$this->sync( $product );
	}

	public function sync( \WC_Product $product ): bool {
		if ( 'publish' !== $product->get_status() ) {
			return false;
		}

		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( ! $parent instanceof \WC_Product ) {
				return false;
			}
			$product = $parent;
		}

		$payload = ( new CatalogService() )->to_array( $product );
		$stored  = $product->get_meta( MessengerChannelPublisher::META );
		$stored  = is_array( $stored ) ? $stored : array();
		$retry   = false;

		foreach ( SettingsStore::platforms() as $platform ) {
			if ( ! SettingsStore::is_enabled( $platform ) || '' === SettingsStore::channel_id( $platform ) ) {
				continue;
			}

			$publisher = new MessengerChannelPublisher( $platform );
			$existing  = (string) ( $stored[ $platform ] ?? '' );
			$result    = $publisher->sync_product( $existing, $payload );
			if ( in_array( $result['status'], array( 'updated', 'unchanged', 'published' ), true ) && '' !== $result['external_id'] ) {
				$stored[ $platform ] = $result['external_id'];
				continue;
			}
			if ( 'failed' === $result['status'] ) {
				$retry = true;
			}
		}

		update_post_meta( $product->get_id(), MessengerChannelPublisher::META, $stored );
		return $retry;
	}

	/**
	 * @return array<int, int>
	 */
	public function enqueue_from_request( int $category_id, string $raw_ids ): array {
		$ids = $this->parse_ids( $raw_ids );
		if ( $category_id > 0 ) {
			$ids = array_merge( $ids, $this->ids_in_category( $category_id ) );
		}

		$ids    = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		$queued = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product instanceof \WC_Product || $product->is_type( 'variation' ) || 'publish' !== $product->get_status() ) {
				continue;
			}
			if ( ! in_array( $product->get_type(), array( 'simple', 'variable' ), true ) ) {
				continue;
			}
			if ( $this->enqueue_one( $id ) ) {
				$queued[] = $id;
			}
		}

		return $queued;
	}

	private function enqueue_one( int $product_id ): bool {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			Log::warning( 'Action Scheduler is not available for channel publish.' );
			return false;
		}

		as_enqueue_async_action( self::HOOK, array( $product_id ), self::GROUP );
		return true;
	}

	/**
	 * @return array<int, int>
	 */
	private function parse_ids( string $raw ): array {
		$parts = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		return array_map( 'absint', $parts ?: array() );
	}

	/**
	 * @return array<int, int>
	 */
	private function ids_in_category( int $category_id ): array {
		$term = get_term( $category_id, 'product_cat' );
		if ( ! $term instanceof \WP_Term ) {
			return array();
		}

		$ids = wc_get_products(
			array(
				'status'   => 'publish',
				'type'     => array( 'simple', 'variable' ),
				'limit'    => -1,
				'return'   => 'ids',
				'category' => array( $term->slug ),
			)
		);

		return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	}
}
