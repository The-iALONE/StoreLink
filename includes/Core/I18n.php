<?php
/**
 * Internationalization.
 *
 * @package StoreLink
 */

namespace StoreLink\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Loads plugin text domain.
 */
class I18n {

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'storelink',
			false,
			dirname( plugin_basename( STORELINK_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
