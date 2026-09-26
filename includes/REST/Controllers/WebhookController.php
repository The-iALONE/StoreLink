<?php
/**
 * Public messenger webhooks.
 *
 * @package StoreLink
 */

namespace StoreLink\REST\Controllers;

use StoreLink\Admin\SettingsStore;
use StoreLink\Bot\BotEngine;
use StoreLink\Messengers\GatewayRegistry;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Webhook endpoints for registered gateways.
 */
class WebhookController {

	private const NAMESPACE = 'storelink/v1';

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/webhooks/(?P<platform>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'platform'         => array(
						'type' => 'string',
					),
					'storelink_secret' => array(
						'type' => 'string',
					),
				),
			)
		);

		$rubika = array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle_rubika' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'secret' => array(
					'type' => 'string',
				),
			),
		);

		register_rest_route(
			self::NAMESPACE,
			'/webhooks/rubika/(?P<secret>[A-Za-z0-9_-]+)',
			$rubika
		);
		register_rest_route(
			self::NAMESPACE,
			'/webhooks/rubika/(?P<secret>[A-Za-z0-9_-]+)/(?P<kind>receiveUpdate|ReceiveUpdate|receiveInlineMessage|ReceiveInlineMessage)',
			$rubika
		);
	}

	public function handle_rubika( WP_REST_Request $request ) {
		$request->set_param( 'platform', 'rubika' );
		return $this->handle( $request );
	}

	public function handle( WP_REST_Request $request ) {
		$platform = sanitize_key( (string) $request->get_param( 'platform' ) );
		$gateway  = GatewayRegistry::instance()->get( $platform );

		if ( ! $gateway || ! SettingsStore::is_enabled( $platform ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 403 );
		}

		if ( ! $gateway->verify_request( $request ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 403 );
		}

		$update = $gateway->parse_update( $this->payload( $request ) );
		if ( $update ) {
			( new BotEngine() )->handle( $update, $gateway );
		}

		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function payload( WP_REST_Request $request ): array {
		$params = $request->get_json_params();
		if ( is_array( $params ) && array() !== $params ) {
			return $params;
		}

		$decoded = json_decode( (string) $request->get_body(), true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
