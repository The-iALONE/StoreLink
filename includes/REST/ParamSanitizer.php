<?php
/**
 * REST parameter sanitizers compatible with WP_REST_Request callbacks.
 *
 * @package PricePilot
 */

namespace PricePilot\REST;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizers that accept the REST API callback signature.
 */
class ParamSanitizer {

	/**
	 * Sanitize a positive integer parameter.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function absint( $value ): int {
		return absint( $value );
	}

	/**
	 * Sanitize an optional float; preserve empty values.
	 *
	 * @param mixed $value Raw value.
	 * @return float|string
	 */
	public static function optional_float( $value ) {
		if ( null === $value || '' === $value ) {
			return '';
		}

		return (float) $value;
	}

	/**
	 * Sanitize an optional integer; preserve empty values.
	 *
	 * @param mixed $value Raw value.
	 * @return int|string
	 */
	public static function optional_absint( $value ) {
		if ( null === $value || '' === $value ) {
			return '';
		}

		return absint( $value );
	}
}
