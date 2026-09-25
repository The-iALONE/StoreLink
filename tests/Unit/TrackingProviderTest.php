<?php
/**
 * Tracking provider URL tests.
 *
 * @package StoreLink
 */

namespace StoreLink\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StoreLink\Tracking\IranPost;
use StoreLink\Tracking\ManualCourier;
use StoreLink\Tracking\ProviderRegistry;
use StoreLink\Tracking\Tipax;

/**
 * Link-only carriers. No live API.
 */
class TrackingProviderTest extends TestCase {

	public function test_iran_post_url_contains_number(): void {
		$post = new IranPost();
		$this->assertSame( 'iran_post', $post->id() );
		$this->assertFalse( $post->can_refresh() );
		$url = $post->tracking_url( '1234567890' );
		$this->assertStringContainsString( 'tracking.post.ir', $url );
		$this->assertStringContainsString( '1234567890', $url );
	}

	public function test_tipax_url_contains_number(): void {
		$tipax = new Tipax();
		$this->assertSame( 'tipax', $tipax->id() );
		$this->assertFalse( $tipax->can_refresh() );
		$url = $tipax->tracking_url( 'TX99' );
		$this->assertStringContainsString( 'tipaxco.com', $url );
		$this->assertStringContainsString( 'TX99', $url );
	}

	public function test_manual_has_no_url(): void {
		$manual = new ManualCourier();
		$this->assertSame( '', $manual->tracking_url( 'ANY' ) );
		$this->assertFalse( $manual->can_refresh() );
	}

	public function test_registry_keeps_providers(): void {
		$registry = new ProviderRegistry();
		$registry->register( new ManualCourier() );
		$registry->register( new IranPost() );
		$this->assertNotNull( $registry->get( 'manual' ) );
		$this->assertNotNull( $registry->get( 'iran_post' ) );
	}
}
