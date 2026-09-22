<?php
/**
 * Telegram Bot API HTTP client.
 *
 * @package StoreLink
 */

namespace StoreLink\Telegram;

use StoreLink\Admin\SettingsStore;

defined( 'ABSPATH' ) || exit;

/**
 * Thin Bot API wrapper. Base URL is injectable (Bale can reuse later).
 */
class TelegramClient {

	public function __construct(
		private string $token,
		private string $base_url = 'https://api.telegram.org'
	) {}

	/**
	 * @param array<string, mixed> $params Request body.
	 * @return array<string, mixed>
	 */
	public function call( string $method, array $params = array() ): array {
		if ( '' === $this->token ) {
			return array( 'ok' => false, 'description' => 'missing_token' );
		}

		$url = rtrim( $this->base_url, '/' ) . '/bot' . $this->token . '/' . ltrim( $method, '/' );

		$json = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $params ),
			)
		);
		if ( ! empty( $json['ok'] ) ) {
			return $json;
		}

		if ( empty( $params ) ) {
			$get = $this->request(
				$url,
				array(
					'method'  => 'GET',
					'timeout' => 20,
				)
			);
			if ( ! empty( $get['ok'] ) ) {
				return $get;
			}
			return $this->prefer_error( $json, $get );
		}

		$form = $this->request(
			$url,
			array(
				'method'  => 'POST',
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => $params,
			)
		);
		if ( ! empty( $form['ok'] ) ) {
			return $form;
		}

		return $this->prefer_error( $json, $form );
	}

	/**
	 * @param array<string, mixed> $args wp_remote_request arguments.
	 * @return array<string, mixed>
	 */
	private function request( string $url, array $args ): array {
		$callback = $this->proxy_callback();
		if ( $callback ) {
			add_action( 'http_api_curl', $callback, 10, 1 );
		}

		$response = wp_remote_request( $url, $args );

		if ( $callback ) {
			remove_action( 'http_api_curl', $callback, 10 );
		}

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'description' => $response->get_error_message() );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) ) {
			return $body;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return array(
			'ok'          => false,
			'description' => sprintf( 'HTTP %d', $code ),
		);
	}

	private function proxy_callback(): ?callable {
		$proxy = SettingsStore::telegram_proxy();
		if ( '' === $proxy ) {
			return null;
		}

		return static function ( $handle ) use ( $proxy ): void {
			if ( ! is_resource( $handle ) && ! ( $handle instanceof \CurlHandle ) ) {
				return;
			}

			if ( str_starts_with( strtolower( $proxy ), 'socks5' ) ) {
				$host = (string) preg_replace( '/^socks5h?:\/\//i', '', $proxy );
				curl_setopt( $handle, CURLOPT_PROXY, $host );
				$type = defined( 'CURLPROXY_SOCKS5_HOSTNAME' ) ? CURLPROXY_SOCKS5_HOSTNAME : 7;
				curl_setopt( $handle, CURLOPT_PROXYTYPE, $type );
				return;
			}

			curl_setopt( $handle, CURLOPT_PROXY, $proxy );
		};
	}

	/**
	 * @param array<string, mixed> $first First attempt.
	 * @param array<string, mixed> $second Second attempt.
	 * @return array<string, mixed>
	 */
	private function prefer_error( array $first, array $second ): array {
		$first_desc  = (string) ( $first['description'] ?? '' );
		$second_desc = (string) ( $second['description'] ?? '' );
		if ( '' !== $second_desc && ( '' === $first_desc || str_starts_with( $first_desc, 'HTTP ' ) ) ) {
			return $second;
		}

		return $first;
	}
}
