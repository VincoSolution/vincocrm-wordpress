<?php
/**
 * Plugin Name: VincoCRM for WordPress and WooCommerce
 * Plugin URI:  https://www.vincocrm.ai/
 * Description: Connects your WordPress and WooCommerce store to VincoCRM (https://www.vincocrm.ai/), automatically syncing customers and orders into your CRM.
 * Version:     1.0.0
 * Author:      VincoSolution
 * Author URI:  https://www.vincocrm.ai/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vincocrm
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:      8.0
 *
 * @package VincoCRM
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'VINCOCRM_VERSION', '1.0.0' );
define( 'VINCOCRM_PLUGIN_FILE', __FILE__ );
define( 'VINCOCRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VINCOCRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VINCOCRM_API_BASE_URL', 'https://www.vincocrm.ai/api/v1' );

/**
 * Main VincoCRM plugin class.
 */
final class VincoCRM {

	/**
	 * Single instance of the class.
	 *
	 * @var VincoCRM|null
	 */
	private static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return VincoCRM
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor – load dependencies and register hooks.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	/**
	 * Load required class files.
	 */
	private function load_dependencies() {
		require_once VINCOCRM_PLUGIN_DIR . 'includes/class-vincocrm-api.php';
		require_once VINCOCRM_PLUGIN_DIR . 'includes/class-vincocrm-settings.php';
		require_once VINCOCRM_PLUGIN_DIR . 'includes/class-vincocrm-admin.php';
		require_once VINCOCRM_PLUGIN_DIR . 'includes/class-vincocrm-woocommerce.php';
	}

	/**
	 * Register WordPress and WooCommerce hooks.
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Declare HPOS compatibility.
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		VincoCRM_Admin::init();
		VincoCRM_Settings::init();

		// Only load WooCommerce integration when WC is active.
		add_action( 'plugins_loaded', array( $this, 'maybe_load_woocommerce' ) );
	}

	/**
	 * Load the plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'vincocrm',
			false,
			dirname( plugin_basename( VINCOCRM_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Declare High-Performance Order Storage (HPOS) compatibility.
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				VINCOCRM_PLUGIN_FILE,
				true
			);
		}
	}

	/**
	 * Initialise the WooCommerce integration if WooCommerce is active.
	 */
	public function maybe_load_woocommerce() {
		if ( class_exists( 'WooCommerce' ) ) {
			VincoCRM_WooCommerce::init();
		}
	}
}

/**
 * Return the singleton plugin instance.
 *
 * @return VincoCRM
 */
function vincocrm() {
	return VincoCRM::instance();
}

// Bootstrap the plugin.
vincocrm();
