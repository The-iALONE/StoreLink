<?php
/**
 * Internationalization.
 *
 * @package PricePilot
 */

namespace PricePilot\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Loads plugin text domain.
 */
class I18n {

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		unload_textdomain( 'pricepilot' );

		$locale = Locale::get_user_locale();

		if ( 'en_US' === $locale ) {
			return;
		}

		$mofile = Locale::get_mo_file( $locale );

		if ( file_exists( $mofile ) ) {
			load_textdomain( 'pricepilot', $mofile );
		}
	}
}
