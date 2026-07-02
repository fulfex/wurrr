<?php
/**
 * Main plugin class for WP Currency Exchange.
 *
 * Wires together all components and handles lifecycle events.
 *
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WP_Exchange_Plugin
 */
class WP_Exchange_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WP_Exchange_Plugin|null
	 */
	private static ?WP_Exchange_Plugin $instance = null;

	/**
	 * API handler.
	 *
	 * @var WP_Exchange_API
	 */
	public WP_Exchange_API $api;

	/**
	 * Cache handler.
	 *
	 * @var WP_Exchange_Cache
	 */
	public WP_Exchange_Cache $cache;

	/**
	 * Currency utilities.
	 *
	 * @var WP_Exchange_Currency
	 */
	public WP_Exchange_Currency $currency;

	/**
	 * Admin handler.
	 *
	 * @var WP_Exchange_Admin
	 */
	public WP_Exchange_Admin $admin;

	/**
	 * Frontend handler.
	 *
	 * @var WP_Exchange_Frontend
	 */
	public WP_Exchange_Frontend $frontend;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return WP_Exchange_Plugin
	 */
	public static function get_instance(): WP_Exchange_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->register_providers();

		$this->cache    = new WP_Exchange_Cache();
		$this->api      = new WP_Exchange_API( $this->cache );
		$this->currency = new WP_Exchange_Currency( $this->api, $this->cache );
		$this->admin    = new WP_Exchange_Admin();
		$this->frontend = new WP_Exchange_Frontend( $this->currency );
	}

	/**
	 * Register built-in providers via the filter.
	 *
	 * @return void
	 */
	private function register_providers(): void {
		$provider_files = array(
			'exchangerate-api'     => WP_EXCHANGE_PLUGIN_DIR . 'includes/providers/class-wp-exchange-provider-exchangerate-api.php',
			'exchangeratesapi'     => WP_EXCHANGE_PLUGIN_DIR . 'includes/providers/class-wp-exchange-provider-exchangeratesapi.php',
			'openexchangerates'    => WP_EXCHANGE_PLUGIN_DIR . 'includes/providers/class-wp-exchange-provider-openexchangerates.php',
			'frankfurter'          => WP_EXCHANGE_PLUGIN_DIR . 'includes/providers/class-wp-exchange-provider-frankfurter.php',
		);

		foreach ( $provider_files as $id => $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		add_filter(
			'wp_exchange_providers',
			function ( array $providers ): array {
				$classes = array(
					'WP_Exchange_Provider_Exchangerate_Api',
					'WP_Exchange_Provider_Exchangeratesapi',
					'WP_Exchange_Provider_Openexchangerates',
					'WP_Exchange_Provider_Frankfurter',
				);

				foreach ( $classes as $class ) {
					if ( class_exists( $class ) ) {
						$providers[] = new $class();
					}
				}

				return $providers;
			}
		);
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( WP_EXCHANGE_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'WP Currency Exchange requires WooCommerce to be installed and activated.', 'wp-exchange' )
			);
		}

		add_option( 'wp_exchange_base_currency', 'USD' );
		add_option( 'wp_exchange_cache_duration', 24 );
		add_option( 'wp_exchange_enable_ip_detection', 'yes' );
		add_option( 'wp_exchange_display_style', 'inline' );
		add_option( 'wp_exchange_switcher_position', 'shortcode' );
		add_option( 'wp_exchange_enable_round_robin', 'no' );
		add_option( 'wp_exchange_providers_settings', array() );
		add_option( 'wp_exchange_provider_order', array() );
		add_option( 'wp_exchange_rr_index', 0 );
	}

	/**
	 * Plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$cache = new WP_Exchange_Cache();
		$cache->clear_rates();
	}
}
