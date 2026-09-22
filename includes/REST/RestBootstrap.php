<?php
/**
 * REST routes bootstrap.
 *
 * @package StoreLink
 */

namespace StoreLink\REST;

use StoreLink\REST\Controllers\WebhookController;

defined( 'ABSPATH' ) || exit;

/**
 * Registers REST API routes.
 */
class RestBootstrap {

	public function register_routes(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		( new WebhookController() )->register_routes();
	}
}
