<?php
/**
 * Operation logger.
 *
 * @package PricePilot
 */

namespace PricePilot\Logging;

use PricePilot\Database\Repositories\OperationItemRepository;
use PricePilot\Database\Repositories\OperationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Logs bulk operations and item changes.
 */
class OperationLogger {

	/**
	 * Operation repository.
	 *
	 * @var OperationRepository
	 */
	private OperationRepository $operations;

	/**
	 * Item repository.
	 *
	 * @var OperationItemRepository
	 */
	private OperationItemRepository $items;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->operations = new OperationRepository();
		$this->items      = new OperationItemRepository();
	}

	/**
	 * Start logging an operation.
	 *
	 * @param array<string, mixed> $data Operation data.
	 * @return int Operation ID.
	 */
	public function log_operation_start( array $data ): int {
		return $this->operations->create( $data );
	}

	/**
	 * Log operation item.
	 *
	 * @param int                  $operation_id Operation ID.
	 * @param array<string, mixed> $data         Item data.
	 * @return int Item ID.
	 */
	public function log_item( int $operation_id, array $data ): int {
		$data['operation_id'] = $operation_id;
		return $this->items->create( $data );
	}
}
