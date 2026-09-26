<?php
/**
 * PHPUnit bootstrap.
 *
 * @package StoreLink
 */

define( 'ABSPATH', true );
define( 'SECURE_AUTH_KEY', 'storelink-test-key-storelink-test-key' );

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

$root = dirname( __DIR__ );

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( file_exists( $root . '/vendor/autoload.php' ) ) {
	require_once $root . '/vendor/autoload.php';
}

spl_autoload_register(
	static function ( string $class ) use ( $root ): void {
		$prefix = 'StoreLink\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file     = $root . '/includes/' . $relative . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

spl_autoload_register(
	static function ( string $class ) use ( $root ): void {
		$prefix = 'StoreLink\\Tests\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file     = $root . '/tests/' . $relative . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
