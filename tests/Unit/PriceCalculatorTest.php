<?php
/**
 * PriceCalculator tests.
 *
 * @package PricePilot
 */

namespace PricePilot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PricePilot\Pricing\OperationTypes;
use PricePilot\Pricing\PriceCalculator;

/**
 * PriceCalculator test cases.
 */
class PriceCalculatorTest extends TestCase {

	/**
	 * Calculator instance.
	 *
	 * @var PriceCalculator
	 */
	private PriceCalculator $calculator;

	/**
	 * Setup.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->calculator = new PriceCalculator();
	}

	/**
	 * Test increase percent.
	 *
	 * @return void
	 */
	public function test_increase_percent(): void {
		$result = $this->calculator->calculate( 100.0, OperationTypes::INCREASE_PERCENT, 10.0 );
		$this->assertEquals( 110.0, $result['price'] );
		$this->assertNull( $result['error'] );
	}

	/**
	 * Test decrease percent.
	 *
	 * @return void
	 */
	public function test_decrease_percent(): void {
		$result = $this->calculator->calculate( 100.0, OperationTypes::DECREASE_PERCENT, 10.0 );
		$this->assertEquals( 90.0, $result['price'] );
	}

	/**
	 * Test fixed increase.
	 *
	 * @return void
	 */
	public function test_increase_fixed(): void {
		$result = $this->calculator->calculate( 50.0, OperationTypes::INCREASE_FIXED, 25.0 );
		$this->assertEquals( 75.0, $result['price'] );
	}

	/**
	 * Test fixed decrease clamped to zero.
	 *
	 * @return void
	 */
	public function test_decrease_fixed_clamps_to_zero(): void {
		$result = $this->calculator->calculate( 10.0, OperationTypes::DECREASE_FIXED, 20.0 );
		$this->assertEquals( 0.0, $result['price'] );
		$this->assertNotNull( $result['warning'] );
	}

	/**
	 * Test set price.
	 *
	 * @return void
	 */
	public function test_set_price(): void {
		$result = $this->calculator->calculate( 100.0, OperationTypes::SET_PRICE, 250.0 );
		$this->assertEquals( 250.0, $result['price'] );
	}

	/**
	 * Test remove sale operation.
	 *
	 * @return void
	 */
	public function test_remove_sale(): void {
		$result = $this->calculator->calculate( 80.0, OperationTypes::REMOVE_SALE, 0.0 );
		$this->assertNull( $result['price'] );
	}

	/**
	 * Test sale greater than regular validation.
	 *
	 * @return void
	 */
	public function test_sale_gt_regular_invalid(): void {
		$error = $this->calculator->validate_sale_regular( 100.0, 150.0 );
		$this->assertNotNull( $error );
	}

	/**
	 * Test zero regular price allowed.
	 *
	 * @return void
	 */
	public function test_zero_price_allowed(): void {
		$result = $this->calculator->calculate( 0.0, OperationTypes::SET_PRICE, 0.0 );
		$this->assertEquals( 0.0, $result['price'] );
	}
}
