<?php
/**
 * Failure log that never stores bot tokens.
 *
 * @package StoreLink
 */

namespace StoreLink\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Writes to the WooCommerce log (source: storelink).
 */
class Log {

	public static function warning( string $message ): void {
		self::write( 'warning', $message );
	}

	private static function write( string $level, string $message ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$message = self::redact( $message );
		$logger  = wc_get_logger();
		if ( 'warning' === $level ) {
			$logger->warning( $message, array( 'source' => 'storelink' ) );
			return;
		}

		$logger->info( $message, array( 'source' => 'storelink' ) );
	}

	public static function redact( string $message ): string {
		$redacted = preg_replace( '/\d{5,}:[A-Za-z0-9_-]{10,}/', '[redacted-token]', $message );
		return is_string( $redacted ) ? $redacted : $message;
	}
}
