<?php
/**
 * Undo service.
 *
 * @package PricePilot
 */

namespace PricePilot\Pricing;

use PricePilot\Database\Repositories\OperationItemRepository;
use PricePilot\Database\Repositories\OperationRepository;
use PricePilot\Products\ProductReader;
use PricePilot\Products\ProductUpdater;

defined( 'ABSPATH' ) || exit;

/**
 * Reverts the last bulk operation.
 */
class UndoService {

	/**
	 * Undo last applied bulk operation.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function undo_last() {
		$operations = new OperationRepository();
		$operation  = $operations->get_last_undoable();

		if ( ! $operation ) {
			return new \WP_Error(
				'nothing_to_undo',
				__( 'No operation available to undo.', 'pricepilot' ),
				array( 'status' => 400 )
			);
		}

		$items   = ( new OperationItemRepository() )->get_by_operation( (int) $operation->id );
		$reader  = new ProductReader();
		$updater = new ProductUpdater();
		$success = 0;
		$failed  = array();

		foreach ( $items as $item ) {
			if ( 'success' !== $item->status ) {
				continue;
			}

			$product = $reader->get_product( (int) $item->product_id );
			if ( ! $product ) {
				$failed[] = array(
					'product_id' => (int) $item->product_id,
					'message'    => __( 'Product not found.', 'pricepilot' ),
				);
				continue;
			}

			$result = $updater->update_prices(
				$product,
				array(
					'regular_price' => $item->old_regular,
					'sale_price'    => $item->old_sale,
				)
			);

			if ( is_wp_error( $result ) ) {
				$failed[] = array(
					'product_id' => (int) $item->product_id,
					'message'    => $result->get_error_message(),
				);
				continue;
			}

			++$success;
		}

		$operations->update(
			(int) $operation->id,
			array(
				'status'    => 'undone',
				'undone_at' => current_time( 'mysql' ),
			)
		);

		delete_transient( 'pricepilot_dashboard_stats' );

		return array(
			'operation_id'  => (int) $operation->id,
			'success_count' => $success,
			'error_count'   => count( $failed ),
			'failed'        => $failed,
		);
	}
}
