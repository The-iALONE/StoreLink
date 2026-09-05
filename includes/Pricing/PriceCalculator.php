<?php
/**
 * Price calculation engine.
 *
 * @package PricePilot
 */

namespace PricePilot\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Pure price calculation logic.
 */
class PriceCalculator {

	/**
	 * Calculate new price from operation.
	 *
	 * @param float|null $old_price      Current price.
	 * @param string     $operation_type Operation type.
	 * @param float      $value          Operation value.
	 * @return array{price: float|null, change_label: string, error: string|null, warning: string|null}
	 */
	public function calculate( ?float $old_price, string $operation_type, float $value ): array {
		if ( OperationTypes::REMOVE_SALE === $operation_type ) {
			return array(
				'price'        => null,
				'change_label' => __( 'Remove sale', 'pricepilot' ),
				'error'        => null,
				'warning'      => null,
			);
		}

		$base = null === $old_price ? 0.0 : (float) $old_price;

		switch ( $operation_type ) {
			case OperationTypes::INCREASE_PERCENT:
				$new_price    = $base * ( 1 + ( $value / 100 ) );
				$change_label = sprintf( '+%s%%', rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) );
				break;

			case OperationTypes::DECREASE_PERCENT:
				$new_price    = $base * ( 1 - ( $value / 100 ) );
				$change_label = sprintf( '-%s%%', rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) );
				break;

			case OperationTypes::INCREASE_FIXED:
				$new_price    = $base + $value;
				$change_label = sprintf( '+%s', $value );
				break;

			case OperationTypes::DECREASE_FIXED:
				$new_price    = $base - $value;
				$change_label = sprintf( '-%s', $value );
				break;

			case OperationTypes::SET_PRICE:
				$new_price    = $value;
				$change_label = __( 'Set price', 'pricepilot' );
				break;

			default:
				return array(
					'price'        => null,
					'change_label' => '',
					'error'        => __( 'Invalid operation type.', 'pricepilot' ),
					'warning'      => null,
				);
		}

		$warning = null;
		if ( $new_price < 0 ) {
			$new_price = 0.0;
			$warning   = __( 'Price was clamped to zero.', 'pricepilot' );
		}

		if ( 0.0 === $new_price && OperationTypes::SET_PRICE !== $operation_type ) {
			$warning = __( 'Resulting price is zero.', 'pricepilot' );
		}

		return array(
			'price'        => round( (float) $new_price, 4 ),
			'change_label' => $change_label,
			'error'        => null,
			'warning'      => $warning,
		);
	}

	/**
	 * Validate sale vs regular relationship.
	 *
	 * @param float|null $regular Regular price.
	 * @param float|null $sale    Sale price.
	 * @return string|null Error message or null if valid.
	 */
	public function validate_sale_regular( ?float $regular, ?float $sale ): ?string {
		if ( null === $sale || '' === (string) $sale ) {
			return null;
		}

		if ( null !== $regular && (float) $sale > (float) $regular ) {
			return __( 'Sale price cannot be greater than regular price.', 'pricepilot' );
		}

		return null;
	}
}
