<?php
/**
 * Frankfurter provider (free, no API key required).
 *
 * @see https://frankfurter.dev
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WP_Exchange_Provider_Frankfurter
 */
class WP_Exchange_Provider_Frankfurter implements WP_Exchange_Provider {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	const API_URL = 'https://api.frankfurter.dev/v2/rates';

	/**
	 * Fetch rates from Frankfurter.
	 *
	 * Frankfurter requires no API key and has no usage quotas.
	 *
	 * @param  string $base_currency Base currency code.
	 * @return array Normalized response.
	 */
	public function fetch_rates( string $base_currency ): array {
		$url = add_query_arg(
			array( 'base' => strtoupper( $base_currency ) ),
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

		return array(
			'base_code'              => $data['base'] ?? strtoupper( $base_currency ),
			'conversion_rates'       => $data['rates'],
			'time_next_update_unix'  => 0,
			'provider'               => $this->get_id(),
		);
	}

	/**
	 * Provider ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'frankfurter';
	}

	/**
	 * Provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Frankfurter', 'wp-exchange' );
	}

	/**
	 * No settings fields needed — Frankfurter is free and requires no key.
	 *
	 * @return array
	 */
	public function get_settings_fields(): array {
		return array();
	}

	/**
	 * Always valid — no credentials needed.
	 *
	 * @param  array $settings Unused.
	 * @return true
	 */
	public function validate_credentials( array $settings ) {
		return true;
	}
}
