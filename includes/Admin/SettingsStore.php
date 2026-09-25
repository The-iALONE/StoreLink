<?php
/**
 * Plugin settings option.
 *
 * @package StoreLink
 */

namespace StoreLink\Admin;

use StoreLink\Core\TokenVault;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypted messenger settings.
 */
class SettingsStore {

	public const OPTION = 'storelink_settings';

	/**
	 * @return array<int, string>
	 */
	public static function platforms(): array {
		return array( 'telegram', 'bale' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$defaults = array(
			'webhook_public_base'  => '',
			'telegram_proxy'       => '',
			'telegram_relay'       => '',
			'telegram_probe_ok'    => false,
			'telegram_probe_text'  => '',
			'telegram_admin_ids'   => '',
			'telegram_admin_chats' => array(),
		);

		foreach ( self::platforms() as $platform ) {
			$defaults[ $platform . '_enabled' ]       = false;
			$defaults[ $platform . '_token' ]         = '';
			$defaults[ $platform . '_secret' ]        = '';
			$defaults[ $platform . '_username' ]      = '';
			$defaults[ $platform . '_webhook_ok' ]    = false;
			$defaults[ $platform . '_webhook_error' ] = '';
		}

		$defaults['bale_admin_ids']      = '';
		$defaults['bale_admin_chats']    = array();
		$defaults['telegram_channel_id'] = '';
		$defaults['bale_channel_id']     = '';
		$defaults['free_download_skip_checkout'] = true;
		$defaults['bot_show_uncategorized']      = true;
		$defaults['notify_admin_new_order']      = true;
		$defaults['notify_customer_status']      = true;
		$defaults['notify_customer_refunded']    = false;
		$defaults['notify_customer_shipped']     = false;
		$defaults['notify_customer_tracking']    = false;
		$defaults['notify_admin_status']         = false;
		$defaults['channel_caption_name']        = true;
		$defaults['channel_caption_price']       = true;
		$defaults['channel_caption_stock']       = true;
		$defaults['channel_caption_link']        = true;
		$defaults['channel_caption_short_desc']  = false;

		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	public static function token( string $platform = 'telegram' ): string {
		$key = sanitize_key( $platform ) . '_token';
		return trim( TokenVault::decrypt( (string) ( self::get()[ $key ] ?? '' ) ) );
	}

	public static function secret( string $platform = 'telegram' ): string {
		$key = sanitize_key( $platform ) . '_secret';
		return (string) ( self::get()[ $key ] ?? '' );
	}

	public static function is_enabled( string $platform ): bool {
		return ! empty( self::get()[ sanitize_key( $platform ) . '_enabled' ] );
	}

	public static function telegram_proxy(): string {
		return trim( (string) ( self::get()['telegram_proxy'] ?? '' ) );
	}

	public static function telegram_relay(): string {
		return untrailingslashit( trim( (string) ( self::get()['telegram_relay'] ?? '' ) ) );
	}

	/**
	 * @return array<int, string>
	 */
	public static function admin_user_ids( string $platform = 'telegram' ): array {
		$platform = self::normalize_platform( $platform );
		$raw      = (string) ( self::get()[ $platform . '_admin_ids' ] ?? '' );
		$ids      = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_unique( array_map( 'strval', $ids ?: array() ) ) );
	}

	public static function is_admin_user( string $user_id, string $platform = 'telegram' ): bool {
		return in_array( $user_id, self::admin_user_ids( $platform ), true );
	}

	public static function remember_admin_chat( string $user_id, string $chat_id, string $platform = 'telegram' ): void {
		$platform = self::normalize_platform( $platform );
		if ( ! self::is_admin_user( $user_id, $platform ) || '' === $chat_id ) {
			return;
		}

		$key   = $platform . '_admin_chats';
		$chats = self::get()[ $key ];
		$chats = is_array( $chats ) ? $chats : array();
		$chats[ $user_id ] = $chat_id;
		self::update( array( $key => $chats ) );
	}

	/**
	 * @return array<int, string>
	 */
	public static function admin_chat_ids( string $platform = 'telegram' ): array {
		$platform = self::normalize_platform( $platform );
		$chats    = self::get()[ $platform . '_admin_chats' ] ?? array();
		if ( ! is_array( $chats ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'strval', $chats ) ) );
	}

	public static function channel_id( string $platform = 'telegram' ): string {
		$platform = self::normalize_platform( $platform );
		return trim( (string) ( self::get()[ $platform . '_channel_id' ] ?? '' ) );
	}

	public static function skip_free_download_checkout(): bool {
		return ! empty( self::get()['free_download_skip_checkout'] );
	}

	public static function show_uncategorized_in_bot(): bool {
		return ! empty( self::get()['bot_show_uncategorized'] );
	}

	public static function notify_admin_new_order(): bool {
		return ! empty( self::get()['notify_admin_new_order'] );
	}

	public static function notify_customer_status(): bool {
		return ! empty( self::get()['notify_customer_status'] );
	}

	public static function notify_customer_refunded(): bool {
		return ! empty( self::get()['notify_customer_refunded'] );
	}

	public static function notify_customer_shipped(): bool {
		return ! empty( self::get()['notify_customer_shipped'] );
	}

	public static function notify_customer_tracking(): bool {
		return ! empty( self::get()['notify_customer_tracking'] );
	}

	public static function notify_admin_status(): bool {
		return ! empty( self::get()['notify_admin_status'] );
	}

	/**
	 * @return array<string, bool>
	 */
	public static function channel_caption_fields(): array {
		$settings = self::get();
		return array(
			'name'       => ! empty( $settings['channel_caption_name'] ),
			'price'      => ! empty( $settings['channel_caption_price'] ),
			'stock'      => ! empty( $settings['channel_caption_stock'] ),
			'link'       => ! empty( $settings['channel_caption_link'] ),
			'short_desc' => ! empty( $settings['channel_caption_short_desc'] ),
		);
	}

	private static function normalize_platform( string $platform ): string {
		$platform = sanitize_key( $platform );
		return in_array( $platform, self::platforms(), true ) ? $platform : 'telegram';
	}

	/**
	 * @param array<string, mixed> $data Incoming form data.
	 */
	public static function save( array $data ): void {
		$current = self::get();

		$current['webhook_public_base'] = self::sanitize_public_base( (string) ( $data['webhook_public_base'] ?? $current['webhook_public_base'] ) );
		$current['telegram_proxy']      = self::sanitize_proxy( (string) ( $data['telegram_proxy'] ?? $current['telegram_proxy'] ) );
		$current['telegram_relay']      = self::sanitize_relay( (string) ( $data['telegram_relay'] ?? $current['telegram_relay'] ) );
		$current['telegram_admin_ids']  = sanitize_text_field( (string) ( $data['telegram_admin_ids'] ?? $current['telegram_admin_ids'] ) );
		$current['bale_admin_ids']      = sanitize_text_field( (string) ( $data['bale_admin_ids'] ?? $current['bale_admin_ids'] ) );
		$current['telegram_channel_id'] = sanitize_text_field( (string) ( $data['telegram_channel_id'] ?? $current['telegram_channel_id'] ) );
		$current['bale_channel_id']     = sanitize_text_field( (string) ( $data['bale_channel_id'] ?? $current['bale_channel_id'] ) );
		$current['free_download_skip_checkout'] = ! empty( $data['free_download_skip_checkout'] );
		$current['bot_show_uncategorized']      = ! empty( $data['bot_show_uncategorized'] );
		$current['notify_admin_new_order']      = ! empty( $data['notify_admin_new_order'] );
		$current['notify_customer_status']      = ! empty( $data['notify_customer_status'] );
		$current['notify_customer_refunded']    = ! empty( $data['notify_customer_refunded'] );
		$current['notify_customer_shipped']     = ! empty( $data['notify_customer_shipped'] );
		$current['notify_customer_tracking']    = ! empty( $data['notify_customer_tracking'] );
		$current['notify_admin_status']         = ! empty( $data['notify_admin_status'] );
		$current['channel_caption_name']        = ! empty( $data['channel_caption_name'] );
		$current['channel_caption_price']       = ! empty( $data['channel_caption_price'] );
		$current['channel_caption_stock']       = ! empty( $data['channel_caption_stock'] );
		$current['channel_caption_link']        = ! empty( $data['channel_caption_link'] );
		$current['channel_caption_short_desc']  = ! empty( $data['channel_caption_short_desc'] );

		foreach ( self::platforms() as $platform ) {
			$token = trim( (string) ( $data[ $platform . '_token' ] ?? '' ) );
			if ( '' !== $token && ! str_contains( $token, '*' ) ) {
				$current[ $platform . '_token' ] = TokenVault::encrypt( $token );
			}
			$current[ $platform . '_enabled' ] = ! empty( $data[ $platform . '_enabled' ] );
		}

		update_option( self::OPTION, $current );
	}

	public static function webhook_url( string $platform ): string {
		$platform = sanitize_key( $platform );
		$path     = 'storelink/v1/webhooks/' . $platform;
		$base     = untrailingslashit( (string) ( self::get()['webhook_public_base'] ?? '' ) );

		if ( '' === $base ) {
			return rest_url( $path );
		}

		if ( str_contains( $base, '/wp-json' ) ) {
			return $base . '/' . $path;
		}

		$home_path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$home_path = untrailingslashit( $home_path );
		if ( '' !== $home_path && ! str_ends_with( $base, $home_path ) ) {
			$base .= $home_path;
		}

		return $base . '/wp-json/' . $path;
	}

	public static function update( array $patch ): void {
		update_option( self::OPTION, array_merge( self::get(), $patch ) );
	}

	private static function sanitize_public_base( string $base ): string {
		$base = untrailingslashit( trim( $base ) );
		if ( '' === $base ) {
			return '';
		}

		$base = esc_url_raw( $base, array( 'https' ) );
		if ( '' === $base || 'https' !== strtolower( (string) wp_parse_url( $base, PHP_URL_SCHEME ) ) ) {
			return '';
		}

		return untrailingslashit( $base );
	}

	private static function sanitize_proxy( string $proxy ): string {
		$proxy = trim( $proxy );
		if ( '' === $proxy ) {
			return '';
		}

		if ( preg_match( '/^[\w.-]+:\d+$/', $proxy ) ) {
			$proxy = 'socks5://' . $proxy;
		}

		if ( ! preg_match( '/^(https?|socks5h?):\/\/[^\s]+$/i', $proxy ) ) {
			return '';
		}

		return $proxy;
	}

	private static function sanitize_relay( string $relay ): string {
		$relay = untrailingslashit( trim( $relay ) );
		if ( '' === $relay ) {
			return '';
		}

		$relay = esc_url_raw( $relay, array( 'https' ) );
		if ( '' === $relay || 'https' !== strtolower( (string) wp_parse_url( $relay, PHP_URL_SCHEME ) ) ) {
			return '';
		}

		return untrailingslashit( $relay );
	}
}
