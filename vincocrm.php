<?php
/**
 * Plugin Name:       VincoCRM
 * Plugin URI:        https://vincocrm.com/wordpress-plugin
 * Description:       Connect your WordPress/WooCommerce store to VincoCRM — AI-powered omnichannel support, live chat widget, contact sync, order sync, and drag-and-drop contact forms.
 * Version:           2.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.0
 * Author:            VincoCRM
 * Author URI:        https://vincocrm.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vincocrm
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   10.6
 *
 * @package VincoCRM
 */

defined( 'ABSPATH' ) || exit;

define( 'VINCOCRM_VERSION', '2.0.0' );
define( 'VINCOCRM_PLUGIN_FILE', __FILE__ );
define( 'VINCOCRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VINCOCRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VINCOCRM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Declare WooCommerce feature compatibility (HPOS + Block Checkout).
add_action( 'before_woocommerce_init', static function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

/**
 * Autoload plugin classes.
 */
spl_autoload_register( static function ( string $class ) {
    $prefix = 'VincoCRM\\';
    if ( 0 !== strpos( $class, $prefix ) ) {
        return;
    }

    $relative = substr( $class, strlen( $prefix ) );
    $file     = VINCOCRM_PLUGIN_DIR . 'includes/class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative ) ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * Plugin activation.
 */
function vincocrm_activate(): void {
    // Schedule retry queue cron.
    if ( ! wp_next_scheduled( 'vincocrm_retry_queue' ) ) {
        wp_schedule_event( time(), 'five_minutes', 'vincocrm_retry_queue' );
    }

    // Schedule order sync cron.
    if ( ! wp_next_scheduled( 'vincocrm_order_sync' ) ) {
        wp_schedule_event( time(), 'hourly', 'vincocrm_order_sync' );
    }

    // Set default options.
    add_option( 'vincocrm_version', VINCOCRM_VERSION );
    add_option( 'vincocrm_setup_complete', false );
    add_option( 'vincocrm_widget_settings', [
        'enabled'        => false,
        'display'        => 'all',
        'include_pages'  => [],
        'exclude_pages'  => [],
    ] );
    add_option( 'vincocrm_contact_sync_settings', [
        'enabled'       => true,
        'on_register'   => true,
        'on_login'      => false,
        'on_checkout'   => true,
    ] );
    add_option( 'vincocrm_order_sync_settings', [
        'enabled'     => true,
        'realtime'    => true,
        'batch_size'  => 20,
    ] );
    add_option( 'vincocrm_form_settings', [
        'engine' => 'classic',
    ] );

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'vincocrm_activate' );

/**
 * Plugin deactivation.
 */
function vincocrm_deactivate(): void {
    wp_clear_scheduled_hook( 'vincocrm_retry_queue' );
    wp_clear_scheduled_hook( 'vincocrm_order_sync' );
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'vincocrm_deactivate' );

/**
 * Register custom cron interval.
 */
add_filter( 'cron_schedules', static function ( array $schedules ): array {
    $schedules['five_minutes'] = [
        'interval' => 300,
        'display'  => esc_html__( 'Every Five Minutes', 'vincocrm' ),
    ];
    return $schedules;
} );

/**
 * Boot the plugin.
 */
add_action( 'plugins_loaded', static function () {
    load_plugin_textdomain( 'vincocrm', false, dirname( VINCOCRM_PLUGIN_BASENAME ) . '/languages' );

    $token_storage = new VincoCRM\Token_Storage();
    $api_client    = new VincoCRM\Api_Client( $token_storage );
    $retry_queue   = new VincoCRM\Retry_Queue( $api_client );

    // Admin.
    if ( is_admin() ) {
        $admin = new VincoCRM\Admin( $api_client, $token_storage );
        $admin->init();

        $setup_wizard = new VincoCRM\Setup_Wizard( $api_client, $token_storage );
        $setup_wizard->init();

        $form_builder = new VincoCRM\Form_Builder( $api_client );
        $form_builder->init();
    }

    // Frontend.
    $widget = new VincoCRM\Widget( $api_client );
    $widget->init();

    $form_renderer = new VincoCRM\Form_Renderer( $api_client );
    $form_renderer->init();

    // Contact sync.
    $contact_sync = new VincoCRM\Contact_Sync( $api_client, $retry_queue );
    $contact_sync->init();

    // Order sync (requires WooCommerce).
    if ( class_exists( 'WooCommerce' ) ) {
        $order_sync = new VincoCRM\Order_Sync( $api_client, $retry_queue );
        $order_sync->init();
    }

    // Retry queue cron.
    $retry_queue->init();

    // Gutenberg blocks.
    $blocks = new VincoCRM\Blocks();
    $blocks->init();

    // Admin bar & notices.
    add_action( 'admin_notices', static function () {
        if ( ! get_option( 'vincocrm_setup_complete' ) && current_user_can( 'manage_options' ) ) {
            $wizard_url = admin_url( 'admin.php?page=vincocrm-setup' );
            wp_admin_notice(
                sprintf(
                    '%s <a href="%s">%s</a>',
                    esc_html__( 'Welcome to VincoCRM! Complete the setup wizard to get started.', 'vincocrm' ),
                    esc_url( $wizard_url ),
                    esc_html__( 'Start Setup →', 'vincocrm' )
                ),
                [
                    'type'        => 'info',
                    'dismissible' => true,
                ]
            );
        }

        if ( get_option( 'vincocrm_setup_complete' ) && class_exists( 'WooCommerce' ) === false ) {
            wp_admin_notice(
                esc_html__( 'VincoCRM: WooCommerce is not active. Order sync and checkout contact sync are disabled.', 'vincocrm' ),
                [
                    'type'        => 'warning',
                    'dismissible' => true,
                ]
            );
        }
    } );

    // Add settings link on plugins page.
    add_filter( 'plugin_action_links_' . VINCOCRM_PLUGIN_BASENAME, static function ( array $links ): array {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=vincocrm' ) ),
            esc_html__( 'Settings', 'vincocrm' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    } );
} );
