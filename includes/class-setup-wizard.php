<?php
/**
 * 4-step setup wizard: Login → Select Workspace → Widget → WooCommerce Connect.
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Setup_Wizard {

    private Api_Client    $api;
    private Token_Storage $tokens;

    public function __construct( Api_Client $api, Token_Storage $tokens ) {
        $this->api    = $api;
        $this->tokens = $tokens;
    }

    public function init(): void {
        add_action( 'wp_ajax_vincocrm_wizard_login', [ $this, 'ajax_login' ] );
        add_action( 'wp_ajax_vincocrm_wizard_select_workspace', [ $this, 'ajax_select_workspace' ] );
        add_action( 'wp_ajax_vincocrm_wizard_connect_widget', [ $this, 'ajax_connect_widget' ] );
        add_action( 'wp_ajax_vincocrm_wizard_connect_woocommerce', [ $this, 'ajax_connect_woocommerce' ] );
        add_action( 'wp_ajax_vincocrm_wizard_skip_woocommerce', [ $this, 'ajax_skip_woocommerce' ] );
        add_action( 'wp_ajax_vincocrm_wizard_get_workspaces', [ $this, 'ajax_get_workspaces' ] );

        // Register the setup page renderer.
        add_action( 'admin_menu', function () {
            // Already registered by Admin class, just override the callback.
            global $submenu;
            if ( isset( $submenu['vincocrm'] ) ) {
                foreach ( $submenu['vincocrm'] as &$item ) {
                    if ( 'vincocrm-setup' === $item[2] ) {
                        $item[3] = __( 'Setup Wizard', 'vincocrm' );
                    }
                }
            }
        }, 20 );

        add_action( 'toplevel_page_vincocrm', function () {} ); // no-op, page is under submenu.

        // Render setup page.
        add_action( 'admin_page_vincocrm-setup', [ $this, 'render' ] );
    }

    /**
     * Render the setup wizard page.
     */
    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'vincocrm' ) );
        }

        wp_enqueue_style( 'vincocrm-admin', VINCOCRM_PLUGIN_URL . 'admin/css/admin.css', [], VINCOCRM_VERSION );
        wp_enqueue_script( 'vincocrm-admin', VINCOCRM_PLUGIN_URL . 'admin/js/admin.js', [], VINCOCRM_VERSION, true );
        wp_localize_script( 'vincocrm-admin', 'vincoCRM', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'vincocrm_admin' ),
            'connected' => $this->api->is_connected(),
            'siteUrl'   => site_url(),
            'hasWoo'    => class_exists( 'WooCommerce' ),
            'strings'   => [
                'connecting'    => __( 'Connecting...', 'vincocrm' ),
                'error'         => __( 'An error occurred. Please try again.', 'vincocrm' ),
                'loginSuccess'  => __( 'Login successful!', 'vincocrm' ),
                'widgetEnabled' => __( 'Widget connected!', 'vincocrm' ),
                'wooConnected'  => __( 'WooCommerce connected!', 'vincocrm' ),
            ],
        ] );

        include VINCOCRM_PLUGIN_DIR . 'admin/views/setup-wizard.php';
    }

    /**
     * Step 1: Login to VincoCRM.
     */
    public function ajax_login(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $api_url  = esc_url_raw( trim( $_POST['api_url'] ?? '' ) );
        $email    = sanitize_email( $_POST['email'] ?? '' );
        $password = $_POST['password'] ?? ''; // Raw password, not sanitized.

        if ( empty( $api_url ) || empty( $email ) || empty( $password ) ) {
            wp_send_json_error( [ 'message' => __( 'All fields are required.', 'vincocrm' ) ] );
        }

        // Save API URL.
        update_option( 'vincocrm_api_url', $api_url );

        $result = $this->api->login( $api_url, $email, $password );

        if ( ! $result['success'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ?? __( 'Login failed.', 'vincocrm' ) ] );
        }

        $data = $result['data'];

        // Handle MFA requirement.
        if ( ! empty( $data['requiresMfa'] ) ) {
            wp_send_json_success( [
                'requiresMfa' => true,
                'mfaToken'    => $data['mfaToken'] ?? '',
            ] );
            return;
        }

        if ( empty( $data['accessToken'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid response from server.', 'vincocrm' ) ] );
        }

        // Store tokens.
        $this->tokens->save( [
            'access_token'  => $data['accessToken'],
            'refresh_token' => $data['refreshToken'] ?? '',
        ] );

        update_option( 'vincocrm_user_email', $email );

        // Fetch workspaces.
        $workspaces = $this->api->get_workspaces();

        wp_send_json_success( [
            'message'    => __( 'Login successful!', 'vincocrm' ),
            'workspaces' => $workspaces,
        ] );
    }

    /**
     * Get workspaces (refresh list).
     */
    public function ajax_get_workspaces(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $workspaces = $this->api->get_workspaces();
        wp_send_json_success( [ 'workspaces' => $workspaces ] );
    }

    /**
     * Step 2: Select workspace.
     */
    public function ajax_select_workspace(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $workspace_id   = sanitize_text_field( $_POST['workspace_id'] ?? '' );
        $workspace_name = sanitize_text_field( $_POST['workspace_name'] ?? '' );

        if ( empty( $workspace_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Please select a workspace.', 'vincocrm' ) ] );
        }

        update_option( 'vincocrm_workspace_id', $workspace_id );
        update_option( 'vincocrm_workspace_name', $workspace_name );

        wp_send_json_success( [ 'message' => __( 'Workspace selected.', 'vincocrm' ) ] );
    }

    /**
     * Step 3: Connect widget.
     */
    public function ajax_connect_widget(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $result = $this->api->get_widget_config();

        if ( ! $result['success'] ) {
            wp_send_json_error( [
                'message' => $result['error'] ?? __( 'Could not fetch widget config.', 'vincocrm' ),
            ] );
        }

        $config = $result['data'];

        update_option( 'vincocrm_widget_api_key', $config['apiKey'] ?? '' );
        update_option( 'vincocrm_widget_config', $config );
        update_option( 'vincocrm_widget_api_url', $this->api->get_base_url() . '/api' );

        // Enable widget by default.
        update_option( 'vincocrm_widget_settings', [
            'enabled'       => true,
            'display'       => 'all',
            'include_pages' => [],
            'exclude_pages' => [],
        ] );

        wp_send_json_success( [
            'message' => __( 'Widget connected!', 'vincocrm' ),
            'apiKey'  => $config['apiKey'] ?? '',
        ] );
    }

    /**
     * Step 4: Connect WooCommerce.
     */
    public function ajax_connect_woocommerce(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'WooCommerce is not installed.', 'vincocrm' ) ] );
        }

        $store_url    = site_url();
        $callback_url = admin_url( 'admin.php?page=vincocrm&wc_callback=1' );

        $result = $this->api->wc_oauth_initiate( $store_url, $callback_url );

        if ( ! $result['success'] ) {
            wp_send_json_error( [
                'message' => $result['error'] ?? __( 'Could not initiate WooCommerce connection.', 'vincocrm' ),
            ] );
        }

        $data = $result['data'];

        if ( ! empty( $data['storeId'] ) ) {
            update_option( 'vincocrm_wc_store_id', $data['storeId'] );
        }

        // Complete setup.
        update_option( 'vincocrm_setup_complete', true );
        update_option( 'vincocrm_wc_connected', true );

        wp_send_json_success( [
            'message' => __( 'WooCommerce connected!', 'vincocrm' ),
            'authUrl' => $data['authUrl'] ?? '',
            'storeId' => $data['storeId'] ?? '',
        ] );
    }

    /**
     * Skip WooCommerce step and finish wizard.
     */
    public function ajax_skip_woocommerce(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        update_option( 'vincocrm_setup_complete', true );

        wp_send_json_success( [ 'message' => __( 'Setup complete!', 'vincocrm' ) ] );
    }
}
