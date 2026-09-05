<?php
/**
 * RowValidator tests.
 *
 * @package PricePilot
 */

namespace PricePilot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PricePilot\ImportExport\RowValidator;

/**
 * RowValidator test cases.
 */
class RowValidatorTest extends TestCase {

	/**
	 * Validator instance.
	 *
	 * @var RowValidator
	 */
	private RowValidator $validator;

	/**
	 * Setup.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->validator = new RowValidator();
	}

	/**
	 * Test missing required column detection.
	 *
	 * @return void
	 */
	public function test_missing_header_detected(): void {
		$error = $this->validator->validate_headers( array( 'ID', 'SKU' ) );
		$this->assertNotNull( $error );
	}

	/**
	 * Test valid headers pass.
	 *
	 * @return void
	 */
	public function test_valid_headers_pass(): void {
		$error = $this->validator->validate_headers( $this->validator->required_headers() );
		$this->assertNull( $error );
	}

	/**
	 * Test row without id or sku fails.
	 *
	 * @return void
	 */
	public function test_row_without_identifier_fails(): void {
		$result = $this->validator->validate_row(
			array(
				'name'          => 'Test',
				'regular price' => '100',
			),
			2
		);
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Test non-numeric price fails.
	 *
	 * @return void
	 */
	public function test_non_numeric_price_fails(): void {
		$result = $this->validator->validate_row(
			array(
				'id'            => '999999',
				'regular price' => 'abc',
			),
			2
		);
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Test header normalization.
	 *
	 * @return void
	 */
	public function test_header_normalization(): void {
		$this->assertEquals( 'regular price', $this->validator->normalize_header( ' Regular Price ' ) );
	}
}
