<?php
/**
 * Import/Export REST controller.
 *
 * @package PricePilot
 */

namespace PricePilot\REST\Controllers;

use PricePilot\ImportExport\Exporter;
use PricePilot\ImportExport\Importer;
use PricePilot\REST\ApiResponse;
use PricePilot\Security\Permissions;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Import and export endpoints.
 */
class ImportExportController {

	private const NAMESPACE = 'pricepilot/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( Permissions::class, 'rest_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( Permissions::class, 'rest_permission' ),
			)
		);
	}

	/**
	 * Export products to XLSX download.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function export( WP_REST_Request $request ) {
		$exporter  = new Exporter();
		$file_path = $exporter->export( $request->get_params() );

		if ( is_wp_error( $file_path ) ) {
			return ApiResponse::error( $file_path );
		}

		$contents = file_get_contents( $file_path );
		@unlink( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $contents ) {
			return ApiResponse::error( __( 'Failed to read export file.', 'pricepilot' ), 'export_failed', 500 );
		}

		$response = new \WP_REST_Response( base64_encode( $contents ) );
		$response->set_status( 200 );
		$response->header( 'Content-Type', 'application/json' );
		$response->set_data(
			array(
				'success'  => true,
				'data'     => array(
					'filename' => 'pricepilot-export-' . gmdate( 'Y-m-d' ) . '.xlsx',
					'content'  => base64_encode( $contents ),
				),
			)
		);

		return $response;
	}

	/**
	 * Import products from uploaded XLSX.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function import( WP_REST_Request $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) || ! empty( $files['file']['error'] ) ) {
			return ApiResponse::error( __( 'No valid file uploaded.', 'pricepilot' ), 'validation_error', 400 );
		}

		$file = $files['file'];

		if ( $file['size'] > Importer::MAX_FILE_SIZE ) {
			return ApiResponse::error( __( 'File exceeds maximum allowed size (5MB).', 'pricepilot' ), 'validation_error', 400 );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'xlsx', 'xls' ), true ) ) {
			return ApiResponse::error( __( 'Only .xlsx or .xls files are allowed.', 'pricepilot' ), 'validation_error', 400 );
		}

		$importer = new Importer();
		$allowed  = $importer->allowed_mimes();

		if ( ! empty( $file['type'] ) && ! in_array( $file['type'], $allowed, true ) ) {
			return ApiResponse::error( __( 'Invalid file type.', 'pricepilot' ), 'validation_error', 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					'xls'  => 'application/vnd.ms-excel',
				),
			)
		);

		if ( isset( $upload['error'] ) ) {
			return ApiResponse::error( $upload['error'], 'upload_error', 400 );
		}

		$result = $importer->import( $upload['file'] );
		@unlink( $upload['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( is_wp_error( $result ) ) {
			return ApiResponse::error( $result );
		}

		return ApiResponse::success( $result->to_array() );
	}
}
