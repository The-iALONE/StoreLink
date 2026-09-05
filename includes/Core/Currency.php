<?php
/**
 * WooCommerce currency detection for admin UI.
 *
 * @package PricePilot
 */

namespace PricePilot\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves currency unit and label from WooCommerce settings.
 */
class Currency {

	public const UNIT_RIAL     = 'rial';
	public const UNIT_TOMAN    = 'toman';
	public const UNIT_STANDARD = 'standard';

	/**
	 * Cached context for the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $context_cache = null;

	/**
	 * Build currency context for the admin UI and pricing operations.
	 *
	 * @return array{code:string,unit:string,label:string,storage_factor:float}
	 */
	public static function get_context(): array {
		if ( null !== self::$context_cache ) {
			return self::$context_cache;
		}

		$code   = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
		$symbol = self::get_symbol();
		$unit   = self::detect_unit( $code, $symbol );
		$label  = self::resolve_label( $unit, $code, $symbol );
		$factor = self::resolve_storage_factor( $code, $unit );

		$context = array(
			'code'            => $code,
			'unit'            => $unit,
			'label'           => $label,
			'storage_factor'  => $factor,
		);

		/**
		 * Filter currency context passed to the admin UI and pricing layer.
		 *
		 * @param array{code:string,unit:string,label:string,storage_factor:float} $context Currency context.
		 */
		self::$context_cache = apply_filters( 'pricepilot_currency_context', $context );

		return self::$context_cache;
	}

	/**
	 * Detect whether WooCommerce prices use Rial, Toman, or a standard currency.
	 *
	 * @param string $code   WooCommerce currency code.
	 * @param string $symbol WooCommerce currency symbol.
	 * @return string
	 */
	public static function detect_unit( string $code, string $symbol ): string {
		$code = strtoupper( trim( $code ) );

		if ( in_array( $code, array( 'IRT', 'IRHT', 'IRHR' ), true ) ) {
			return self::UNIT_TOMAN;
		}

		if ( self::text_indicates_rial( $symbol ) ) {
			return self::UNIT_RIAL;
		}

		if ( self::text_indicates_toman( $symbol ) ) {
			return self::UNIT_TOMAN;
		}

		if ( 'IRR' === $code ) {
			return self::UNIT_RIAL;
		}

		return self::UNIT_STANDARD;
	}

	/**
	 * Convert a WooCommerce stored price to the detected display unit.
	 *
	 * @param mixed $storage_value Stored price value.
	 * @return mixed
	 */
	public static function to_display( mixed $storage_value ): mixed {
		if ( '' === $storage_value || null === $storage_value ) {
			return $storage_value;
		}

		$factor = (float) ( self::get_context()['storage_factor'] ?? 1.0 );

		if ( 1.0 === $factor ) {
			return $storage_value;
		}

		$display = (float) $storage_value / $factor;

		return function_exists( 'wc_format_decimal' )
			? wc_format_decimal( $display )
			: (string) round( $display, 4 );
	}

	/**
	 * Convert a display-unit amount to WooCommerce storage format.
	 *
	 * @param float $display_value Display amount.
	 * @return float
	 */
	public static function to_storage( float $display_value ): float {
		$factor = (float) ( self::get_context()['storage_factor'] ?? 1.0 );

		return round( $display_value * $factor, 4 );
	}

	/**
	 * Convert a bulk operation value when it represents a fixed amount.
	 *
	 * @param string $operation_type Operation type.
	 * @param float  $value          Value from UI/API.
	 * @return float
	 */
	public static function operation_value_to_storage( string $operation_type, float $value ): float {
		if ( in_array(
			$operation_type,
			array(
				'increase_percent',
				'decrease_percent',
				'remove_sale',
			),
			true
		) ) {
			return $value;
		}

		return self::to_storage( $value );
	}

	/**
	 * Convert preview/API row prices to display unit.
	 *
	 * @param array<string, mixed> $row Preview or product row.
	 * @return array<string, mixed>
	 */
	public static function row_to_display( array $row ): array {
		foreach ( array( 'regular_price', 'sale_price', 'price', 'old_regular', 'new_regular', 'old_sale', 'new_sale' ) as $key ) {
			if ( array_key_exists( $key, $row ) ) {
				$row[ $key ] = self::to_display( $row[ $key ] );
			}
		}

		return $row;
	}

	/**
	 * Get decoded WooCommerce currency symbol.
	 *
	 * @return string
	 */
	private static function get_symbol(): string {
		if ( ! function_exists( 'get_woocommerce_currency_symbol' ) ) {
			return '';
		}

		return trim( wp_strip_all_tags( html_entity_decode( get_woocommerce_currency_symbol() ) ) );
	}

	/**
	 * @param string $text Symbol or label text.
	 * @return bool
	 */
	private static function text_indicates_toman( string $text ): bool {
		$text = trim( $text );

		foreach ( array( 'تومان', 'toman', 'irt' ) as $needle ) {
			if ( self::contains_text( $text, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $text Symbol or label text.
	 * @return bool
	 */
	private static function text_indicates_rial( string $text ): bool {
		$text = trim( $text );

		foreach ( array( 'ریال', 'rial', 'irr', 'rls', '﷼' ) as $needle ) {
			if ( self::contains_text( $text, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Case-insensitive text search without requiring mbstring.
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Needle.
	 * @return bool
	 */
	private static function contains_text( string $haystack, string $needle ): bool {
		if ( '' === $needle || '' === $haystack ) {
			return false;
		}

		if ( str_contains( $haystack, $needle ) ) {
			return true;
		}

		if ( self::is_ascii( $haystack ) && self::is_ascii( $needle ) ) {
			return str_contains( strtolower( $haystack ), strtolower( $needle ) );
		}

		return false;
	}

	/**
	 * @param string $text Text to inspect.
	 * @return bool
	 */
	private static function is_ascii( string $text ): bool {
		return (bool) preg_match( '/^[\x00-\x7F]*$/', $text );
	}

	/**
	 * Resolve storage/display conversion factor.
	 *
	 * WooCommerce stores IRR in Rial and IRT in Toman. When the detected display
	 * unit is Toman but the store currency code is IRR, prices are converted by 10.
	 *
	 * @param string $code Currency code.
	 * @param string $unit Detected display unit.
	 * @return float
	 */
	private static function resolve_storage_factor( string $code, string $unit ): float {
		$code = strtoupper( trim( $code ) );

		if ( self::UNIT_TOMAN === $unit && 'IRR' === $code ) {
			$factor = 10.0;
		} else {
			$factor = 1.0;
		}

		/**
		 * Filter the ratio between display unit and WooCommerce storage values.
		 *
		 * storage_value = display_value * factor
		 *
		 * @param float  $factor Conversion factor.
		 * @param string $code   Currency code.
		 * @param string $unit   Detected display unit.
		 */
		return (float) apply_filters( 'pricepilot_currency_storage_factor', $factor, $code, $unit );
	}

	/**
	 * Resolve a human-readable unit label for the UI.
	 *
	 * @param string $unit   Detected unit.
	 * @param string $code   Currency code.
	 * @param string $symbol Currency symbol.
	 * @return string
	 */
	private static function resolve_label( string $unit, string $code, string $symbol ): string {
		if ( self::UNIT_TOMAN === $unit ) {
			if ( self::text_indicates_toman( $symbol ) && ! self::is_bare_code_symbol( $symbol, $code ) ) {
				return $symbol;
			}

			return __( 'Toman', 'pricepilot' );
		}

		if ( self::UNIT_RIAL === $unit ) {
			if ( self::text_indicates_rial( $symbol ) && ! self::is_bare_code_symbol( $symbol, $code ) ) {
				return $symbol;
			}

			return __( 'Rial', 'pricepilot' );
		}

		if ( '' !== $symbol ) {
			return $symbol;
		}

		if ( '' !== $code && function_exists( 'get_woocommerce_currencies' ) ) {
			$currencies = get_woocommerce_currencies();

			if ( isset( $currencies[ $code ] ) ) {
				return (string) $currencies[ $code ];
			}
		}

		return $code;
	}

	/**
	 * @param string $symbol Currency symbol.
	 * @param string $code   Currency code.
	 * @return bool
	 */
	private static function is_bare_code_symbol( string $symbol, string $code ): bool {
		return strtoupper( trim( $symbol ) ) === strtoupper( trim( $code ) );
	}
}
