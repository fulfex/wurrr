<?php
/**
 * Cache layer for WP Currency Exchange.
 *
 * Uses WordPress Transients API with provider-aware cache keys
 * and stale cache fallback for resilience.
 *
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WP_Exchange_Cache
 */
class WP_Exchange_Cache {

	/**
	 * Get cached rates for a provider + base currency pair.
	 *
	 * @param  string $provider_id   Provider identifier.
	 * @param  string $base_currency Base currency code.
	 * @return array|false Cached data or false on miss.
	 */
	public function get_rates( string $provider_id, string $base_currency ): array|false {
		return get_transient( $this->cache_key( $provider_id, $base_currency ) );
	}

	/**
	 * Set cached rates.
	 *
	 * @param  string $provider_id   Provider identifier.
	 * @param  string $base_currency Base currency code.
	 * @param  array  $data          Rate data to cache.
	 * @param  int    $ttl           Time-to-live in seconds.
	 * @return bool Success.
	 */
	public function set_rates( string $provider_id, string $base_currency, array $data, int $ttl = 86400 ): bool {
		$set = set_transient( $this->cache_key( $provider_id, $base_currency ), $data, $ttl );

		if ( $set && ! empty( $data ) ) {
			set_transient( $this->stale_key( $provider_id, $base_currency ), $data, YEAR_IN_SECONDS );
		}

		return $set;
	}

	/**
	 * Retrieve stale (expired but preserved) rates.
	 *
	 * @param  string $provider_id   Provider identifier.
	 * @param  string $base_currency Base currency code.
	 * @return array|false
	 */
	public function get_stale_rates( string $provider_id, string $base_currency ): array|false {
		return get_transient( $this->stale_key( $provider_id, $base_currency ) );
	}

	/**
	 * Flush all cached and stale rates.
	 *
	 * Iterates over known provider IDs to clear their cache slots.
	 *
	 * @return void
	 */
	public function clear_rates(): void {
		$providers = apply_filters( 'wp_exchange_providers', array() );
		$base      = get_option( 'wp_exchange_base_currency', 'USD' );

		foreach ( $providers as $provider ) {
			$key   = $this->cache_key( $provider->get_id(), $base );
			$stale = $this->stale_key( $provider->get_id(), $base );

			delete_transient( $key );
			delete_transient( $stale );

			$stale_suffix = WP_EXCHANGE_STALE_PREFIX . $provider->get_id() . '_' . strtolower( $base );

			global $wpdb;
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'_transient_' . $stale_suffix . '%'
				)
			);
		}
	}

	/**
	 * Build cache key for a provider + base currency.
	 *
	 * @param  string $provider_id   Provider ID.
	 * @param  string $base_currency Base currency.
	 * @return string
	 */
	private function cache_key( string $provider_id, string $base_currency ): string {
		return WP_EXCHANGE_CACHE_PREFIX . $provider_id . '_' . strtolower( $base_currency );
	}

	/**
	 * Build stale cache key.
	 *
	 * @param  string $provider_id   Provider ID.
	 * @param  string $base_currency Base currency.
	 * @return string
	 */
	private function stale_key( string $provider_id, string $base_currency ): string {
		return WP_EXCHANGE_STALE_PREFIX . $provider_id . '_' . strtolower( $base_currency );
	}
}
