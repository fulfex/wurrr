<?php
/**
 * Plugin Name: Wurrr 🐱 - Free Forever Currency Exchange for Storefront
 * Plugin URI:  https://wordpress.org/plugins/wurrr/
 * Description: 🐱 Purrfectly convert WooCommerce store prices to your customer's local currency. Free forever with multi-provider support and round-robin failover.
 * Version:     1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      7.0
 * WC requires at least: 6.0
 * WC tested up to:      8.5
 * Author:       Fulfex
 * Author URI:   https://fulfex.com
 * License:      GPL v3 or later
 * License URI:  https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:  wurrr
 * Domain Path:  /languages
 *
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

define( 'WURRR_VERSION', '1.0.0' );
define( 'WURRR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WURRR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WURRR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WURRR_CACHE_PREFIX', 'wp_exchange_rates_' );
define( 'WURRR_STALE_PREFIX', 'wp_exchange_stale_rates_' );
define( 'WURRR_SESSION_KEY', 'wp_exchange_user_currency' );

require_once WURRR_PLUGIN_DIR . 'includes/interface-fulfex-provider.php';
require_once WURRR_PLUGIN_DIR . 'includes/class-fulfex-cache.php';
require_once WURRR_PLUGIN_DIR . 'includes/class-fulfex-currency.php';
require_once WURRR_PLUGIN_DIR . 'includes/class-fulfex-api.php';
require_once WURRR_PLUGIN_DIR . 'includes/class-fulfex-geolocation.php';
require_once WURRR_PLUGIN_DIR . 'includes/class-fulfex-frontend.php';
require_once WURRR_PLUGIN_DIR . 'includes/class-fulfex-admin.php';
require_once WURRR_PLUGIN_DIR . 'includes/class-fulfex-plugin.php';

register_activation_hook(
	__FILE__,
	array( 'Fulfex_Plugin', 'activate' )
);

register_deactivation_hook(
	__FILE__,
	array( 'Fulfex_Plugin', 'deactivate' )
);

add_action(
	'plugins_loaded',
	array( 'Fulfex_Plugin', 'get_instance' )
);

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'wurrr', false, dirname( WURRR_PLUGIN_BASENAME ) . '/languages' );
	}
);
