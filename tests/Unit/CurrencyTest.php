<?php
/**
 * Currency detection tests.
 *
 * @package PricePilot
 */

namespace PricePilot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PricePilot\Core\Currency;

/**
 * Currency test cases.
 */
class CurrencyTest extends TestCase {

	/**
	 * Reset cached currency context between tests.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$property = new \ReflectionProperty( Currency::class, 'context_cache' );
		$property->setAccessible( true );
		$property->setValue( null, null );
		parent::tearDown();
	}

	/**
	 * @return array<string, array{0:string,1:string,2:string}>
	 */
	public function unit_detection_provider(): array {
		return array(
			'irt code is toman'         => array( 'IRT', 'تومان', Currency::UNIT_TOMAN ),
			'irr code with rial symbol' => array( 'IRR', '﷼', Currency::UNIT_RIAL ),
			'irr with toman symbol'     => array( 'IRR', 'تومان', Currency::UNIT_TOMAN ),
			'usd is standard'             => array( 'USD', '$', Currency::UNIT_STANDARD ),
		);
	}

	/**
	 * @dataProvider unit_detection_provider
	 * @param string $code     Currency code.
	 * @param string $symbol   Currency symbol.
	 * @param string $expected Expected unit.
	 * @return void
	 */
	public function test_detect_unit( string $code, string $symbol, string $expected ): void {
		$this->assertSame( $expected, Currency::detect_unit( $code, $symbol ) );
	}

	/**
	 * @return void
	 */
	public function test_to_storage_and_display_with_factor(): void {
		$property = new \ReflectionProperty( Currency::class, 'context_cache' );
		$property->setAccessible( true );
		$property->setValue(
			null,
			array(
				'code'           => 'IRR',
				'unit'           => Currency::UNIT_TOMAN,
				'label'          => 'Toman',
				'storage_factor' => 10.0,
			)
		);

		$this->assertSame( 1000000.0, Currency::to_storage( 100000.0 ) );
		$this->assertSame( 100000.0, (float) Currency::to_display( '1000000' ) );
	}
}
