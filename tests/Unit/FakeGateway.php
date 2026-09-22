<?php
/**
 * Fake messenger gateway for tests.
 *
 * @package StoreLink
 */

namespace StoreLink\Tests\Unit;

use StoreLink\Messengers\GatewayInterface;
use StoreLink\Messengers\IncomingUpdate;
use StoreLink\Messengers\OutgoingMessage;

/**
 * In-memory gateway.
 */
class FakeGateway implements GatewayInterface {

	/** @var array<int, OutgoingMessage> */
	public array $sent = array();

	public function id(): string {
		return 'fake';
	}

	public function label(): string {
		return 'Fake';
	}

	public function capabilities(): array {
		return array( 'catalog', 'orders' );
	}

	public function verify_request( object $request ): bool {
		return true;
	}

	public function parse_update( mixed $payload ): ?IncomingUpdate {
		if ( ! is_array( $payload ) ) {
			return null;
		}

		return new IncomingUpdate(
			'fake',
			(int) ( $payload['update_id'] ?? 1 ),
			(string) ( $payload['chat_id'] ?? '1' ),
			(string) ( $payload['user_id'] ?? '1' ),
			'message',
			(string) ( $payload['text'] ?? '' )
		);
	}

	public function send( OutgoingMessage $message ): bool {
		$this->sent[] = $message;
		return true;
	}

	public function set_webhook( string $url, string $secret ): bool {
		return true;
	}
}
