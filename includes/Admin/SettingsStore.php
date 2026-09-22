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

	/**
	 * @return array<int, string>
	 */
	public static function admin_user_ids(): array {
		$raw = (string) ( self::get()['telegram_admin_ids'] ?? '' );
		$ids = preg_split( '/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
		return array_values( array_unique( array_map( 'strval', $ids ?: array() ) ) );
	}

	public static function is_admin_user( string $user_id ): bool {
		return in_array( $user_id, self::admin_user_ids(), true );
	}

	public static function remember_admin_chat( string $user_id, string $chat_id ): void {
		if ( ! self::is_admin_user( $user_id ) || '' === $chat_id ) {
			return;
		}

		$chats              = self::get()['telegram_admin_chats'];
		$chats              = is_array( $chats ) ? $chats : array();
		$chats[ $user_id ]  = $chat_id;
		self::update( array( 'telegram_admin_chats' => $chats ) );
	}

	/**
	 * @return array<int, string>
	 */
	public static function admin_chat_ids(): array {
		$chats = self::get()['telegram_admin_chats'];
		if ( ! is_array( $chats ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'strval', $chats ) ) );
	}

	/**
	 * @param array<string, mixed> $data Incoming form data.
	 */
	public static function save( array $data ): void {
		$current = self::get();

		$current['webhook_public_base'] = esc_url_raw( trim( (string) ( $data['webhook_public_base'] ?? $current['webhook_public_base'] ) ) );
		$current['telegram_proxy']      = self::sanitize_proxy( (string) ( $data['telegram_proxy'] ?? $current['telegram_proxy'] ) );
		$current['telegram_admin_ids']  = sanitize_text_field( (string) ( $data['telegram_admin_ids'] ?? $current['telegram_admin_ids'] ) );

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
}
