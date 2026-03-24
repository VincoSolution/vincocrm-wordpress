<?php
/**
 * VincoCRM Settings
 *
 * Registers and renders the plugin's settings page under the WordPress
 * Settings menu, and stores the options in the database.
 *
 * @package VincoCRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * VincoCRM_Settings class.
 */
class VincoCRM_Settings {

	/**
	 * The option group name used with register_setting().
	 */
	const OPTION_GROUP = 'vincocrm_settings';

	/**
	 * The option name stored in wp_options.
	 */
	const OPTION_NAME = 'vincocrm_options';

	/**
	 * Initialise hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
	}

	/**
	 * Add a "VincoCRM" entry under the Settings menu.
	 */
	public static function add_settings_page() {
		add_options_page(
			__( 'VincoCRM Settings', 'vincocrm' ),
			__( 'VincoCRM', 'vincocrm' ),
			'manage_options',
			'vincocrm-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register the settings, sections, and fields.
	 */
	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_options' ),
				'default'           => self::defaults(),
			)
		);

		// ── Section: API Connection ──────────────────────────────────────── //
		add_settings_section(
			'vincocrm_section_api',
			__( 'API Connection', 'vincocrm' ),
			array( __CLASS__, 'render_section_api' ),
			'vincocrm-settings'
		);

		add_settings_field(
			'api_key',
			__( 'API Key', 'vincocrm' ),
			array( __CLASS__, 'render_field_api_key' ),
			'vincocrm-settings',
			'vincocrm_section_api'
		);

		add_settings_field(
			'api_base_url',
			__( 'API Base URL', 'vincocrm' ),
			array( __CLASS__, 'render_field_api_base_url' ),
			'vincocrm-settings',
			'vincocrm_section_api'
		);

		// ── Section: Synchronisation ─────────────────────────────────────── //
		add_settings_section(
			'vincocrm_section_sync',
			__( 'Synchronisation', 'vincocrm' ),
			array( __CLASS__, 'render_section_sync' ),
			'vincocrm-settings'
		);

		add_settings_field(
			'sync_orders',
			__( 'Sync Orders', 'vincocrm' ),
			array( __CLASS__, 'render_field_sync_orders' ),
			'vincocrm-settings',
			'vincocrm_section_sync'
		);

		add_settings_field(
			'sync_customers',
			__( 'Sync Customers', 'vincocrm' ),
			array( __CLASS__, 'render_field_sync_customers' ),
			'vincocrm-settings',
			'vincocrm_section_sync'
		);

		add_settings_field(
			'sync_order_statuses',
			__( 'Sync on Order Statuses', 'vincocrm' ),
			array( __CLASS__, 'render_field_sync_order_statuses' ),
			'vincocrm-settings',
			'vincocrm_section_sync'
		);
	}

	// ── Section callbacks ────────────────────────────────────────────────── //

	/**
	 * Render the API Connection section description.
	 */
	public static function render_section_api() {
		echo '<p>' . esc_html__( 'Enter your VincoCRM API credentials. You can find your API key inside your VincoCRM account at https://www.vincocrm.ai/.', 'vincocrm' ) . '</p>';
	}

	/**
	 * Render the Synchronisation section description.
	 */
	public static function render_section_sync() {
		echo '<p>' . esc_html__( 'Choose which WooCommerce data should be automatically synced to VincoCRM.', 'vincocrm' ) . '</p>';
	}

	// ── Field callbacks ──────────────────────────────────────────────────── //

	/**
	 * Render the API Key field.
	 */
	public static function render_field_api_key() {
		$options = self::get_options();
		?>
		<input
			type="password"
			id="vincocrm_api_key"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_key]"
			value="<?php echo esc_attr( $options['api_key'] ); ?>"
			class="regular-text"
			autocomplete="off"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: %s: URL to VincoCRM account */
				esc_html__( 'Get your API key from your %s account.', 'vincocrm' ),
				'<a href="https://www.vincocrm.ai/" target="_blank" rel="noopener noreferrer">VincoCRM</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the API Base URL field.
	 */
	public static function render_field_api_base_url() {
		$options = self::get_options();
		?>
		<input
			type="url"
			id="vincocrm_api_base_url"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[api_base_url]"
			value="<?php echo esc_attr( $options['api_base_url'] ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Leave as default unless advised otherwise by VincoCRM support.', 'vincocrm' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Sync Orders checkbox.
	 */
	public static function render_field_sync_orders() {
		$options = self::get_options();
		?>
		<label>
			<input
				type="checkbox"
				id="vincocrm_sync_orders"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sync_orders]"
				value="1"
				<?php checked( 1, $options['sync_orders'] ); ?>
			/>
			<?php esc_html_e( 'Automatically push WooCommerce orders to VincoCRM.', 'vincocrm' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the Sync Customers checkbox.
	 */
	public static function render_field_sync_customers() {
		$options = self::get_options();
		?>
		<label>
			<input
				type="checkbox"
				id="vincocrm_sync_customers"
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[sync_customers]"
				value="1"
				<?php checked( 1, $options['sync_customers'] ); ?>
			/>
			<?php esc_html_e( 'Automatically sync WooCommerce customer data to VincoCRM contacts.', 'vincocrm' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the order-status multi-select field.
	 */
	public static function render_field_sync_order_statuses() {
		$options          = self::get_options();
		$selected_statuses = $options['sync_order_statuses'];

		$available_statuses = array(
			'wc-pending'    => __( 'Pending payment', 'vincocrm' ),
			'wc-processing' => __( 'Processing', 'vincocrm' ),
			'wc-on-hold'    => __( 'On hold', 'vincocrm' ),
			'wc-completed'  => __( 'Completed', 'vincocrm' ),
			'wc-cancelled'  => __( 'Cancelled', 'vincocrm' ),
			'wc-refunded'   => __( 'Refunded', 'vincocrm' ),
			'wc-failed'     => __( 'Failed', 'vincocrm' ),
		);

		// If WooCommerce is active, pull the live list of statuses.
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$available_statuses = wc_get_order_statuses();
		}

		echo '<select multiple id="vincocrm_sync_order_statuses" name="' . esc_attr( self::OPTION_NAME ) . '[sync_order_statuses][]" size="7">';
		foreach ( $available_statuses as $slug => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $slug ),
				in_array( $slug, $selected_statuses, true ) ? ' selected' : '',
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Hold Ctrl / Cmd to select multiple statuses. Orders reaching one of the selected statuses will be synced.', 'vincocrm' ) . '</p>';
	}

	// ── Settings page ────────────────────────────────────────────────────── //

	/**
	 * Render the full settings page HTML.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show a "Test Connection" result if the button was clicked.
		$test_result = self::maybe_handle_test_connection();
		?>
		<div class="wrap vincocrm-settings-wrap">
			<h1>
				<img
					src="<?php echo esc_url( VINCOCRM_PLUGIN_URL . 'assets/images/vincocrm-logo.svg' ); ?>"
					alt="VincoCRM"
					class="vincocrm-logo"
					onerror="this.style.display='none'"
				/>
				<?php esc_html_e( 'VincoCRM Settings', 'vincocrm' ); ?>
			</h1>

			<?php if ( $test_result ) : ?>
				<div class="notice <?php echo is_wp_error( $test_result ) ? 'notice-error' : 'notice-success'; ?> is-dismissible">
					<p>
						<?php
						if ( is_wp_error( $test_result ) ) {
							echo esc_html(
								sprintf(
									/* translators: %s: error message */
									__( 'Connection failed: %s', 'vincocrm' ),
									$test_result->get_error_message()
								)
							);
						} else {
							esc_html_e( 'Connection successful! VincoCRM is reachable.', 'vincocrm' );
						}
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'vincocrm-settings' );
				submit_button( __( 'Save Settings', 'vincocrm' ) );
				?>
			</form>

			<form method="post">
				<?php wp_nonce_field( 'vincocrm_test_connection', 'vincocrm_test_nonce' ); ?>
				<input type="hidden" name="vincocrm_action" value="test_connection" />
				<p>
					<button type="submit" class="button button-secondary">
						<?php esc_html_e( 'Test Connection', 'vincocrm' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	// ── Helpers ──────────────────────────────────────────────────────────── //

	/**
	 * Handle the "Test Connection" form submission.
	 *
	 * @return array|WP_Error|null API response, WP_Error, or null if not submitted.
	 */
	private static function maybe_handle_test_connection() {
		if (
			! isset( $_POST['vincocrm_action'], $_POST['vincocrm_test_nonce'] ) ||
			'test_connection' !== $_POST['vincocrm_action'] ||
			! wp_verify_nonce( sanitize_key( $_POST['vincocrm_test_nonce'] ), 'vincocrm_test_connection' )
		) {
			return null;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'vincocrm_forbidden', __( 'You do not have permission to perform this action.', 'vincocrm' ) );
		}

		$options = self::get_options();
		$api     = new VincoCRM_API( $options['api_key'], $options['api_base_url'] );

		return $api->test_connection();
	}

	/**
	 * Sanitize the options array before saving to the database.
	 *
	 * @param array $input Raw input from the settings form.
	 * @return array Sanitized options.
	 */
	public static function sanitize_options( $input ) {
		$sanitized = self::defaults();

		if ( isset( $input['api_key'] ) ) {
			$sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
		}

		if ( isset( $input['api_base_url'] ) ) {
			$sanitized['api_base_url'] = esc_url_raw( $input['api_base_url'] );
		}

		$sanitized['sync_orders']    = ! empty( $input['sync_orders'] ) ? 1 : 0;
		$sanitized['sync_customers'] = ! empty( $input['sync_customers'] ) ? 1 : 0;

		if ( isset( $input['sync_order_statuses'] ) && is_array( $input['sync_order_statuses'] ) ) {
			$sanitized['sync_order_statuses'] = array_map( 'sanitize_key', $input['sync_order_statuses'] );
		}

		return $sanitized;
	}

	/**
	 * Return the default option values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'api_key'             => '',
			'api_base_url'        => VINCOCRM_API_BASE_URL,
			'sync_orders'         => 1,
			'sync_customers'      => 1,
			'sync_order_statuses' => array( 'wc-processing', 'wc-completed' ),
		);
	}

	/**
	 * Retrieve the saved plugin options, merged with defaults.
	 *
	 * @return array
	 */
	public static function get_options() {
		$saved = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Retrieve a single option value.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get_option( $key, $default = null ) {
		$options = self::get_options();
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}
}
