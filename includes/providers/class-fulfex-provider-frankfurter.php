<?php
/**
 * Frankfurter provider (free, no API key required).
 *
 * V2 API returns a flat array: [{date, base, quote, rate}, ...]
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
			error_log( '[Wurrr] Frankfurter connection error: ' . $response->get_error_message() );
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			error_log( '[Wurrr] Frankfurter HTTP ' . $code . ' body=' . substr( $body, 0, 300 ) );
			return array();
		}

		$base = strtoupper( $base_currency );
		$rates = array();

		foreach ( $data as $item ) {
			if ( isset( $item['quote'], $item['rate'] ) ) {
				$rates[ strtoupper( $item['quote'] ) ] = (float) $item['rate'];
			}
		}

		if ( empty( $rates ) ) {
			return array();
		}

		return array(
			'base_code'              => $base,
			'conversion_rates'       => $rates,
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
