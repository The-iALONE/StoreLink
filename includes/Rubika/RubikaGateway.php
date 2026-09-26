<?php
/**
 * Rubika messenger gateway.
 *
 * @package StoreLink
 */

namespace StoreLink\Rubika;

use StoreLink\Admin\SettingsStore;
use StoreLink\Core\Log;
use StoreLink\Messengers\GatewayInterface;
use StoreLink\Messengers\IncomingUpdate;
use StoreLink\Messengers\OutgoingMessage;

defined( 'ABSPATH' ) || exit;

/**
 * Rubika Bot API adapter. Same shop contract as Telegram.
 */
class RubikaGateway implements GatewayInterface {

	public function id(): string {
		return 'rubika';
	}

	public function label(): string {
		return __( 'Rubika', 'storelink' );
	}

	public function capabilities(): array {
		return array( 'catalog', 'orders', 'channel_publish', 'post_edit' );
	}

	public function verify_request( object $request ): bool {
		$secret = SettingsStore::secret( $this->id() );
		if ( '' === $secret ) {
			return false;
		}

		$provided = '';
		if ( method_exists( $request, 'get_param' ) ) {
			$provided = (string) $request->get_param( 'secret' );
		}

		return '' !== $provided && hash_equals( $secret, $provided );
	}

	public function parse_update( mixed $payload ): ?IncomingUpdate {
		if ( ! is_array( $payload ) ) {
			return null;
		}

		if ( isset( $payload['data'] ) && is_array( $payload['data'] ) && ( isset( $payload['data']['update'] ) || isset( $payload['data']['inline_message'] ) ) ) {
			$payload = $payload['data'];
		}

		if ( isset( $payload['inline_message'] ) && is_array( $payload['inline_message'] ) ) {
			return $this->from_inline( $payload['inline_message'] );
		}

		$update = $payload['update'] ?? $payload;
		if ( ! is_array( $update ) ) {
			return null;
		}

		if ( isset( $update['inline_message'] ) && is_array( $update['inline_message'] ) ) {
			return $this->from_inline( $update['inline_message'] );
		}

		return $this->from_message( $update );
	}

	public function send( OutgoingMessage $message ): bool {
		$client = $this->client();
		if ( ! $client ) {
			return false;
		}

		$params = array(
			'chat_id' => $message->chat_id,
			'text'    => $this->plain_text( $message, true ),
		);

		$markup = $this->keypad( $message );
		if ( $markup ) {
			if ( 'contact' === $message->keyboard || 'reply' === $message->keyboard ) {
				$params['chat_keypad']      = $markup;
				$params['chat_keypad_type'] = 'New';
			} else {
				$params['inline_keypad'] = $markup;
			}
		}

		$result = $client->call( 'sendMessage', $params );
		if ( empty( $result['ok'] ) && ( ! empty( $params['inline_keypad'] ) || ! empty( $params['chat_keypad'] ) ) ) {
			unset( $params['inline_keypad'], $params['chat_keypad'], $params['chat_keypad_type'] );
			$again = $this->keypad_without_urls( $message );
			if ( $again && 'reply' !== $message->keyboard && 'contact' !== $message->keyboard ) {
				$params['inline_keypad'] = $again;
				$params['text']          = $this->plain_text( $message, true );
			} else {
				$params['text'] = $this->plain_text( $message, true );
			}
			$result = $client->call( 'sendMessage', $params );
		}

		$this->note_failure( 'sendMessage', $result );
		return ! empty( $result['ok'] );
	}

	public function send_document( string $chat_id, string $path, string $filename = '', string $caption = '' ): bool {
		$client = $this->client();
		if ( ! $client || '' === $chat_id ) {
			return false;
		}

		$result = $client->upload_file( $chat_id, $path, $filename );
		$this->note_failure( 'sendFile', $result );
		if ( ! empty( $result['ok'] ) && '' !== $caption ) {
			$client->call(
				'sendMessage',
				array(
					'chat_id' => $chat_id,
					'text'    => $caption,
				)
			);
		}

		return ! empty( $result['ok'] );
	}

	/**
	 * @return array{id:string, kind:string}
	 */
	public function send_channel_post( string $chat_id, string $text, string $photo_url = '' ): array {
		$client = $this->client();
		if ( ! $client || '' === $chat_id ) {
			Log::warning( $this->id() . ' channel post: bot client unavailable' );
			return array( 'id' => '', 'kind' => '' );
		}

		$result = $client->call(
			'sendMessage',
			array(
				'chat_id' => $chat_id,
				'text'    => $text,
			)
		);
		$id = $this->message_id_from( $result );
		if ( '' === $id ) {
			$this->note_failure( 'sendMessage', $result );
			return array( 'id' => '', 'kind' => '' );
		}

		return array(
			'id'   => $id,
			'kind' => 'text',
		);
	}

	/**
	 * @return string updated|unchanged|missing|failed
	 */
	public function edit_channel_outcome( string $chat_id, string $message_id, string $kind, string $text ): string {
		$client = $this->client();
		if ( ! $client || '' === $chat_id || '' === $message_id ) {
			Log::warning( $this->id() . ' channel edit: bot client unavailable' );
			return 'failed';
		}

		$result = $client->call(
			'editMessageText',
			array(
				'chat_id'    => $chat_id,
				'message_id' => $message_id,
				'text'       => $text,
			)
		);

		if ( ! empty( $result['ok'] ) ) {
			return 'updated';
		}

		$description = strtolower( (string) ( $result['description'] ?? '' ) );
		if ( str_contains( $description, 'not modified' ) || str_contains( $description, 'unchanged' ) ) {
			return 'unchanged';
		}
		if ( str_contains( $description, 'not found' ) || str_contains( $description, 'invalid' ) ) {
			return 'missing';
		}

		$this->note_failure( 'editMessageText', $result );
		return 'failed';
	}

	public function set_webhook( string $url, string $secret ): bool {
		$client = $this->client();
		if ( ! $client ) {
			$this->store_webhook_error( __( 'Save the bot token first, then connect the webhook.', 'storelink' ) );
			return false;
		}

		if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
			$this->store_webhook_error(
				__( 'Rubika only accepts a public HTTPS webhook. Local http:// URLs are rejected. Use a tunnel such as ngrok and set the public webhook base URL.', 'storelink' )
			);
			return false;
		}

		if ( str_contains( $url, '?' ) ) {
			$this->store_webhook_error(
				__( 'Rubika rejects webhook URLs with a query string. Set Public webhook base URL to HTTPS and use pretty permalinks so the address uses /wp-json/.', 'storelink' )
			);
			return false;
		}

		$me = $client->call( 'getMe' );
		if ( empty( $me['ok'] ) ) {
			$this->store_webhook_error( $this->api_error_text( $me ) );
			return false;
		}

		$endpoint = untrailingslashit( $url ) . '/' . $secret;
		$types    = array( 'ReceiveUpdate', 'ReceiveInlineMessage' );
		foreach ( $types as $type ) {
			$result = $client->call(
				'updateBotEndpoints',
				array(
					'url'  => $endpoint,
					'type' => $type,
				)
			);
			if ( empty( $result['ok'] ) ) {
				$this->store_webhook_error( $this->api_error_text( $result ) );
				return false;
			}
		}

		$username = $this->bot_username( $me );

		SettingsStore::update(
			array(
				'rubika_secret'        => $secret,
				'rubika_username'      => $username,
				'rubika_webhook_ok'    => true,
				'rubika_webhook_error' => '',
			)
		);

		return true;
	}

	/**
	 * @return array{ok:bool, text:string, username:string}
	 */
	public function ping(): array {
		$client = $this->client();
		if ( ! $client ) {
			return array(
				'ok'       => false,
				'text'     => __( 'Save the bot token first, then test the connection.', 'storelink' ),
				'username' => '',
			);
		}

		$me = $client->call( 'getMe' );
		if ( empty( $me['ok'] ) ) {
			$this->note_failure( 'getMe', $me );
			return array(
				'ok'       => false,
				'text'     => $this->api_error_text( $me ),
				'username' => '',
			);
		}

		$username = $this->bot_username( $me );

		return array(
			'ok'       => true,
			'text'     => $username
				? '@' . $username
				: sprintf(
					/* translators: %s: messenger name */
					__( '%s API is reachable.', 'storelink' ),
					$this->label()
				),
			'username' => $username,
		);
	}

	/**
	 * @param array<string, mixed> $inline InlineMessage body.
	 */
	private function from_inline( array $inline ): ?IncomingUpdate {
		$chat_id = (string) ( $inline['chat_id'] ?? '' );
		$user_id = (string) ( $inline['sender_id'] ?? '' );
		$aux     = is_array( $inline['aux_data'] ?? null ) ? $inline['aux_data'] : array();
		$data    = sanitize_text_field( (string) ( $aux['button_id'] ?? '' ) );
		$mid     = (string) ( $inline['message_id'] ?? '' );

		if ( '' === $chat_id || '' === $user_id ) {
			return null;
		}

		return new IncomingUpdate(
			$this->id(),
			$this->numeric_id( $mid ),
			$chat_id,
			$user_id,
			'callback',
			'',
			$data,
			sanitize_text_field( (string) ( $inline['text'] ?? '' ) ),
			'',
			$mid
		);
	}

	/**
	 * @param array<string, mixed> $update Update body.
	 */
	private function from_message( array $update ): ?IncomingUpdate {
		$type    = (string) ( $update['type'] ?? 'NewMessage' );
		$chat_id = (string) ( $update['chat_id'] ?? '' );
		$message = $update['new_message'] ?? $update['updated_message'] ?? $update['message'] ?? array();
		if ( ! is_array( $message ) ) {
			$message = array();
		}

		$user_id = (string) ( $message['sender_id'] ?? $update['sender_id'] ?? '' );
		if ( '' === $user_id ) {
			$user_id = $chat_id;
		}
		$mid     = (string) ( $message['message_id'] ?? $update['message_id'] ?? '' );
		$text    = sanitize_text_field( (string) ( $message['text'] ?? '' ) );
		$phone   = sanitize_text_field(
			(string) (
				$message['contact_message']['phone_number']
				?? $message['contact']['phone_number']
				?? ''
			)
		);
		$aux     = is_array( $message['aux_data'] ?? null ) ? $message['aux_data'] : array();
		$button  = sanitize_text_field( (string) ( $aux['button_id'] ?? '' ) );

		if ( in_array( $type, array( 'StartedBot', 'started_bot' ), true ) || str_starts_with( $text, '/start' ) ) {
			$text = '/start';
		}

		if ( '' === $chat_id ) {
			return null;
		}

		if ( '' !== $button ) {
			return new IncomingUpdate(
				$this->id(),
				$this->numeric_id( $mid ),
				$chat_id,
				$user_id,
				'callback',
				'',
				$button,
				'',
				$phone,
				$mid
			);
		}

		return new IncomingUpdate(
			$this->id(),
			$this->numeric_id( $mid ),
			$chat_id,
			$user_id,
			'message',
			$text,
			'',
			sanitize_text_field( (string) ( $message['sender_name'] ?? '' ) ),
			$phone
		);
	}

	private function numeric_id( string $message_id ): int {
		if ( '' === $message_id ) {
			return 0;
		}
		if ( ctype_digit( $message_id ) ) {
			$id = (int) $message_id;
			return $id > 0 ? $id : 0;
		}

		$id = (int) sprintf( '%u', crc32( $message_id ) );
		return $id > 0 ? $id : 0;
	}

	private function plain_text( OutgoingMessage $message, bool $append_urls = false ): string {
		$text = wp_strip_all_tags( $message->text );
		if ( ! $append_urls ) {
			return $text;
		}

		foreach ( $message->buttons as $row ) {
			foreach ( $row as $button ) {
				if ( empty( $button['url'] ) ) {
					continue;
				}
				$text .= "\n" . $button['text'] . ': ' . $button['url'];
			}
		}

		return $text;
	}

	/**
	 * @return array{rows: array<int, array{buttons: array<int, array<string, mixed>>}>}|null
	 */
	private function keypad( OutgoingMessage $message ): ?array {
		if ( 'contact' === $message->keyboard ) {
			$label = $message->buttons[0][0]['text'] ?? __( 'Share phone number', 'storelink' );
			return $this->wrap_keypad(
				array(
					array(
						'buttons' => array(
							array(
								'id'          => 'share_phone',
								'type'        => 'AskMyPhoneNumber',
								'button_text' => $label,
							),
						),
					),
				),
				true
			);
		}

		if ( empty( $message->buttons ) ) {
			return null;
		}

		$rows = array();
		foreach ( $message->buttons as $row ) {
			$line = array();
			foreach ( $row as $button ) {
				if ( ! empty( $button['url'] ) ) {
					continue;
				}
				$line[] = array(
					'id'          => $this->button_id( $button ),
					'type'        => 'Simple',
					'button_text' => (string) $button['text'],
				);
			}
			if ( $line ) {
				$rows[] = array( 'buttons' => $line );
			}
		}

		return $rows ? $this->wrap_keypad( $rows ) : null;
	}

	/**
	 * @param array<int, array{buttons: array<int, array<string, mixed>>}> $rows Keypad rows.
	 * @return array{rows: array<int, array{buttons: array<int, array<string, mixed>>}>, resize_keyboard: bool, one_time_keyboard: bool}
	 */
	private function wrap_keypad( array $rows, bool $one_time = false ): array {
		return array(
			'rows'                => $rows,
			'resize_keyboard'     => true,
			'one_time_keyboard'   => $one_time,
		);
	}

	/**
	 * @param array{text?:string, data?:string} $button Button.
	 */
	private function button_id( array $button ): string {
		$raw = (string) ( $button['data'] ?? '' );
		if ( '' !== $raw && (bool) preg_match( '/^[A-Za-z0-9:_-]{1,64}$/', $raw ) ) {
			return $raw;
		}

		return 'b' . substr( md5( $raw . (string) ( $button['text'] ?? '' ) ), 0, 12 );
	}

	/**
	 * @return array{rows: array<int, array{buttons: array<int, array<string, mixed>>}>}|null
	 */
	private function keypad_without_urls( OutgoingMessage $message ): ?array {
		$rows = array();
		foreach ( $message->buttons as $row ) {
			$line = array();
			foreach ( $row as $button ) {
				if ( ! empty( $button['url'] ) ) {
					continue;
				}
				$line[] = $button;
			}
			if ( $line ) {
				$rows[] = $line;
			}
		}

		return $this->keypad(
			new OutgoingMessage(
				$message->chat_id,
				$message->text,
				$rows,
				$message->photo_url,
				$message->callback_query_id,
				'inline'
			)
		);
	}

	/**
	 * @param array<string, mixed> $result API payload.
	 */
	private function message_id_from( array $result ): string {
		$data = is_array( $result['data'] ?? null ) ? $result['data'] : array();
		$id   = (string) ( $data['message_id'] ?? $result['message_id'] ?? '' );
		if ( '' !== $id ) {
			return $id;
		}

		$nested = is_array( $data['message'] ?? null ) ? $data['message'] : array();
		return (string) ( $nested['message_id'] ?? '' );
	}

	/**
	 * @param array<string, mixed> $me getMe payload.
	 */
	private function bot_username( array $me ): string {
		$data = is_array( $me['data'] ?? null ) ? $me['data'] : array();
		if ( isset( $data['bot'] ) && is_array( $data['bot'] ) ) {
			return (string) ( $data['bot']['username'] ?? '' );
		}

		return (string) ( $data['username'] ?? '' );
	}

	private function client(): ?RubikaClient {
		$token = SettingsStore::token( $this->id() );
		if ( '' === $token ) {
			return null;
		}

		$base = (string) apply_filters( 'storelink_rubika_api_base', 'https://botapi.rubika.ir' );
		return new RubikaClient( $token, $base );
	}

	/**
	 * @param array<string, mixed> $result API payload.
	 */
	private function api_error_text( array $result ): string {
		$text = trim( (string) ( $result['description'] ?? '' ) );
		if ( '' === $text ) {
			$text = __( 'The messenger API rejected the request.', 'storelink' );
		}

		return Log::redact( $text );
	}

	private function store_webhook_error( string $message ): void {
		Log::warning( $this->id() . ' setWebhook: ' . $message );
		SettingsStore::update(
			array(
				'rubika_webhook_ok'    => false,
				'rubika_webhook_error' => $message,
			)
		);
	}

	/**
	 * @param array<string, mixed> $result API payload.
	 */
	private function note_failure( string $method, array $result ): void {
		if ( ! empty( $result['ok'] ) ) {
			return;
		}

		Log::warning( $this->id() . ' ' . $method . ': ' . $this->api_error_text( $result ) );
	}
}
