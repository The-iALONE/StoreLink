<?php
/**
 * Shipment carrier contract.
 *
 * @package StoreLink
 */

namespace StoreLink\Tracking;

defined( 'ABSPATH' ) || exit;

/**
 * A tracking provider maps a number to a public URL. Live status APIs are optional.
 */
interface ProviderInterface {

	public function id(): string;

	public function label(): string;

	public function tracking_url( string $number ): string;

	public function can_refresh(): bool;
}
