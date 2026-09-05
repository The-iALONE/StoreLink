<?php
/**
 * Admin menu registration.
 *
 * @package PricePilot
 */

namespace PricePilot\Admin;

use PricePilot\Core\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers admin menu pages.
 */
class Menu {

	/**
	 * Register WooCommerce submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'PricePilot', 'pricepilot' ),
			__( 'PricePilot', 'pricepilot' ),
			Plugin::capability(),
			'pricepilot',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render admin page mount point.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pricepilot' ) );
		}

		include PRICEPILOT_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}
}
