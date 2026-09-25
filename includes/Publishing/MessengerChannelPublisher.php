<?php
/**
 * Plain product posts on Telegram or Bale channels.
 *
 * @package StoreLink
 */

namespace StoreLink\Publishing;

use StoreLink\Admin\SettingsStore;
use StoreLink\Messengers\GatewayRegistry;
use StoreLink\Telegram\TelegramGateway;

defined( 'ABSPATH' ) || exit;

/**
 * Sends name, price, stock, and permalink. No AI captions.
 */
class MessengerChannelPublisher implements ChannelPublisherInterface {

	public const META = '_storelink_channel_posts';

	public function __construct(
		private string $platform = 'telegram'
	) {
		$this->platform = sanitize_key( $this->platform );
	}

	public function platform(): string {
		return $this->platform;
	}

	/**
	 * @param array<string, mixed> $product Product array from CatalogService.
	 */
	public function publish_product( array $product ): string {
		$caption = $this->caption( $product );
		if ( '' === $caption ) {
			return '';
		}

		$gateway = $this->gateway();
		$chat    = SettingsStore::channel_id( $this->platform );
		if ( ! $gateway || '' === $chat ) {
			return '';
		}

		$sent = $gateway->send_channel_post(
			$chat,
			$caption,
			(string) ( $product['image'] ?? '' )
		);
		if ( '' === $sent['id'] ) {
			return '';
		}

		return $sent['kind'] . ':' . $sent['id'];
	}

	/**
	 * @param array<string, mixed> $product Product array from CatalogService.
	 */
	public function update_post( string $external_id, array $product ): bool {
		$caption = $this->caption( $product );
		if ( '' === $caption ) {
			return false;
		}

		$gateway = $this->gateway();
		$chat    = SettingsStore::channel_id( $this->platform );
		if ( ! $gateway || '' === $chat || '' === $external_id ) {
			return false;
		}

		$parts   = explode( ':', $external_id, 2 );
		$kind    = $parts[0] ?? 'text';
		$id      = $parts[1] ?? $parts[0];
		$outcome = $gateway->edit_channel_outcome( $chat, $id, $kind, $caption );

		return 'updated' === $outcome || 'unchanged' === $outcome;
	}

	/**
	 * Edit the existing channel post. Publish a new one only when that post is gone.
	 *
	 * @param array<string, mixed> $product Product array from CatalogService.
	 * @return array{status:string, external_id:string}
	 */
	public function sync_product( string $external_id, array $product ): array {
		$caption = $this->caption( $product );
		if ( '' === $caption ) {
			return array(
				'status'      => 'skipped',
				'external_id' => $external_id,
			);
		}

		$gateway = $this->gateway();
		$chat    = SettingsStore::channel_id( $this->platform );
		if ( ! $gateway || '' === $chat ) {
			return array(
				'status'      => 'skipped',
				'external_id' => $external_id,
			);
		}

		if ( '' !== $external_id ) {
			$parts   = explode( ':', $external_id, 2 );
			$kind    = $parts[0] ?? 'text';
			$id      = $parts[1] ?? $parts[0];
			$outcome = $gateway->edit_channel_outcome( $chat, $id, $kind, $caption );
			if ( 'updated' === $outcome || 'unchanged' === $outcome ) {
				return array(
					'status'      => $outcome,
					'external_id' => $external_id,
				);
			}
			if ( 'failed' === $outcome ) {
				return array(
					'status'      => 'failed',
					'external_id' => $external_id,
				);
			}
		}

		$sent = $gateway->send_channel_post( $chat, $caption, (string) ( $product['image'] ?? '' ) );
		if ( '' === $sent['id'] ) {
			return array(
				'status'      => 'failed',
				'external_id' => $external_id,
			);
		}

		return array(
			'status'      => 'published',
			'external_id' => $sent['kind'] . ':' . $sent['id'],
		);
	}

	/**
	 * @param array<string, mixed> $product Product array.
	 */
	private function caption( array $product ): string {
		$fields = SettingsStore::channel_caption_fields();
		$lines  = array();

		if ( ! empty( $fields['name'] ) ) {
			$lines[] = (string) ( $product['name'] ?? '' );
		}
		if ( ! empty( $fields['price'] ) ) {
			$lines[] = (string) ( $product['price_html'] ?? '' );
		}
		if ( ! empty( $fields['stock'] ) ) {
			$lines[] = ! empty( $product['in_stock'] ) ? __( 'In stock', 'storelink' ) : __( 'Out of stock', 'storelink' );
		}
		if ( ! empty( $fields['short_desc'] ) ) {
			$lines[] = trim( (string) ( $product['short_desc'] ?? '' ) );
		}
		if ( ! empty( $fields['link'] ) ) {
			$lines[] = (string) ( $product['permalink'] ?? '' );
		}

		$text = implode( "\n", array_filter( $lines ) );
		if ( function_exists( 'mb_substr' ) ) {
			return (string) mb_substr( $text, 0, 1024 );
		}

		return substr( $text, 0, 1024 );
	}

	private function gateway(): ?TelegramGateway {
		$gateway = GatewayRegistry::instance()->get( $this->platform );
		return $gateway instanceof TelegramGateway ? $gateway : null;
	}
}
