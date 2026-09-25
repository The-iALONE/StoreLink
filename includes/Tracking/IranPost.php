<?php
/**
 * Iran Post official tracking page (no scrape).
 *
 * @package StoreLink
 */

namespace StoreLink\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * Link to tracking.post.ir. No live API.
 */
class IranPost implements ProviderInterface {

	public function id(): string {
		return 'iran_post';
	}

	public function label(): string {
		return __( 'Iran Post', 'storelink' );
	}

	public function tracking_url( string $number ): string {
		$number = rawurlencode( $number );
		if ( '' === $number ) {
			return 'https://tracking.post.ir';
		}

		return 'https://tracking.post.ir/search.aspx?id=' . $number;
	}

	public function can_refresh(): bool {
		return false;
	}
}
