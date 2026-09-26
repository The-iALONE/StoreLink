<?php
/**
 * Rubika Bot API HTTP client.
 *
 * @package StoreLink
 */

namespace StoreLink\Rubika;

use StoreLink\Core\Log;

defined( 'ABSPATH' ) || exit;

/**
 * POST https://botapi.rubika.ir/v3/{token}/{method}
 */
class RubikaClient {

	private string $token;

	public function __construct(
		string $token,
		private string $base_url = 'https://botapi.rubika.ir'
	) {
		$this->token = trim( $token );
	}

	/**
	 * @param array<string, mixed> $params Request body.
	 * @return array<string, mixed>
	 */
	public function call( string $method, array $params = array() ): array {
		if ( '' === $this->token ) {
			return array( 'ok' => false, 'description' => 'missing_token' );
		}

		$url  = rtrim( $this->base_url, '/' ) . '/v3/' . $this->token . '/' . ltrim( $method, '/' );
		$args = array(
			'method'  => 'POST',
			'timeout' => 20,
		);

		if ( array() !== $params ) {
			$args['headers'] = array( 'Content-Type' => 'application/json' );
			$args['body']    = wp_json_encode( $params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'description' => Log::redact( $response->get_error_message() ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			$code = (int) wp_remote_retrieve_response_code( $response );
			return array(
				'ok'          => false,
				'description' => sprintf( 'HTTP %d', $code ),
			);
		}

		return $this->normalize( $body );
	}

	/**
	 * @param array<string, mixed> $params Extra fields.
	 * @return array<string, mixed>
	 */
	public function upload_file( string $chat_id, string $path, string $filename = '' ): array {
		if ( '' === $this->token || ! is_readable( $path ) ) {
			return array( 'ok' => false, 'description' => 'missing_file' );
		}

		$name = '' !== $filename ? $filename : basename( $path );
		$size = (int) filesize( $path );
		$ask  = $this->call(
			'requestSendFile',
			array(
				'file_name' => $name,
				'size'      => $size,
			)
		);
		if ( empty( $ask['ok'] ) ) {
			return $ask;
		}

		$data       = is_array( $ask['data'] ?? null ) ? $ask['data'] : array();
		$upload_url = (string) ( $data['upload_url'] ?? $data['url'] ?? '' );
		$file_id    = (string) ( $data['file_id'] ?? $data['fileId'] ?? '' );
		if ( '' === $upload_url ) {
			return array( 'ok' => false, 'description' => 'missing_upload_url' );
		}

		$put = wp_remote_request(
			$upload_url,
			array(
				'method'  => 'POST',
				'timeout' => 120,
				'headers' => array( 'Content-Type' => 'application/octet-stream' ),
				'body'    => file_get_contents( $path ),
			)
		);
		if ( is_wp_error( $put ) ) {
			return array( 'ok' => false, 'description' => Log::redact( $put->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $put );
		if ( $code >= 400 ) {
			return array( 'ok' => false, 'description' => sprintf( 'HTTP %d', $code ) );
		}

		$uploaded = json_decode( (string) wp_remote_retrieve_body( $put ), true );
		if ( is_array( $uploaded ) ) {
			$norm     = $this->normalize( $uploaded );
			$up_data  = is_array( $norm['data'] ?? null ) ? $norm['data'] : array();
			$file_id  = (string) ( $up_data['file_id'] ?? $file_id );
		}

		if ( '' === $file_id ) {
			return array( 'ok' => false, 'description' => 'missing_file_id' );
		}

		return $this->call(
			'sendFile',
			array(
				'chat_id' => $chat_id,
				'file_id' => $file_id,
			)
		);
	}

	/**
	 * @param array<string, mixed> $body Raw API JSON.
	 * @return array<string, mixed>
	 */
	private function normalize( array $body ): array {
		$status = (string) ( $body['status'] ?? '' );
		$ok     = ! empty( $body['ok'] ) || 'OK' === strtoupper( $status );
		$text   = trim( (string) ( $body['status_det'] ?? $body['description'] ?? '' ) );
		if ( '' === $text && '' !== $status && 'OK' !== strtoupper( $status ) ) {
			$text = $status;
		}

		$data = $body['data'] ?? null;
		if ( ! $ok && is_string( $data ) && '' !== $data ) {
			$text = trim( $text . ' ' . $data );
		}
		if ( ! $ok && is_array( $data ) && isset( $data['message'] ) ) {
			$text = trim( $text . ' ' . (string) $data['message'] );
		}
		if ( ! $ok && is_array( $data ) && '' === $text ) {
			$encoded = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$text    = is_string( $encoded ) ? $encoded : $status;
		}
		if ( '' === $text && ! $ok ) {
			$text = __( 'The messenger API rejected the request.', 'storelink' );
		}

		return array(
			'ok'          => $ok,
			'description' => Log::redact( $text ),
			'data'        => is_array( $data ) ? $data : $body,
			'result'      => is_array( $data ) ? $data : ( $body['result'] ?? $body ),
		);
	}
}
