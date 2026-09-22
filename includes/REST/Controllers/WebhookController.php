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

		$update = $gateway->parse_update( $request->get_json_params() );
		if ( $update ) {
			( new BotEngine() )->handle( $update, $gateway );
		}

		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
