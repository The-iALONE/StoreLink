<?php
/**
 * Product importer.
 *
 * @package PricePilot
 */

namespace PricePilot\ImportExport;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PricePilot\Products\ProductUpdater;

defined( 'ABSPATH' ) || exit;

/**
 * Imports product data from XLSX.
 */
class Importer {

	public const MAX_FILE_SIZE = 5242880; // 5MB.

	/**
	 * Allowed MIME types.
	 *
	 * @return array<int, string>
	 */
	public function allowed_mimes(): array {
		return array(
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-excel',
			'application/octet-stream',
		);
	}

	/**
	 * Import from uploaded file path.
	 *
	 * @param string $file_path Absolute path to uploaded file.
	 * @return ImportResult|\WP_Error
	 */
	public function import( string $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_missing', __( 'Import file not found.', 'pricepilot' ) );
		}

		if ( filesize( $file_path ) > self::MAX_FILE_SIZE ) {
			return new \WP_Error( 'file_too_large', __( 'Import file exceeds maximum allowed size.', 'pricepilot' ) );
		}

		$validator = new RowValidator();
		$updater   = new ProductUpdater();
		$result    = new ImportResult();

		try {
			$spreadsheet = IOFactory::load( $file_path );
			$rows        = $spreadsheet->getActiveSheet()->toArray();
		} catch ( \Throwable $exception ) {
			return new \WP_Error( 'parse_failed', __( 'Unable to read spreadsheet file.', 'pricepilot' ) );
		}

		if ( empty( $rows ) ) {
			return new \WP_Error( 'empty_file', __( 'Spreadsheet is empty.', 'pricepilot' ) );
		}

		$headers = array_shift( $rows );
		$header_error = $validator->validate_headers( array_map( 'strval', $headers ) );

		if ( $header_error ) {
			return new \WP_Error( 'invalid_headers', $header_error );
		}

		foreach ( $rows as $index => $row ) {
			$row_num = $index + 2;
			$mapped  = $validator->map_row( array_map( 'strval', $headers ), array_map( 'strval', $row ) );

			if ( $this->is_empty_row( $mapped ) ) {
				continue;
			}

			$validated = $validator->validate_row( $mapped, $row_num );

			if ( ! empty( $validated['errors'] ) || ! $validated['product'] ) {
				++$result->error_count;
				$result->errors[] = array(
					'row'     => $row_num,
					'message' => implode( ' ', $validated['errors'] ),
				);
				continue;
			}

			$product = $validated['product'];
			$updates = $validated['updates'];

			if ( isset( $updates['regular_price'] ) || isset( $updates['sale_price'] ) ) {
				$price_update = array();
				if ( isset( $updates['regular_price'] ) ) {
					$price_update['regular_price'] = $updates['regular_price'];
				}
				if ( isset( $updates['sale_price'] ) ) {
					$price_update['sale_price'] = $updates['sale_price'];
				}

				$price_result = $updater->update_prices( $product, $price_update );
				if ( is_wp_error( $price_result ) ) {
					++$result->error_count;
					$result->errors[] = array(
						'row'     => $row_num,
						'message' => $price_result->get_error_message(),
					);
					continue;
				}
			}

			if ( isset( $updates['stock'] ) ) {
				$stock_result = $updater->update_stock( $product, (int) $updates['stock'] );
				if ( is_wp_error( $stock_result ) ) {
					++$result->error_count;
					$result->errors[] = array(
						'row'     => $row_num,
						'message' => $stock_result->get_error_message(),
					);
					continue;
				}
			}

			++$result->success_count;
		}

		delete_transient( 'pricepilot_dashboard_stats' );

		return $result;
	}

	/**
	 * Check if row has no meaningful data.
	 *
	 * @param array<string, string> $row Mapped row.
	 * @return bool
	 */
	private function is_empty_row( array $row ): bool {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}
		return true;
	}
}
