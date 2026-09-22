<?php
/**
 * Bale messenger gateway.
 *
 * @package StoreLink
 */

namespace StoreLink\Bale;

use StoreLink\Admin\SettingsStore;
use StoreLink\Telegram\TelegramGateway;

defined( 'ABSPATH' ) || exit;

/**
 * Bale uses a Telegram-compatible Bot API with a different base URL.
 */
class BaleGateway extends TelegramGateway {

	public function id(): string {
		return 'bale';
	}

	public function label(): string {
		return __( 'Bale', 'storelink' );
	}

	protected function api_base(): string {
		return (string) apply_filters( 'storelink_bale_api_base', 'https://tapi.bale.ai' );
	}

	protected function supports_secret_token(): bool {
		return false;
	}

	public function verify_request( object $request ): bool {
		$secret = SettingsStore::secret( $this->id() );
		if ( '' === $secret ) {
			return false;
		}

		$provided = '';
		if ( method_exists( $request, 'get_query_params' ) ) {
			$params   = $request->get_query_params();
			$provided = (string) ( $params['storelink_secret'] ?? '' );
		}
		if ( '' === $provided && method_exists( $request, 'get_param' ) ) {
			$provided = (string) $request->get_param( 'storelink_secret' );
		}

		return '' !== $provided && hash_equals( $secret, $provided );
	}
}
