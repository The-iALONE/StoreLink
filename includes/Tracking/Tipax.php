<?php
/**
 * Tipax official tracking page (no scrape).
 *
 * @package StoreLink
 */

namespace StoreLink\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * Link to tipaxco.com tracking. No live API.
 */
class Tipax implements ProviderInterface {

	public function id(): string {
		return 'tipax';
	}

	public function label(): string {
		return __( 'Tipax', 'storelink' );
	}

	public function tracking_url( string $number ): string {
		$number = rawurlencode( $number );
		if ( '' === $number ) {
			return 'https://tipaxco.com/tracking';
		}

		return 'https://tipaxco.com/tracking?code=' . $number;
	}

	public function can_refresh(): bool {
		return false;
	}
}
