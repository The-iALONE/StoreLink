<?php
/**
 * Bulk apply integration test placeholder.
 *
 * Full integration tests require WordPress test suite bootstrap.
 *
 * @package PricePilot
 */

namespace PricePilot\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PricePilot\Pricing\OperationTypes;

/**
 * Bulk apply integration tests (requires WP environment).
 */
class BulkApplyTest extends TestCase {

	/**
	 * Verify operation types are defined for integration scenarios.
	 *
	 * @return void
	 */
	public function test_operation_types_available(): void {
		$this->assertContains( OperationTypes::INCREASE_PERCENT, OperationTypes::all() );
		$this->assertContains( OperationTypes::REMOVE_SALE, OperationTypes::all() );
	}
}
