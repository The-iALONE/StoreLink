<?php
/**
 * Main plugin class.
 *
 * @package StoreLink
 */

namespace StoreLink\Core;

use StoreLink\Bot\OrderNotifier;
use StoreLink\Admin\Menu;
use StoreLink\Admin\OrderTrackingMetabox;
use StoreLink\Admin\SettingsPage;
use StoreLink\Bale\BaleGateway;
use StoreLink\Messengers\GatewayRegistry;
use StoreLink\Publishing\ChannelPublishQueue;
use StoreLink\Publishing\ProductChangeHooks;
use StoreLink\REST\RestBootstrap;
use StoreLink\Telegram\TelegramGateway;
use StoreLink\Tracking\IranPost;
use StoreLink\Tracking\ManualCourier;
use StoreLink\Tracking\ProviderRegistry;
use StoreLink\Tracking\Tipax;
use StoreLink\Tracking\TrackingService;

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
		$this->loader->add_action( 'plugins_loaded', $this, 'register_tracking_providers' );

		if ( is_admin() ) {
			$menu     = new Menu();
			$settings = new SettingsPage();
			$tracking = new OrderTrackingMetabox();
			$this->loader->add_action( 'admin_menu', $menu, 'register_menu' );
			$this->loader->add_action( 'admin_enqueue_scripts', $menu, 'enqueue' );
			$this->loader->add_action( 'add_meta_boxes', $tracking, 'register' );
			$this->loader->add_action( 'add_meta_boxes_woocommerce_page_wc-orders', $tracking, 'register' );
			$this->loader->add_action( 'woocommerce_process_shop_order_meta', $tracking, 'save', 20, 1 );
			$this->loader->add_action( 'admin_post_storelink_save_settings', $settings, 'save' );
			$this->loader->add_action( 'admin_post_storelink_connect_webhook', $settings, 'connect_webhook' );
		}

		$rest = new RestBootstrap();
		$this->loader->add_action( 'rest_api_init', $rest, 'register_routes' );

		$hooks = new ProductChangeHooks();
		$this->loader->add_action( 'woocommerce_update_product', $hooks, 'on_product_updated', 20, 1 );
		$this->loader->add_action( 'woocommerce_product_set_stock', $hooks, 'on_stock_changed', 20, 1 );
		$this->loader->add_action( 'woocommerce_variation_set_stock', $hooks, 'on_stock_changed', 20, 1 );
		$queue = new ChannelPublishQueue();
		$this->loader->add_action( ChannelPublishQueue::HOOK, $queue, 'run', 10, 1 );

		$notifier = new OrderNotifier();
		$this->loader->add_action( 'woocommerce_new_order', $notifier, 'on_new_order', 40, 1 );
		$this->loader->add_action( 'woocommerce_checkout_order_processed', $notifier, 'on_new_order', 20, 1 );
		$this->loader->add_action( 'woocommerce_store_api_checkout_order_processed', $notifier, 'on_new_order', 20, 1 );
		$this->loader->add_action( 'woocommerce_order_status_changed', $notifier, 'on_status_changed', 20, 4 );
		$this->loader->add_filter( 'woocommerce_order_is_download_permitted', $this, 'permit_processing_downloads', 10, 2 );
		$this->loader->add_filter( 'woocommerce_is_purchasable', $this, 'allow_zero_price_purchase', 10, 2 );
		$this->loader->add_filter( 'woocommerce_payment_complete_order_status', $this, 'virtual_payment_complete_status', 10, 3 );

		$this->loader->add_action( 'init', $this, 'maybe_migrate' );
		$this->loader->add_action( 'init', $this, 'schedule_tracking_cron' );
		$this->loader->add_action( TrackingService::CRON_HOOK, $this, 'refresh_tracking' );
	}

	public function register_gateways(): void {
		$registry = GatewayRegistry::instance();
		$registry->register( new TelegramGateway() );
		$registry->register( new BaleGateway() );
		do_action( 'storelink_register_gateways', $registry );
	}

	public function register_tracking_providers(): void {
		$registry = ProviderRegistry::instance();
		$registry->register( new ManualCourier() );
		$registry->register( new IranPost() );
		$registry->register( new Tipax() );
		do_action( 'storelink_register_tracking_providers', $registry );
	}

	public function schedule_tracking_cron(): void {
		TrackingService::schedule_cron();
	}

	public function refresh_tracking(): void {
		( new TrackingService() )->refresh_due();
	}

	public function maybe_migrate(): void {
		\StoreLink\Database\Migrator::migrate();
	}

	public function allow_zero_price_purchase( $purchasable, $product ): bool {
		if ( $purchasable || ! $product instanceof \WC_Product ) {
			return (bool) $purchasable;
		}

		if ( (float) $product->get_price() > 0 ) {
			return false;
		}

		if ( $product->is_downloadable() || $product->is_virtual() ) {
			return true;
		}

		return 'outofstock' !== $product->get_stock_status();
	}

	public function permit_processing_downloads( $permitted, $order ): bool {
		if ( $permitted || ! $order instanceof \WC_Order ) {
			return (bool) $permitted;
		}

		return $order->has_status( 'processing' ) && $order->has_downloadable_item();
	}

	public function virtual_payment_complete_status( $status, $order_id, $order = null ): string {
		$wc_order = $order instanceof \WC_Order ? $order : wc_get_order( (int) $order_id );
		if ( $wc_order instanceof \WC_Order && \StoreLink\Commerce\OrderService::is_digital_only( $wc_order ) ) {
			return 'processing';
		}

		return (string) $status;
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
