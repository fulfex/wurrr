# WP Currency Exchange Plugin

## Overview
A WooCommerce plugin that automatically converts store prices to a customer's local currency. Supports multiple exchange rate providers via a common interface (BYOK each). Uses caching to minimize API calls and supports IP-based geolocation with manual currency selector.

## File Structure

```
wp-exchange/
├── wp-exchange.php                        # Plugin bootstrap, activation hooks, constants
├── includes/
│   ├── class-wp-exchange-plugin.php       # Main plugin class - hooks everything together
│   ├── class-wp-exchange-admin.php        # Admin settings page (provider selection, API keys, etc.)
│   ├── class-wp-exchange-api.php          # Provider registry + factory — delegates to active provider
│   ├── class-wp-exchange-cache.php        # Transients-based caching layer for rates
│   ├── class-wp-exchange-geolocation.php  # IP-to-country detection (maxmind or free fallback)
│   ├── class-wp-exchange-frontend.php     # Price filter hooks + currency switcher UI
│   ├── class-wp-exchange-currency.php     # Formatting, ISO 4217 helpers, session logic
│   ├── interface-wp-exchange-provider.php # Contract all providers must implement
│   └── providers/
│       ├── class-wp-exchange-provider-exchangerate-api.php   # exchangerate-api.com
│       ├── class-wp-exchange-provider-exchangeratesapi.php   # https://github.com/exchangeratesapi/exchangeratesapi (ECB data)
│       ├── class-wp-exchange-provider-openexchangerates.php  # openexchangerates.org
│       └── class-wp-exchange-provider-frankfurter.php        # frankfurter.dev (free, no key required)
├── assets/
│   ├── css/
│   │   └── wp-exchange.css               # Currency switcher dropdown, badge styling
│   └── js/
│       └── wp-exchange.js                # AJAX price conversion, switcher interactions
└── languages/                            # .pot file for i18n
```

## Provider Interface (`interface-wp-exchange-provider.php`)

```php
interface WP_Exchange_Provider {
    // Fetch raw rates from the provider. Must normalize to standard array format.
    public function fetch_rates( string $base_currency ): array;

    // Unique string ID used in settings and cache keys (e.g. 'exchangerate-api').
    public function get_id(): string;

    // Human-readable name (e.g. 'ExchangeRate-API').
    public function get_name(): string;

    // Return admin field definitions for this provider's credentials/config.
    // Each entry: [ 'id' => string, 'label' => string, 'type' => 'text'|'password', 'description' => string ]
    public function get_settings_fields(): array;

    // Validate the provider's credentials. Return true or a WP_Error.
    public function validate_credentials( array $settings ): bool|WP_Error;
}
```

### Normalized Response Format (all providers must return)

```php
[
    'base_code'          => 'USD',
    'conversion_rates'   => [ 'USD' => 1, 'EUR' => 0.85, 'GBP' => 0.76, ... ],
    'time_next_update_unix' => 1585353700,
    'provider'           => 'exchangerate-api',
]
```

## Data Flow

```
┌──────────┐    ┌──────────────┐    ┌───────────────┐    ┌──────────────────┐
│ Customer  │───▶│  WordPress   │───▶│  Price Filter  │───▶│  Rendered Page   │
│ (browser) │    │  Page Load   │    │  hooks into    │    │  with converted  │
└──────────┘    └──────────────┘    │  WooCommerce   │    │  prices          │
                                    │  price HTML    │    └──────────────────┘
                                    └───────┬───────┘
                                            │
                                    ┌───────▼───────┐
                                    │  API Factory   │
                                    │  (resolves to  │
                                    │  active prov.) │
                                    └───┬───┬───┬───┘
                                        │   │   │
                        ┌───────────────┘   │   └───────────────┐
                        │          ┌────────▼────────┐          │
                        │          │  Cache Check    │          │
                        │          │  (by provider + │          │
                        │          │   base currency)│          │
                        │          └───┬────────┬───┘          │
                        │              │        │                │
                ┌───────▼───────┐ ┌────▼────┐ ┌─▼──────────────┐ ┌───────────┐
                │ ExchangeRate- │ │exchanges│ │ OpenExchange   │ │ Frankfurter│
                │ API Provider  │ │ratesapi │ │ Rates Provider │ │ (free)    │
                └───────────────┘ └─────────┘ └────────────────┘ └───────────┘
```

## Round-Robin Provider Rotation

When the admin enables round-robin mode and configures multiple providers, the plugin distributes API requests across them to avoid hitting rate limits on any single key.

### How it works

```
┌─────────────────────────────────────────────────────────────┐
│                    Cache Miss / Expired                       │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
            ┌────────────────────────┐
            │  Get round-robin index  │
            │  from wp_options        │
            └─────────┬──────────────┘
                      │
                      ▼
         ┌──────────────────────────┐
         │ Try provider at index 0  │
         │ (e.g., exchangerate-api) │
         └─────────┬────────────────┘
                   │
          ┌────────┴────────┐
          ▼                 ▼
     Success             Failure
          │                 │
          │          ┌──────▼──────┐
          │          │ Try next    │
          │          │ provider    │
          │          │ (index++)   │
          │          └──────┬──────┘
          │                 │
          │          ┌──────▼──────┐
          │          │ All failed? │
          │          └──────┬──────┘
          │            ┌────┴────┐
          │            ▼         ▼
          │        Return     Return
          │        error      last
          │                   cached
          ▼                   (stale)
  ┌───────────────┐
  │ Store which   │
  │ provider      │
  │ succeeded in  │
  │ cache + save  │
  │ next index    │
  │ for future    │
  └───────────────┘
```

### Rotation Strategy

- **Index tracking:** `wp_exchange_rr_index` option stores the next provider index to try (0, 1, 2, ...)
- **On each cache refresh:** Start at the current index, cycle through providers in order until one succeeds
- **On success:** Save the provider ID in the cached data (`$data['provider']`). Increment the index for next time (so the next refresh starts with a different provider).
- **On failure (all exhausted):** Return the last successful provider's stale cache (with `wp_exchange_stale_rates_` prefix) so the store never breaks. Log errors.
- **Cache key:** Still `wp_exchange_rates_{provider_id}_{base}` — the round-robin is transparent to the cache layer because each provider has its own cache slot.

### Admin Configuration

| Field | Type | Description |
|-------|------|-------------|
| Enable Round-Robin | Toggle | Distribute API requests across multiple providers |
| Provider Priority | Sortable list | Drag to reorder priority. First is tried first. |
| *Per-provider fields* | Dynamic | Each active provider shows its credential fields |

When round-robin is **disabled** (default), the first provider in the list acts as the single active provider — same behavior as before.

When round-robin is **enabled**, all configured providers with valid credentials are used in the rotation.

## Component Details

### 1. Provider Interface + Factory (`class-wp-exchange-api.php`)
- Acts as a registry — providers self-register via `wp_exchange_providers` filter
- `get_active_provider(): WP_Exchange_Provider` — if round-robin is off, returns the single selected provider; if on, returns provider at current round-robin index
- `fetch_rates(string $base_currency): array` — with round-robin: tries providers in order until success, increments index; without: delegates to the single active provider
- All provider credentials stored in `wp_options` under `wp_exchange_providers_settings` (nested array keyed by provider ID)

### 2. Individual Providers (`providers/*.php`)
Each class implements `WP_Exchange_Provider`:
- **ExchangeRate-API** (`class-wp-exchange-provider-exchangerate-api.php`):
  - `GET https://v6.exchangerate-api.com/v6/{API_KEY}/latest/{BASE}`
  - Handles error types: `unsupported-code`, `malformed-request`, `invalid-key`, `inactive-account`, `quota-reached`

- **exchangeratesapi** (`class-wp-exchange-provider-exchangeratesapi.php`):
  - Uses ECB data via `https://api.exchangeratesapi.io/v1/latest?access_key={KEY}&base={BASE}` or the free/open version
  - Free tier limited to EUR base only

- **Open Exchange Rates** (`class-wp-exchange-provider-openexchangerates.php`):
  - `GET https://openexchangerates.org/api/latest.json?app_id={APP_ID}&base={BASE}`
  - Always returns USD as base on free tier (conversion handled internally)

- **Frankfurter** (`class-wp-exchange-provider-frankfurter.php`):
  - `GET https://api.frankfurter.dev/v2/rates?base={BASE}`
  - **No API key required** — completely free, no usage quotas
  - 201 currencies sourced from 84 central banks
  - `get_settings_fields()` returns empty array (no credentials needed)
  - `validate_credentials()` always returns `true`
  - Response has no `time_next_update_unix`; uses admin cache TTL exclusively

### 3. Cache Layer (`class-wp-exchange-cache.php`)
- **Storage:** WordPress Transients API (object-cache friendly)
- **Cache key:** `wp_exchange_rates_{provider_id}_{base_currency}` (e.g., `wp_exchange_rates_exchangerate-api_usd`)
- **Stale cache:** `wp_exchange_stale_rates_{provider_id}_{base_currency}` — preserves the last-known-good rates when all providers fail
- **TTL:** Configurable in admin (default 24 hours); if provider supplies `time_next_update_unix`, use whichever is shorter
- **Methods:**
  - `get_rates(string $provider_id, string $base_currency): array|false`
  - `set_rates(string $provider_id, string $base_currency, array $data): bool`
  - `get_stale_rates(string $provider_id, string $base_currency): array|false`
  - `set_stale_rates(string $provider_id, string $base_currency, array $data): bool`
  - `clear_rates(): bool` — flush all cached rates (called when settings change or provider switched)
- Cache is provider-aware so switching providers doesn't serve stale data from the old one

### 4. Geolocation (`class-wp-exchange-geolocation.php`)
- **Strategy A (preferred):** Use `WC_Geolocation` (maxmind GeoLite2 DB bundled with WooCommerce)
- **Strategy B (fallback):** Parse `$_SERVER['HTTP_CF_IPCOUNTRY']` (Cloudflare) or `$_SERVER['GEOIP_COUNTRY_CODE']`
- **Output:** ISO 3166-1 alpha-2 country code → mapped to common currency via hardcoded mapping table
- **User override:** If user has selected a preferred currency in session/cookie, that takes precedence

### 5. Frontend (`class-wp-exchange-frontend.php`)
- **Hooks used:**
  - `woocommerce_get_price_html` — wraps displayed price with converted amount + original as tooltip
  - `woocommerce_variable_price_html` — handles variable products (show range)
  - `woocommerce_cart_item_price` — convert cart prices
  - `woocommerce_cart_subtotal` / `woocommerce_cart_total` — convert cart totals
  - `wp_enqueue_scripts` — enqueue assets
- **Currency Switcher:**
  - Renders a dropdown on the header/sidebar via action hook or shortcode `[wp_exchange_switcher]`
  - Options: all currencies from the cached rates, labeled as "USD - US Dollar"
  - Selection stored in cookie for 30 days
- **Price range support:** For variable products, convert both min and max prices

### 6. Admin Settings (`class-wp-exchange-admin.php`)
- **Page:** WooCommerce → Settings → Currency Exchange
- **Sections:**

  **Provider Settings:**
  | Field | Type | Description |
  |-------|------|-------------|
  | Enable Round-Robin | Toggle | Distribute load across multiple providers (requires ≥2 configured) |
  | Active Provider(s) | Sortable list | Drag to set priority order. Per-provider credential fields appear inline |
  | Test Connection | Button | Validate credentials for each provider before saving |

  **General Settings:**
  | Field | Type | Description |
  |-------|------|-------------|
  | Base Currency | Select | Default store currency (default: USD) |
  | Cache Duration | Number (hours) | How long to cache rates (default: 24) |
  | Enable IP Detection | Toggle | Auto-detect currency from visitor IP |
  | Display Style | Select | Inline / Badge / Dropdown |
  | Switcher Position | Select | Header / Footer / Sidebar / Shortcode only |

- **Validation:** Calls `validate_credentials()` on each configured provider; if any fails, show admin notice and highlight which provider has the issue
- **Cache flush:** Fires `clear_rates()` when provider config or API keys change

### 7. Currency Utilities (`class-wp-exchange-currency.php`)
- `get_user_currency(): string` — returns resolved currency code (IP or user-picked)
- `convert_price(float $amount, string $from, string $to): float` — uses cached rates from active provider
- `format_price(float $amount, string $currency_code): string` — locale-aware formatting
- `get_currency_symbol(string $code): string` — maps code to `$`, `€`, `£`, `¥`, etc.
- `get_currency_name(string $code): string` — "US Dollar", "Euro", etc.

## Cache Design

```php
// Cache key includes provider ID so switching providers invalidates cleanly
$cache_key = 'wp_exchange_rates_' . $provider_id . '_' . strtolower($base_currency);

// On fetch: check cache first
$rates = get_transient($cache_key);
if (false === $rates) {
    // Round-robin: try providers in order until one succeeds
    $providers = $api->get_configured_providers(); // ordered list
    $index     = get_option('wp_exchange_rr_index', 0);
    $count     = count($providers);

    for ($i = 0; $i < $count; $i++) {
        $provider_idx = ($index + $i) % $count;
        $provider     = $providers[$provider_idx];
        $rates        = $provider->fetch_rates($base_currency);

        if ($rates && !is_wp_error($rates)) {
            // Update index so next request starts with next provider
            update_option('wp_exchange_rr_index', ($provider_idx + 1) % $count);
            break;
        }
    }

    if ($rates && isset($rates['time_next_update_unix'])) {
        $ttl = min(
            $settings['cache_duration'] * HOUR_IN_SECONDS,
            $rates['time_next_update_unix'] - time()
        );
        set_transient($cache_key, $rates, $ttl);
        // Also set stale cache as fallback
        set_transient('wp_exchange_stale_' . $cache_key, $rates, YEAR_IN_SECONDS);
    } elseif (false === $rates) {
        // All providers failed — serve stale cache
        $rates = get_transient('wp_exchange_stale_' . $cache_key);
    }
}
```

## Geolocation → Currency Mapping (hardcoded fallback)

```
US → USD, GB → GBP, EU/AT/BE/FI/FR/DE/GR/IE/IT/NL/PT/ES → EUR,
JP → JPY, CN → CNY, KR → KRW, HK → HKD, SG → SGD,
MY → MYR, ID → IDR, PH → PHP, TH → THB, VN → VND,
IN → INR, BR → BRL, MX → MXN, CA → CAD, AU → AUD,
CH → CHF, SE → SEK, NO → NOK, DK → DKK, NZ → NZD,
ZA → ZAR, RU → RUB, TR → TRY, SA → SAR, AE → AED,
NG → NGN, EG → EGP, AR → ARS, CL → CLP, CO → COP
```

## AJAX Endpoints

| Action | Handler | Purpose |
|--------|---------|---------|
| `wp_exchange_convert` | `Frontend::ajax_convert_price()` | Convert a given price amount on the fly (for cart/checkout) |
| `wp_exchange_set_currency` | `Frontend::ajax_set_currency()` | Store user's currency preference in cookie/session |

## WooCommerce Compatibility

- **HPOS** (High-Performance Order Storage) — use `WC_Data` methods, never direct `wp_post` queries
- **Cart/Checkout Blocks** — use `woocommerce_store_api_cart_item_price` and related Store API filters
- **Product Bundles, Composites, Subscriptions** — ensure price filtering applies to child items, recurring totals

## Development Workflow

1. Set up local WordPress + WooCommerce environment
2. Activate plugin, select provider, enter API key
3. Verify: prices convert on frontend, cache stores/retrieves correctly, IP detection works
4. Run `phpcs --standard=WordPress` for coding standards
5. Run WooCommerce unit tests if available

## Key Constants

```php
define('WP_EXCHANGE_VERSION', '1.0.0');
define('WP_EXCHANGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_EXCHANGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_EXCHANGE_CACHE_PREFIX', 'wp_exchange_rates_');
define('WP_EXCHANGE_STALE_PREFIX', 'wp_exchange_stale_rates_');
define('WP_EXCHANGE_SESSION_KEY', 'wp_exchange_user_currency');
```

## Security Considerations

- Sanitize API key before saving to options (`sanitize_text_field`)
- Escape all output on frontend (`esc_html`, `wc_price`, `esc_attr`)
- Nonce-protect AJAX endpoints
- Validate currency codes against allowed list before passing to API
- Do not expose API keys in frontend JS
- Use capability checks for admin pages (`manage_woocommerce`)
- Providers validate credentials server-side before saving
