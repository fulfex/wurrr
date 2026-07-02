<?php
/**
 * Geolocation for Wurrr Currency Exchange.
 *
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

class Fulfex_Geolocation {

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

	public static function country_to_currency( string $country_code ): string|false {
		$mapping = array(
			'US' => 'USD', 'GB' => 'GBP',
			'AT' => 'EUR', 'BE' => 'EUR', 'FI' => 'EUR', 'FR' => 'EUR',
			'DE' => 'EUR', 'GR' => 'EUR', 'IE' => 'EUR', 'IT' => 'EUR',
			'NL' => 'EUR', 'PT' => 'EUR', 'ES' => 'EUR',
			'JP' => 'JPY', 'CN' => 'CNY', 'KR' => 'KRW', 'HK' => 'HKD',
			'SG' => 'SGD', 'MY' => 'MYR', 'ID' => 'IDR', 'PH' => 'PHP',
			'TH' => 'THB', 'VN' => 'VND', 'IN' => 'INR', 'BR' => 'BRL',
			'MX' => 'MXN', 'CA' => 'CAD', 'AU' => 'AUD', 'CH' => 'CHF',
			'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK', 'NZ' => 'NZD',
			'ZA' => 'ZAR', 'RU' => 'RUB', 'TR' => 'TRY', 'SA' => 'SAR',
			'AE' => 'AED', 'NG' => 'NGN', 'EG' => 'EGP', 'AR' => 'ARS',
			'CL' => 'CLP', 'CO' => 'COP', 'PL' => 'PLN', 'CZ' => 'CZK',
			'HU' => 'HUF', 'IL' => 'ILS',
		);

		$code = strtoupper( $country_code );
		return $mapping[ $code ] ?? false;
	}
}
