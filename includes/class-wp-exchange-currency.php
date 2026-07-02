<?php
/**
 * Currency utilities for WP Currency Exchange.
 *
 * Handles conversion, formatting, symbol/name lookup,
 * and user currency resolution (IP or session).
 *
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WP_Exchange_Currency
 */
class WP_Exchange_Currency {

	/**
	 * API handler instance.
	 *
	 * @var WP_Exchange_API
	 */
	private $api;

	/**
	 * Cache handler instance.
	 *
	 * @var WP_Exchange_Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param WP_Exchange_API   $api   API handler.
	 * @param WP_Exchange_Cache $cache Cache handler.
	 */
	public function __construct( WP_Exchange_API $api, WP_Exchange_Cache $cache ) {
		$this->api   = $api;
		$this->cache = $cache;
	}

	/**
	 * Resolve the user's preferred currency.
	 *
	 * Priority: session/cookie > IP detection > store default.
	 *
	 * @return string ISO 4217 currency code.
	 */
	public function get_user_currency(): string {
		$session_currency = $this->get_session_currency();
		if ( $session_currency ) {
			return $session_currency;
		}

		$geolocation = WP_Exchange_Geolocation::detect();
		if ( $geolocation ) {
			return $geolocation;
		}

		return $this->get_base_currency();
	}

	/**
	 * Convert a price from one currency to another.
	 *
	 * @param  float  $amount Price amount.
	 * @param  string $from   Source currency code.
	 * @param  string $to     Target currency code.
	 * @return float Converted amount.
	 */
	public function convert_price( float $amount, string $from, string $to ): float {
		if ( strtoupper( $from ) === strtoupper( $to ) ) {
			return $amount;
		}

		$cache = $this->cache;
		$api   = $this->api;

		$provider_id = '';
		$rates       = array();

		$configured_providers = $api->get_configured_providers();

		if ( empty( $configured_providers ) ) {
			return $amount;
		}

		$active_provider = $api->get_active_provider();
		if ( $active_provider ) {
			$provider_id = $active_provider->get_id();
			$cached      = $cache->get_rates( $provider_id, $from );

			if ( false !== $cached && ! empty( $cached['conversion_rates'] ) ) {
				$rates = $cached['conversion_rates'];
			} else {
				$fetched = $api->fetch_rates( $from );
				if ( ! empty( $fetched['conversion_rates'] ) ) {
					$rates      = $fetched['conversion_rates'];
					$provider   = $fetched['provider'] ?? $provider_id;
					$ttl        = $this->calculate_ttl( $fetched );
					$cache->set_rates( $provider, $from, $fetched, $ttl );
					$provider_id = $provider;
				}
			}
		}

		if ( empty( $rates ) ) {
			return $amount;
		}

		$to_upper = strtoupper( $to );
		if ( ! isset( $rates[ $to_upper ] ) ) {
			return $amount;
		}

		return $amount * (float) $rates[ $to_upper ];
	}

	/**
	 * Format a price with the appropriate currency symbol.
	 *
	 * @param  float  $amount        Price amount.
	 * @param  string $currency_code ISO 4217 code.
	 * @return string Formatted price string.
	 */
	public function format_price( float $amount, string $currency_code ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount, array( 'currency' => strtoupper( $currency_code ) ) );
		}

		$symbol = $this->get_currency_symbol( $currency_code );
		return $symbol . number_format( $amount, 2 );
	}

	/**
	 * Get the currency symbol for a given code.
	 *
	 * @param  string $code ISO 4217 currency code.
	 * @return string Symbol.
	 */
	public function get_currency_symbol( string $code ): string {
		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'JPY' => '¥',
			'CNY' => '¥',
			'KRW' => '₩',
			'HKD' => 'HK$',
			'SGD' => 'S$',
			'MYR' => 'RM',
			'IDR' => 'Rp',
			'PHP' => '₱',
			'THB' => '฿',
			'VND' => '₫',
			'INR' => '₹',
			'BRL' => 'R$',
			'MXN' => 'Mex$',
			'CAD' => 'C$',
			'AUD' => 'A$',
			'NZD' => 'NZ$',
			'CHF' => 'CHF',
			'SEK' => 'kr',
			'NOK' => 'kr',
			'DKK' => 'kr',
			'RUB' => '₽',
			'TRY' => '₺',
			'ZAR' => 'R',
			'NGN' => '₦',
			'EGP' => 'E£',
			'ARS' => 'ARS$',
			'CLP' => 'CLP$',
			'COP' => 'COP$',
			'SAR' => '﷼',
			'AE'  => 'د.إ',
			'PLN' => 'zł',
			'CZK' => 'Kč',
			'HUF' => 'Ft',
			'ILS' => '₪',
		);

		return $symbols[ strtoupper( $code ) ] ?? '$';
	}

	/**
	 * Get the human-readable name for a currency code.
	 *
	 * @param  string $code ISO 4217 code.
	 * @return string Name.
	 */
	public function get_currency_name( string $code ): string {
		$names = array(
			'USD' => __( 'US Dollar', 'wp-exchange' ),
			'EUR' => __( 'Euro', 'wp-exchange' ),
			'GBP' => __( 'British Pound', 'wp-exchange' ),
			'JPY' => __( 'Japanese Yen', 'wp-exchange' ),
			'CNY' => __( 'Chinese Yuan', 'wp-exchange' ),
			'KRW' => __( 'South Korean Won', 'wp-exchange' ),
			'HKD' => __( 'Hong Kong Dollar', 'wp-exchange' ),
			'SGD' => __( 'Singapore Dollar', 'wp-exchange' ),
			'MYR' => __( 'Malaysian Ringgit', 'wp-exchange' ),
			'IDR' => __( 'Indonesian Rupiah', 'wp-exchange' ),
			'PHP' => __( 'Philippine Peso', 'wp-exchange' ),
			'THB' => __( 'Thai Baht', 'wp-exchange' ),
			'VND' => __( 'Vietnamese Dong', 'wp-exchange' ),
			'INR' => __( 'Indian Rupee', 'wp-exchange' ),
			'BRL' => __( 'Brazilian Real', 'wp-exchange' ),
			'MXN' => __( 'Mexican Peso', 'wp-exchange' ),
			'CAD' => __( 'Canadian Dollar', 'wp-exchange' ),
			'AUD' => __( 'Australian Dollar', 'wp-exchange' ),
			'NZD' => __( 'New Zealand Dollar', 'wp-exchange' ),
			'CHF' => __( 'Swiss Franc', 'wp-exchange' ),
			'SEK' => __( 'Swedish Krona', 'wp-exchange' ),
			'NOK' => __( 'Norwegian Krone', 'wp-exchange' ),
			'DKK' => __( 'Danish Krone', 'wp-exchange' ),
			'RUB' => __( 'Russian Ruble', 'wp-exchange' ),
			'TRY' => __( 'Turkish Lira', 'wp-exchange' ),
			'ZAR' => __( 'South African Rand', 'wp-exchange' ),
			'NGN' => __( 'Nigerian Naira', 'wp-exchange' ),
			'EGP' => __( 'Egyptian Pound', 'wp-exchange' ),
			'ARS' => __( 'Argentine Peso', 'wp-exchange' ),
			'CLP' => __( 'Chilean Peso', 'wp-exchange' ),
			'COP' => __( 'Colombian Peso', 'wp-exchange' ),
			'SAR' => __( 'Saudi Riyal', 'wp-exchange' ),
			'AED' => __( 'UAE Dirham', 'wp-exchange' ),
			'PLN' => __( 'Polish Zloty', 'wp-exchange' ),
			'CZK' => __( 'Czech Koruna', 'wp-exchange' ),
			'HUF' => __( 'Hungarian Forint', 'wp-exchange' ),
			'ILS' => __( 'Israeli Shekel', 'wp-exchange' ),
		);

		return $names[ strtoupper( $code ) ] ?? strtoupper( $code );
	}

	/**
	 * Get the store's base currency from settings.
	 *
	 * @return string
	 */
	public function get_base_currency(): string {
		return get_option( 'wp_exchange_base_currency', 'USD' );
	}

	/**
	 * Get the user's selected currency from session/cookie.
	 *
	 * @return string|false
	 */
	public function get_session_currency(): string|false {
		if ( isset( $_COOKIE[ WP_EXCHANGE_SESSION_KEY ] ) ) {
			$currency = sanitize_text_field( wp_unslash( $_COOKIE[ WP_EXCHANGE_SESSION_KEY ] ) );
			if ( preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				return $currency;
			}
		}
		return false;
	}

	/**
	 * Calculate TTL for cached rates.
	 *
	 * @param  array $data Fetched rates data.
	 * @return int TTL in seconds.
	 */
	private function calculate_ttl( array $data ): int {
		$default = (int) get_option( 'wp_exchange_cache_duration', 24 ) * HOUR_IN_SECONDS;

		if ( ! empty( $data['time_next_update_unix'] ) ) {
			$api_ttl = (int) $data['time_next_update_unix'] - time();
			if ( $api_ttl > 0 ) {
				return min( $default, $api_ttl );
			}
		}

		return $default;
	}
}
