<?php
/**
 * ExchangeRate-API provider.
 *
 * @see https://www.exchangerate-api.com/docs/standard-requests
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WP_Exchange_Provider_Exchangerate_Api
 */
class WP_Exchange_Provider_Exchangerate_Api implements WP_Exchange_Provider {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	const API_URL = 'https://v6.exchangerate-api.com/v6/%s/latest/%s';

	/**
	 * Fetch rates from ExchangeRate-API.
	 *
	 * @param  string $base_currency Base currency code.
	 * @return array Normalized response.
	 */
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
			// translators: %1$s provider name, %2$s error type.
			error_log( sprintf( __( '[WP Exchange] %1$s error: %2$s', 'wp-exchange' ), $this->get_name(), $error_type ) );
			return array();
		}

		return array(
			'base_code'              => $data['base_code'] ?? strtoupper( $base_currency ),
			'conversion_rates'       => $data['conversion_rates'] ?? array(),
			'time_next_update_unix'  => $data['time_next_update_unix'] ?? 0,
			'provider'               => $this->get_id(),
		);
	}

	/**
	 * Provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'exchangerate-api';
	}

	/**
	 * Provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'ExchangeRate-API', 'wp-exchange' );
	}

	/**
	 * Settings fields for this provider.
	 *
	 * @return array
	 */
	public function get_settings_fields(): array {
		return array(
			array(
				'id'          => 'api_key',
				'label'       => __( 'API Key', 'wp-exchange' ),
				'type'        => 'password',
				'description' => __( 'Enter your ExchangeRate-API key from your dashboard.', 'wp-exchange' ),
			),
		);
	}

	/**
	 * Validate the API key by making a test request.
	 *
	 * @param  array $settings Credential key-value pairs.
	 * @return true|\WP_Error
	 */
	public function validate_credentials( array $settings ) {
		$api_key = $settings['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', __( 'API key is required.', 'wp-exchange' ) );
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
					// translators: %s error type from API.
					__( 'API returned error: %s', 'wp-exchange' ),
					$error_type
				)
			);
		}

		return true;
	}

	/**
	 * Retrieve stored credentials for this provider.
	 *
	 * @return array
	 */
	private function get_credentials(): array {
		$all_settings = get_option( 'wp_exchange_providers_settings', array() );
		return isset( $all_settings[ $this->get_id() ] ) ? $all_settings[ $this->get_id() ] : array();
	}
}
