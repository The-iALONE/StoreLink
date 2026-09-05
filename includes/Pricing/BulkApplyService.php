<?php
/**
 * Bulk apply service.
 *
 * @package PricePilot
 */

namespace PricePilot\Pricing;

use PricePilot\Database\Repositories\OperationItemRepository;
use PricePilot\Database\Repositories\OperationRepository;
use PricePilot\Logging\OperationLogger;
use PricePilot\Products\ProductReader;
use PricePilot\Products\ProductUpdater;

defined( 'ABSPATH' ) || exit;

/**
 * Applies bulk price changes in batches.
 */
class BulkApplyService {

	public const BATCH_SIZE = 50;

	/**
	 * Preview service.
	 *
	 * @var BulkPreviewService
	 */
	private BulkPreviewService $preview_service;

	/**
	 * Product updater.
	 *
	 * @var ProductUpdater
	 */
	private ProductUpdater $updater;

	/**
	 * Product reader.
	 *
	 * @var ProductReader
	 */
	private ProductReader $reader;

	/**
	 * Operation logger.
	 *
	 * @var OperationLogger
	 */
	private OperationLogger $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->preview_service = new BulkPreviewService();
		$this->updater         = new ProductUpdater();
		$this->reader          = new ProductReader();
		$this->logger          = new OperationLogger();
	}

	/**
	 * Apply previewed bulk operation.
	 *
	 * @param string $preview_token Preview token from preview step.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function apply( string $preview_token ) {
		$data = $this->preview_service->get_preview_data( $preview_token );

		if ( null === $data ) {
			return new \WP_Error(
				'invalid_preview_token',
				__( 'Preview expired or invalid. Please preview again.', 'pricepilot' ),
				array( 'status' => 400 )
			);
		}

		$operation_id = $this->logger->log_operation_start(
			array(
				'operation_type'  => $data['operation_type'],
				'operation_value' => (string) $data['value'],
				'target_field'    => $data['target_field'],
				'product_count'   => count( $data['items'] ?? array() ),
				'status'          => 'applied',
			)
		);

		$success = array();
		$failed  = array();
		$items   = $data['items'] ?? array();

		foreach ( array_chunk( $items, self::BATCH_SIZE ) as $batch ) {
			foreach ( $batch as $row ) {
				$result = $this->apply_row( $operation_id, $row, $data );

				if ( is_wp_error( $result ) ) {
					$failed[] = array(
						'product_id' => (int) $row['product_id'],
						'name'       => $row['name'] ?? '',
						'message'    => $result->get_error_message(),
					);
				} else {
					$success[] = $result;
				}
			}
		}

		delete_transient( 'pricepilot_preview_' . sanitize_text_field( $preview_token ) );
		delete_transient( 'pricepilot_dashboard_stats' );

		$status = empty( $failed )
			? 'applied'
			: ( empty( $success ) ? 'failed' : 'partial' );

		( new OperationRepository() )->update(
			$operation_id,
			array(
				'status'        => $status,
				'success_count' => count( $success ),
				'error_count'   => count( $failed ),
			)
		);

		return array(
			'operation_id'  => $operation_id,
			'status'        => $status,
			'success_count' => count( $success ),
			'error_count'   => count( $failed ),
			'success'       => $success,
			'failed'        => $failed,
		);
	}

	/**
	 * Apply single preview row.
	 *
	 * @param int                  $operation_id Operation ID.
	 * @param array<string, mixed> $row          Preview row.
	 * @param array<string, mixed> $data         Preview data.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function apply_row( int $operation_id, array $row, array $data ) {
		$product = $this->reader->get_product( (int) $row['product_id'] );

		if ( ! $product ) {
			$this->logger->log_item(
				$operation_id,
				array(
					'product_id'    => (int) $row['product_id'],
					'product_name'  => $row['name'] ?? '',
					'status'        => 'failed',
					'error_message' => __( 'Product not found.', 'pricepilot' ),
				)
			);

			return new \WP_Error( 'not_found', __( 'Product not found.', 'pricepilot' ) );
		}

		$prices = array();

		if ( OperationTypes::REMOVE_SALE === $data['operation_type'] ) {
			$prices['sale_price'] = null;
		} else {
			if ( isset( $row['new_regular'] ) && in_array( $data['target_field'], array( 'regular', 'both' ), true ) ) {
				$prices['regular_price'] = $row['new_regular'];
			}
			if ( isset( $row['new_sale'] ) && in_array( $data['target_field'], array( 'sale', 'both' ), true ) ) {
				$prices['sale_price'] = $row['new_sale'];
			}
		}

		$updated = $this->updater->update_prices( $product, $prices );

		if ( is_wp_error( $updated ) ) {
			$this->logger->log_item(
				$operation_id,
				array_merge(
					$this->item_snapshot( $product, $row ),
					array(
						'status'        => 'failed',
						'error_message' => $updated->get_error_message(),
					)
				)
			);

			return $updated;
		}

		$this->logger->log_item(
			$operation_id,
			array_merge(
				$this->item_snapshot( $product, $row ),
				array( 'status' => 'success' )
			)
		);

		return array(
			'product_id' => $product->get_id(),
			'name'       => $product->get_name(),
		);
	}

	/**
	 * Build log item snapshot from preview row.
	 *
	 * @param \WC_Product          $product Product.
	 * @param array<string, mixed> $row     Preview row.
	 * @return array<string, mixed>
	 */
	private function item_snapshot( \WC_Product $product, array $row ): array {
		return array(
			'product_id'   => $product->get_id(),
			'product_type' => $product->get_type(),
			'sku'          => $product->get_sku(),
			'product_name' => $product->get_name(),
			'old_regular'  => $row['old_regular'] ?? null,
			'new_regular'  => $row['new_regular'] ?? null,
			'old_sale'     => $row['old_sale'] ?? null,
			'new_sale'     => $row['new_sale'] ?? null,
			'change_label' => $row['change_label'] ?? '',
		);
	}
}
