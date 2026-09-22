<?php
/**
 * TokenVault tests.
 *
 * @package StoreLink
 */

namespace StoreLink\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StoreLink\Core\TokenVault;

/**
 * Token encryption.
 */
class TokenVaultTest extends TestCase {

	public function test_roundtrip_and_mask(): void {
		$token     = '123456:ABC-DEF-GHIJKLMNOP';
		$encrypted = TokenVault::encrypt( $token );

		$this->assertNotSame( $token, $encrypted );
		$this->assertSame( $token, TokenVault::decrypt( $encrypted ) );
		$this->assertStringContainsString( '*', TokenVault::mask( $token ) );
		$this->assertStringStartsWith( '1234', TokenVault::mask( $token ) );
	}
}
