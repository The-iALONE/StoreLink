<?php
/**
 * Encrypted token storage.
 *
 * @package StoreLink
 */

namespace StoreLink\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts messenger tokens at rest.
 */
class TokenVault {

	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$key    = self::key();
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return '';
		}

		return base64_encode( $iv . $cipher );
	}

	public static function decrypt( string $payload ): string {
		if ( '' === $payload ) {
			return '';
		}

		$raw = base64_decode( $payload, true );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}

		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}

	public static function mask( string $token ): string {
		$len = strlen( $token );
		if ( $len <= 8 ) {
			return $len ? str_repeat( '*', $len ) : '';
		}

		return substr( $token, 0, 4 ) . str_repeat( '*', $len - 8 ) . substr( $token, -4 );
	}

	private static function key(): string {
		$salt = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'storelink';
		return hash( 'sha256', $salt, true );
	}
}
