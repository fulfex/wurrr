<?php
/**
 * Frontend hooks for WP Currency Exchange.
 *
 * Handles price conversion display, currency switcher UI,
 * and AJAX endpoints.
 *
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WP_Exchange_Frontend
 */
class WP_Exchange_Frontend {

	/**
	 * Currency utility instance.
	 *
	 * @var WP_Exchange_Currency
	 */
	private $currency;

	/**
	 * Constructor.
	 *
	 * @param WP_Exchange_Currency $currency Currency utilities.
	 */
	public function __construct( WP_Exchange_Currency $currency ) {
		$this->currency = $currency;

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_filter( 'woocommerce_get_price_html', array( $this, 'convert_price_html' ), 10, 2 );
		add_filter( 'woocommerce_variable_price_html', array( $this, 'convert_variable_price_html' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'convert_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_subtotal', array( $this, 'convert_cart_subtotal' ), 10, 3 );
		add_filter( 'woocommerce_cart_total', array( $this, 'convert_cart_total' ), 10 );
		add_filter( 'woocommerce_cart_totals_coupon_html', array( $this, 'convert_coupon_html' ), 10, 2 );

		add_shortcode( 'wp_exchange_switcher', array( $this, 'render_switcher' ) );

		add_action( 'wp_ajax_wp_exchange_convert', array( $this, 'ajax_convert_price' ) );
		add_action( 'wp_ajax_nopriv_wp_exchange_convert', array( $this, 'ajax_convert_price' ) );
		add_action( 'wp_ajax_wp_exchange_set_currency', array( $this, 'ajax_set_currency' ) );
		add_action( 'wp_ajax_nopriv_wp_exchange_set_currency', array( $this, 'ajax_set_currency' ) );

		$position = get_option( 'wp_exchange_switcher_position', 'shortcode' );
		if ( 'header' === $position ) {
			add_action( 'wp_head', array( $this, 'render_switcher' ) );
		} elseif ( 'footer' === $position ) {
			add_action( 'wp_footer', array( $this, 'render_switcher' ) );
		}
	}

	/**
	 * Enqueue frontend CSS and JS.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'wp-exchange',
			WP_EXCHANGE_PLUGIN_URL . 'assets/css/wp-exchange.css',
			array(),
			WP_EXCHANGE_VERSION
		);

		wp_enqueue_script(
			'wp-exchange',
			WP_EXCHANGE_PLUGIN_URL . 'assets/js/wp-exchange.js',
			array( 'jquery' ),
			WP_EXCHANGE_VERSION,
			true
		);

		wp_localize_script(
			'wp-exchange',
			'wp_exchange',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wp_exchange_nonce' ),
				'currency' => $this->currency->get_user_currency(),
				'i18n'     => array(
					'converting' => __( 'Converting...', 'wp-exchange' ),
				),
			)
		);
	}

	/**
	 * Convert a single product price HTML.
	 *
	 * @param  string        $price_html Original price HTML.
	 * @param  \WC_Product   $product    Product object.
	 * @return string Modified price HTML.
	 */
	public function convert_price_html( string $price_html, \WC_Product $product ): string {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price_html;
		}

		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		if ( $user_currency === $base ) {
			return $price_html;
		}

		$price = (float) $product->get_price();

		if ( $price <= 0 ) {
			return $price_html;
		}

		$converted = $this->currency->convert_price( $price, $base, $user_currency );

		if ( $converted === $price ) {
			return $price_html;
		}

		$style    = get_option( 'wp_exchange_display_style', 'inline' );
		$original = $this->currency->format_price( $price, $base );
		$new      = $this->currency->format_price( $converted, $user_currency );

		if ( 'badge' === $style ) {
			return sprintf(
				'<span class="wp-exchange-price-badge">%s</span>',
				wp_kses_post( $new )
			);
		}

		return sprintf(
			'<span class="wp-exchange-price-converted" title="%s">%s <small class="wp-exchange-original">(%s)</small></span>',
			esc_attr( $original ),
			wp_kses_post( $new ),
			wp_kses_post( $original )
		);
	}

	/**
	 * Convert variable product price range HTML.
	 *
	 * @param  string      $price_html Original price HTML.
	 * @param  \WC_Product $product    Variable product object.
	 * @return string Modified price HTML.
	 */
	public function convert_variable_price_html( string $price_html, \WC_Product $product ): string {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $price_html;
		}

		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		if ( $user_currency === $base ) {
			return $price_html;
		}

		$min_price = (float) $product->get_variation_price( 'min' );
		$max_price = (float) $product->get_variation_price( 'max' );

		if ( $min_price <= 0 && $max_price <= 0 ) {
			return $price_html;
		}

		$converted_min = $this->currency->convert_price( $min_price, $base, $user_currency );
		$converted_max = $this->currency->convert_price( $max_price, $base, $user_currency );

		if ( $converted_min === $min_price && $converted_max === $max_price ) {
			return $price_html;
		}

		$style = get_option( 'wp_exchange_display_style', 'inline' );

		if ( $converted_min === $converted_max ) {
			$new_price = $this->currency->format_price( $converted_min, $user_currency );
		} else {
			$new_price = sprintf(
				'%s &ndash; %s',
				$this->currency->format_price( $converted_min, $user_currency ),
				$this->currency->format_price( $converted_max, $user_currency )
			);
		}

		if ( 'badge' === $style ) {
			return sprintf(
				'<span class="wp-exchange-price-badge">%s</span>',
				wp_kses_post( $new_price )
			);
		}

		return sprintf(
			'<span class="wp-exchange-price-converted" title="%s">%s</span>',
			esc_attr( wp_strip_all_tags( $price_html ) ),
			wp_kses_post( $new_price )
		);
	}

	/**
	 * Convert cart item price.
	 *
	 * @param  string $price_html Price HTML.
	 * @param  array  $cart_item Cart item data.
	 * @param  string $cart_item_key Cart item key.
	 * @return string
	 */
	public function convert_cart_item_price( string $price_html, array $cart_item, string $cart_item_key ): string {
		return $this->convert_cart_price( $price_html, $cart_item['data'] ?? null );
	}

	/**
	 * Convert cart subtotal.
	 *
	 * @param  string   $subtotal Subtotal HTML.
	 * @param  bool     $compound Whether to include compound taxes.
	 * @param  WC_Cart  $cart     Cart object.
	 * @return string
	 */
	public function convert_cart_subtotal( string $subtotal, bool $compound, $cart ): string {
		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		if ( $user_currency === $base ) {
			return $subtotal;
		}

		$cart_subtotal = $compound ? $cart->get_cart_contents_total() + $cart->get_shipping_total() : $cart->get_subtotal();
		$converted     = $this->currency->convert_price( (float) $cart_subtotal, $base, $user_currency );

		if ( $converted === (float) $cart_subtotal ) {
			return $subtotal;
		}

		return $this->currency->format_price( $converted, $user_currency );
	}

	/**
	 * Convert coupon amount HTML.
	 *
	 * @param  string   $coupon_html Coupon HTML.
	 * @param  WC_Coupon $coupon     Coupon object.
	 * @return string
	 */
	public function convert_coupon_html( string $coupon_html, $coupon ): string {
		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		if ( $user_currency === $base ) {
			return $coupon_html;
		}

		$amount    = (float) $coupon->get_amount();
		$converted = $this->currency->convert_price( $amount, $base, $user_currency );

		if ( $converted === $amount ) {
			return $coupon_html;
		}

		return str_replace(
			wc_price( $amount, array( 'currency' => $base ) ),
			$this->currency->format_price( $converted, $user_currency ),
			$coupon_html
		);
	}

	/**
	 * Convert cart total.
	 *
	 * @param  string $total Total HTML.
	 * @return string
	 */
	public function convert_cart_total( string $total ): string {
		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		if ( $user_currency === $base ) {
			return $total;
		}

		$cart_total = (float) WC()->cart->get_total( 'edit' );
		$converted  = $this->currency->convert_price( $cart_total, $base, $user_currency );

		if ( $converted === $cart_total ) {
			return $total;
		}

		return $this->currency->format_price( $converted, $user_currency );
	}

	/**
	 * Helper to convert a price string in cart context.
	 *
	 * @param  string         $price_html Original price HTML.
	 * @param  \WC_Product|null $product    Product object.
	 * @return string
	 */
	private function convert_cart_price( string $price_html, $product ): string {
		if ( ! $product ) {
			return $price_html;
		}

		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		if ( $user_currency === $base ) {
			return $price_html;
		}

		$price = (float) $product->get_price();

		if ( $price <= 0 ) {
			return $price_html;
		}

		$converted = $this->currency->convert_price( $price, $base, $user_currency );

		if ( $converted === $price ) {
			return $price_html;
		}

		return $this->currency->format_price( $converted, $user_currency );
	}

	/**
	 * Render the currency switcher dropdown.
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_switcher( array $atts = array() ): string {
		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		$rates = array();
		$api   = new WP_Exchange_API( new WP_Exchange_Cache() );
		$data  = $api->fetch_rates( $base );

		if ( ! empty( $data['conversion_rates'] ) ) {
			$rates = $data['conversion_rates'];
		}

		if ( empty( $rates ) ) {
			$rates = array( $base => 1.0 );
		}

		$output = '<div class="wp-exchange-switcher">';
		$output .= '<select name="wp_exchange_currency" class="wp-exchange-currency-select">';

		$selected = strtoupper( $user_currency );

		foreach ( array_keys( $rates ) as $code ) {
			$code       = strtoupper( $code );
			$name       = $this->currency->get_currency_name( $code );
			$symbol     = $this->currency->get_currency_symbol( $code );
			$label      = sprintf( '%s (%s) - %s', $code, $symbol, $name );
			$is_selected = selected( $selected, $code, false );

			$output .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $code ),
				$is_selected,
				esc_html( $label )
			);
		}

		$output .= '</select>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * AJAX handler: convert a given price on the fly.
	 *
	 * @return void
	 */
	public function ajax_convert_price(): void {
		check_ajax_referer( 'wp_exchange_nonce', 'nonce' );

		$amount  = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;
		$from    = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to      = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';

		if ( $amount <= 0 || empty( $from ) || empty( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'wp-exchange' ) ) );
		}

		$converted = $this->currency->convert_price( $amount, $from, $to );

		wp_send_json_success(
			array(
				'original'  => $amount,
				'converted' => $converted,
				'from'      => $from,
				'to'        => $to,
				'formatted' => $this->currency->format_price( $converted, $to ),
			)
		);
	}

	/**
	 * AJAX handler: store user's currency preference.
	 *
	 * @return void
	 */
	public function ajax_set_currency(): void {
		check_ajax_referer( 'wp_exchange_nonce', 'nonce' );

		$currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';

		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid currency code.', 'wp-exchange' ) ) );
		}

		setcookie(
			WP_EXCHANGE_SESSION_KEY,
			$currency,
			time() + DAY_IN_SECONDS * 30,
			COOKIEPATH,
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);

		wp_send_json_success(
			array(
				'currency' => $currency,
			)
		);
	}
}
