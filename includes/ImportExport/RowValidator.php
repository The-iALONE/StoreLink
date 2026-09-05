<?php
/**
 * Import row validator.
 *
 * @package PricePilot
 */

namespace PricePilot\ImportExport;

use PricePilot\Core\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Validates import spreadsheet rows.
 */
class RowValidator {

	/**
	 * Required column headers.
	 *
	 * @return array<int, string>
	 */
	public function required_headers(): array {
		return array( 'ID', 'SKU', 'Name', 'Regular Price', 'Sale Price', 'Stock', 'Category' );
	}

	/**
	 * Validate file headers.
	 *
	 * @param array<int, string> $headers Header row.
	 * @return string|null Error message or null.
	 */
	public function validate_headers( array $headers ): ?string {
		$normalized = array_map( array( $this, 'normalize_header' ), $headers );
		$required   = array_map( array( $this, 'normalize_header' ), $this->required_headers() );

		foreach ( $required as $column ) {
			if ( ! in_array( $column, $normalized, true ) ) {
				return sprintf(
					/* translators: %s: column name */
					__( 'Missing required column: %s', 'pricepilot' ),
					$column
				);
			}
		}

		return null;
	}

	/**
	 * Validate a single data row.
	 *
	 * @param array<string, mixed> $row     Row data keyed by header.
	 * @param int                  $row_num Row number for reporting.
	 * @return array{product: \WC_Product|null, errors: array<int, string>, updates: array<string, mixed>}
	 */
	public function validate_row( array $row, int $row_num ): array {
		$errors  = array();
		$updates = array();
		$product = null;

		$id  = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
		$sku = isset( $row['sku'] ) ? sanitize_text_field( (string) $row['sku'] ) : '';

		if ( $id ) {
			$product = wc_get_product( $id );
		} elseif ( $sku ) {
			$product_id = wc_get_product_id_by_sku( $sku );
			if ( $product_id ) {
				$product = wc_get_product( $product_id );
			}
		} else {
			$errors[] = __( 'Row must include ID or SKU.', 'pricepilot' );
		}

		if ( ! $product && empty( $errors ) ) {
			$errors[] = __( 'Product not found.', 'pricepilot' );
		}

		if ( isset( $row['regular price'] ) && '' !== (string) $row['regular price'] ) {
			if ( ! is_numeric( $row['regular price'] ) ) {
				$errors[] = __( 'Regular Price must be numeric.', 'pricepilot' );
			} else {
				$updates['regular_price'] = wc_format_decimal(
					Currency::to_storage( (float) $row['regular price'] )
				);
			}
		}

		if ( isset( $row['sale price'] ) && '' !== (string) $row['sale price'] ) {
			if ( ! is_numeric( $row['sale price'] ) ) {
				$errors[] = __( 'Sale Price must be numeric.', 'pricepilot' );
			} else {
				$updates['sale_price'] = wc_format_decimal(
					Currency::to_storage( (float) $row['sale price'] )
				);
			}
		}

		if ( isset( $row['stock'] ) && '' !== (string) $row['stock'] ) {
			if ( ! is_numeric( $row['stock'] ) ) {
				$errors[] = __( 'Stock must be numeric.', 'pricepilot' );
			} else {
				$updates['stock'] = (int) $row['stock'];
			}
		}

		return array(
			'product' => $product instanceof \WC_Product ? $product : null,
			'errors'  => $errors,
			'updates' => $updates,
		);
	}

	/**
	 * Normalize header name for comparison.
	 *
	 * @param string $header Header cell value.
	 * @return string
	 */
	public function normalize_header( string $header ): string {
		return strtolower( trim( $header ) );
	}

	/**
	 * Map header row to associative keys.
	 *
	 * @param array<int, string>   $headers Header cells.
	 * @param array<int, string>   $row     Data cells.
	 * @return array<string, string>
	 */
	public function map_row( array $headers, array $row ): array {
		$mapped = array();

		foreach ( $headers as $index => $header ) {
			$key            = $this->normalize_header( (string) $header );
			$mapped[ $key ] = isset( $row[ $index ] ) ? (string) $row[ $index ] : '';
		}

		return $mapped;
	}
}
