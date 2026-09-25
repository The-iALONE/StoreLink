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

	public function enqueue( string $hook ): void {
		if ( 'woocommerce_page_storelink' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'storelink-admin-settings',
			STORELINK_PLUGIN_URL . 'assets/admin/settings.css',
			array(),
			STORELINK_VERSION
		);
		wp_enqueue_script(
			'storelink-admin-settings',
			STORELINK_PLUGIN_URL . 'assets/admin/settings.js',
			array(),
			STORELINK_VERSION,
			true
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'storelink' ) );
		}

		include STORELINK_PLUGIN_DIR . 'templates/admin-settings.php';
	}
}
