<?php
/**
 * Main plugin class.
 *
 * @package PricePilot
 */

namespace PricePilot\Core;

use PricePilot\Admin\Assets;
use PricePilot\Admin\Menu;
use PricePilot\REST\RestBootstrap;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton plugin bootstrap.
 */
final class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->loader = new Loader();
		$this->define_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function define_hooks(): void {
		Locale::init();

		$i18n = new I18n();
		$this->loader->add_action( 'init', $i18n, 'load_textdomain', 1 );

		$this->loader->add_action(
			'before_woocommerce_init',
			$this,
			'declare_compatibility'
		);

		if ( is_admin() ) {
			$menu   = new Menu();
			$assets = new Assets();

			$this->loader->add_action( 'admin_menu', $menu, 'register_menu' );
			$this->loader->add_action( 'admin_enqueue_scripts', $assets, 'enqueue' );
		}

		$rest = new RestBootstrap();
		$this->loader->add_action( 'rest_api_init', $rest, 'register_routes' );

		$this->loader->add_action( 'init', $this, 'maybe_migrate' );
	}

	/**
	 * Run database migrations if needed.
	 *
	 * @return void
	 */
	public function maybe_migrate(): void {
		\PricePilot\Database\Migrator::migrate();
	}

	/**
	 * Declare WooCommerce feature compatibility.
	 *
	 * @return void
	 */
	public function declare_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				PRICEPILOT_PLUGIN_FILE,
				true
			);
		}
	}

	/**
	 * Run the plugin.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * Required capability for plugin actions.
	 *
	 * @return string
	 */
	public static function capability(): string {
		return apply_filters( 'pricepilot_capability', 'manage_woocommerce' );
	}
}
