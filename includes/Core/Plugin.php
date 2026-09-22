<?php
/**
 * Main plugin class.
 *
 * @package StoreLink
 */

namespace StoreLink\Core;

use StoreLink\Bot\OrderNotifier;
use StoreLink\Admin\Menu;
use StoreLink\Admin\SettingsPage;
use StoreLink\Bale\BaleGateway;
use StoreLink\Messengers\GatewayRegistry;
use StoreLink\Publishing\ProductChangeHooks;
use StoreLink\REST\RestBootstrap;
use StoreLink\Telegram\TelegramGateway;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton plugin bootstrap.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private Loader $loader;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->loader = new Loader();
		$this->define_hooks();
	}

	private function define_hooks(): void {
		$i18n = new I18n();
		$this->loader->add_action( 'init', $i18n, 'load_textdomain', 1 );

		$this->loader->add_action( 'before_woocommerce_init', $this, 'declare_compatibility' );
		$this->loader->add_action( 'plugins_loaded', $this, 'register_gateways' );

		if ( is_admin() ) {
			$menu     = new Menu();
			$settings = new SettingsPage();
			$this->loader->add_action( 'admin_menu', $menu, 'register_menu' );
			$this->loader->add_action( 'admin_post_storelink_save_settings', $settings, 'save' );
			$this->loader->add_action( 'admin_post_storelink_connect_webhook', $settings, 'connect_webhook' );
		}

		$rest = new RestBootstrap();
		$this->loader->add_action( 'rest_api_init', $rest, 'register_routes' );

		$hooks = new ProductChangeHooks();
		$this->loader->add_action( 'woocommerce_update_product', $hooks, 'on_product_updated', 20, 1 );
		$this->loader->add_action( 'woocommerce_product_set_stock', $hooks, 'on_stock_changed', 20, 1 );
		$this->loader->add_action( 'woocommerce_variation_set_stock', $hooks, 'on_stock_changed', 20, 1 );

		$notifier = new OrderNotifier();
		$this->loader->add_action( 'woocommerce_new_order', $notifier, 'on_new_order', 40, 1 );
		$this->loader->add_action( 'woocommerce_checkout_order_processed', $notifier, 'on_new_order', 20, 1 );
		$this->loader->add_action( 'woocommerce_store_api_checkout_order_processed', $notifier, 'on_new_order', 20, 1 );

		$this->loader->add_action( 'init', $this, 'maybe_migrate' );
	}

	public function register_gateways(): void {
		$registry = GatewayRegistry::instance();
		$registry->register( new TelegramGateway() );
		$registry->register( new BaleGateway() );
		do_action( 'storelink_register_gateways', $registry );
	}

	public function maybe_migrate(): void {
		\StoreLink\Database\Migrator::migrate();
	}

	public function declare_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				STORELINK_PLUGIN_FILE,
				true
			);
		}
	}

	public function run(): void {
		$this->loader->run();
	}

	public static function capability(): string {
		return apply_filters( 'storelink_capability', 'manage_woocommerce' );
	}
}
