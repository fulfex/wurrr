<?php
/**
 * Plugin Name: WP Currency Exchange
 * Plugin URI:  https://wordpress.org/plugins/wp-exchange/
 * Description: Automatically convert WooCommerce store prices to your customer's local currency. Supports multiple exchange rate providers with round-robin failover.
 * Version:     1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      6.4
 * WC requires at least: 6.0
 * WC tested up to:      8.5
 * Author:       Your Name
 * Author URI:   https://example.com
 * License:      GPL v3 or later
 * License URI:  https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:  wp-exchange
 * Domain Path:  /languages
 *
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declare compatibility with WooCommerce HPOS (High-Performance Order Storage).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

define( 'WP_EXCHANGE_VERSION', '1.0.0' );
define( 'WP_EXCHANGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_EXCHANGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_EXCHANGE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WP_EXCHANGE_CACHE_PREFIX', 'wp_exchange_rates_' );
define( 'WP_EXCHANGE_STALE_PREFIX', 'wp_exchange_stale_rates_' );
define( 'WP_EXCHANGE_SESSION_KEY', 'wp_exchange_user_currency' );

require_once WP_EXCHANGE_PLUGIN_DIR . 'includes/interface-wp-exchange-provider.php';
require_once WP_EXCHANGE_PLUGIN_DIR . 'includes/class-wp-exchange-cache.php';
require_once WP_EXCHANGE_PLUGIN_DIR . 'includes/class-wp-exchange-currency.php';
require_once WP_EXCHANGE_PLUGIN_DIR . 'includes/class-wp-exchange-api.php';
require_once WP_EXCHANGE_PLUGIN_DIR . 'includes/class-wp-exchange-geolocation.php';
require_once WP_EXCHANGE_PLUGIN_DIR . 'includes/class-wp-exchange-frontend.php';
require_once WP_EXCHANGE_PLUGIN_DIR . 'includes/class-wp-exchange-admin.php';
require_once WP_EXCHANGE_PLUGIN_DIR . 'includes/class-wp-exchange-plugin.php';

/**
 * Activation hook.
 */
register_activation_hook(
	__FILE__,
	array( 'WP_Exchange_Plugin', 'activate' )
);

/**
 * Deactivation hook.
 */
register_deactivation_hook(
	__FILE__,
	array( 'WP_Exchange_Plugin', 'deactivate' )
);

/**
 * Bootstrap the plugin.
 */
add_action(
	'plugins_loaded',
	array( 'WP_Exchange_Plugin', 'get_instance' )
);
