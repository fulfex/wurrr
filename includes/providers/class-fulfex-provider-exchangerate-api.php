<?php
/**
 * ExchangeRate-API provider.
 *
 * @see https://www.exchangerate-api.com/docs/standard-requests
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

class Fulfex_Provider_Exchangerate_Api implements Fulfex_Provider {

	const API_URL = 'https://v6.exchangerate-api.com/v6/%s/latest/%s';

	public function fetch_rates( string $base_currency ): array {
		$settings = $this->get_credentials();
		$api_key  = $settings['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return array();
		}

		$url      = sprintf( self::API_URL, $api_key, strtoupper( $base_currency ) );
		$response = wp_remote_get( esc_url_raw( $url ), array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['result'] ) ) {
			return array();
		}

		if ( 'error' === $data['result'] ) {
			$error_type = isset( $data['error-type'] ) ? $data['error-type'] : 'unknown';
			error_log(
				sprintf(
					/* translators: 1: provider name, 2: provider error code. */
					__( '[Wurrr] %1$s error: %2$s', 'wurrr' ),
					$this->get_name(),
					$error_type
				)
			);
			return array();
		}

		return array(
			'base_code'              => $data['base_code'] ?? strtoupper( $base_currency ),
			'conversion_rates'       => $data['conversion_rates'] ?? array(),
			'time_next_update_unix'  => $data['time_next_update_unix'] ?? 0,
			'provider'               => $this->get_id(),
		);
	}

	public function get_id(): string {
		return 'exchangerate-api';
	}

	public function get_name(): string {
		return __( 'ExchangeRate-API', 'wurrr' );
	}

	public function get_settings_fields(): array {
		return array(
			array(
				'id'          => 'api_key',
				'label'       => __( 'API Key', 'wurrr' ),
				'type'        => 'password',
				'description' => __( 'Enter your ExchangeRate-API key from your dashboard.', 'wurrr' ),
			),
		);
	}

	public function validate_credentials( array $settings ) {
		$api_key = $settings['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', __( 'API key is required.', 'wurrr' ) );
		}

		$url      = sprintf( self::API_URL, $api_key, 'USD' );
		$response = wp_remote_get( esc_url_raw( $url ), array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'connection_error', $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ( isset( $data['result'] ) && 'error' === $data['result'] ) ) {
			$error_type = isset( $data['error-type'] ) ? $data['error-type'] : 'invalid-key';
			return new WP_Error(
				$error_type,
				sprintf(
					/* translators: %s: provider error code. */
					__( 'API returned error: %s', 'wurrr' ),
					$error_type
				)
			);
		}

		return true;
	}

	private function get_credentials(): array {
		$all_settings = get_option( 'wp_exchange_providers_settings', array() );
		return isset( $all_settings[ $this->get_id() ] ) ? $all_settings[ $this->get_id() ] : array();
	}
}
