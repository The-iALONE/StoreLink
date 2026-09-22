<?php
/**
 * Telegram messenger gateway.
 *
 * @package StoreLink
 */

namespace StoreLink\Telegram;

use StoreLink\Admin\SettingsStore;
use StoreLink\Messengers\GatewayInterface;
use StoreLink\Messengers\IncomingUpdate;
use StoreLink\Messengers\OutgoingMessage;

defined( 'ABSPATH' ) || exit;

/**
 * Telegram Bot API adapter.
 */
class TelegramGateway implements GatewayInterface {

	public function id(): string {
		return 'telegram';
	}

	public function label(): string {
		return __( 'Telegram', 'storelink' );
	}

	public function capabilities(): array {
		return array( 'catalog', 'orders', 'channel_publish', 'post_edit' );
	}

	public function verify_request( object $request ): bool {
		$secret = SettingsStore::secret( $this->id() );
		if ( '' === $secret ) {
			return false;
		}

		if ( method_exists( $request, 'get_header' ) ) {
			$header = (string) $request->get_header( 'x_telegram_bot_api_secret_token' );
			if ( '' === $header ) {
				$header = (string) $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );
			}
		} else {
			$header = '';
		}

		return hash_equals( $secret, $header );
	}

	public function parse_update( mixed $payload ): ?IncomingUpdate {
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$update_id = (int) ( $payload['update_id'] ?? 0 );
		$message   = $payload['message'] ?? $payload['callback_query']['message'] ?? null;
		if ( ! is_array( $message ) ) {
			return null;
		}

		$chat_id = (string) ( $message['chat']['id'] ?? '' );
		$from    = $payload['callback_query']['from'] ?? $message['from'] ?? array();
		$user_id = (string) ( $from['id'] ?? '' );
		$name    = trim( ( $from['first_name'] ?? '' ) . ' ' . ( $from['last_name'] ?? '' ) );
		$platform = $this->id();

		if ( isset( $payload['callback_query'] ) ) {
			$cq = $payload['callback_query'];
			return new IncomingUpdate(
				$platform,
				$update_id,
				$chat_id,
				$user_id,
				'callback',
				'',
				sanitize_text_field( (string) ( $cq['data'] ?? '' ) ),
				sanitize_text_field( $name ),
				'',
				(string) ( $cq['id'] ?? '' )
			);
		}

		$text  = sanitize_text_field( (string) ( $message['text'] ?? '' ) );
		$phone = sanitize_text_field( (string) ( $message['contact']['phone_number'] ?? '' ) );

		return new IncomingUpdate(
			$platform,
			$update_id,
			$chat_id,
			$user_id,
			'message',
			$text,
			'',
			sanitize_text_field( $name ),
			$phone
		);
	}

	public function send( OutgoingMessage $message ): bool {
		$client = $this->client();
		if ( ! $client ) {
			return false;
		}

		if ( $message->callback_query_id ) {
			$client->call(
				'answerCallbackQuery',
				array( 'callback_query_id' => $message->callback_query_id )
			);
		}

		$markup = $this->keyboard( $message );
		$params = array(
			'chat_id'    => $message->chat_id,
			'text'       => $message->text,
			'parse_mode' => 'HTML',
		);
		if ( $markup ) {
			$params['reply_markup'] = $markup;
		}

		if ( $message->photo_url ) {
			$photo = array(
				'chat_id'    => $message->chat_id,
				'photo'      => $message->photo_url,
				'caption'    => $this->escape( $message->text ),
				'parse_mode' => 'HTML',
			);
			if ( $markup ) {
				$photo['reply_markup'] = $markup;
			}
			$result = $client->call( 'sendPhoto', $photo );
			return ! empty( $result['ok'] );
		}

		$params['text'] = $this->escape( $message->text );
		$result         = $client->call( 'sendMessage', $params );
		return ! empty( $result['ok'] );
	}

	protected function supports_secret_token(): bool {
		return true;
	}

	public function set_webhook( string $url, string $secret ): bool {
		$prefix = $this->id();
		$client = $this->client();
		if ( ! $client ) {
			$this->store_webhook_error( __( 'Save the bot token first, then connect the webhook.', 'storelink' ) );
			return false;
		}

		if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
			$this->store_webhook_error(
				__( 'Telegram and Bale only accept a public HTTPS webhook. Local http:// URLs are rejected. Use a tunnel such as ngrok and set the public webhook base URL.', 'storelink' )
			);
			return false;
		}

		$me = $client->call( 'getMe' );
		if ( empty( $me['ok'] ) ) {
			$this->store_webhook_error( $this->api_error_text( $me ) );
			return false;
		}

		$hook_url = $url;
		$params   = array( 'url' => $hook_url );
		if ( $this->supports_secret_token() ) {
			$params['secret_token'] = $secret;
		} else {
			$hook_url      = add_query_arg( 'storelink_secret', $secret, $url );
			$params['url'] = $hook_url;
		}

		$result = $client->call( 'setWebhook', $params );
		if ( empty( $result['ok'] ) ) {
			$this->store_webhook_error( $this->api_error_text( $result ) );
			return false;
		}

		$username = is_array( $me['result'] ?? null ) ? (string) ( $me['result']['username'] ?? '' ) : '';

		SettingsStore::update(
			array(
				$prefix . '_secret'         => $secret,
				$prefix . '_username'       => $username,
				$prefix . '_webhook_ok'     => true,
				$prefix . '_webhook_error'  => '',
			)
		);

		return true;
	}

	/**
	 * @param array<string, mixed> $result API payload.
	 */
	protected function api_error_text( array $result ): string {
		$text = trim( (string) ( $result['description'] ?? '' ) );
		if ( '' === $text ) {
			$text = __( 'The messenger API rejected the request.', 'storelink' );
		}

		return $text;
	}

	protected function store_webhook_error( string $message ): void {
		SettingsStore::update(
			array(
				$this->id() . '_webhook_ok'    => false,
				$this->id() . '_webhook_error' => $message,
			)
		);
	}

	protected function api_base(): string {
		return (string) apply_filters( 'storelink_telegram_api_base', 'https://api.telegram.org' );
	}

	protected function client(): ?TelegramClient {
		$token = SettingsStore::token( $this->id() );
		if ( '' === $token ) {
			return null;
		}

		return new TelegramClient( $token, $this->api_base() );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function keyboard( OutgoingMessage $message ): ?array {
		if ( 'contact' === $message->keyboard ) {
			$label = $message->buttons[0][0]['text'] ?? __( 'Share phone number', 'storelink' );
			return array(
				'keyboard' => array(
					array(
						array(
							'text'            => $label,
							'request_contact' => true,
						),
					),
				),
				'resize_keyboard'   => true,
				'one_time_keyboard' => true,
			);
		}

		if ( 'reply' === $message->keyboard ) {
			$rows = array();
			foreach ( $message->buttons as $row ) {
				$line = array();
				foreach ( $row as $button ) {
					$line[] = array( 'text' => $button['text'] );
				}
				$rows[] = $line;
			}

			return array(
				'keyboard'          => $rows,
				'resize_keyboard'   => true,
				'one_time_keyboard' => false,
			);
		}

		if ( empty( $message->buttons ) ) {
			return null;
		}

		$rows = array();
		foreach ( $message->buttons as $row ) {
			$line = array();
			foreach ( $row as $button ) {
				$item = array( 'text' => $button['text'] );
				if ( ! empty( $button['url'] ) ) {
					$item['url'] = $button['url'];
				} else {
					$item['callback_data'] = substr( (string) ( $button['data'] ?? '' ), 0, 64 );
				}
				$line[] = $item;
			}
			$rows[] = $line;
		}

		return array( 'inline_keyboard' => $rows );
	}

	private function escape( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
