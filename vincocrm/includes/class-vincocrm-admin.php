<?php
/**
 * VincoCRM Admin
 *
 * Handles WordPress admin-area UI elements that are not part of the settings
 * page, such as admin notices and enqueueing assets.
 *
 * @package VincoCRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * VincoCRM_Admin class.
 */
class VincoCRM_Admin {

	/**
	 * Register admin hooks.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( VINCOCRM_PLUGIN_FILE ), array( __CLASS__, 'add_action_links' ) );
	}

	/**
	 * Show admin notices when the plugin configuration is incomplete.
	 */
	public static function maybe_show_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Warn if WooCommerce is not active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			self::render_notice(
				'warning',
				sprintf(
					/* translators: %s: URL to the settings page */
					__( '<strong>VincoCRM</strong> requires <a href="%s" target="_blank">WooCommerce</a> to be installed and activated for order and customer sync.', 'vincocrm' ),
					esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) )
				)
			);
		}

		// Warn if the API key is missing.
		$api_key = VincoCRM_Settings::get_option( 'api_key' );
		if ( empty( $api_key ) ) {
			self::render_notice(
				'info',
				sprintf(
					/* translators: %s: URL to the VincoCRM settings page */
					__( '<strong>VincoCRM</strong> is not yet connected. <a href="%s">Enter your API key</a> to start syncing data.', 'vincocrm' ),
					esc_url( admin_url( 'options-general.php?page=vincocrm-settings' ) )
				)
			);
		}
	}

	/**
	 * Enqueue admin CSS on the VincoCRM settings page.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public static function enqueue_assets( $hook ) {
		if ( 'settings_page_vincocrm-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'vincocrm-admin',
			VINCOCRM_PLUGIN_URL . 'assets/css/vincocrm-admin.css',
			array(),
			VINCOCRM_VERSION
		);
	}

	/**
	 * Add a "Settings" link to the plugin's entry on the Plugins list page.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]       Modified action links.
	 */
	public static function add_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=vincocrm-settings' ) ),
			esc_html__( 'Settings', 'vincocrm' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Output an admin notice.
	 *
	 * @param string $type    Notice type: 'info', 'warning', 'error', 'success'.
	 * @param string $message HTML message (already escaped by caller where needed).
	 */
	private static function render_notice( $type, $message ) {
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			wp_kses(
				$message,
				array(
					'a'      => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
					'strong' => array(),
				)
			)
		);
	}
}
