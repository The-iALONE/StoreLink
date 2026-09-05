<?php
/**
 * Environment requirements check.
 *
 * @package PricePilot
 */

namespace PricePilot\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Validates runtime requirements before loading the plugin.
 */
class Requirements {

	/**
	 * Minimum PHP version.
	 */
	public const MIN_PHP = '8.3';

	/**
	 * Minimum WooCommerce version.
	 */
	public const MIN_WC = '8.0';

	/**
	 * Check all requirements.
	 *
	 * @return bool
	 */
	public static function check(): bool {
		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %s: required PHP version */
								__( 'PricePilot requires PHP %s or higher.', 'pricepilot' ),
								self::MIN_PHP
							)
						)
					);
				}
			);
			return false;
		}

		add_action( 'plugins_loaded', array( self::class, 'check_woocommerce' ), 20 );

		return true;
	}

	/**
	 * Verify WooCommerce is active and meets version requirement.
	 *
	 * @return void
	 */
	public static function check_woocommerce(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'PricePilot requires WooCommerce to be installed and active.', 'pricepilot' )
					);
				}
			);
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MIN_WC, '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %s: required WooCommerce version */
								__( 'PricePilot requires WooCommerce %s or higher.', 'pricepilot' ),
								self::MIN_WC
							)
						)
					);
				}
			);
		}
	}

	/**
	 * Whether WooCommerce is ready for plugin features.
	 *
	 * @return bool
	 */
	public static function is_woocommerce_ready(): bool {
		return class_exists( 'WooCommerce' )
			&& defined( 'WC_VERSION' )
			&& version_compare( WC_VERSION, self::MIN_WC, '>=' );
	}
}
