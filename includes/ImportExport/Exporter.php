<?php
/**
 * Product exporter.
 *
 * @package PricePilot
 */

namespace PricePilot\ImportExport;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PricePilot\Products\ProductQuery;

defined( 'ABSPATH' ) || exit;

/**
 * Exports products to XLSX.
 */
class Exporter {

	/**
	 * Export products matching filters to file path.
	 *
	 * @param array<string, mixed> $params Filter params.
	 * @return string|\WP_Error Absolute file path.
	 */
	public function export( array $params ) {
		$query      = new ProductQuery();
		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();

		$headers = array( 'ID', 'SKU', 'Name', 'Regular Price', 'Sale Price', 'Stock', 'Category' );
		$sheet->fromArray( $headers, null, 'A1' );

		$row_num = 2;
		$page    = 1;

		do {
			$params['page']     = $page;
			$params['per_page'] = 100;
			$result             = $query->query( $params );
			$items              = $result['items'];

			foreach ( $items as $item ) {
				$sheet->fromArray(
					array(
						$item['id'],
						$item['sku'],
						$item['name'],
						$item['regular_price'],
						$item['sale_price'],
						$item['stock_quantity'],
						implode( ', ', $item['categories'] ?? array() ),
					),
					null,
					'A' . $row_num
				);
				++$row_num;
			}

			++$page;
		} while ( count( $items ) === 100 );

		$upload_dir = wp_upload_dir();
		$file_path  = trailingslashit( $upload_dir['basedir'] ) . 'pricepilot-export-' . time() . '.xlsx';

		try {
			$writer = new Xlsx( $spreadsheet );
			$writer->save( $file_path );
		} catch ( \Throwable $exception ) {
			return new \WP_Error( 'export_failed', $exception->getMessage() );
		}

		return $file_path;
	}
}
