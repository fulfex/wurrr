<?php
/**
 * Provider interface for WP Currency Exchange.
 *
 * All exchange rate providers must implement this contract.
 *
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Interface WP_Exchange_Provider
 */
interface WP_Exchange_Provider {

	/**
	 * Fetch raw rates from the provider.
	 *
	 * Must return a normalized array with keys:
	 *   'base_code'        => string
	 *   'conversion_rates' => array<string, float>
	 *   'time_next_update_unix' => int (optional)
	 *   'provider'         => string (provider ID)
	 *
	 * @param  string $base_currency ISO 4217 code (e.g. 'USD').
	 * @return array Normalized response.
	 */
	public function fetch_rates( string $base_currency ): array;

	/**
	 * Unique string identifier used in settings and cache keys.
	 *
	 * @return string e.g. 'exchangerate-api'.
	 */
	public function get_id(): string;

	/**
	 * Human-readable display name.
	 *
	 * @return string e.g. 'ExchangeRate-API'.
	 */
	public function get_name(): string;

	/**
	 * Admin field definitions for this provider's credentials.
	 *
	 * Each entry: array{ id: string, label: string, type: string, description: string }
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_settings_fields(): array;

	/**
	 * Validate provider credentials.
	 *
	 * @param  array $settings Credential key-value pairs.
	 * @return true|\WP_Error
	 */
	public function validate_credentials( array $settings );
}
