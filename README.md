# Wurrr 🐱 — Free Forever Currency Exchange for WooCommerce

![Wurrr](https://img.shields.io/badge/version-1.0.0-blue) ![PHP](https://img.shields.io/badge/PHP-7.4+-7377ad) ![WP](https://img.shields.io/badge/WordPress-5.8+-21759b) ![WC](https://img.shields.io/badge/WooCommerce-6.0+-7f54b3)

> 🐱 Purrfectly convert WooCommerce store prices to your customer's local currency. Free forever. No hidden costs, no premium tiers.

---

## Why Wurrr?

Wurrr automatically detects where your customers are and displays prices in their local currency. Built-in **Frankfurter** provider means zero API keys, zero quotas, zero cost — ever. Need more? BYOK to ExchangeRate-API, Open Exchange Rates, or exchangeratesapi.

## Features

- **Free forever** — Frankfurter provider requires no API key and has no usage caps
- **Multi-provider** — ExchangeRate-API, exchangeratesapi, Open Exchange Rates, Frankfurter
- **Round-robin failover** — distribute load across providers; if one fails, the next takes over
- **IP geolocation** — auto-detect currency from MaxMind, Cloudflare headers, or server vars
- **Smart caching** — WordPress Transients with stale-cache fallback (store never breaks)
- **Currency switcher** — `[wurrr_switcher]` shortcode + dropdown with full currency labels
- **Full WooCommerce** — converts single products, variable ranges, cart items, subtotals, totals, coupons
- **HPOS ready** — compatible with WooCommerce High-Performance Order Storage
- **i18n ready** — translation-ready with `.pot` file

## Quick Start

```bash
# Clone into your plugins directory
git clone https://github.com/fulfex/wurrr.git wp-content/plugins/wurrr
```

1. Activate the plugin
2. Go to **WooCommerce → Currency Exchange 🐱**
3. **Frankfurter is ready out of the box** — no API key needed
4. Optional: add API keys for other providers under the **Providers** tab
5. Place `[wurrr_switcher]` shortcode where you want the currency dropdown

## Architecture

```
Customer → WooCommerce Price Hook → Currency Conversion → Rendered Page
                                         │
                              ┌──────────▼──────────┐
                              │    Fulfex_API        │
                              │  (round-robin calls) │
                              └──┬──────┬──────┬─────┘
                                 │      │      │
                    ExchangeRate │  exch.  │ OpenExch. │ Frankfurter
                       -API      │ ratesapi│  Rates    │  (free)
                                 │
                          Fulfex_Cache
                        (Transients + stale)
```

## Providers

| Provider | API Key | Free Tier | URL |
|----------|:-------:|:---------:|-----|
| **Frankfurter** | ❌ | ✅ Unlimited | [frankfurter.dev](https://frankfurter.dev) |
| **ExchangeRate-API** | ✅ | ✅ Daily quota | [exchangerate-api.com](https://www.exchangerate-api.com) |
| **exchangeratesapi** | ✅ | ✅ Limited | [github.com/exchangeratesapi](https://github.com/exchangeratesapi/exchangeratesapi) |
| **Open Exchange Rates** | ✅ | ✅ Monthly quota | [openexchangerates.org](https://openexchangerates.org) |

## Adding a New Provider

Extend the `Fulfex_Provider` interface. Four methods required:

```php
<?php
// includes/providers/class-fulfex-provider-myapi.php

defined( 'ABSPATH' ) || exit;

class Fulfex_Provider_Myapi implements Fulfex_Provider {

    /**
     * Fetch rates from your API. Return normalized array.
     */
    public function fetch_rates( string $base_currency ): array {
        $settings = $this->get_credentials();
        $api_key  = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return array();
        }

        $response = wp_remote_get( 'https://myapi.example.com/latest?key=' . $api_key . '&base=' . $base_currency );

        if ( is_wp_error( $response ) ) {
            return array();
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $data ) ) {
            return array();
        }

        // Must return this exact normalized structure.
        return array(
            'base_code'              => strtoupper( $base_currency ),
            'conversion_rates'       => $data['rates'] ?? array(),
            'time_next_update_unix'  => $data['next_update'] ?? 0,  // optional
            'provider'               => $this->get_id(),
        );
    }

    /**
     * Unique ID — used in cache keys and settings.
     */
    public function get_id(): string {
        return 'myapi';
    }

    /**
     * Human-readable name shown in admin.
     */
    public function get_name(): string {
        return __( 'My API', 'wurrr' );
    }

    /**
     * Admin credential fields. Return empty array if no credentials needed.
     */
    public function get_settings_fields(): array {
        return array(
            array(
                'id'          => 'api_key',
                'label'       => __( 'API Key', 'wurrr' ),
                'type'        => 'password',
                'description' => __( 'Enter your My API key.', 'wurrr' ),
            ),
        );
    }

    /**
     * Validate credentials. Return true or WP_Error.
     */
    public function validate_credentials( array $settings ) {
        $api_key = $settings['api_key'] ?? '';

        if ( empty( $api_key ) ) {
            return new WP_Error( 'missing_key', __( 'API key is required.', 'wurrr' ) );
        }

        // Make a test request to verify the key works.
        $response = wp_remote_get( 'https://myapi.example.com/validate?key=' . $api_key );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'connection_error', $response->get_error_message() );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $data['valid'] ) || ! $data['valid'] ) {
            return new WP_Error( 'invalid_key', __( 'Invalid API key.', 'wurrr' ) );
        }

        return true;
    }

    /**
     * Retrieve stored credentials from options.
     */
    private function get_credentials(): array {
        $all = get_option( 'wp_exchange_providers_settings', array() );
        return $all[ $this->get_id() ] ?? array();
    }
}
```

Then register it in `includes/class-fulfex-plugin.php`:

```php
// In register_providers():
$provider_files = array(
    // ... existing providers ...
    'myapi' => WURRR_PLUGIN_DIR . 'includes/providers/class-fulfex-provider-myapi.php',
);

// In the wp_exchange_providers filter callback:
$classes = array(
    // ... existing classes ...
    'Fulfex_Provider_Myapi',
);
```

The new provider will automatically appear in **WooCommerce → Currency Exchange → Providers** with its credential fields, drag-to-reorder support, and round-robin participation.

## Normalized Response Format

All providers must return this structure from `fetch_rates()`:

```php
array(
    'base_code'              => 'USD',       // ISO 4217 source currency
    'conversion_rates'       => array(       // Target → rate mapping
        'USD' => 1.0,
        'EUR' => 0.92,
        'GBP' => 0.79,
        // ...
    ),
    'time_next_update_unix'  => 1716768000,  // optional: next refresh timestamp
    'provider'               => 'myapi',     // provider ID
)
```

## Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Base Currency | USD | Store's default currency |
| Cache Duration | 24 hours | How often to refresh rates |
| IP Detection | On | Auto-detect currency from visitor location |
| Display Style | Inline | Inline / Badge / Dropdown |
| Switcher Position | Shortcode | Header / Footer / `[wurrr_switcher]` |
| Round-Robin | Off | Rotate across multiple providers |

## Hooks & Filters

| Hook | Type | Description |
|------|------|-------------|
| `wp_exchange_providers` | filter | Register custom providers |
| `wp_exchange_convert` | AJAX action | On-the-fly price conversion |
| `wp_exchange_set_currency` | AJAX action | Store user currency preference |

## Development

```bash
# PHP lint
find . -name '*.php' -exec php -l {} \;

# Build ZIP
zip -r wurrr.zip . -x "*.git*" "*/.github/*"
```

CI runs on every PR via `.github/workflows/pr.yml`:
- PHP syntax checks
- WordPress + WooCommerce integration test
- Plugin Check (WordPress.org compliance)

## License

[GPL v3 or later](https://www.gnu.org/licenses/gpl-3.0.html)
