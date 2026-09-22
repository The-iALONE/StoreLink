<?php
/**
 * Admin settings page handlers.
 *
 * @package StoreLink
 */

namespace StoreLink\Admin;

use StoreLink\Core\Plugin;
use StoreLink\Messengers\GatewayRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Saves settings and registers messenger webhooks.
 */
class SettingsPage {

	public function save(): void {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'storelink' ) );
		}

		check_admin_referer( 'storelink_save_settings' );

		$posted = isset( $_POST[ SettingsStore::OPTION ] ) && is_array( $_POST[ SettingsStore::OPTION ] )
			? wp_unslash( $_POST[ SettingsStore::OPTION ] )
			: array();

		SettingsStore::save( $posted );

		$connect = sanitize_key( (string) ( $_POST['storelink_connect'] ?? '' ) );
		if ( '' !== $connect ) {
			$this->redirect_connect( $connect );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'storelink',
					'storelink_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function connect_webhook(): void {
		$this->save();
	}

	private function redirect_connect( string $platform ): void {
		if ( ! in_array( $platform, SettingsStore::platforms(), true ) ) {
			$platform = 'telegram';
		}

		$secret  = wp_generate_password( 32, false );
		$url     = SettingsStore::webhook_url( $platform );
		$gateway = GatewayRegistry::instance()->get( $platform );
		$ok      = $gateway && $gateway->set_webhook( $url, $secret );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'storelink',
					'storelink_webhook' => $ok ? '1' : '0',
					'platform'          => $platform,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
