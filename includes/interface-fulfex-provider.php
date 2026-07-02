<?php
/**
 * Provider interface for Wurrr Currency Exchange.
 *
 * All exchange rate providers must implement this contract.
 *
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

interface Fulfex_Provider {

	public function fetch_rates( string $base_currency ): array;

	public function get_id(): string;

	public function get_name(): string;

	public function get_settings_fields(): array;

	public function validate_credentials( array $settings );
}
