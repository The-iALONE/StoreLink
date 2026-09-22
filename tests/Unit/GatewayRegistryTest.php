<?php
/**
 * Gateway registry tests.
 *
 * @package StoreLink
 */

namespace StoreLink\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StoreLink\Messengers\GatewayRegistry;

/**
 * Registry can accept extra messengers without changing BotEngine.
 */
class GatewayRegistryTest extends TestCase {

	public function test_register_fake_gateway(): void {
		$registry = new GatewayRegistry();
		$fake     = new FakeGateway();
		$registry->register( $fake );

		$this->assertSame( $fake, $registry->get( 'fake' ) );
		$this->assertSame( 'fake', $registry->get( 'fake' )->id() );
	}

	public function test_bale_gateway_registers_without_changing_engine(): void {
		$registry = new GatewayRegistry();
		$bale     = new \StoreLink\Bale\BaleGateway();
		$registry->register( $bale );

		$this->assertSame( $bale, $registry->get( 'bale' ) );
		$this->assertSame( 'bale', $registry->get( 'bale' )->id() );
	}
}
