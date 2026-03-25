<?php
/**
 * WooCommerce order sync to VincoCRM.
 *
 * Real-time push on order events + bulk historical sync via wp_cron.
 * HPOS-compatible using WC_Order methods instead of post meta.
 *
 * @package VincoCRM
 */

namespace VincoCRM;

defined( 'ABSPATH' ) || exit;

class Order_Sync {

    private Api_Client  $api;
    private Retry_Queue $queue;

    public function __construct( Api_Client $api, Retry_Queue $queue ) {
        $this->api   = $api;
        $this->queue = $queue;
    }

    public function init(): void {
        $settings = get_option( 'vincocrm_order_sync_settings', [] );

        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        // Real-time hooks.
        if ( ! empty( $settings['realtime'] ) ) {
            // New order created (classic + block checkout).
            add_action( 'woocommerce_new_order', [ $this, 'on_order_created' ], 10, 2 );

            // Block-based checkout order processed (WC 8.3+).
            add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'on_block_checkout_order' ] );

            // Status changes.
            add_action( 'woocommerce_order_status_changed', [ $this, 'on_status_changed' ], 10, 4 );

            // Tracking meta added/updated.
            // HPOS: woocommerce_order_meta_updated fires for custom order tables.
            add_action( 'added_order_meta', [ $this, 'on_tracking_added' ], 10, 4 );
            add_action( 'updated_order_meta', [ $this, 'on_tracking_added' ], 10, 4 );

            // Refund hook (WC 10.5+).
            add_action( 'woocommerce_update_order_refund', [ $this, 'on_refund_updated' ], 10, 2 );
        }

        // Bulk sync cron.
        add_action( 'vincocrm_order_sync', [ $this, 'bulk_sync' ] );

        // Admin action for manual sync trigger.
        add_action( 'wp_ajax_vincocrm_trigger_order_sync', [ $this, 'ajax_trigger_sync' ] );
    }

    /**
     * Push new order to CRM.
     *
     * @param int       $order_id
     * @param \WC_Order $order
     */
    public function on_order_created( int $order_id, \WC_Order $order ): void {
        $this->push_order( $order );
    }

    /**
     * Push on block-based checkout (WC 8.3+ Store API).
     *
     * @param \WC_Order $order
     */
    public function on_block_checkout_order( \WC_Order $order ): void {
        $this->push_order( $order );
    }

    /**
     * Push on status change.
     *
     * @param int       $order_id
     * @param string    $old_status
     * @param string    $new_status
     * @param \WC_Order $order
     */
    public function on_status_changed( int $order_id, string $old_status, string $new_status, \WC_Order $order ): void {
        $this->push_order( $order );
    }

    /**
     * Push on refund update (WC 10.5+).
     *
     * @param int $refund_id
     * @param int $order_id
     */
    public function on_refund_updated( int $refund_id, int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $this->push_order( $order );
        }
    }

    /**
     * Push when tracking meta is added/updated (VillaTheme).
     *
     * @param int    $meta_id
     * @param int    $object_id
     * @param string $meta_key
     * @param mixed  $meta_value
     */
    public function on_tracking_added( int $meta_id, int $object_id, string $meta_key, mixed $meta_value ): void {
        $tracking_keys = [ '_wc_shipment_tracking_items', '_vi_wot_order_item_tracking' ];
        if ( ! in_array( $meta_key, $tracking_keys, true ) ) {
            return;
        }

        $order = wc_get_order( $object_id );
        if ( ! $order ) {
            return;
        }

        $this->push_order( $order );
    }

    /**
     * Bulk historical sync via wp_cron.
     */
    public function bulk_sync(): void {
        if ( ! $this->api->is_connected() ) {
            return;
        }

        $store_id = get_option( 'vincocrm_wc_store_id', '' );
        if ( ! empty( $store_id ) ) {
            // Trigger CRM-side sync.
            $this->api->sync_orders( $store_id );
            return;
        }

        // Fallback: push orders directly.
        $settings   = get_option( 'vincocrm_order_sync_settings', [] );
        $batch_size = $settings['batch_size'] ?? 20;
        $last_sync  = get_option( 'vincocrm_last_order_sync', '' );

        $args = [
            'limit'   => $batch_size,
            'orderby' => 'date',
            'order'   => 'ASC',
            'status'  => [ 'wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending' ],
            'type'    => 'shop_order',
        ];

        if ( ! empty( $last_sync ) ) {
            $args['date_created'] = '>' . $last_sync;
        }

        $orders = wc_get_orders( $args );

        foreach ( $orders as $order ) {
            $this->push_order( $order );
        }

        if ( ! empty( $orders ) ) {
            $last_order = end( $orders );
            $date       = $last_order->get_date_created();
            if ( $date ) {
                update_option( 'vincocrm_last_order_sync', $date->format( 'Y-m-d H:i:s' ) );
            }
        }
    }

    /**
     * AJAX: Manual sync trigger.
     */
    public function ajax_trigger_sync(): void {
        check_ajax_referer( 'vincocrm_admin', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'vincocrm' ) ] );
        }

        $this->bulk_sync();

        wp_send_json_success( [ 'message' => __( 'Order sync triggered.', 'vincocrm' ) ] );
    }

    /**
     * Push a single WC order to VincoCRM.
     *
     * @param \WC_Order $order
     */
    private function push_order( \WC_Order $order ): void {
        if ( ! $this->api->is_connected() ) {
            return;
        }

        $items = [];
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $items[] = [
                'name'      => $item->get_name(),
                'quantity'  => $item->get_quantity(),
                'price'     => (float) $item->get_total(),
                'sku'       => $product ? $product->get_sku() : '',
                'productId' => $product ? $product->get_id() : 0,
            ];
        }

        $tracking = $this->get_tracking_data( $order );

        $data = [
            'externalId'    => (string) $order->get_id(),
            'orderNumber'   => $order->get_order_number(),
            'status'        => $order->get_status(),
            'totalAmount'   => (int) round( (float) $order->get_total() * 100 ),
            'currency'      => strtolower( $order->get_currency() ),
            'customerEmail' => $order->get_billing_email(),
            'customerName'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
            'customerPhone' => $order->get_billing_phone(),
            'itemsJson'     => $items,
            'shippingJson'  => [
                'firstName' => $order->get_shipping_first_name(),
                'lastName'  => $order->get_shipping_last_name(),
                'address1'  => $order->get_shipping_address_1(),
                'address2'  => $order->get_shipping_address_2(),
                'city'      => $order->get_shipping_city(),
                'state'     => $order->get_shipping_state(),
                'postcode'  => $order->get_shipping_postcode(),
                'country'   => $order->get_shipping_country(),
            ],
            'billingJson'   => [
                'firstName' => $order->get_billing_first_name(),
                'lastName'  => $order->get_billing_last_name(),
                'email'     => $order->get_billing_email(),
                'phone'     => $order->get_billing_phone(),
                'address1'  => $order->get_billing_address_1(),
                'city'      => $order->get_billing_city(),
                'state'     => $order->get_billing_state(),
                'postcode'  => $order->get_billing_postcode(),
                'country'   => $order->get_billing_country(),
            ],
            'trackingJson'  => $tracking,
            'orderDate'     => $order->get_date_created() ? $order->get_date_created()->format( 'c' ) : null,
        ];

        // Use store-scoped endpoint if connected via OAuth.
        $store_id = get_option( 'vincocrm_wc_store_id', '' );

        if ( ! empty( $store_id ) ) {
            // The CRM handles order sync through its store endpoints.
            // This path is for direct push when not using OAuth.
            return;
        }

        $result = $this->api->post( '/ecommerce/orders', $data );

        if ( ! $result['success'] ) {
            $status = $result['status'] ?? 0;
            if ( $status >= 500 || 0 === $status ) {
                $this->queue->enqueue( 'POST', '/ecommerce/orders', $data, 'order_sync' );
            }
        }
    }

    /**
     * Extract tracking data from WooCommerce order.
     *
     * Supports:
     * - WooCommerce Shipment Tracking (official extension)
     * - VillaTheme WooCommerce Order Tracking
     *
     * @param \WC_Order $order
     * @return array
     */
    private function get_tracking_data( \WC_Order $order ): array {
        $tracking = [];

        // WooCommerce Shipment Tracking (official).
        $wc_tracking = $order->get_meta( '_wc_shipment_tracking_items', true );
        if ( is_array( $wc_tracking ) ) {
            foreach ( $wc_tracking as $item ) {
                $tracking[] = [
                    'trackingNumber' => $item['tracking_number'] ?? '',
                    'carrier'        => $item['tracking_provider'] ?? $item['custom_tracking_provider'] ?? '',
                    'url'            => $item['tracking_link'] ?? $item['custom_tracking_link'] ?? '',
                    'dateShipped'    => $item['date_shipped'] ?? '',
                ];
            }
        }

        // VillaTheme WooCommerce Order Tracking.
        $vi_tracking = $order->get_meta( '_vi_wot_order_item_tracking', true );
        if ( is_array( $vi_tracking ) ) {
            foreach ( $vi_tracking as $item ) {
                $tracking[] = [
                    'trackingNumber' => $item['tracking_number'] ?? '',
                    'carrier'        => $item['carrier_name'] ?? '',
                    'url'            => $item['tracking_url'] ?? '',
                ];
            }
        }

        return $tracking;
    }
}
