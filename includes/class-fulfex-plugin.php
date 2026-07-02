<?php
/**
 * Main plugin class for Wurrr Currency Exchange.
 *
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

class Fulfex_Plugin {

	private static ?Fulfex_Plugin $instance = null;

	public Fulfex_API $api;
	public Fulfex_Cache $cache;
	public Fulfex_Currency $currency;
	public Fulfex_Admin $admin;
	public Fulfex_Frontend $frontend;

	public static function get_instance(): Fulfex_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->register_providers();

		$this->cache    = new Fulfex_Cache();
		$this->api      = new Fulfex_API( $this->cache );
		$this->currency = new Fulfex_Currency( $this->api, $this->cache );
		$this->admin    = new Fulfex_Admin();
		$this->frontend = new Fulfex_Frontend( $this->currency );
	}

	private function register_providers(): void {
		$provider_files = array(
			'exchangerate-api'  => WURRR_PLUGIN_DIR . 'includes/providers/class-fulfex-provider-exchangerate-api.php',
			'exchangeratesapi'  => WURRR_PLUGIN_DIR . 'includes/providers/class-fulfex-provider-exchangeratesapi.php',
			'openexchangerates' => WURRR_PLUGIN_DIR . 'includes/providers/class-fulfex-provider-openexchangerates.php',
			'frankfurter'       => WURRR_PLUGIN_DIR . 'includes/providers/class-fulfex-provider-frankfurter.php',
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
					'Fulfex_Provider_Exchangerate_Api',
					'Fulfex_Provider_Exchangeratesapi',
					'Fulfex_Provider_Openexchangerates',
					'Fulfex_Provider_Frankfurter',
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

	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( WURRR_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Wurrr requires WooCommerce to be installed and activated.', 'wurrr' )
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

	public static function deactivate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$cache = new Fulfex_Cache();
		$cache->clear_rates();
	}
}
