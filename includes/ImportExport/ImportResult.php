<?php
/**
 * Import result DTO.
 *
 * @package PricePilot
 */

namespace PricePilot\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Import operation result.
 */
class ImportResult {

	/**
	 * @param int                       $success_count Successful rows.
	 * @param int                       $error_count   Failed rows.
	 * @param array<int, array<string>> $errors        Row errors.
	 */
	public function __construct(
		public int $success_count = 0,
		public int $error_count = 0,
		public array $errors = array()
	) {}

	/**
	 * Convert to array for API response.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'success_count' => $this->success_count,
			'error_count'   => $this->error_count,
			'errors'        => $this->errors,
		);
	}
}
