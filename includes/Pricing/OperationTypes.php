<?php
/**
 * Bulk operation type constants.
 *
 * @package PricePilot
 */

namespace PricePilot\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Operation type definitions.
 */
final class OperationTypes {

	public const INCREASE_PERCENT = 'increase_percent';
	public const DECREASE_PERCENT = 'decrease_percent';
	public const INCREASE_FIXED   = 'increase_fixed';
	public const DECREASE_FIXED   = 'decrease_fixed';
	public const SET_PRICE        = 'set_price';
	public const REMOVE_SALE      = 'remove_sale';

	/**
	 * All valid operation types.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array {
		return array(
			self::INCREASE_PERCENT,
			self::DECREASE_PERCENT,
			self::INCREASE_FIXED,
			self::DECREASE_FIXED,
			self::SET_PRICE,
			self::REMOVE_SALE,
		);
	}

	/**
	 * Valid target fields.
	 *
	 * @return array<int, string>
	 */
	public static function target_fields(): array {
		return array( 'regular', 'sale', 'both' );
	}
}
