<?php
/**
 * Admin settings for WP Currency Exchange.
 *
 * @package WP_Exchange
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WP_Exchange_Admin
 */
class WP_Exchange_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wp_exchange_save_providers', array( $this, 'save_providers' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_filter(
			'plugin_action_links_' . WP_EXCHANGE_PLUGIN_BASENAME,
			array( $this, 'plugin_action_links' )
		);
	}

	/**
	 * Add admin menu page under WooCommerce.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Currency Exchange', 'wp-exchange' ),
			__( 'Currency Exchange', 'wp-exchange' ),
			'manage_woocommerce',
			'wp-exchange',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// General settings.
		register_setting( 'wp_exchange_settings', 'wp_exchange_base_currency', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wp_exchange_settings', 'wp_exchange_cache_duration', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'wp_exchange_settings', 'wp_exchange_enable_ip_detection', array( 'sanitize_callback' => array( $this, 'sanitize_yes_no' ) ) );
		register_setting( 'wp_exchange_settings', 'wp_exchange_display_style', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wp_exchange_settings', 'wp_exchange_switcher_position', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wp_exchange_settings', 'wp_exchange_enable_round_robin', array( 'sanitize_callback' => array( $this, 'sanitize_yes_no' ) ) );

		add_settings_section(
			'wp_exchange_general',
			__( 'General Settings', 'wp-exchange' ),
			null,
			'wp_exchange_settings'
		);

		add_settings_field(
			'wp_exchange_base_currency',
			__( 'Base Currency', 'wp-exchange' ),
			array( $this, 'render_currency_select' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);

		add_settings_field(
			'wp_exchange_cache_duration',
			__( 'Cache Duration (hours)', 'wp-exchange' ),
			array( $this, 'render_cache_duration' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);

		add_settings_field(
			'wp_exchange_enable_ip_detection',
			__( 'Enable IP Detection', 'wp-exchange' ),
			array( $this, 'render_ip_detection' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);

		add_settings_field(
			'wp_exchange_display_style',
			__( 'Display Style', 'wp-exchange' ),
			array( $this, 'render_display_style' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);

		add_settings_field(
			'wp_exchange_switcher_position',
			__( 'Switcher Position', 'wp-exchange' ),
			array( $this, 'render_switcher_position' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);

		add_settings_field(
			'wp_exchange_enable_round_robin',
			__( 'Enable Round-Robin', 'wp-exchange' ),
			array( $this, 'render_round_robin' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Currency Exchange Settings', 'wp-exchange' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="?page=wp-exchange&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'wp-exchange' ); ?>
				</a>
				<a href="?page=wp-exchange&tab=providers" class="nav-tab <?php echo 'providers' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Providers', 'wp-exchange' ); ?>
				</a>
			</h2>

			<?php if ( 'providers' === $active_tab ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wp_exchange_save_providers" />
					<?php $this->render_providers_tab(); ?>
				</form>
			<?php else : ?>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'wp_exchange_settings' );
					do_settings_sections( 'wp_exchange_settings' );
					submit_button();
					?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the providers tab with per-provider credential forms.
	 *
	 * @return void
	 */
	private function render_providers_tab(): void {
		$providers         = apply_filters( 'wp_exchange_providers', array() );
		$order             = get_option( 'wp_exchange_provider_order', array() );
		$provider_settings = get_option( 'wp_exchange_providers_settings', array() );

		if ( ! empty( $order ) ) {
			$ordered = array();
			foreach ( $order as $id ) {
				foreach ( $providers as $p ) {
					if ( $p->get_id() === $id ) {
						$ordered[] = $p;
						break;
					}
				}
			}
			foreach ( $providers as $p ) {
				if ( ! in_array( $p->get_id(), $order, true ) ) {
					$ordered[] = $p;
				}
			}
			$providers = $ordered;
		}

		wp_nonce_field( 'wp_exchange_save_providers', 'wp_exchange_providers_nonce' );
		?>
		<table class="form-table wp-exchange-providers-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Provider', 'wp-exchange' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Credentials', 'wp-exchange' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'wp-exchange' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $providers as $provider ) : ?>
					<tr class="wp-exchange-provider-row" data-provider-id="<?php echo esc_attr( $provider->get_id() ); ?>">
						<td>
							<strong><?php echo esc_html( $provider->get_name() ); ?></strong>
						</td>
						<td>
							<?php
							$fields       = $provider->get_settings_fields();
							$saved_values = $provider_settings[ $provider->get_id() ] ?? array();

							if ( empty( $fields ) ) {
								echo '<em>' . esc_html__( 'No credentials required.', 'wp-exchange' ) . '</em>';
							} else {
								foreach ( $fields as $field ) {
									$field_id    = $provider->get_id() . '_' . $field['id'];
									$field_name  = 'wp_exchange_providers_settings[' . $provider->get_id() . '][' . $field['id'] . ']';
									$field_value = $saved_values[ $field['id'] ] ?? '';
									?>
									<p>
										<label for="<?php echo esc_attr( $field_id ); ?>">
											<?php echo esc_html( $field['label'] ); ?>
										</label><br>
										<input
											type="<?php echo esc_attr( $field['type'] ); ?>"
											id="<?php echo esc_attr( $field_id ); ?>"
											name="<?php echo esc_attr( $field_name ); ?>"
											value="<?php echo esc_attr( $field_value ); ?>"
											class="regular-text"
										/>
										<?php if ( ! empty( $field['description'] ) ) : ?>
											<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
										<?php endif; ?>
									</p>
									<?php
								}
							}
							?>
						</td>
						<td>
							<?php
							$validation = $provider->validate_credentials( $saved_values );
							if ( true === $validation ) {
								echo '<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> ';
								esc_html_e( 'Configured', 'wp-exchange' );
							} else {
								echo '<span class="dashicons dashicons-warning" style="color:#dc3232;"></span> ';
								esc_html_e( 'Not Configured', 'wp-exchange' );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<input type="hidden" name="wp_exchange_provider_order" id="wp_exchange_provider_order" value="<?php echo esc_attr( wp_json_encode( $order ) ); ?>" />

		<?php submit_button( __( 'Save Providers', 'wp-exchange' ) ); ?>

		<script>
		jQuery( function( $ ) {
			var table = $( '.wp-exchange-providers-table tbody' );
			table.sortable( {
				handle: 'td:first',
				update: function() {
					var order = [];
					$( '.wp-exchange-provider-row' ).each( function() {
						order.push( $( this ).data( 'provider-id' ) );
					} );
					$( '#wp_exchange_provider_order' ).val( JSON.stringify( order ) );
				}
			} );
		} );
		</script>
		<style>
		.wp-exchange-providers-table tbody tr td:first-child {
			cursor: grab;
			width: 200px;
		}
		.wp-exchange-providers-table tbody tr td:first-child:active {
			cursor: grabbing;
		}
		</style>
		<?php
	}

	/**
	 * Save provider settings on form submission.
	 *
	 * @return void
	 */
	public function save_providers(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! isset( $_POST['wp_exchange_providers_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wp_exchange_providers_nonce'] ), 'wp_exchange_save_providers' ) ) {
			return;
		}

		if ( isset( $_POST['wp_exchange_providers_settings'] ) ) {
			$raw     = wp_unslash( $_POST['wp_exchange_providers_settings'] );
			$sanitized = array();

			foreach ( $raw as $provider_id => $fields ) {
				$provider_id = sanitize_text_field( $provider_id );

				foreach ( $fields as $key => $value ) {
					$sanitized[ $provider_id ][ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
				}
			}

			update_option( 'wp_exchange_providers_settings', $sanitized );
		}

		if ( isset( $_POST['wp_exchange_provider_order'] ) ) {
			$order_raw = sanitize_text_field( wp_unslash( $_POST['wp_exchange_provider_order'] ) );
			$order     = json_decode( $order_raw, true );

			if ( is_array( $order ) ) {
				update_option( 'wp_exchange_provider_order', $order );
			}
		}

		$cache = new WP_Exchange_Cache();
		$cache->clear_rates();

		add_settings_error(
			'wp_exchange',
			'providers_saved',
			__( 'Provider settings saved.', 'wp-exchange' ),
			'success'
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wp-exchange',
					'tab'  => 'providers',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Display admin notices for provider save actions.
	 *
	 * @return void
	 */
	public function admin_notices(): void {
		settings_errors( 'wp_exchange' );
	}

	/**
	 * Render base currency select field.
	 *
	 * @return void
	 */
	public function render_currency_select(): void {
		$selected = get_option( 'wp_exchange_base_currency', 'USD' );
		$common   = array( 'USD', 'EUR', 'GBP', 'JPY', 'CNY', 'KRW', 'HKD', 'SGD', 'MYR', 'IDR', 'INR', 'BRL', 'CAD', 'AUD', 'CHF', 'SEK', 'NOK', 'DKK', 'NZD', 'ZAR' );
		?>
		<select name="wp_exchange_base_currency">
			<?php foreach ( $common as $code ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $selected, $code ); ?>>
					<?php echo esc_html( $code ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Your store\'s default base currency. All prices are converted from this currency.', 'wp-exchange' ); ?></p>
		<?php
	}

	/**
	 * Render cache duration field.
	 *
	 * @return void
	 */
	public function render_cache_duration(): void {
		$value = get_option( 'wp_exchange_cache_duration', 24 );
		?>
		<input type="number" name="wp_exchange_cache_duration" value="<?php echo esc_attr( $value ); ?>" min="1" max="168" class="small-text" />
		<p class="description"><?php esc_html_e( 'How often to refresh exchange rates from the provider. Minimum 1 hour, maximum 168 hours (7 days).', 'wp-exchange' ); ?></p>
		<?php
	}

	/**
	 * Render IP detection toggle.
	 *
	 * @return void
	 */
	public function render_ip_detection(): void {
		$value = get_option( 'wp_exchange_enable_ip_detection', 'yes' );
		?>
		<label>
			<input type="checkbox" name="wp_exchange_enable_ip_detection" value="yes" <?php checked( $value, 'yes' ); ?> />
			<?php esc_html_e( 'Automatically detect currency from visitor IP address', 'wp-exchange' ); ?>
		</label>
		<?php
	}

	/**
	 * Render display style selector.
	 *
	 * @return void
	 */
	public function render_display_style(): void {
		$value = get_option( 'wp_exchange_display_style', 'inline' );
		$styles = array(
			'inline'   => __( 'Inline (original next to converted)', 'wp-exchange' ),
			'badge'    => __( 'Badge (show only converted, flag badge)', 'wp-exchange' ),
			'dropdown' => __( 'Dropdown (let user pick)', 'wp-exchange' ),
		);
		?>
		<select name="wp_exchange_display_style">
			<?php foreach ( $styles as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render switcher position selector.
	 *
	 * @return void
	 */
	public function render_switcher_position(): void {
		$value    = get_option( 'wp_exchange_switcher_position', 'shortcode' );
		$positions = array(
			'header'    => __( 'Header', 'wp-exchange' ),
			'footer'    => __( 'Footer', 'wp-exchange' ),
			'shortcode' => __( 'Shortcode only (use [wp_exchange_switcher] in content or widgets)', 'wp-exchange' ),
		);
		?>
		<select name="wp_exchange_switcher_position">
			<?php foreach ( $positions as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Use shortcode', 'wp-exchange' ); ?>
			<code>[wp_exchange_switcher]</code>
			<?php esc_html_e( 'to place the currency switcher anywhere.', 'wp-exchange' ); ?>
		</p>
		<?php
	}

	/**
	 * Render round-robin toggle.
	 *
	 * @return void
	 */
	public function render_round_robin(): void {
		$value = get_option( 'wp_exchange_enable_round_robin', 'no' );
		?>
		<label>
			<input type="checkbox" name="wp_exchange_enable_round_robin" value="yes" <?php checked( $value, 'yes' ); ?> />
			<?php esc_html_e( 'Distribute API requests across multiple providers for redundancy and quota management', 'wp-exchange' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, the plugin will cycle through configured providers. If one fails, it falls back to the next. Requires at least 2 providers with valid credentials.', 'wp-exchange' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize yes/no fields.
	 *
	 * @param  string $value Input value.
	 * @return string 'yes' or 'no'.
	 */
	public function sanitize_yes_no( string $value ): string {
		return 'yes' === $value ? 'yes' : 'no';
	}

	/**
	 * Add settings link on plugins page.
	 *
	 * @param  array<int, string> $links Existing plugin action links.
	 * @return array<int, string>
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=wp-exchange' ),
			__( 'Settings', 'wp-exchange' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}
