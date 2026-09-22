<?php
/**
 * Environment requirements check.
 *
 * @package StoreLink
 */

namespace StoreLink\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Validates runtime requirements before loading the plugin.
 */
class Requirements {

	public const MIN_PHP = '8.3';
	public const MIN_WC  = '8.0';

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
								__( 'StoreLink requires PHP %s or higher.', 'storelink' ),
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

	public static function check_woocommerce(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'StoreLink requires WooCommerce to be installed and active.', 'storelink' )
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
								__( 'StoreLink requires WooCommerce %s or higher.', 'storelink' ),
								self::MIN_WC
							)
						)
					);
				}
			);
		}
	}

	public static function is_woocommerce_ready(): bool {
		return class_exists( 'WooCommerce' )
			&& defined( 'WC_VERSION' )
			&& version_compare( WC_VERSION, self::MIN_WC, '>=' );
	}
}
