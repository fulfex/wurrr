<?php
/**
 * Frontend hooks for Wurrr Currency Exchange.
 *
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

class Fulfex_Frontend {

	private $currency;

	public function __construct( Fulfex_Currency $currency ) {
		$this->currency = $currency;

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_filter( 'woocommerce_get_price_html', array( $this, 'convert_price_html' ), 10, 2 );
		add_filter( 'woocommerce_variable_price_html', array( $this, 'convert_variable_price_html' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'convert_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'convert_cart_item_subtotal' ), 10, 3 );
		add_filter( 'woocommerce_cart_subtotal', array( $this, 'convert_cart_subtotal' ), 10, 3 );
		add_filter( 'woocommerce_cart_total', array( $this, 'convert_cart_total' ), 10 );
		add_filter( 'woocommerce_cart_totals_coupon_html', array( $this, 'convert_coupon_html' ), 10, 2 );
		add_filter( 'woocommerce_cart_totals_fee_html', array( $this, 'convert_fee_html' ), 10, 2 );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'convert_shipping_label' ), 10, 2 );

		add_shortcode( 'wurrr', array( $this, 'render_shortcode' ) );
		add_shortcode( 'wurrr_switcher', array( $this, 'render_shortcode' ) );

		add_action( 'wp_ajax_wp_exchange_convert', array( $this, 'ajax_convert_price' ) );
		add_action( 'wp_ajax_nopriv_wp_exchange_convert', array( $this, 'ajax_convert_price' ) );
		add_action( 'wp_ajax_wp_exchange_set_currency', array( $this, 'ajax_set_currency' ) );
		add_action( 'wp_ajax_nopriv_wp_exchange_set_currency', array( $this, 'ajax_set_currency' ) );

		$position = get_option( 'wp_exchange_switcher_position', 'shortcode' );
		if ( 'header' === $position ) {
			add_action( 'wp_head', array( $this, 'render_shortcode' ) );
		} elseif ( 'footer' === $position ) {
			add_action( 'wp_footer', array( $this, 'render_shortcode' ) );
		}
	}

	public function enqueue_assets(): void {
		wp_enqueue_style(
			'wurrr',
			WURRR_PLUGIN_URL . 'assets/css/wurrr.css',
			array(),
			WURRR_VERSION
		);

		wp_enqueue_script(
			'wurrr',
			WURRR_PLUGIN_URL . 'assets/js/wurrr.js',
			array( 'jquery' ),
			WURRR_VERSION,
			true
		);

		wp_localize_script(
			'wurrr',
			'wurrr',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wurrr_nonce' ),
				'currency' => $this->currency->get_user_currency(),
				'i18n'     => array(
					'converting' => __( 'Converting...', 'wurrr' ),
				),
			)
		);
	}

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

		$style    = get_option( 'wp_exchange_display_style', 'badge' );
		$original = $this->currency->format_price( $price, $base );
		$new      = $this->currency->format_price( $converted, $user_currency );

		if ( 'badge' === $style ) {
			return sprintf(
				'<span class="wurrr-price-badge">%s</span>',
				wp_kses_post( $new )
			);
		}

		return sprintf(
			'<span class="wurrr-price-converted" title="%s">%s <small class="wurrr-original">(%s)</small></span>',
			esc_attr( $original ),
			wp_kses_post( $new ),
			wp_kses_post( $original )
		);
	}

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

		$style = get_option( 'wp_exchange_display_style', 'badge' );

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
				'<span class="wurrr-price-badge">%s</span>',
				wp_kses_post( $new_price )
			);
		}

		return sprintf(
			'<span class="wurrr-price-converted" title="%s">%s</span>',
			esc_attr( wp_strip_all_tags( $price_html ) ),
			wp_kses_post( $new_price )
		);
	}

	public function convert_cart_item_price( string $price_html, array $cart_item, string $cart_item_key ): string {
		return $this->convert_cart_price( $price_html, $cart_item['data'] ?? null );
	}

	public function convert_cart_item_subtotal( string $subtotal, array $cart_item, string $cart_item_key ): string {
		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		if ( $user_currency === $base ) {
			return $subtotal;
		}

		$line_total = (float) ( $cart_item['line_total'] ?? 0 );
		$amount     = $line_total;
		if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->display_prices_including_tax() ) {
			$amount += (float) ( $cart_item['line_tax'] ?? 0 );
		}

		if ( $amount <= 0 ) {
			return $subtotal;
		}

		$converted = $this->currency->convert_price( $amount, $base, $user_currency );

		if ( $converted === $amount ) {
			return $subtotal;
		}

		return $this->currency->format_price( $converted, $user_currency );
	}

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

	public function convert_fee_html( string $fee_html, $fee ): string {
		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		if ( $user_currency === $base ) {
			return $fee_html;
		}

		$amount = (float) ( $fee->total ?? 0 );
		if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->display_prices_including_tax() ) {
			$amount += (float) ( $fee->tax ?? 0 );
		}

		if ( 0.0 === $amount ) {
			return $fee_html;
		}

		$converted = $this->currency->convert_price( $amount, $base, $user_currency );

		if ( $converted === $amount ) {
			return $fee_html;
		}

		return $this->currency->format_price( $converted, $user_currency );
	}

	public function convert_shipping_label( string $label, $method ): string {
		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		if ( $user_currency === $base || ! is_object( $method ) || ! method_exists( $method, 'get_cost' ) ) {
			return $label;
		}

		$amount = (float) $method->get_cost();
		if ( function_exists( 'WC' ) && WC()->cart && WC()->cart->display_prices_including_tax() && method_exists( $method, 'get_taxes' ) ) {
			$amount += array_sum( array_map( 'floatval', (array) $method->get_taxes() ) );
		}

		if ( $amount <= 0 ) {
			return $label;
		}

		$converted = $this->currency->convert_price( $amount, $base, $user_currency );

		if ( $converted === $amount ) {
			return $label;
		}

		$base_price      = function_exists( 'wc_price' ) ? wc_price( $amount, array( 'currency' => $base ) ) : $this->currency->format_price( $amount, $base );
		$converted_price = $this->currency->format_price( $converted, $user_currency );

		if ( false !== strpos( $label, $base_price ) ) {
			return str_replace( $base_price, $converted_price, $label );
		}

		return preg_replace( '/:\s*<span class="woocommerce-Price-amount amount">.*?<\/span>/s', ': ' . $converted_price, $label ) ?: $label;
	}

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

	public function render_shortcode( array $atts = array() ): string {
		$public = get_option( 'wp_exchange_public_currencies', 'USD,EUR,GBP,JPY,AUD,CAD,CHF,CNY,SGD' );
		$codes  = array_filter( array_map( 'trim', explode( ',', $public ) ) );

		if ( empty( $codes ) ) {
			return '';
		}

		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();
		$selected      = strtoupper( $user_currency );

		$output  = '<div class="wurrr" data-wurrr>';
		$output .= '<select class="wurrr-select" aria-label="' . esc_attr__( 'Currency selector', 'wurrr' ) . '">';

		foreach ( $codes as $code ) {
			$code   = strtoupper( $code );
			$name   = $this->currency->get_currency_name( $code );
			$symbol = $this->currency->get_currency_symbol( $code );
			$label  = sprintf( '%s (%s)', $code, $symbol );
			$is_selected = selected( $selected, $code, false );

			$output .= sprintf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $code ),
				$is_selected,
				esc_html( $label )
			);
		}

		$output .= '</select></div>';

		return $output;
	}

	public function render_switcher( array $atts = array() ): string {
		$user_currency = $this->currency->get_user_currency();
		$base          = $this->currency->get_base_currency();

		$rates = array();
		$api   = new Fulfex_API( new Fulfex_Cache() );
		$data  = $api->fetch_rates( $base );

		if ( ! empty( $data['conversion_rates'] ) ) {
			$rates = $data['conversion_rates'];
		}

		if ( empty( $rates ) ) {
			$rates = array( $base => 1.0 );
		}

		$output = '<div class="wurrr-switcher">';
		$output .= '<select name="wurrr_currency" class="wurrr-currency-select">';

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

	public function ajax_convert_price(): void {
		check_ajax_referer( 'wurrr_nonce', 'nonce' );

		$amount = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;
		$from   = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to     = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';

		if ( $amount <= 0 || empty( $from ) || empty( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'wurrr' ) ) );
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

	public function ajax_set_currency(): void {
		check_ajax_referer( 'wurrr_nonce', 'nonce' );

		$currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : '';

		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid currency code.', 'wurrr' ) ) );
		}

		setcookie(
			WURRR_SESSION_KEY,
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
