=== Wurrr 🐱 - Free Forever Currency Exchange for Storefront ===
Contributors: fulfex
Tags: woocommerce, currency, exchange rate, converter, multi-currency, price conversion, currency switcher, free
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

🐱 Purrfectly convert WooCommerce prices to your customer's local currency. Free forever with multi-provider, round-robin failover, and IP geolocation.

== Description ==

Wurrr 🐱 automatically detects your customer's location and converts WooCommerce store prices to their local currency. No manual exchange rate management needed. Free forever with no hidden costs.

= Key Features =

🐱 **Free Forever** – No premium tiers, no upsells. The built-in Frankfurter provider requires no API key at all.
🐱 **Multiple Providers** – Choose from ExchangeRate-API, exchangeratesapi, Open Exchange Rates, or the completely free Frankfurter API.
🐱 **Round-Robin Failover** – Configure multiple providers for automatic failover if one hits its rate limit.
🐱 **IP Geolocation** – Automatically detect visitor currency from their IP address using WooCommerce's MaxMind database or server headers.
🐱 **Manual Currency Switcher** – Let customers override their currency with a dropdown or shortcode.
🐱 **Smart Caching** – Caches exchange rates using WordPress Transients to minimize API calls. Falls back to stale cache if all providers are unavailable.
🐱 **Full WooCommerce Compatibility** – Works with HPOS, Cart/Checkout Blocks, variable products, and cart totals.

= Supported Providers =

* **Frankfurter** (frankfurter.dev) – Completely free. No API key required. 201 currencies from 84 central banks.
* **ExchangeRate-API** (exchangerate-api.com) – Free tier available. Bring your own API key.
* **exchangeratesapi** (github.com/exchangeratesapi/exchangeratesapi) – ECB data via API key.
* **Open Exchange Rates** (openexchangerates.org) – Popular provider with generous free tier.

== Installation ==

1. Upload the `wurrr` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce → Settings → Currency Exchange.
4. Select your preferred exchange rate provider and enter any required API key.
5. Configure display options and save.

== Frequently Asked Questions ==

= Is this really free forever? =

Yes 🐱! The Frankfurter provider is completely free with no API key, no quotas, and no rate limits. No premium tiers, no upsells.

= Do I need an API key? =

Only if you choose a provider that requires one. The Frankfurter provider works without any API key.

= Which provider should I use? =

Frankfurter is the easiest to start with since it requires no API key. For production stores, consider adding a second provider with round-robin enabled for redundancy.

= Can customers choose their own currency? =

Yes. Enable the currency switcher in settings and place it via the `[wurrr_switcher]` shortcode or a widget area.

= Does this work with variable products? =

Yes. Price ranges are converted correctly, showing both min and max in the customer's currency.

== Changelog ==

= 1.0.0 =
* Initial release.
