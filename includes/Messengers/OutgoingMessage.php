<?php
/**
 * Normalized outbound message.
 *
 * @package StoreLink
 */

namespace StoreLink\Messengers;

defined( 'ABSPATH' ) || exit;

/**
 * Messenger-agnostic reply.
 */
class OutgoingMessage {

	/**
	 * @param array<int, array<int, array{text:string, data?:string, url?:string, request_contact?:bool}>> $buttons Button rows.
	 * @param string $keyboard inline | contact | reply.
	 */
	public function __construct(
		public readonly string $chat_id,
		public readonly string $text,
		public readonly array $buttons = array(),
		public readonly string $photo_url = '',
		public readonly string $callback_query_id = '',
		public readonly string $keyboard = 'inline'
	) {}
}
