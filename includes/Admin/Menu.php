<?php
/**
 * Admin menu.
 *
 * @package StoreLink
 */

namespace StoreLink\Admin;

use StoreLink\Core\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce submenu.
 */
class Menu {

	public function register_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'StoreLink', 'storelink' ),
			__( 'StoreLink', 'storelink' ),
			Plugin::capability(),
			'storelink',
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'storelink' ) );
		}

		include STORELINK_PLUGIN_DIR . 'templates/admin-settings.php';
	}
}
