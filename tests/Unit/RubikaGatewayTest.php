<?php
/**
 * Rubika gateway parse tests. No live API.
 *
 * @package StoreLink
 */

namespace StoreLink\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StoreLink\Rubika\RubikaGateway;

/**
 * Incoming Update mapping for Rubika webhooks.
 */
class RubikaGatewayTest extends TestCase {

	public function test_parse_new_message(): void {
		$gateway = new RubikaGateway();
		$update  = $gateway->parse_update(
			array(
				'update' => array(
					'type'        => 'NewMessage',
					'chat_id'     => 'b0chat',
					'new_message' => array(
						'message_id' => '204215121115944300',
						'text'       => '/start',
						'sender_id'  => 'u0user',
					),
				),
			)
		);

		$this->assertNotNull( $update );
		$this->assertSame( 'rubika', $update->platform );
		$this->assertSame( 'b0chat', $update->chat_id );
		$this->assertSame( 'u0user', $update->user_id );
		$this->assertSame( '/start', $update->text );
		$this->assertSame( '', $update->callback_data );
	}

	public function test_parse_inline_button(): void {
		$gateway = new RubikaGateway();
		$update  = $gateway->parse_update(
			array(
				'inline_message' => array(
					'sender_id'  => 'u0user',
					'chat_id'    => 'b0chat',
					'message_id' => '99',
					'aux_data'   => array(
						'button_id' => 'mo:12',
					),
				),
			)
		);

		$this->assertNotNull( $update );
		$this->assertSame( 'callback', $update->type );
		$this->assertSame( 'mo:12', $update->callback_data );
		$this->assertSame( 'b0chat', $update->chat_id );
	}

	public function test_started_bot_without_sender_uses_chat(): void {
		$gateway = new RubikaGateway();
		$update  = $gateway->parse_update(
			array(
				'update' => array(
					'type'    => 'StartedBot',
					'chat_id' => 'b0chat',
				),
			)
		);

		$this->assertNotNull( $update );
		$this->assertSame( '/start', $update->text );
		$this->assertSame( 'b0chat', $update->user_id );
		$this->assertSame( 0, $update->update_id );
	}
}
