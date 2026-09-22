<?php
/**
 * Messenger gateway contract.
 *
 * @package StoreLink
 */

namespace StoreLink\Messengers;

defined( 'ABSPATH' ) || exit;

/**
 * Translates a messenger into the shared bot protocol.
 */
interface GatewayInterface {

	public function id(): string;

	public function label(): string;

	/**
	 * @return array<int, string>
	 */
	public function capabilities(): array;

	public function verify_request( object $request ): bool;

	public function parse_update( mixed $payload ): ?IncomingUpdate;

	public function send( OutgoingMessage $message ): bool;

	public function set_webhook( string $url, string $secret ): bool;
}
