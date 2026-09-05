<?php
/**
 * Bulk preview service.
 *
 * @package PricePilot
 */

namespace PricePilot\Pricing;

use PricePilot\Core\Currency;
use PricePilot\Products\ProductReader;

defined( 'ABSPATH' ) || exit;

/**
 * Generates bulk price change previews without writing data.
 */
class BulkPreviewService {

	/**
	 * Price calculator.
	 *
	 * @var PriceCalculator
	 */
	private PriceCalculator $calculator;

	/**
	 * Product reader.
	 *
	 * @var ProductReader
	 */
	private ProductReader $reader;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->calculator = new PriceCalculator();
		$this->reader     = new ProductReader();
	}

	/**
	 * Preview price changes for product IDs.
	 *
	 * @param array<int>           $product_ids    Product IDs.
	 * @param string               $operation_type Operation type.
	 * @param float                $value          Operation value.
	 * @param string               $target_field   Target field.
	 * @return array{preview_token: string, items: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>}
	 */
	public function preview( array $product_ids, string $operation_type, float $value, string $target_field ): array {
		$items         = array();
		$skipped       = array();
		$errors        = array();
		$storage_value = Currency::operation_value_to_storage( $operation_type, $value );

		foreach ( $product_ids as $product_id ) {
			$product = $this->reader->get_product( (int) $product_id );

			if ( ! $product ) {
				$errors[] = array(
					'product_id' => (int) $product_id,
					'message'    => __( 'Product not found.', 'pricepilot' ),
				);
				continue;
			}

			$type = $product->get_type();
			if ( ! in_array( $type, array( 'simple', 'variation' ), true ) ) {
				$skipped[] = array(
					'product_id'   => $product->get_id(),
					'name'         => $product->get_name(),
					'reason'       => $this->reader->to_array( $product )['bulk_skip_reason'],
				);
				continue;
			}

			$row = $this->build_preview_row( $product, $operation_type, $storage_value, $target_field );

			if ( ! empty( $row['error'] ) ) {
				$errors[] = array(
					'product_id' => $product->get_id(),
					'name'       => $product->get_name(),
					'message'    => $row['error'],
				);
				continue;
			}

			$items[] = $row;
		}

		$token = wp_generate_password( 32, false );
		set_transient(
			'pricepilot_preview_' . $token,
			array(
				'product_ids'    => wp_list_pluck( $items, 'product_id' ),
				'operation_type' => $operation_type,
				'value'          => $value,
				'target_field'   => $target_field,
				'items'          => $items,
				'user_id'        => get_current_user_id(),
				'created'        => time(),
			),
			15 * MINUTE_IN_SECONDS
		);

		$display_items = array_map(
			static function ( array $row ): array {
				return Currency::row_to_display( $row );
			},
			$items
		);

		return array(
			'preview_token' => $token,
			'items'         => $display_items,
			'skipped'       => $skipped,
			'errors'        => $errors,
		);
	}

	/**
	 * Build preview row for a single product.
	 *
	 * @param \WC_Product $product        Product.
	 * @param string      $operation_type Operation type.
	 * @param float       $value          Value.
	 * @param string      $target_field   Target field.
	 * @return array<string, mixed>
	 */
	private function build_preview_row( \WC_Product $product, string $operation_type, float $value, string $target_field ): array {
		$old_regular = $product->get_regular_price( 'edit' );
		$old_sale    = $product->get_sale_price( 'edit' );
		$new_regular = $old_regular;
		$new_sale    = $old_sale;
		$error       = null;
		$warning     = null;
		$change_label = '';
		$sale_notice  = null;
		$sale_changed = false;

		$fields = 'both' === $target_field ? array( 'regular', 'sale' ) : array( $target_field );

		if ( OperationTypes::REMOVE_SALE === $operation_type ) {
			if ( '' === $old_sale ) {
				$sale_notice = __( 'No sale price — nothing to remove.', 'pricepilot' );
			} else {
				$new_sale     = '';
				$sale_changed = true;
			}
			$change_label = __( 'Remove sale', 'pricepilot' );
		} else {
			foreach ( $fields as $field ) {
				$old = 'sale' === $field ? ( '' === $old_sale ? null : (float) $old_sale ) : ( '' === $old_regular ? null : (float) $old_regular );

				if ( 'sale' === $field && ( null === $old || 0.0 === $old ) && OperationTypes::SET_PRICE !== $operation_type ) {
					if ( 'sale' === $target_field || 'both' === $target_field ) {
						$sale_notice = __( 'No sale price — not changed.', 'pricepilot' );
					}
					continue;
				}

				$result = $this->calculator->calculate( $old, $operation_type, $value );

				if ( $result['error'] ) {
					$error = $result['error'];
					break;
				}

				if ( $result['warning'] ) {
					$warning = $result['warning'];
				}

				$change_label = $result['change_label'];

				if ( 'regular' === $field ) {
					$new_regular = wc_format_decimal( $result['price'] );
				} else {
					$new_sale    = wc_format_decimal( $result['price'] );
					$sale_changed = true;
					$sale_notice  = null;
				}
			}
		}

		if ( ! $error ) {
			$error = $this->calculator->validate_sale_regular(
				'' === $new_regular ? null : (float) $new_regular,
				'' === $new_sale ? null : (float) $new_sale
			);
		}

		return array(
			'product_id'    => $product->get_id(),
			'name'          => $product->get_name(),
			'sku'           => $product->get_sku(),
			'type'          => $product->get_type(),
			'old_regular'   => $old_regular,
			'new_regular'   => $new_regular,
			'old_sale'      => $old_sale,
			'new_sale'      => $new_sale,
			'sale_changed'  => $sale_changed,
			'sale_notice'   => $sale_notice,
			'change_label'  => $change_label,
			'error'         => $error,
			'warning'       => $warning,
		);
	}

	/**
	 * Retrieve stored preview data by token.
	 *
	 * @param string $token Preview token.
	 * @return array<string, mixed>|null
	 */
	public function get_preview_data( string $token ): ?array {
		$data = get_transient( 'pricepilot_preview_' . sanitize_text_field( $token ) );

		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( (int) ( $data['user_id'] ?? 0 ) !== get_current_user_id() ) {
			return null;
		}

		return $data;
	}
}
