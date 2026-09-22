<?php
/**
 * Messenger gateway registry.
 *
 * @package StoreLink
 */

namespace StoreLink\Messengers;

defined( 'ABSPATH' ) || exit;

/**
 * Holds registered messenger adapters.
 */
class GatewayRegistry {

	private static ?GatewayRegistry $instance = null;

	/**
	 * @var array<string, GatewayInterface>
	 */
	private array $gateways = array();

	public static function instance(): GatewayRegistry {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register( GatewayInterface $gateway ): void {
		$this->gateways[ $gateway->id() ] = $gateway;
	}

	public function get( string $id ): ?GatewayInterface {
		return $this->gateways[ $id ] ?? null;
	}

	/**
	 * @return array<string, GatewayInterface>
	 */
	public function all(): array {
		return $this->gateways;
	}
}
