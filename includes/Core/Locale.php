<?php
/**
 * Plugin locale management.
 *
 * @package PricePilot
 */

namespace PricePilot\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles fa_IR (default) and en_US plugin languages.
 */
class Locale {

	public const DEFAULT_LOCALE = 'fa_IR';

	public const USER_META_KEY = 'pricepilot_admin_locale';

	/**
	 * Register locale filters.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'plugin_locale', array( self::class, 'filter_plugin_locale' ), 10, 2 );
		add_filter( 'pre_load_script_translations', array( self::class, 'filter_pre_load_script_translations' ), 10, 4 );
		add_filter( 'load_script_translation_file', array( self::class, 'filter_script_translation_file' ), 10, 3 );
	}

	/**
	 * Relative script path used by WordPress for translation file hashes.
	 */
	public const SCRIPT_RELATIVE_PATH = 'admin/assets/index.js';

	/**
	 * Inject admin script translations for the user's PricePilot locale.
	 *
	 * WordPress resolves script translations with determine_locale(), which ignores
	 * the plugin locale preference. Short-circuit here when possible.
	 *
	 * @param string|false|null $translations Existing translations.
	 * @param string|false      $file         Candidate translation file.
	 * @param string            $handle       Script handle.
	 * @param string            $domain       Text domain.
	 * @return string|false|null
	 */
	public static function filter_pre_load_script_translations( $translations, $file, string $handle, string $domain ) {
		if ( null !== $translations ) {
			return $translations;
		}

		if ( 'pricepilot' !== $domain || 'pricepilot-admin' !== $handle ) {
			return null;
		}

		return self::get_script_translation_json();
	}

	/**
	 * Resolve JSON translation file for the admin script.
	 *
	 * @param string|false $file   Candidate file path.
	 * @param string       $handle Script handle.
	 * @param string       $domain Text domain.
	 * @return string|false
	 */
	public static function filter_script_translation_file( $file, string $handle, string $domain ) {
		if ( 'pricepilot' !== $domain || 'pricepilot-admin' !== $handle ) {
			return $file;
		}

		$locale = self::get_user_locale();

		if ( 'en_US' === $locale ) {
			return false;
		}

		$json_file = self::get_script_translation_file( $locale );

		return file_exists( $json_file ) ? $json_file : $file;
	}

	/**
	 * Get script translation JSON path for a locale.
	 *
	 * @param string $locale Locale code.
	 * @return string
	 */
	public static function get_script_translation_file( string $locale ): string {
		$hash = md5( self::SCRIPT_RELATIVE_PATH );

		return PRICEPILOT_PLUGIN_DIR . 'languages/pricepilot-' . $locale . '-' . $hash . '.json';
	}

	/**
	 * Read JSON translation payload for the current user locale.
	 *
	 * @param string|null $locale Optional locale override.
	 * @return string|false
	 */
	public static function get_script_translation_json( ?string $locale = null ) {
		$locale = $locale ?? self::get_user_locale();

		if ( 'en_US' === $locale ) {
			return false;
		}

		$json_file = self::get_script_translation_file( $locale );

		if ( ! is_readable( $json_file ) ) {
			return false;
		}

		$json = file_get_contents( $json_file );

		return is_string( $json ) && '' !== $json ? $json : false;
	}

	/**
	 * Get MO file path for a locale.
	 *
	 * @param string $locale Locale code.
	 * @return string
	 */
	public static function get_mo_file( string $locale ): string {
		return PRICEPILOT_PLUGIN_DIR . 'languages/pricepilot-' . $locale . '.mo';
	}

	/**
	 * Supported locales.
	 *
	 * @return array<string, string>
	 */
	public static function get_available(): array {
		return array(
			'fa_IR' => 'فارسی',
			'en_US' => 'English',
		);
	}

	/**
	 * Get current user's PricePilot admin locale.
	 *
	 * @return string
	 */
	public static function get_user_locale(): string {
		if ( ! is_user_logged_in() ) {
			return self::DEFAULT_LOCALE;
		}

		$locale = get_user_meta( get_current_user_id(), self::USER_META_KEY, true );

		if ( is_string( $locale ) && array_key_exists( $locale, self::get_available() ) ) {
			return $locale;
		}

		return self::DEFAULT_LOCALE;
	}

	/**
	 * Save user locale preference.
	 *
	 * @param string $locale Locale code.
	 * @return bool
	 */
	public static function set_user_locale( string $locale ): bool {
		if ( ! array_key_exists( $locale, self::get_available() ) ) {
			return false;
		}

		return (bool) update_user_meta( get_current_user_id(), self::USER_META_KEY, $locale );
	}

	/**
	 * Whether locale is RTL.
	 *
	 * @param string|null $locale Optional locale.
	 * @return bool
	 */
	public static function is_rtl( ?string $locale = null ): bool {
		$locale = $locale ?? self::get_user_locale();

		return 'fa_IR' === $locale;
	}

	/**
	 * Force plugin textdomain to use PricePilot locale.
	 *
	 * @param string $locale Current locale.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function filter_plugin_locale( string $locale, string $domain ): string {
		if ( 'pricepilot' !== $domain ) {
			return $locale;
		}

		return self::get_user_locale();
	}
}
