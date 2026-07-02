<?php
/**
 * Cache layer for Wurrr Currency Exchange.
 *
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

class Fulfex_Cache {

	public function get_rates( string $provider_id, string $base_currency ): array|false {
		return get_transient( $this->cache_key( $provider_id, $base_currency ) );
	}

	public function set_rates( string $provider_id, string $base_currency, array $data, int $ttl = 86400 ): bool {
		$set = set_transient( $this->cache_key( $provider_id, $base_currency ), $data, $ttl );

		if ( $set && ! empty( $data ) ) {
			set_transient( $this->stale_key( $provider_id, $base_currency ), $data, YEAR_IN_SECONDS );
		}

		return $set;
	}

	public function get_stale_rates( string $provider_id, string $base_currency ): array|false {
		return get_transient( $this->stale_key( $provider_id, $base_currency ) );
	}

	public function clear_rates(): void {
		$providers = apply_filters( 'wp_exchange_providers', array() );
		$base      = get_option( 'wp_exchange_base_currency', 'USD' );

		foreach ( $providers as $provider ) {
			$key   = $this->cache_key( $provider->get_id(), $base );
			$stale = $this->stale_key( $provider->get_id(), $base );

			delete_transient( $key );
			delete_transient( $stale );

			$stale_suffix = WURRR_STALE_PREFIX . $provider->get_id() . '_' . strtolower( $base );

			global $wpdb;
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'_transient_' . $stale_suffix . '%'
				)
			);
		}
	}

	private function cache_key( string $provider_id, string $base_currency ): string {
		return WURRR_CACHE_PREFIX . $provider_id . '_' . strtolower( $base_currency );
	}

	private function stale_key( string $provider_id, string $base_currency ): string {
		return WURRR_STALE_PREFIX . $provider_id . '_' . strtolower( $base_currency );
	}
}
