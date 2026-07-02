<?php
/**
 * exchangeratesapi provider (ECB data).
 *
 * @see https://github.com/exchangeratesapi/exchangeratesapi
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WP_Exchange_Provider_Exchangeratesapi
 */
class WP_Exchange_Provider_Exchangeratesapi implements WP_Exchange_Provider {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	const API_URL = 'https://api.exchangeratesapi.io/v1/latest';

	/**
	 * Fetch rates from exchangeratesapi.
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
					// translators: %1$s provider name, %2$s error info.
					__( '[WP Exchange] %1$s error: %2$s', 'wp-exchange' ),
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

	/**
	 * Provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'exchangeratesapi';
	}

	/**
	 * Provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'exchangeratesapi', 'wp-exchange' );
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
				'description' => __( 'Enter your exchangeratesapi access key.', 'wp-exchange' ),
			),
		);
	}

	/**
	 * Validate the API key.
	 *
	 * @param  array $settings Credential key-value pairs.
	 * @return true|\WP_Error
	 */
	public function validate_credentials( array $settings ) {
		$api_key = $settings['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', __( 'API key is required.', 'wp-exchange' ) );
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
				__( 'API key validation failed.', 'wp-exchange' )
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
