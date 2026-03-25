<?php
/**
 * Admin settings pages and tabbed settings interface.
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Admin {

    private Api_Client    $api;
    private Token_Storage $tokens;

    public function __construct( Api_Client $api, Token_Storage $tokens ) {
        $this->api    = $api;
        $this->tokens = $tokens;
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_vincocrm_save_settings', [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_vincocrm_disconnect', [ $this, 'ajax_disconnect' ] );
        add_action( 'wp_ajax_vincocrm_test_connection', [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_vincocrm_clear_retry_queue', [ $this, 'ajax_clear_retry_queue' ] );
    }

    /**
     * Register admin menu pages.
     */
    public function register_menus(): void {
        add_menu_page(
            __( 'VincoCRM', 'vincocrm' ),
            __( 'VincoCRM', 'vincocrm' ),
            'manage_options',
            'vincocrm',
            [ $this, 'render_settings_page' ],
            'dashicons-format-chat',
            56
        );

        add_submenu_page(
            'vincocrm',
            __( 'Settings', 'vincocrm' ),
            __( 'Settings', 'vincocrm' ),
            'manage_options',
            'vincocrm',
            [ $this, 'render_settings_page' ]
        );

        add_submenu_page(
            'vincocrm',
            __( 'Forms', 'vincocrm' ),
            __( 'Forms', 'vincocrm' ),
            'manage_options',
            'vincocrm-forms',
            [ $this, 'render_forms_page' ]
        );

        add_submenu_page(
            'vincocrm',
            __( 'Setup Wizard', 'vincocrm' ),
            __( 'Setup Wizard', 'vincocrm' ),
            'manage_options',
            'vincocrm-setup',
            '__return_null' // Rendered by Setup_Wizard class.
        );
    }

    /**
     * Enqueue admin CSS/JS.
     */
    public function enqueue_assets( string $hook ): void {
        if ( false === strpos( $hook, 'vincocrm' ) ) {
            return;
        }

        wp_enqueue_style(
            'vincocrm-admin',
            VINCOCRM_PLUGIN_URL . 'admin/css/admin.css',
            [],
            VINCOCRM_VERSION
        );

        wp_enqueue_script(
            'vincocrm-admin',
            VINCOCRM_PLUGIN_URL . 'admin/js/admin.js',
            [],
            VINCOCRM_VERSION,
            true
        );

        wp_localize_script( 'vincocrm-admin', 'vincoCRM', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'vincocrm_admin' ),
            'connected' => $this->api->is_connected(),
            'strings'   => [
                'saving'       => __( 'Saving...', 'vincocrm' ),
                'saved'        => __( 'Settings saved.', 'vincocrm' ),
                'error'        => __( 'An error occurred. Please try again.', 'vincocrm' ),
                'confirm'      => __( 'Are you sure?', 'vincocrm' ),
                'disconnected' => __( 'Disconnected from VincoCRM.', 'vincocrm' ),
            ],
        ] );
    }

    /**
     * Render the main settings page.
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'connection'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $tabs = [
            'connection'   => __( 'Connection', 'vincocrm' ),
            'widget'       => __( 'Widget', 'vincocrm' ),
            'contact_sync' => __( 'Contact Sync', 'vincocrm' ),
            'order_sync'   => __( 'Order Sync', 'vincocrm' ),
            'forms'        => __( 'Forms', 'vincocrm' ),
            'advanced'     => __( 'Advanced', 'vincocrm' ),
        ];

        include VINCOCRM_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render the forms management page.
     */
    public function render_forms_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Delegated to Form_Builder.
        do_action( 'vincocrm_render_forms_page' );
    }

    /**
     * AJAX: Save settings.
     */
    public function ajax_save_settings(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $tab = sanitize_key( $_POST['tab'] ?? '' );

        switch ( $tab ) {
            case 'widget':
                $settings = [
                    'enabled'       => ! empty( $_POST['widget_enabled'] ),
                    'display'       => sanitize_key( $_POST['widget_display'] ?? 'all' ),
                    'include_pages' => $this->sanitize_id_list( $_POST['widget_include_pages'] ?? '' ),
                    'exclude_pages' => $this->sanitize_id_list( $_POST['widget_exclude_pages'] ?? '' ),
                ];
                update_option( 'vincocrm_widget_settings', $settings );
                break;

            case 'contact_sync':
                $settings = [
                    'enabled'     => ! empty( $_POST['contact_sync_enabled'] ),
                    'on_register' => ! empty( $_POST['contact_sync_on_register'] ),
                    'on_login'    => ! empty( $_POST['contact_sync_on_login'] ),
                    'on_checkout' => ! empty( $_POST['contact_sync_on_checkout'] ),
                ];
                update_option( 'vincocrm_contact_sync_settings', $settings );
                break;

            case 'order_sync':
                $settings = [
                    'enabled'    => ! empty( $_POST['order_sync_enabled'] ),
                    'realtime'   => ! empty( $_POST['order_sync_realtime'] ),
                    'batch_size' => absint( $_POST['order_sync_batch_size'] ?? 20 ),
                ];
                $settings['batch_size'] = max( 1, min( 100, $settings['batch_size'] ) );
                update_option( 'vincocrm_order_sync_settings', $settings );
                break;

            case 'forms':
                $settings = [
                    'engine' => in_array( $_POST['form_engine'] ?? '', [ 'classic', 'modern' ], true )
                        ? sanitize_key( $_POST['form_engine'] )
                        : 'classic',
                ];
                update_option( 'vincocrm_form_settings', $settings );
                break;

            case 'advanced':
                if ( ! empty( $_POST['api_url'] ) ) {
                    update_option( 'vincocrm_api_url', esc_url_raw( $_POST['api_url'] ) );
                }
                break;

            default:
                wp_send_json_error( [ 'message' => __( 'Invalid tab.', 'vincocrm' ) ] );
        }

        wp_send_json_success( [ 'message' => __( 'Settings saved.', 'vincocrm' ) ] );
    }

    /**
     * AJAX: Disconnect from CRM.
     */
    public function ajax_disconnect(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $this->tokens->clear();
        delete_option( 'vincocrm_workspace_id' );
        delete_option( 'vincocrm_workspace_name' );
        delete_option( 'vincocrm_widget_api_key' );
        delete_option( 'vincocrm_widget_config' );
        delete_option( 'vincocrm_wc_connected' );
        delete_option( 'vincocrm_wc_store_id' );
        delete_option( 'vincocrm_user_email' );
        update_option( 'vincocrm_setup_complete', false );

        wp_send_json_success( [ 'message' => __( 'Disconnected from VincoCRM.', 'vincocrm' ) ] );
    }

    /**
     * AJAX: Test connection.
     */
    public function ajax_test_connection(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $result = $this->api->get_profile();

        if ( $result['success'] ) {
            wp_send_json_success( [
                'message' => __( 'Connection successful!', 'vincocrm' ),
                'user'    => $result['data']['name'] ?? $result['data']['email'] ?? '',
            ] );
        }

        wp_send_json_error( [
            'message' => sprintf(
                /* translators: %s: error message */
                __( 'Connection failed: %s', 'vincocrm' ),
                $result['error'] ?? __( 'Unknown error', 'vincocrm' )
            ),
        ] );
    }

    /**
     * AJAX: Clear retry queue.
     */
    public function ajax_clear_retry_queue(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $retry_queue = new Retry_Queue( $this->api );
        $retry_queue->clear();

        wp_send_json_success( [ 'message' => __( 'Retry queue cleared.', 'vincocrm' ) ] );
    }

    /**
     * Sanitize a comma-separated list of IDs.
     *
     * @param string $input
     * @return int[]
     */
    private function sanitize_id_list( string $input ): array {
        if ( empty( $input ) ) {
            return [];
        }
        return array_filter( array_map( 'absint', explode( ',', $input ) ) );
    }
}
