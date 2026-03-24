<?php
/**
 * VincoCRM API Client
 *
 * Handles all HTTP communication with the VincoCRM REST API
 * (https://www.vincocrm.ai/api/v1).
 *
 * @package VincoCRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * VincoCRM_API class.
 */
class VincoCRM_API {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * API key used for authentication.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private $timeout;

	/**
	 * Constructor.
	 *
	 * @param string $api_key  API key for VincoCRM.
	 * @param string $base_url Optional override for the API base URL.
	 * @param int    $timeout  Optional HTTP request timeout in seconds.
	 */
	public function __construct( $api_key, $base_url = VINCOCRM_API_BASE_URL, $timeout = 30 ) {
		$this->api_key  = sanitize_text_field( $api_key );
		$this->base_url = untrailingslashit( esc_url_raw( $base_url ) );
		$this->timeout  = absint( $timeout );
	}

	/**
	 * Create or update a contact in VincoCRM.
	 *
	 * @param array $contact_data {
	 *     Contact fields to sync.
	 *
	 *     @type string $email       Contact e-mail address (required).
	 *     @type string $first_name  First name.
	 *     @type string $last_name   Last name.
	 *     @type string $phone       Phone number.
	 *     @type string $company     Company / billing company.
	 *     @type string $address     Street address.
	 *     @type string $city        City.
	 *     @type string $state       State / province.
	 *     @type string $postcode    Postal code.
	 *     @type string $country     Two-letter country code.
	 *     @type array  $meta        Arbitrary key-value pairs.
	 * }
	 * @return array|WP_Error Decoded API response body or a WP_Error on failure.
	 */
	public function sync_contact( array $contact_data ) {
		return $this->request( 'POST', '/contacts/sync', $contact_data );
	}

	/**
	 * Create or update an order in VincoCRM.
	 *
	 * @param array $order_data {
	 *     Order fields to sync.
	 *
	 *     @type string|int $order_id       WooCommerce order ID (used as external reference).
	 *     @type string     $status         Order status.
	 *     @type string     $currency       Currency code.
	 *     @type float      $total          Order total.
	 *     @type float      $subtotal       Order subtotal (excluding tax / shipping).
	 *     @type float      $tax_total      Total tax.
	 *     @type float      $shipping_total Shipping cost.
	 *     @type string     $customer_email Customer e-mail.
	 *     @type string     $customer_note  Order note from the customer.
	 *     @type array      $line_items     Array of line-item arrays (name, sku, qty, total).
	 *     @type array      $billing        Billing address array.
	 *     @type array      $shipping       Shipping address array.
	 *     @type string     $created_at     ISO-8601 order creation date.
	 * }
	 * @return array|WP_Error Decoded API response body or a WP_Error on failure.
	 */
	public function sync_order( array $order_data ) {
		return $this->request( 'POST', '/orders/sync', $order_data );
	}

	/**
	 * Test the API connection by calling the health-check endpoint.
	 *
	 * @return array|WP_Error Decoded API response body or a WP_Error on failure.
	 */
	public function test_connection() {
		return $this->request( 'GET', '/ping' );
	}

	/**
	 * Perform an HTTP request against the VincoCRM API.
	 *
	 * @param string $method   HTTP method (GET, POST, PUT, DELETE).
	 * @param string $endpoint API endpoint path, e.g. '/contacts/sync'.
	 * @param array  $body     Request body (will be JSON-encoded for non-GET requests).
	 * @return array|WP_Error Decoded response body array or WP_Error on failure.
	 */
	private function request( $method, $endpoint, array $body = array() ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'vincocrm_missing_api_key',
				__( 'VincoCRM API key is not configured.', 'vincocrm' )
			);
		}

		$url = $this->base_url . $endpoint;

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => $this->timeout,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'X-Source'      => 'vincocrm-wordpress/' . VINCOCRM_VERSION,
			),
		);

		if ( ! empty( $body ) && 'GET' !== strtoupper( $method ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'HTTP request failed', $response->get_error_message(), $endpoint );
			return $response;
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$decoded       = json_decode( $response_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = isset( $decoded['message'] )
				? $decoded['message']
				/* translators: %d: HTTP status code */
				: sprintf( __( 'VincoCRM API returned HTTP %d.', 'vincocrm' ), $status_code );

			$this->log_error( 'API error', $error_message, $endpoint );

			return new WP_Error(
				'vincocrm_api_error',
				$error_message,
				array(
					'status'   => $status_code,
					'response' => $decoded,
				)
			);
		}

		return is_array( $decoded ) ? $decoded : array( 'raw' => $response_body );
	}

	/**
	 * Log an error message using the WordPress debug log.
	 *
	 * @param string $context Short description of the context.
	 * @param string $message Detailed error message.
	 * @param string $endpoint API endpoint that triggered the error.
	 */
	private function log_error( $context, $message, $endpoint ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[VincoCRM] %s (%s): %s', $context, $endpoint, $message ) );
		}
	}
}
