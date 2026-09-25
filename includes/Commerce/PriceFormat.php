<?php
/**
 * Display money amounts in messenger text.
 *
 * @package StoreLink
 */

namespace StoreLink\Commerce;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a space between the number and the currency label (تومان).
 */
class PriceFormat {

	public static function from_html( string $html ): string {
		$text = wp_strip_all_tags( html_entity_decode( $html ) );
		$text = str_replace( "\xc2\xa0", ' ', $text );
		$text = preg_replace( '/[\x{200e}\x{200f}\x{202a}-\x{202e}]/u', '', $text ) ?? $text;
		$text = preg_replace( '/\s+/u', ' ', $text ) ?? $text;
		$text = preg_replace( '/(تومان|ریال|IRT|IRR)(?=\s*[0-9۰-۹])/u', '$1 ', $text ) ?? $text;
		$text = preg_replace( '/(تومان|ریال|IRT|IRR)\s+/u', '$1 ', $text ) ?? $text;
		$text = preg_replace( '/([0-9۰-۹][0-9۰-۹,٬.]*)\s*(تومان|ریال|IRT|IRR)/u', '$1 $2', $text ) ?? $text;
		return trim( $text );
	}

	public static function amount( float $amount ): string {
		return self::from_html( (string) wc_price( $amount ) );
	}
}
