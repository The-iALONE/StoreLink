<?php
/**
 * REST routes bootstrap.
 *
 * @package PricePilot
 */

namespace PricePilot\REST;

use PricePilot\REST\Controllers\BulkController;
use PricePilot\REST\Controllers\DashboardController;
use PricePilot\REST\Controllers\HistoryController;
use PricePilot\REST\Controllers\ImportExportController;
use PricePilot\REST\Controllers\ProductsController;
use PricePilot\REST\Controllers\SettingsController;

defined( 'ABSPATH' ) || exit;

/**
 * Registers REST API routes.
 */
class RestBootstrap {

	/**
	 * Register all plugin routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		( new ProductsController() )->register_routes();
		( new BulkController() )->register_routes();
		( new HistoryController() )->register_routes();
		( new DashboardController() )->register_routes();
		( new ImportExportController() )->register_routes();
		( new SettingsController() )->register_routes();
	}
}
