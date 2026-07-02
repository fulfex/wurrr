<?php
/**
 * Open Exchange Rates provider.
 *
 * @see https://openexchangerates.org/api/docs
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

class Fulfex_Provider_Openexchangerates implements Fulfex_Provider {

	const API_URL = 'https://openexchangerates.org/api/latest.json';

	public function fetch_rates( string $base_currency ): array {
		$settings = $this->get_credentials();
		$app_id   = $settings['app_id'] ?? '';

		if ( empty( $app_id ) ) {
			return array();
		}

		$url = add_query_arg(
			array( 'app_id' => $app_id ),
			self::API_URL
		);

		$response = wp_remote_get( esc_url_raw( $url ), array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['rates'] ) ) {
			return array();
		}

		$rates = $data['rates'];

		if ( 'USD' !== strtoupper( $base_currency ) && isset( $rates[ strtoupper( $base_currency ) ] ) ) {
			$base_rate = $rates[ strtoupper( $base_currency ) ];
			foreach ( $rates as $code => $rate ) {
				$rates[ $code ] = $rate / $base_rate;
			}
		}

		$timestamp = isset( $data['timestamp'] ) ? (int) $data['timestamp'] : 0;

		return array(
			'base_code'              => strtoupper( $base_currency ),
			'conversion_rates'       => $rates,
			'time_next_update_unix'  => $timestamp,
			'provider'               => $this->get_id(),
		);
	}

	public function get_id(): string {
		return 'openexchangerates';
	}

	public function get_name(): string {
		return __( 'Open Exchange Rates', 'wurrr' );
	}

	public function get_settings_fields(): array {
		return array(
			array(
				'id'          => 'app_id',
				'label'       => __( 'App ID', 'wurrr' ),
				'type'        => 'password',
				'description' => __( 'Enter your Open Exchange Rates App ID.', 'wurrr' ),
			),
		);
	}

	public function validate_credentials( array $settings ) {
		$app_id = $settings['app_id'] ?? '';

		if ( empty( $app_id ) ) {
			return new WP_Error( 'missing_app_id', __( 'App ID is required.', 'wurrr' ) );
		}

		$url = add_query_arg(
			array( 'app_id' => $app_id ),
			self::API_URL
		);

		$response = wp_remote_get( esc_url_raw( $url ), array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'connection_error', $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['rates'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Could not validate App ID.', 'wurrr' ) );
		}

		return true;
	}

	private function get_credentials(): array {
		$all_settings = get_option( 'wp_exchange_providers_settings', array() );
		return isset( $all_settings[ $this->get_id() ] ) ? $all_settings[ $this->get_id() ] : array();
	}
}
