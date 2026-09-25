<?php
/**
 * Registered shipment providers.
 *
 * @package StoreLink
 */

namespace StoreLink\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * Holds tracking carriers. Extra providers register on storelink_register_tracking_providers.
 */
class ProviderRegistry {

	private static ?ProviderRegistry $instance = null;

	/**
	 * @var array<string, ProviderInterface>
	 */
	private array $providers = array();

	public static function instance(): ProviderRegistry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register( ProviderInterface $provider ): void {
		$this->providers[ $provider->id() ] = $provider;
	}

	public function get( string $id ): ?ProviderInterface {
		return $this->providers[ sanitize_key( $id ) ] ?? null;
	}

	/**
	 * @return array<string, ProviderInterface>
	 */
	public function all(): array {
		return $this->providers;
	}
}
