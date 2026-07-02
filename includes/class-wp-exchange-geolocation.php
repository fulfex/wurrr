<?php
/**
 * Geolocation for WP Currency Exchange.
 *
 * Detects visitor country via WooCommerce MaxMind DB,
 * Cloudflare headers, or server variables, then maps
 * to a default currency.
 *
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WP_Exchange_Geolocation
 */
class WP_Exchange_Geolocation {

	/**
	 * Detect the user's currency based on their location.
	 *
	 * Returns an ISO 4217 currency code or false if detection fails.
	 *
	 * @return string|false
	 */
	public static function detect(): string|false {
		if ( 'yes' !== get_option( 'wp_exchange_enable_ip_detection', 'yes' ) ) {
			return false;
		}

		$country_code = self::get_country_code();

		if ( empty( $country_code ) ) {
			return false;
		}

		return self::country_to_currency( $country_code );
	}

	/**
	 * Resolve country code via available methods.
	 *
	 * Priority: WooCommerce Geolocation > Cloudflare > Server GEOIP.
	 *
	 * @return string|false ISO 3166-1 alpha-2 code.
	 */
	private static function get_country_code(): string|false {
		if ( function_exists( 'WC' ) && isset( WC()->customer ) ) {
			$country = WC()->customer->get_shipping_country();
			if ( ! empty( $country ) && 2 === strlen( $country ) ) {
				return strtoupper( $country );
			}
		}

		if ( class_exists( 'WC_Geolocation' ) ) {
			$location = \WC_Geolocation::geolocate_ip( '', true, true );
			if ( ! empty( $location['country'] ) ) {
				return strtoupper( $location['country'] );
			}
		}

		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
		}

		if ( ! empty( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) {
			return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) );
		}

		return false;
	}

	/**
	 * Map ISO 3166-1 alpha-2 country code to a common currency.
	 *
	 * @param  string $country_code Two-letter country code.
	 * @return string|false Currency code or false if unmapped.
	 */
	public static function country_to_currency( string $country_code ): string|false {
		$mapping = array(
			'US' => 'USD',
			'GB' => 'GBP',
			'AT' => 'EUR',
			'BE' => 'EUR',
			'FI' => 'EUR',
			'FR' => 'EUR',
			'DE' => 'EUR',
			'GR' => 'EUR',
			'IE' => 'EUR',
			'IT' => 'EUR',
			'NL' => 'EUR',
			'PT' => 'EUR',
			'ES' => 'EUR',
			'JP' => 'JPY',
			'CN' => 'CNY',
			'KR' => 'KRW',
			'HK' => 'HKD',
			'SG' => 'SGD',
			'MY' => 'MYR',
			'ID' => 'IDR',
			'PH' => 'PHP',
			'TH' => 'THB',
			'VN' => 'VND',
			'IN' => 'INR',
			'BR' => 'BRL',
			'MX' => 'MXN',
			'CA' => 'CAD',
			'AU' => 'AUD',
			'CH' => 'CHF',
			'SE' => 'SEK',
			'NO' => 'NOK',
			'DK' => 'DKK',
			'NZ' => 'NZD',
			'ZA' => 'ZAR',
			'RU' => 'RUB',
			'TR' => 'TRY',
			'SA' => 'SAR',
			'AE' => 'AED',
			'NG' => 'NGN',
			'EG' => 'EGP',
			'AR' => 'ARS',
			'CL' => 'CLP',
			'CO' => 'COP',
			'PL' => 'PLN',
			'CZ' => 'CZK',
			'HU' => 'HUF',
			'IL' => 'ILS',
		);

		$code = strtoupper( $country_code );
		return $mapping[ $code ] ?? false;
	}
}
