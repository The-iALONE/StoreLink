<?php
/**
 * Admin settings page handlers.
 *
 * @package StoreLink
 */

namespace StoreLink\Admin;

use StoreLink\Core\Plugin;
use StoreLink\Messengers\GatewayRegistry;
use StoreLink\Publishing\ChannelPublishQueue;

defined( 'ABSPATH' ) || exit;

/**
 * Saves settings and registers messenger webhooks.
 */
class SettingsPage {

	public static function sanitize_tab( string $tab ): string {
		$tab = sanitize_key( $tab );
		if ( 'general' === $tab || in_array( $tab, SettingsStore::platforms(), true ) ) {
			return $tab;
		}

		return 'general';
	}

	private function requested_tab(): string {
		return self::sanitize_tab( (string) wp_unslash( $_POST['storelink_tab'] ?? 'general' ) );
	}

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

		if ( ! empty( $_POST['storelink_test_telegram'] ) ) {
			$this->redirect_test();
		}

		if ( ! empty( $_POST['storelink_channel_publish'] ) ) {
			$this->redirect_publish( $posted );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'storelink',
					'tab'             => $this->requested_tab(),
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
					'tab'               => $platform,
					'storelink_webhook' => $ok ? '1' : '0',
					'platform'          => $platform,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function redirect_test(): void {
		$gateway = GatewayRegistry::instance()->get( 'telegram' );
		$ping    = ( $gateway && method_exists( $gateway, 'ping' ) )
			? $gateway->ping()
			: array(
				'ok'       => false,
				'text'     => __( 'Telegram gateway is not registered.', 'storelink' ),
				'username' => '',
			);

		$patch = array(
			'telegram_probe_ok'   => ! empty( $ping['ok'] ),
			'telegram_probe_text' => (string) ( $ping['text'] ?? '' ),
		);
		if ( ! empty( $ping['username'] ) ) {
			$patch['telegram_username'] = (string) $ping['username'];
		}
		SettingsStore::update( $patch );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'storelink',
					'tab'            => 'telegram',
					'storelink_test' => ! empty( $ping['ok'] ) ? '1' : '0',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function redirect_publish( array $posted ): void {
		$category_id = absint( $posted['channel_publish_category'] ?? 0 );
		$raw_ids     = (string) ( $posted['channel_publish_ids'] ?? '' );
		$queued      = ( new ChannelPublishQueue() )->enqueue_from_request( $category_id, $raw_ids );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                     => 'storelink',
					'tab'                      => $this->requested_tab(),
					'storelink_channel_queued' => (string) count( $queued ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
