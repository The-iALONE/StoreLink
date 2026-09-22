<?php
/**
 * Channel publisher contract for later modules.
 *
 * @package StoreLink
 */

namespace StoreLink\Publishing;

defined( 'ABSPATH' ) || exit;

/**
 * Publish or update catalog posts on a channel.
 */
interface ChannelPublisherInterface {

	public function platform(): string;

	/**
	 * @param array<string, mixed> $product Product array from CatalogService.
	 * @return string External post id.
	 */
	public function publish_product( array $product ): string;

	/**
	 * @param array<string, mixed> $product Product array from CatalogService.
	 */
	public function update_post( string $external_id, array $product ): bool;
}
