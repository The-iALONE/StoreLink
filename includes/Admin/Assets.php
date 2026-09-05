<?php
/**
 * Admin asset loader.
 *
 * @package PricePilot
 */

namespace PricePilot\Admin;

use PricePilot\Core\Currency;
use PricePilot\Core\Locale;
use PricePilot\Core\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues admin scripts and styles.
 */
class Assets {

	/**
	 * Enqueue assets on PricePilot admin page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		if ( 'woocommerce_page_pricepilot' !== $hook ) {
			return;
		}

		$asset_file = PRICEPILOT_PLUGIN_DIR . 'admin/assets/index.asset.php';
		$script_url = PRICEPILOT_PLUGIN_DIR . 'admin/assets/index.js';

		if ( ! file_exists( $asset_file ) || ! file_exists( $script_url ) ) {
			wp_enqueue_style(
				'pricepilot-admin-fallback',
				PRICEPILOT_PLUGIN_URL . 'admin/assets/fallback.css',
				array(),
				PRICEPILOT_VERSION
			);
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_style(
			'pricepilot-admin',
			PRICEPILOT_PLUGIN_URL . 'admin/assets/style-index.css',
			array( 'wp-components' ),
			$asset['version'] ?? PRICEPILOT_VERSION
		);

		wp_style_add_data( 'pricepilot-admin', 'rtl', 'replace' );

		wp_enqueue_script(
			'pricepilot-admin',
			PRICEPILOT_PLUGIN_URL . 'admin/assets/index.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? PRICEPILOT_VERSION,
			true
		);

		$plugin_locale  = Locale::get_user_locale();
		$currency_context = Currency::get_context();

		wp_localize_script(
			'pricepilot-admin',
			'pricePilotData',
			array(
				'apiUrl'        => trailingslashit( rest_url( 'pricepilot/v1' ) ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'locale'        => $plugin_locale,
				'isRtl'         => Locale::is_rtl( $plugin_locale ),
				'locales'       => Locale::get_available(),
				'defaultLocale' => Locale::DEFAULT_LOCALE,
				'currency'      => $currency_context['label'],
				'currencyLabel' => $currency_context['label'],
				'currencyCode'  => $currency_context['code'],
				'currencyUnit'  => $currency_context['unit'],
				'storageFactor' => $currency_context['storage_factor'],
				'pluginUrl'     => PRICEPILOT_PLUGIN_URL,
				'version'       => PRICEPILOT_VERSION,
			)
		);

		wp_set_script_translations( 'pricepilot-admin', 'pricepilot', PRICEPILOT_PLUGIN_DIR . 'languages' );

		$translations = Locale::get_script_translation_json( $plugin_locale );

		if ( is_string( $translations ) ) {
			wp_add_inline_script(
				'pricepilot-admin',
				'( function( domain, translations ) {
					var localeData = translations.locale_data[ domain ] || translations.locale_data.messages;
					localeData[""].domain = domain;
					wp.i18n.setLocaleData( localeData, domain );
				} )( ' . wp_json_encode( 'pricepilot' ) . ', ' . $translations . ' );',
				'before'
			);
		}
	}
}
