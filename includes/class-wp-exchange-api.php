<?php
/**
 * Provider registry and factory for WP Currency Exchange.
 *
 * Manages provider registration, round-robin selection,
 * and delegates fetch_rates to the appropriate provider.
 *
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WP_Exchange_API
 */
class WP_Exchange_API {

	/**
	 * Cache handler instance.
	 *
	 * @var WP_Exchange_Cache
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param WP_Exchange_Cache $cache Cache handler.
	 */
	public function __construct( WP_Exchange_Cache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Fetch rates for the given base currency.
	 *
	 * With round-robin: cycles through configured providers until one succeeds.
	 * Without round-robin: delegates to the single active provider.
	 *
	 * @param  string $base_currency Base currency code.
	 * @return array Normalized rates or empty array on failure.
	 */
	public function fetch_rates( string $base_currency ): array {
		$providers = $this->get_configured_providers();

		if ( empty( $providers ) ) {
			return array();
		}

		$round_robin = 'yes' === get_option( 'wp_exchange_enable_round_robin', 'no' );

		if ( ! $round_robin ) {
			$provider = $providers[0];
			$cached   = $this->cache->get_rates( $provider->get_id(), $base_currency );

			if ( false !== $cached && ! empty( $cached['conversion_rates'] ) ) {
				return $cached;
			}

			$rates = $provider->fetch_rates( $base_currency );

			if ( ! empty( $rates['conversion_rates'] ) ) {
				$ttl = $this->calculate_ttl( $rates );
				$this->cache->set_rates( $provider->get_id(), $base_currency, $rates, $ttl );
				return $rates;
			}

			$stale = $this->cache->get_stale_rates( $provider->get_id(), $base_currency );
			if ( false !== $stale ) {
				return $stale;
			}

			return array();
		}

		$index = (int) get_option( 'wp_exchange_rr_index', 0 );
		$count = count( $providers );

		for ( $i = 0; $i < $count; $i++ ) {
			$provider_idx = ( $index + $i ) % $count;
			$provider     = $providers[ $provider_idx ];

			$cached = $this->cache->get_rates( $provider->get_id(), $base_currency );
			if ( false !== $cached && ! empty( $cached['conversion_rates'] ) ) {
				return $cached;
			}

			$rates = $provider->fetch_rates( $base_currency );

			if ( ! empty( $rates['conversion_rates'] ) ) {
				$ttl = $this->calculate_ttl( $rates );
				$this->cache->set_rates( $provider->get_id(), $base_currency, $rates, $ttl );

				update_option( 'wp_exchange_rr_index', ( $provider_idx + 1 ) % $count );

				return $rates;
			}
		}

		$last_provider = $providers[ ( $index - 1 + $count ) % $count ];
		$stale         = $this->cache->get_stale_rates( $last_provider->get_id(), $base_currency );
		if ( false !== $stale ) {
			return $stale;
		}

		return array();
	}

	/**
	 * Get the active provider.
	 *
	 * In single-provider mode, returns the first configured provider.
	 * In round-robin mode, returns the provider at the current index.
	 *
	 * @return WP_Exchange_Provider|null
	 */
	public function get_active_provider(): ?WP_Exchange_Provider {
		$providers = $this->get_configured_providers();

		if ( empty( $providers ) ) {
			return null;
		}

		$round_robin = 'yes' === get_option( 'wp_exchange_enable_round_robin', 'no' );

		if ( ! $round_robin ) {
			return $providers[0];
		}

		$index = (int) get_option( 'wp_exchange_rr_index', 0 ) % count( $providers );
		return $providers[ $index ];
	}

	/**
	 * Get all configured providers with valid credentials.
	 *
	 * Respects the provider order stored in settings.
	 * Filters out providers whose credentials are missing.
	 *
	 * @return array<int, WP_Exchange_Provider>
	 */
	public function get_configured_providers(): array {
		$all_providers    = apply_filters( 'wp_exchange_providers', array() );
		$order            = get_option( 'wp_exchange_provider_order', array() );
		$provider_settings = get_option( 'wp_exchange_providers_settings', array() );

		$providers_by_id = array();
		foreach ( $all_providers as $p ) {
			$providers_by_id[ $p->get_id() ] = $p;
		}

		$configured = array();

		if ( ! empty( $order ) ) {
			foreach ( $order as $id ) {
				if ( isset( $providers_by_id[ $id ] ) ) {
					$settings = $provider_settings[ $id ] ?? array();
					$valid    = $providers_by_id[ $id ]->validate_credentials( $settings );
					if ( true === $valid ) {
						$configured[] = $providers_by_id[ $id ];
					}
				}
			}
		} else {
			foreach ( $providers_by_id as $id => $provider ) {
				$settings = $provider_settings[ $id ] ?? array();
				$valid    = $provider->validate_credentials( $settings );
				if ( true === $valid ) {
					$configured[] = $provider;
				}
			}
		}

		return $configured;
	}

	/**
	 * Calculate cache TTL from fetched data.
	 *
	 * @param  array $data Fetched rates.
	 * @return int TTL in seconds.
	 */
	private function calculate_ttl( array $data ): int {
		$default = (int) get_option( 'wp_exchange_cache_duration', 24 ) * HOUR_IN_SECONDS;

		if ( ! empty( $data['time_next_update_unix'] ) ) {
			$api_ttl = (int) $data['time_next_update_unix'] - time();
			if ( $api_ttl > 0 ) {
				return min( $default, $api_ttl );
			}
		}

		return $default;
	}
}
