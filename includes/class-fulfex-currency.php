<?php
/**
 * Currency utilities for Wurrr Currency Exchange.
 *
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

class Fulfex_Currency {

	private $api;
	private $cache;

	public function __construct( Fulfex_API $api, Fulfex_Cache $cache ) {
		$this->api   = $api;
		$this->cache = $cache;
	}

	public function get_user_currency(): string {
		$session_currency = $this->get_session_currency();
		if ( $session_currency ) {
			return $session_currency;
		}

		$geolocation = Fulfex_Geolocation::detect();
		if ( $geolocation ) {
			return $geolocation;
		}

		return $this->get_base_currency();
	}

	public function convert_price( float $amount, string $from, string $to ): float {
		if ( strtoupper( $from ) === strtoupper( $to ) ) {
			return $amount;
		}

		$configured_providers = $this->api->get_configured_providers();
		if ( empty( $configured_providers ) ) {
			return $amount;
		}

		$fetched = $this->api->fetch_rates( $from );
		$rates   = $fetched['conversion_rates'] ?? array();

		if ( empty( $rates ) ) {
			return $amount;
		}

		$to_upper = strtoupper( $to );
		if ( ! isset( $rates[ $to_upper ] ) ) {
			return $amount;
		}

		return $amount * (float) $rates[ $to_upper ];
	}

	public function format_price( float $amount, string $currency_code ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wc_price( $amount, array( 'currency' => strtoupper( $currency_code ) ) );
		}

		$symbol = $this->get_currency_symbol( $currency_code );
		return $symbol . number_format( $amount, 2 );
	}

	public function get_currency_symbol( string $code ): string {
		$symbols = array(
			'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
			'CNY' => '¥', 'KRW' => '₩', 'HKD' => 'HK$', 'SGD' => 'S$',
			'MYR' => 'RM', 'IDR' => 'Rp', 'PHP' => '₱', 'THB' => '฿',
			'VND' => '₫', 'INR' => '₹', 'BRL' => 'R$', 'MXN' => 'Mex$',
			'CAD' => 'C$', 'AUD' => 'A$', 'NZD' => 'NZ$', 'CHF' => 'CHF',
			'SEK' => 'kr', 'NOK' => 'kr', 'DKK' => 'kr',
			'RUB' => '₽', 'TRY' => '₺', 'ZAR' => 'R', 'NGN' => '₦',
			'EGP' => 'E£', 'ARS' => 'ARS$', 'CLP' => 'CLP$', 'COP' => 'COP$',
			'SAR' => '﷼', 'AE' => 'د.إ', 'PLN' => 'zł', 'CZK' => 'Kč',
			'HUF' => 'Ft', 'ILS' => '₪',
		);

		return $symbols[ strtoupper( $code ) ] ?? '$';
	}

	public function get_currency_name( string $code ): string {
		$names = array(
			'USD' => __( 'US Dollar', 'wurrr' ),
			'EUR' => __( 'Euro', 'wurrr' ),
			'GBP' => __( 'British Pound', 'wurrr' ),
			'JPY' => __( 'Japanese Yen', 'wurrr' ),
			'CNY' => __( 'Chinese Yuan', 'wurrr' ),
			'KRW' => __( 'South Korean Won', 'wurrr' ),
			'HKD' => __( 'Hong Kong Dollar', 'wurrr' ),
			'SGD' => __( 'Singapore Dollar', 'wurrr' ),
			'MYR' => __( 'Malaysian Ringgit', 'wurrr' ),
			'IDR' => __( 'Indonesian Rupiah', 'wurrr' ),
			'PHP' => __( 'Philippine Peso', 'wurrr' ),
			'THB' => __( 'Thai Baht', 'wurrr' ),
			'VND' => __( 'Vietnamese Dong', 'wurrr' ),
			'INR' => __( 'Indian Rupee', 'wurrr' ),
			'BRL' => __( 'Brazilian Real', 'wurrr' ),
			'MXN' => __( 'Mexican Peso', 'wurrr' ),
			'CAD' => __( 'Canadian Dollar', 'wurrr' ),
			'AUD' => __( 'Australian Dollar', 'wurrr' ),
			'NZD' => __( 'New Zealand Dollar', 'wurrr' ),
			'CHF' => __( 'Swiss Franc', 'wurrr' ),
			'SEK' => __( 'Swedish Krona', 'wurrr' ),
			'NOK' => __( 'Norwegian Krone', 'wurrr' ),
			'DKK' => __( 'Danish Krone', 'wurrr' ),
			'RUB' => __( 'Russian Ruble', 'wurrr' ),
			'TRY' => __( 'Turkish Lira', 'wurrr' ),
			'ZAR' => __( 'South African Rand', 'wurrr' ),
			'NGN' => __( 'Nigerian Naira', 'wurrr' ),
			'EGP' => __( 'Egyptian Pound', 'wurrr' ),
			'ARS' => __( 'Argentine Peso', 'wurrr' ),
			'CLP' => __( 'Chilean Peso', 'wurrr' ),
			'COP' => __( 'Colombian Peso', 'wurrr' ),
			'SAR' => __( 'Saudi Riyal', 'wurrr' ),
			'AED' => __( 'UAE Dirham', 'wurrr' ),
			'PLN' => __( 'Polish Zloty', 'wurrr' ),
			'CZK' => __( 'Czech Koruna', 'wurrr' ),
			'HUF' => __( 'Hungarian Forint', 'wurrr' ),
			'ILS' => __( 'Israeli Shekel', 'wurrr' ),
		);

		return $names[ strtoupper( $code ) ] ?? strtoupper( $code );
	}

	public function get_base_currency(): string {
		return get_option( 'wp_exchange_base_currency', 'USD' );
	}

	public function get_session_currency(): string|false {
		if ( isset( $_COOKIE[ WURRR_SESSION_KEY ] ) ) {
			$currency = sanitize_text_field( wp_unslash( $_COOKIE[ WURRR_SESSION_KEY ] ) );
			if ( preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				return $currency;
			}
		}
		return false;
	}
}
