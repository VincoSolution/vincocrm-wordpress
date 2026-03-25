<?php
/**
 * HTTP client for VincoCRM API communication.
 *
 * Handles JWT auth, auto-refresh on 401, workspace header injection.
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Api_Client {

    private Token_Storage $tokens;
    private bool $refreshing = false;

    public function __construct( Token_Storage $tokens ) {
        $this->tokens = $tokens;
    }

    /**
     * Get the configured API base URL.
     */
    public function get_base_url(): string {
        return untrailingslashit( get_option( 'vincocrm_api_url', '' ) );
    }

    /**
     * Whether the plugin is connected to the CRM.
     */
    public function is_connected(): bool {
        return $this->tokens->has_tokens() && ! empty( $this->get_base_url() );
    }

    /**
     * Perform an authenticated GET request.
     *
     * @param string $endpoint Relative path (e.g. "/auth/profile").
     * @param array  $query    Query parameters.
     * @return array{success: bool, data?: mixed, error?: string, status?: int}
     */
    public function get( string $endpoint, array $query = [] ): array {
        return $this->request( 'GET', $endpoint, [ 'query' => $query ] );
    }

    /**
     * Perform an authenticated POST request.
     *
     * @param string $endpoint Relative path.
     * @param array  $body     JSON body.
     * @return array{success: bool, data?: mixed, error?: string, status?: int}
     */
    public function post( string $endpoint, array $body = [] ): array {
        return $this->request( 'POST', $endpoint, [ 'body' => $body ] );
    }

    /**
     * Perform an authenticated PUT request.
     *
     * @param string $endpoint Relative path.
     * @param array  $body     JSON body.
     * @return array{success: bool, data?: mixed, error?: string, status?: int}
     */
    public function put( string $endpoint, array $body = [] ): array {
        return $this->request( 'PUT', $endpoint, [ 'body' => $body ] );
    }

    /**
     * Perform an authenticated DELETE request.
     *
     * @param string $endpoint Relative path.
     * @return array{success: bool, data?: mixed, error?: string, status?: int}
     */
    public function delete( string $endpoint ): array {
        return $this->request( 'DELETE', $endpoint );
    }

    /**
     * Perform an unauthenticated POST (for login).
     *
     * @param string $base_url API base URL.
     * @param string $endpoint Relative path.
     * @param array  $body     JSON body.
     * @return array{success: bool, data?: mixed, error?: string, status?: int}
     */
    public function post_public( string $base_url, string $endpoint, array $body = [] ): array {
        $url = untrailingslashit( $base_url ) . '/api' . $endpoint;

        $response = wp_remote_post(
            $url,
            [
                'timeout'     => 30,
                'headers'     => [ 'Content-Type' => 'application/json' ],
                'body'        => wp_json_encode( $body ),
                'data_format' => 'body',
                'sslverify'   => ! WP_DEBUG,
            ]
        );

        return $this->parse_response( $response );
    }

    /**
     * Core request method with auth + auto-refresh.
     *
     * @param string $method   HTTP method.
     * @param string $endpoint Relative API path.
     * @param array  $options  Request options (body, query).
     * @return array{success: bool, data?: mixed, error?: string, status?: int}
     */
    private function request( string $method, string $endpoint, array $options = [] ): array {
        $base_url = $this->get_base_url();
        if ( empty( $base_url ) ) {
            return [ 'success' => false, 'error' => __( 'API URL not configured.', 'vincocrm' ) ];
        }

        $url = $base_url . '/api' . $endpoint;

        // Add query params.
        if ( ! empty( $options['query'] ) ) {
            $url = add_query_arg( $options['query'], $url );
        }

        $args = [
            'method'      => $method,
            'timeout'     => 30,
            'headers'     => $this->build_headers(),
            'sslverify'   => ! WP_DEBUG,
        ];

        if ( ! empty( $options['body'] ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
            $args['body']        = wp_json_encode( $options['body'] );
            $args['data_format'] = 'body';
        }

        $response = wp_remote_request( $url, $args );
        $result   = $this->parse_response( $response );

        // Auto-refresh on 401.
        if ( ! $result['success'] && 401 === ( $result['status'] ?? 0 ) && ! $this->refreshing ) {
            $refreshed = $this->refresh_token();
            if ( $refreshed ) {
                // Retry with new token.
                $args['headers'] = $this->build_headers();
                $response        = wp_remote_request( $url, $args );
                $result          = $this->parse_response( $response );
            }
        }

        return $result;
    }

    /**
     * Build request headers with JWT and workspace context.
     *
     * @return array<string, string>
     */
    private function build_headers(): array {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        $access_token = $this->tokens->get_access_token();
        if ( ! empty( $access_token ) ) {
            $headers['Authorization'] = 'Bearer ' . $access_token;
        }

        $workspace_id = get_option( 'vincocrm_workspace_id', '' );
        if ( ! empty( $workspace_id ) ) {
            $headers['x-workspace-id'] = $workspace_id;
        }

        return $headers;
    }

    /**
     * Attempt to refresh the access token.
     */
    private function refresh_token(): bool {
        $this->refreshing = true;

        $refresh_token = $this->tokens->get_refresh_token();
        if ( empty( $refresh_token ) ) {
            $this->refreshing = false;
            return false;
        }

        $result = $this->post_public( $this->get_base_url(), '/auth/refresh', [
            'refreshToken' => $refresh_token,
        ] );

        $this->refreshing = false;

        if ( $result['success'] && ! empty( $result['data']['accessToken'] ) ) {
            $this->tokens->save( [
                'access_token'  => $result['data']['accessToken'],
                'refresh_token' => $result['data']['refreshToken'] ?? $refresh_token,
            ] );
            return true;
        }

        // Refresh failed — clear tokens and require re-auth.
        $this->tokens->clear();
        update_option( 'vincocrm_setup_complete', false );
        set_transient( 'vincocrm_reauth_notice', true, 3600 );

        return false;
    }

    /**
     * Parse wp_remote response into a standardized format.
     *
     * @param array|\WP_Error $response
     * @return array{success: bool, data?: mixed, error?: string, status?: int}
     */
    private function parse_response( array|\WP_Error $response ): array {
        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $data   = json_decode( $body, true );

        if ( $status >= 200 && $status < 300 ) {
            return [
                'success' => true,
                'data'    => $data,
                'status'  => $status,
            ];
        }

        $error_msg = $data['message'] ?? ( $data['error'] ?? wp_remote_retrieve_response_message( $response ) );

        return [
            'success' => false,
            'error'   => is_array( $error_msg ) ? implode( ', ', $error_msg ) : (string) $error_msg,
            'status'  => $status,
            'data'    => $data,
        ];
    }

    // --- Convenience API methods ---

    /**
     * Login to VincoCRM.
     */
    public function login( string $base_url, string $email, string $password ): array {
        return $this->post_public( $base_url, '/auth/login', [
            'email'    => $email,
            'password' => $password,
        ] );
    }

    /**
     * Get user profile with workspaces.
     */
    public function get_profile(): array {
        return $this->get( '/auth/profile' );
    }

    /**
     * Get widget config for current workspace.
     */
    public function get_widget_config(): array {
        return $this->get( '/widget/config' );
    }

    /**
     * Create/update contact.
     */
    public function upsert_contact( array $contact_data ): array {
        return $this->post( '/contacts', $contact_data );
    }

    /**
     * Submit contact form.
     */
    public function submit_contact_form( array $form_data ): array {
        $base_url = $this->get_base_url();
        return $this->post_public( $base_url, '/contact', $form_data );
    }

    /**
     * Get ecommerce stores.
     */
    public function get_stores(): array {
        return $this->get( '/ecommerce/stores' );
    }

    /**
     * Connect WooCommerce store via OAuth.
     */
    public function wc_oauth_initiate( string $store_url, string $callback_url, string $name = '' ): array {
        return $this->post( '/ecommerce/woocommerce/oauth/initiate', [
            'name'        => $name ?: wp_parse_url( $store_url, PHP_URL_HOST ),
            'storeUrl'    => $store_url,
            'callbackUrl' => $callback_url,
        ] );
    }

    /**
     * Complete WooCommerce OAuth.
     */
    public function wc_oauth_callback( string $store_id, string $consumer_key, string $consumer_secret ): array {
        return $this->post( '/ecommerce/woocommerce/oauth/callback?storeId=' . $store_id, [
            'consumer_key'    => $consumer_key,
            'consumer_secret' => $consumer_secret,
        ] );
    }

    /**
     * Trigger order sync for a store.
     */
    public function sync_orders( string $store_id ): array {
        return $this->post( '/ecommerce/stores/' . $store_id . '/sync' );
    }

    /**
     * Get workspaces (from profile).
     *
     * @return array{id: string, name: string, role: string}[]
     */
    public function get_workspaces(): array {
        $result = $this->get_profile();
        if ( ! $result['success'] ) {
            return [];
        }
        return $result['data']['workspaces'] ?? [];
    }
}
