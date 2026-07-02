<?php
/**
 * Frankfurter provider (free, no API key required).
 *
 * @see https://frankfurter.dev
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

class Fulfex_Provider_Frankfurter implements Fulfex_Provider {

	const API_URL = 'https://api.frankfurter.dev/v2/rates';

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

	public function get_id(): string {
		return 'frankfurter';
	}

	public function get_name(): string {
		return __( 'Frankfurter', 'wurrr' );
	}

	public function get_settings_fields(): array {
		return array();
	}

	public function validate_credentials( array $settings ) {
		return true;
	}
}
