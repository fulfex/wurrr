# Wurrr 🐱 - Free Forever Currency Exchange for Storefront

## Overview
A WooCommerce plugin that automatically converts store prices to a customer's local currency. Free forever with built-in Frankfurter (no API key needed) and multiple BYOK providers. Round-robin failover across all configured providers. Proudly built by Fulfex.

## File Structure

```
wurrr/
├── wurrr.php                                      # Plugin bootstrap, activation hooks, constants
├── includes/
│   ├── class-fulfex-plugin.php                    # Main plugin class
│   ├── class-fulfex-admin.php                     # Admin settings page
│   ├── class-fulfex-api.php                       # Provider registry + factory
│   ├── class-fulfex-cache.php                     # Transients-based caching layer
│   ├── class-fulfex-geolocation.php               # IP-to-country detection
│   ├── class-fulfex-frontend.php                  # Price filter hooks + currency switcher
│   ├── class-fulfex-currency.php                  # Formatting, ISO 4217 helpers
│   ├── interface-fulfex-provider.php              # Provider contract
│   └── providers/
│       ├── class-fulfex-provider-exchangerate-api.php
│       ├── class-fulfex-provider-exchangeratesapi.php
│       ├── class-fulfex-provider-openexchangerates.php
│       └── class-fulfex-provider-frankfurter.php
├── assets/
│   ├── css/wurrr.css
│   └── js/wurrr.js
├── languages/wurrr.pot
├── .github/
│   └── workflows/pr.yml                           # CI: lint, WP + WC integration, plugin-check
└── readme.txt
```

## Class Naming Convention

All classes use the `Fulfex_` prefix:

| Class | Purpose |
|-------|---------|
| `Fulfex_Plugin` | Main orchestrator, singleton |
| `Fulfex_Provider` (interface) | Contract all providers implement |
| `Fulfex_Provider_Exchangerate_Api` | exchangerate-api.com |
| `Fulfex_Provider_Exchangeratesapi` | ECB data via API key |
| `Fulfex_Provider_Openexchangerates` | openexchangerates.org |
| `Fulfex_Provider_Frankfurter` | Free, no-key provider |
| `Fulfex_Cache` | Transients + stale cache |
| `Fulfex_Currency` | Conversion, formatting, symbols |
| `Fulfex_API` | Registry, round-robin, fetch_rates |
| `Fulfex_Geolocation` | IP → country → currency |
| `Fulfex_Admin` | Settings, provider management |
| `Fulfex_Frontend` | WooCommerce hooks, shortcode, AJAX |

## Provider Interface

```php
interface Fulfex_Provider {
    public function fetch_rates( string $base_currency ): array;
    public function get_id(): string;
    public function get_name(): string;
    public function get_settings_fields(): array;
    public function validate_credentials( array $settings );
}
```

## CI/CD

`.github/workflows/pr.yml` runs on every PR:
1. **Static** - PHP lint + builds ZIP
2. **Integration** - Installs WP + WC, activates plugin, runs `wp plugin check` for WordPress.org compliance
