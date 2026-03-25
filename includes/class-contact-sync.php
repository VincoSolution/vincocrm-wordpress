<?php
/**
 * Contact sync: push WordPress/WooCommerce users to VincoCRM.
 *
 * Hooks into registration, login, and WooCommerce checkout.
 * Uses hash-based deduplication to avoid redundant API calls.
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Contact_Sync {

    private Api_Client  $api;
    private Retry_Queue $queue;

    public function __construct( Api_Client $api, Retry_Queue $queue ) {
        $this->api   = $api;
        $this->queue = $queue;
    }

    public function init(): void {
        $settings = get_option( 'vincocrm_contact_sync_settings', [] );

        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        if ( ! empty( $settings['on_register'] ) ) {
            add_action( 'user_register', [ $this, 'on_register' ], 10, 1 );
        }

        if ( ! empty( $settings['on_login'] ) ) {
            add_action( 'wp_login', [ $this, 'on_login' ], 10, 2 );
        }

        if ( ! empty( $settings['on_checkout'] ) && class_exists( 'WooCommerce' ) ) {
            // Classic checkout.
            add_action( 'woocommerce_checkout_order_processed', [ $this, 'on_checkout' ], 10, 3 );
            // Block-based checkout (WC 8.3+ / default in WC 9+).
            add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'on_block_checkout' ] );
        }
    }

    /**
     * Sync on user registration.
     *
     * @param int $user_id
     */
    public function on_register( int $user_id ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $this->sync_user( $user, 'registration' );
    }

    /**
     * Sync on login.
     *
     * @param string   $user_login
     * @param \WP_User $user
     */
    public function on_login( string $user_login, \WP_User $user ): void {
        $this->sync_user( $user, 'login' );
    }

    /**
     * Sync on WooCommerce checkout.
     *
     * @param int       $order_id
     * @param array     $posted_data
     * @param \WC_Order $order
     */
    public function on_checkout( int $order_id, array $posted_data, \WC_Order $order ): void {
        $contact_data = [
            'name'         => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
            'primaryEmail' => $order->get_billing_email(),
            'phone'        => $order->get_billing_phone(),
            'address'      => $order->get_billing_address_1(),
            'city'         => $order->get_billing_city(),
            'state'        => $order->get_billing_state(),
            'country'      => $order->get_billing_country(),
            'zipCode'      => $order->get_billing_postcode(),
            'source'       => 'woocommerce',
            'tags'         => [ 'woocommerce', 'checkout' ],
        ];

        $company = $order->get_billing_company();
        if ( ! empty( $company ) ) {
            $contact_data['company'] = $company;
        }

        $this->push_contact( $contact_data );
    }

    /**
     * Sync on WooCommerce block-based checkout (WC 8.3+).
     *
     * This hook fires from the Store API checkout flow (Checkout Block).
     *
     * @param \WC_Order $order
     */
    public function on_block_checkout( \WC_Order $order ): void {
        $contact_data = [
            'name'         => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
            'primaryEmail' => $order->get_billing_email(),
            'phone'        => $order->get_billing_phone(),
            'address'      => $order->get_billing_address_1(),
            'city'         => $order->get_billing_city(),
            'state'        => $order->get_billing_state(),
            'country'      => $order->get_billing_country(),
            'zipCode'      => $order->get_billing_postcode(),
            'source'       => 'woocommerce',
            'tags'         => [ 'woocommerce', 'checkout' ],
        ];

        $company = $order->get_billing_company();
        if ( ! empty( $company ) ) {
            $contact_data['company'] = $company;
        }

        $this->push_contact( $contact_data );
    }

    /**
     * Sync a WP_User to CRM.
     *
     * @param \WP_User $user
     * @param string   $source
     */
    private function sync_user( \WP_User $user, string $source ): void {
        $contact_data = [
            'name'         => $user->display_name ?: trim( $user->first_name . ' ' . $user->last_name ),
            'primaryEmail' => $user->user_email,
            'source'       => 'wordpress',
            'tags'         => [ 'wordpress', $source ],
        ];

        // Add WooCommerce billing data if available.
        if ( function_exists( 'wc_get_customer' ) ) {
            $phone = get_user_meta( $user->ID, 'billing_phone', true );
            if ( ! empty( $phone ) ) {
                $contact_data['phone'] = $phone;
            }
            $company = get_user_meta( $user->ID, 'billing_company', true );
            if ( ! empty( $company ) ) {
                $contact_data['company'] = $company;
            }
        }

        $this->push_contact( $contact_data );
    }

    /**
     * Push a contact to CRM with dedup check.
     *
     * @param array $data Contact data.
     */
    private function push_contact( array $data ): void {
        if ( ! $this->api->is_connected() ) {
            return;
        }

        // Filter empty values.
        $data = array_filter( $data, static function ( $v ) {
            return '' !== $v && null !== $v;
        } );

        if ( empty( $data['primaryEmail'] ) ) {
            return;
        }

        // Hash-based dedup: skip if same data was sent recently.
        $hash     = md5( wp_json_encode( $data ) );
        $cache_key = 'vincocrm_contact_' . $hash;

        if ( get_transient( $cache_key ) ) {
            return;
        }

        $result = $this->api->upsert_contact( $data );

        if ( $result['success'] ) {
            // Cache for 1 hour to avoid duplicate syncs.
            set_transient( $cache_key, 1, HOUR_IN_SECONDS );
        } else {
            // Queue for retry on transient failures.
            $status = $result['status'] ?? 0;
            if ( $status >= 500 || 0 === $status ) {
                $this->queue->enqueue( 'POST', '/contacts', $data, 'contact_sync' );
            }
        }
    }
}
