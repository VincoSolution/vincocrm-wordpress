<?php
/**
 * VincoCRM WooCommerce Integration
 *
 * Hooks into WooCommerce order and customer events and pushes data to the
 * VincoCRM API.
 *
 * @package VincoCRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * VincoCRM_WooCommerce class.
 */
class VincoCRM_WooCommerce {

	/**
	 * Register WooCommerce hooks.
	 */
	public static function init() {
		// Sync on order status changes.
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_order_status_changed' ), 10, 3 );

		// Sync when a new order is created (covers guest checkouts).
		add_action( 'woocommerce_new_order', array( __CLASS__, 'on_new_order' ), 10, 2 );

		// Sync when a customer registers.
		add_action( 'woocommerce_created_customer', array( __CLASS__, 'on_customer_created' ), 10, 1 );

		// Sync when a customer's profile is updated.
		add_action( 'woocommerce_customer_save_address', array( __CLASS__, 'on_customer_updated' ), 10, 1 );
		add_action( 'woocommerce_save_account_details', array( __CLASS__, 'on_customer_updated' ), 10, 1 );
	}

	// ── Order hooks ──────────────────────────────────────────────────────── //

	/**
	 * Triggered when an order's status changes.
	 *
	 * @param int    $order_id   WooCommerce order ID.
	 * @param string $old_status Previous order status (without 'wc-' prefix).
	 * @param string $new_status New order status (without 'wc-' prefix).
	 */
	public static function on_order_status_changed( $order_id, $old_status, $new_status ) {
		if ( ! VincoCRM_Settings::get_option( 'sync_orders', 1 ) ) {
			return;
		}

		$watched_statuses = VincoCRM_Settings::get_option( 'sync_order_statuses', array( 'wc-processing', 'wc-completed' ) );

		// WooCommerce passes the status without the 'wc-' prefix; normalise.
		if ( ! in_array( 'wc-' . $new_status, $watched_statuses, true ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		self::sync_order( $order );
	}

	/**
	 * Triggered when a brand-new order is inserted in the database.
	 *
	 * @param int      $order_id WooCommerce order ID.
	 * @param WC_Order $order    Order object.
	 */
	public static function on_new_order( $order_id, $order ) {
		if ( ! VincoCRM_Settings::get_option( 'sync_orders', 1 ) ) {
			return;
		}

		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		self::sync_order( $order );
	}

	// ── Customer hooks ───────────────────────────────────────────────────── //

	/**
	 * Triggered when a new WooCommerce customer is created.
	 *
	 * @param int $customer_id WordPress user ID of the new customer.
	 */
	public static function on_customer_created( $customer_id ) {
		if ( ! VincoCRM_Settings::get_option( 'sync_customers', 1 ) ) {
			return;
		}

		self::sync_customer( $customer_id );
	}

	/**
	 * Triggered when a WooCommerce customer updates their account details or
	 * address.
	 *
	 * @param int $customer_id WordPress user ID.
	 */
	public static function on_customer_updated( $customer_id ) {
		if ( ! VincoCRM_Settings::get_option( 'sync_customers', 1 ) ) {
			return;
		}

		self::sync_customer( $customer_id );
	}

	// ── Sync helpers ─────────────────────────────────────────────────────── //

	/**
	 * Build the order payload and push it to the VincoCRM API.
	 *
	 * @param WC_Order $order Order object.
	 * @return array|WP_Error API response or WP_Error on failure.
	 */
	public static function sync_order( WC_Order $order ) {
		$api = self::get_api();
		if ( ! $api ) {
			return new WP_Error( 'vincocrm_missing_api_key', __( 'VincoCRM API key is not set.', 'vincocrm' ) );
		}

		$line_items = array();
		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $item */
			$product    = $item->get_product();
			$line_items[] = array(
				'name'     => $item->get_name(),
				'sku'      => $product ? $product->get_sku() : '',
				'quantity' => $item->get_quantity(),
				'total'    => (float) $item->get_total(),
			);
		}

		$payload = array(
			'external_id'     => $order->get_id(),
			'status'          => $order->get_status(),
			'currency'        => $order->get_currency(),
			'total'           => (float) $order->get_total(),
			'subtotal'        => (float) $order->get_subtotal(),
			'tax_total'       => (float) $order->get_total_tax(),
			'shipping_total'  => (float) $order->get_shipping_total(),
			'customer_email'  => $order->get_billing_email(),
			'customer_note'   => $order->get_customer_note(),
			'line_items'      => $line_items,
			'billing'         => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'company'    => $order->get_billing_company(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
				'phone'      => $order->get_billing_phone(),
				'email'      => $order->get_billing_email(),
			),
			'shipping'        => array(
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
				'country'    => $order->get_shipping_country(),
			),
			'created_at'      => $order->get_date_created() ? $order->get_date_created()->format( 'c' ) : '',
			'source'          => 'woocommerce',
		);

		/**
		 * Filter the order payload before sending it to VincoCRM.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $payload Order data array.
		 * @param WC_Order $order   WooCommerce order object.
		 */
		$payload = apply_filters( 'vincocrm_sync_order_payload', $payload, $order );

		$result = $api->sync_order( $payload );

		if ( is_wp_error( $result ) ) {
			/**
			 * Fires when an order sync fails.
			 *
			 * @since 1.0.0
			 *
			 * @param WP_Error $result   The error object.
			 * @param WC_Order $order    The WooCommerce order.
			 * @param array    $payload  The payload that was attempted.
			 */
			do_action( 'vincocrm_order_sync_failed', $result, $order, $payload );
		} else {
			/**
			 * Fires when an order is successfully synced to VincoCRM.
			 *
			 * @since 1.0.0
			 *
			 * @param array    $result  API response.
			 * @param WC_Order $order   The WooCommerce order.
			 * @param array    $payload The payload that was sent.
			 */
			do_action( 'vincocrm_order_synced', $result, $order, $payload );
		}

		return $result;
	}

	/**
	 * Build the customer/contact payload and push it to the VincoCRM API.
	 *
	 * @param int $customer_id WordPress / WooCommerce customer ID.
	 * @return array|WP_Error API response or WP_Error on failure.
	 */
	public static function sync_customer( $customer_id ) {
		$api = self::get_api();
		if ( ! $api ) {
			return new WP_Error( 'vincocrm_missing_api_key', __( 'VincoCRM API key is not set.', 'vincocrm' ) );
		}

		$customer = new WC_Customer( $customer_id );

		$payload = array(
			'external_id' => $customer_id,
			'email'       => $customer->get_email(),
			'first_name'  => $customer->get_first_name(),
			'last_name'   => $customer->get_last_name(),
			'phone'       => $customer->get_billing_phone(),
			'company'     => $customer->get_billing_company(),
			'address'     => $customer->get_billing_address_1(),
			'address_2'   => $customer->get_billing_address_2(),
			'city'        => $customer->get_billing_city(),
			'state'       => $customer->get_billing_state(),
			'postcode'    => $customer->get_billing_postcode(),
			'country'     => $customer->get_billing_country(),
			'source'      => 'woocommerce',
		);

		/**
		 * Filter the customer/contact payload before sending it to VincoCRM.
		 *
		 * @since 1.0.0
		 *
		 * @param array       $payload  Contact data array.
		 * @param WC_Customer $customer WooCommerce customer object.
		 */
		$payload = apply_filters( 'vincocrm_sync_customer_payload', $payload, $customer );

		$result = $api->sync_contact( $payload );

		if ( is_wp_error( $result ) ) {
			/**
			 * Fires when a customer sync fails.
			 *
			 * @since 1.0.0
			 *
			 * @param WP_Error    $result   The error object.
			 * @param WC_Customer $customer The WooCommerce customer.
			 * @param array       $payload  The payload that was attempted.
			 */
			do_action( 'vincocrm_customer_sync_failed', $result, $customer, $payload );
		} else {
			/**
			 * Fires when a customer is successfully synced to VincoCRM.
			 *
			 * @since 1.0.0
			 *
			 * @param array       $result   API response.
			 * @param WC_Customer $customer The WooCommerce customer.
			 * @param array       $payload  The payload that was sent.
			 */
			do_action( 'vincocrm_customer_synced', $result, $customer, $payload );
		}

		return $result;
	}

	/**
	 * Instantiate the API client using the saved settings.
	 *
	 * @return VincoCRM_API|null API instance, or null if no API key is configured.
	 */
	private static function get_api() {
		$api_key = VincoCRM_Settings::get_option( 'api_key' );
		if ( empty( $api_key ) ) {
			return null;
		}

		$base_url = VincoCRM_Settings::get_option( 'api_base_url', VINCOCRM_API_BASE_URL );

		return new VincoCRM_API( $api_key, $base_url );
	}
}
