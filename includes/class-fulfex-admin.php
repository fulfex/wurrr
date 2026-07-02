<?php
/**
 * Admin settings for Wurrr Currency Exchange.
 *
 * @package Wurrr
 */

defined( 'ABSPATH' ) || exit;

class Fulfex_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wp_exchange_save_providers', array( $this, 'save_providers' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_wurrr_health_test', array( $this, 'ajax_health_test' ) );
		add_filter(
			'plugin_action_links_' . WURRR_PLUGIN_BASENAME,
			array( $this, 'plugin_action_links' )
		);
	}

	public function add_admin_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Currency Exchange', 'wurrr' ),
			__( 'Currency (Wurrr)', 'wurrr' ),
			'manage_woocommerce',
			'wurrr',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings(): void {
		register_setting( 'wp_exchange_settings', 'wp_exchange_base_currency', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wp_exchange_settings', 'wp_exchange_cache_duration', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'wp_exchange_settings', 'wp_exchange_enable_ip_detection', array( 'sanitize_callback' => array( $this, 'sanitize_yes_no' ) ) );
		register_setting( 'wp_exchange_settings', 'wp_exchange_display_style', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wp_exchange_settings', 'wp_exchange_switcher_position', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'wp_exchange_settings', 'wp_exchange_enable_round_robin', array( 'sanitize_callback' => array( $this, 'sanitize_yes_no' ) ) );

		add_settings_section(
			'wp_exchange_general',
			__( 'General Settings', 'wurrr' ),
			null,
			'wp_exchange_settings'
		);

		add_settings_field(
			'wp_exchange_base_currency',
			__( 'Base Currency', 'wurrr' ),
			array( $this, 'render_currency_select' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);

		add_settings_field(
			'wp_exchange_cache_duration',
			__( 'Cache Duration (hours)', 'wurrr' ),
			array( $this, 'render_cache_duration' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);

		add_settings_field(
			'wp_exchange_enable_ip_detection',
			__( 'Enable IP Detection', 'wurrr' ),
			array( $this, 'render_ip_detection' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);

		add_settings_field(
			'wp_exchange_display_style',
			__( 'Display Style', 'wurrr' ),
			array( $this, 'render_display_style' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);

		add_settings_field(
			'wp_exchange_switcher_position',
			__( 'Switcher Position', 'wurrr' ),
			array( $this, 'render_switcher_position' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);

		add_settings_field(
			'wp_exchange_enable_round_robin',
			__( 'Enable Round-Robin', 'wurrr' ),
			array( $this, 'render_round_robin' ),
			'wp_exchange_settings',
			'wp_exchange_general'
		);
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Currency Exchange Settings 🐱', 'wurrr' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="?page=wurrr&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'wurrr' ); ?>
				</a>
				<a href="?page=wurrr&tab=providers" class="nav-tab <?php echo 'providers' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Providers', 'wurrr' ); ?>
				</a>
				<a href="?page=wurrr&tab=health" class="nav-tab <?php echo 'health' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Health Monitor', 'wurrr' ); ?>
				</a>
			</h2>

			<?php if ( 'providers' === $active_tab ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wp_exchange_save_providers" />
					<?php $this->render_providers_tab(); ?>
				</form>
			<?php elseif ( 'health' === $active_tab ) : ?>
				<?php $this->render_health_tab(); ?>
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
					<th scope="col"><?php esc_html_e( 'Provider', 'wurrr' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Credentials', 'wurrr' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'wurrr' ); ?></th>
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
								echo '<em>' . esc_html__( 'No credentials required.', 'wurrr' ) . '</em>';
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
								esc_html_e( 'Configured', 'wurrr' );
							} else {
								echo '<span class="dashicons dashicons-warning" style="color:#dc3232;"></span> ';
								esc_html_e( 'Not Configured', 'wurrr' );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<input type="hidden" name="wp_exchange_provider_order" id="wp_exchange_provider_order" value="<?php echo esc_attr( wp_json_encode( $order ) ); ?>" />

		<?php submit_button( __( 'Save Providers', 'wurrr' ) ); ?>

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

	private function render_health_tab(): void {
		$providers = apply_filters( 'wp_exchange_providers', array() );
		$health    = get_option( 'wp_exchange_provider_health', array() );
		$base      = get_option( 'wp_exchange_base_currency', 'USD' );

		?>
		<p>
			<?php esc_html_e( 'Real-time health and performance of each exchange rate provider.', 'wurrr' ); ?>
			<button type="button" id="wurrr-test-all" class="button button-secondary" style="margin-left:1em;">
				<?php esc_html_e( 'Test All Providers Now', 'wurrr' ); ?>
			</button>
			<span id="wurrr-test-status" style="margin-left:1em; display:none;"></span>
		</p>

		<table class="widefat striped" id="wurrr-health-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'wurrr' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wurrr' ); ?></th>
					<th><?php esc_html_e( 'Last Success', 'wurrr' ); ?></th>
					<th><?php esc_html_e( 'Last Error', 'wurrr' ); ?></th>
					<th><?php esc_html_e( 'Requests', 'wurrr' ); ?></th>
					<th><?php esc_html_e( 'Error Rate', 'wurrr' ); ?></th>
					<th><?php esc_html_e( 'Cache', 'wurrr' ); ?></th>
					<th><?php esc_html_e( 'Action', 'wurrr' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $providers as $provider ) : ?>
					<?php
					$pid = $provider->get_id();
					$h   = $health[ $pid ] ?? array();

					$last_success   = $h['last_success'] ?? 0;
					$last_error     = $h['last_error'] ?? 0;
					$last_error_msg = $h['last_error_msg'] ?? '';
					$total_requests = (int) ( $h['total_requests'] ?? 0 );
					$total_errors   = (int) ( $h['total_errors'] ?? 0 );
					$error_rate     = $total_requests > 0 ? round( ( $total_errors / $total_requests ) * 100, 1 ) : 0;
					$avg_latency    = isset( $h['avg_latency'] ) ? number_format( $h['avg_latency'], 2 ) : '—';

					$cache = new Fulfex_Cache();
					$cached = $cache->get_rates( $pid, $base );
					$stale  = $cache->get_stale_rates( $pid, $base );

					if ( false !== $cached && ! empty( $cached['conversion_rates'] ) ) {
						$cache_status = __( 'Fresh', 'wurrr' );
						$cache_icon   = '🟢';
					} elseif ( false !== $stale && ! empty( $stale['conversion_rates'] ) ) {
						$cache_status = __( 'Stale', 'wurrr' );
						$cache_icon   = '🟡';
					} else {
						$cache_status = __( 'Empty', 'wurrr' );
						$cache_icon   = '⚪';
					}

					if ( $last_success > 0 && ( $last_error === 0 || $last_success > $last_error ) ) {
						$status       = __( 'Healthy', 'wurrr' );
						$status_icon  = '🟢';
						$status_class = 'healthy';
					} elseif ( $last_error > 0 && $last_success === 0 ) {
						$status       = __( 'Failing', 'wurrr' );
						$status_icon  = '🔴';
						$status_class = 'failing';
					} elseif ( $last_error > $last_success ) {
						$status       = __( 'Degraded', 'wurrr' );
						$status_icon  = '🟠';
						$status_class = 'degraded';
					} else {
						$status       = __( 'Unknown', 'wurrr' );
						$status_icon  = '⚪';
						$status_class = 'unknown';
					}

					$settings = get_option( 'wp_exchange_providers_settings', array() );
					$creds    = $settings[ $pid ] ?? array();
					$valid    = $provider->validate_credentials( $creds );
					?>
					<tr class="wurrr-health-row" data-provider-id="<?php echo esc_attr( $pid ); ?>">
						<td>
							<strong><?php echo esc_html( $provider->get_name() ); ?></strong><br>
							<small><code><?php echo esc_html( $pid ); ?></code></small>
							<?php if ( true !== $valid ) : ?>
								<br><small style="color:#dc3232;"><?php esc_html_e( '(not configured)', 'wurrr' ); ?></small>
							<?php endif; ?>
						</td>
						<td class="health-status <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_icon . ' ' . $status ); ?>
						</td>
						<td>
							<?php
							if ( $last_success ) {
								echo esc_html( gmdate( 'Y-m-d H:i:s', $last_success ) );
								if ( $avg_latency !== '—' ) {
									echo '<br><small>' . sprintf( esc_html__( '%ss avg', 'wurrr' ), esc_html( $avg_latency ) ) . '</small>';
								}
							} else {
								echo '—';
							}
							?>
						</td>
						<td>
							<?php
							if ( $last_error ) {
								echo esc_html( gmdate( 'Y-m-d H:i:s', $last_error ) );
								if ( $last_error_msg ) {
									echo '<br><small style="color:#dc3232;">' . esc_html( $last_error_msg ) . '</small>';
								}
							} else {
								echo '—';
							}
							?>
						</td>
						<td><?php echo esc_html( $total_requests ); ?></td>
						<td>
							<?php echo esc_html( $error_rate ); ?>%
							<small>(<?php echo esc_html( $total_errors ); ?>/<?php echo esc_html( $total_requests ); ?>)</small>
						</td>
						<td>
							<?php echo esc_html( $cache_icon . ' ' . $cache_status ); ?>
						</td>
						<td>
							<button type="button" class="button button-small wurrr-test-provider" data-provider-id="<?php echo esc_attr( $pid ); ?>">
								<?php esc_html_e( 'Test Now', 'wurrr' ); ?>
							</button>
							<span class="wurrr-test-result" style="margin-left:0.5em;"></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<script>
		jQuery( function( $ ) {
			function testProvider( providerId, $button ) {
				var $row = $button.closest( 'tr' );
				var $result = $row.find( '.wurrr-test-result' );

				$button.prop( 'disabled', true );
				$result.text( '<?php echo esc_js( __( 'Testing…', 'wurrr' ) ); ?>' );

				$.post( ajaxurl, {
					action: 'wurrr_health_test',
					provider_id: providerId,
					nonce: '<?php echo esc_js( wp_create_nonce( 'wurrr_health_nonce' ) ); ?>'
				}, function( response ) {
					$button.prop( 'disabled', false );
					if ( response.success ) {
						$result.css( 'color', '#46b450' ).text( '<?php echo esc_js( __( 'OK', 'wurrr' ) ); ?> (' + response.data.latency + 's)' );
						$row.find( '.health-status' ).html( '🟢 <?php echo esc_js( __( 'Healthy', 'wurrr' ) ); ?>' );
					} else {
						$result.css( 'color', '#dc3232' ).text( '<?php echo esc_js( __( 'Failed', 'wurrr' ) ); ?>' );
						$row.find( '.health-status' ).html( '🔴 <?php echo esc_js( __( 'Failing', 'wurrr' ) ); ?>' );
					}
					setTimeout( function() { location.reload(); }, 3000 );
				} );
			}

			$( '.wurrr-test-provider' ).on( 'click', function() {
				testProvider( $( this ).data( 'provider-id' ), $( this ) );
			} );

			$( '#wurrr-test-all' ).on( 'click', function() {
				var $btn = $( this );
				var $status = $( '#wurrr-test-status' );
				$btn.prop( 'disabled', true );
				$status.show().text( '<?php echo esc_js( __( 'Testing all providers…', 'wurrr' ) ); ?>' );

				var rows = $( '.wurrr-health-row' );
				var done = 0;
				rows.each( function() {
					var $row = $( this );
					var pid = $row.data( 'provider-id' );
					var $b = $row.find( '.wurrr-test-provider' );

					$.post( ajaxurl, {
						action: 'wurrr_health_test',
						provider_id: pid,
						nonce: '<?php echo esc_js( wp_create_nonce( 'wurrr_health_nonce' ) ); ?>'
					}, function( response ) {
						done++;
						if ( done >= rows.length ) {
							$btn.prop( 'disabled', false );
							$status.text( '<?php echo esc_js( __( 'All tests complete. Reloading…', 'wurrr' ) ); ?>' );
							setTimeout( function() { location.reload(); }, 2000 );
						}
					} );
				} );
			} );
		} );
		</script>
		<style>
		.health-status.healthy { color: #46b450; font-weight: 600; }
		.health-status.degraded { color: #f0ad4e; font-weight: 600; }
		.health-status.failing { color: #dc3232; font-weight: 600; }
		.health-status.unknown { color: #666; }
		</style>
		<?php
	}

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

		$cache = new Fulfex_Cache();
		$cache->clear_rates();

		add_settings_error(
			'wp_exchange',
			'providers_saved',
			__( 'Provider settings saved.', 'wurrr' ),
			'success'
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'wurrr',
					'tab'  => 'providers',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function admin_notices(): void {
		settings_errors( 'wp_exchange' );
	}

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
		<p class="description"><?php esc_html_e( 'Your store\'s default base currency. All prices are converted from this currency.', 'wurrr' ); ?></p>
		<?php
	}

	public function render_cache_duration(): void {
		$value = get_option( 'wp_exchange_cache_duration', 24 );
		?>
		<input type="number" name="wp_exchange_cache_duration" value="<?php echo esc_attr( $value ); ?>" min="1" max="168" class="small-text" />
		<p class="description"><?php esc_html_e( 'How often to refresh exchange rates from the provider. Minimum 1 hour, maximum 168 hours (7 days).', 'wurrr' ); ?></p>
		<?php
	}

	public function render_ip_detection(): void {
		$value = get_option( 'wp_exchange_enable_ip_detection', 'yes' );
		?>
		<label>
			<input type="checkbox" name="wp_exchange_enable_ip_detection" value="yes" <?php checked( $value, 'yes' ); ?> />
			<?php esc_html_e( 'Automatically detect currency from visitor IP address', 'wurrr' ); ?>
		</label>
		<?php
	}

	public function render_display_style(): void {
		$value = get_option( 'wp_exchange_display_style', 'inline' );
		$styles = array(
			'inline'   => __( 'Inline (original next to converted)', 'wurrr' ),
			'badge'    => __( 'Badge (show only converted, flag badge)', 'wurrr' ),
			'dropdown' => __( 'Dropdown (let user pick)', 'wurrr' ),
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

	public function render_switcher_position(): void {
		$value    = get_option( 'wp_exchange_switcher_position', 'shortcode' );
		$positions = array(
			'header'    => __( 'Header', 'wurrr' ),
			'footer'    => __( 'Footer', 'wurrr' ),
			'shortcode' => __( 'Shortcode only (use [wurrr_switcher] in content or widgets)', 'wurrr' ),
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
			<?php esc_html_e( 'Use shortcode', 'wurrr' ); ?>
			<code>[wurrr_switcher]</code>
			<?php esc_html_e( 'to place the currency switcher anywhere.', 'wurrr' ); ?>
		</p>
		<?php
	}

	public function render_round_robin(): void {
		$value = get_option( 'wp_exchange_enable_round_robin', 'no' );
		?>
		<label>
			<input type="checkbox" name="wp_exchange_enable_round_robin" value="yes" <?php checked( $value, 'yes' ); ?> />
			<?php esc_html_e( 'Distribute API requests across multiple providers for redundancy and quota management', 'wurrr' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, the plugin will cycle through configured providers. If one fails, it falls back to the next. Requires at least 2 providers with valid credentials.', 'wurrr' ); ?>
		</p>
		<?php
	}

	public function sanitize_yes_no( string $value ): string {
		return 'yes' === $value ? 'yes' : 'no';
	}

	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=wurrr' ),
			__( 'Settings 🐱', 'wurrr' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function ajax_health_test(): void {
		check_ajax_referer( 'wurrr_health_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$provider_id = isset( $_POST['provider_id'] ) ? sanitize_text_field( wp_unslash( $_POST['provider_id'] ) ) : '';

		$providers = apply_filters( 'wp_exchange_providers', array() );
		$target    = null;

		foreach ( $providers as $p ) {
			if ( $p->get_id() === $provider_id ) {
				$target = $p;
				break;
			}
		}

		if ( ! $target ) {
			wp_send_json_error( array( 'message' => __( 'Provider not found.', 'wurrr' ) ) );
		}

		$base    = get_option( 'wp_exchange_base_currency', 'USD' );
		$start   = microtime( true );
		$rates   = $target->fetch_rates( $base );
		$latency = round( microtime( true ) - $start, 4 );

		$health = get_option( 'wp_exchange_provider_health', array() );
		$h      = $health[ $provider_id ] ?? array();
		$h['total_requests'] = (int) ( $h['total_requests'] ?? 0 ) + 1;

		if ( ! empty( $rates['conversion_rates'] ) ) {
			$h['last_success'] = time();
			$h['last_error']   = $h['last_error'] ?? 0;
			$h['last_error_msg'] = '';

			if ( isset( $h['total_latency'] ) ) {
				$h['total_latency'] += $latency;
			} else {
				$h['total_latency'] = $latency;
			}
		} else {
			$h['last_error']     = time();
			$h['last_error_msg'] = __( 'No rates returned.', 'wurrr' );
			$h['total_errors']   = (int) ( $h['total_errors'] ?? 0 ) + 1;
		}

		if ( $h['total_requests'] > 0 && isset( $h['total_latency'] ) ) {
			$h['avg_latency'] = round( $h['total_latency'] / $h['total_requests'], 4 );
		}

		$health[ $provider_id ] = $h;
		update_option( 'wp_exchange_provider_health', $health );

		if ( ! empty( $rates['conversion_rates'] ) ) {
			wp_send_json_success(
				array(
					'latency'   => $latency,
					'rates'     => count( $rates['conversion_rates'] ) . ' ' . __( 'currencies', 'wurrr' ),
					'providers_count' => count( $rates['conversion_rates'] ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to fetch rates.', 'wurrr' ) ) );
		}
	}
}
