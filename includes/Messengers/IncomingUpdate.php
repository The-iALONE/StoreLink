<?php
/**
 * Normalized inbound update.
 *
 * @package StoreLink
 */

namespace StoreLink\Messengers;

defined( 'ABSPATH' ) || exit;

/**
 * Messenger-agnostic inbound event.
 */
class IncomingUpdate {

	public function __construct(
		public readonly string $platform,
		public readonly int $update_id,
		public readonly string $chat_id,
		public readonly string $user_id,
		public readonly string $type,
		public readonly string $text = '',
		public readonly string $callback_data = '',
		public readonly string $display_name = '',
		public readonly string $phone = '',
		public readonly string $callback_query_id = ''
	) {}
}
