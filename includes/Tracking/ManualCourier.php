<?php
/**
 * Local / custom courier with no public tracker.
 *
 * @package StoreLink
 */

namespace StoreLink\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * Stores a tracking number only.
 */
class ManualCourier implements ProviderInterface {

	public function id(): string {
		return 'manual';
	}

	public function label(): string {
		return __( 'Local courier', 'storelink' );
	}

	public function tracking_url( string $number ): string {
		unset( $number );
		return '';
	}

	public function can_refresh(): bool {
		return false;
	}
}
