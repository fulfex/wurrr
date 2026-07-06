<?php
/**
 * exchangeratesapi provider (ECB data).
 *
 * @see https://github.com/exchangeratesapi/exchangeratesapi
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

class Fulfex_Provider_Exchangeratesapi implements Fulfex_Provider {

	const API_URL = 'https://api.exchangeratesapi.io/v1/latest';

	public function fetch_rates( string $base_currency ): array {
		$settings = $this->get_credentials();
		$api_key  = $settings['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return array();
		}

		$url = add_query_arg(
			array(
				'access_key' => $api_key,
				'base'       => strtoupper( $base_currency ),
			),
			self::API_URL
		);

		$response = wp_remote_get( esc_url_raw( $url ), array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['success'] ) || false === $data['success'] ) {
			$error_info = isset( $data['error'] ) ? print_r( $data['error'], true ) : 'unknown';
			error_log(
				sprintf(
					/* translators: 1: provider name, 2: provider error details. */
					__( '[Wurrr] %1$s error: %2$s', 'wurrr' ),
					$this->get_name(),
					$error_info
				)
			);
			return array();
		}

		$rates = $data['rates'] ?? array();

		return array(
			'base_code'              => $data['base'] ?? strtoupper( $base_currency ),
			'conversion_rates'       => $rates,
			'time_next_update_unix'  => $data['timestamp'] ?? 0,
			'provider'               => $this->get_id(),
		);
	}

	public function get_id(): string {
		return 'exchangeratesapi';
	}

	public function get_name(): string {
		return __( 'exchangeratesapi', 'wurrr' );
	}

	public function get_settings_fields(): array {
		return array(
			array(
				'id'          => 'api_key',
				'label'       => __( 'API Key', 'wurrr' ),
				'type'        => 'password',
				'description' => __( 'Enter your exchangeratesapi access key.', 'wurrr' ),
			),
		);
	}

	public function validate_credentials( array $settings ) {
		$api_key = $settings['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', __( 'API key is required.', 'wurrr' ) );
		}

		$url = add_query_arg(
			array( 'access_key' => $api_key ),
			self::API_URL
		);

		$response = wp_remote_get( esc_url_raw( $url ), array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'connection_error', $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ( isset( $data['success'] ) && false === $data['success'] ) ) {
			$code = isset( $data['error']['code'] ) ? $data['error']['code'] : 'invalid_key';
			return new WP_Error(
				$code,
				__( 'API key validation failed.', 'wurrr' )
			);
		}

		return true;
	}

	private function get_credentials(): array {
		$all_settings = get_option( 'wp_exchange_providers_settings', array() );
		return isset( $all_settings[ $this->get_id() ] ) ? $all_settings[ $this->get_id() ] : array();
	}
}
