<?php

/**
 * Plugin Name: Yavin WooCommerce Gateway
 * Description: Custom WooCommerce payment gateway integration with Yavin API
 * Version: 1.1
 * Author: Yavin
 * Text Domain: yavin-woocommerce-gateway
 */

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

function get_yavin_api_credentials()
{
	$gateways = WC()->payment_gateways->payment_gateways();

	if (isset($gateways['yavin'])) { // Ensure correct payment gateway ID
		$gateway = $gateways['yavin'];
		// Get the selected environment
		$environment = $gateway->get_option('environment');
		if ($environment === 'live') {
			return array(
				'yapi_key' => $gateway->get_option('liveapikey'),
				'yapi_url' => $gateway->get_option('liveapiurl')
			);
		} else { // Default to sandbox if not live
			return array(
				'yapi_key' => $gateway->get_option('sandboxapikey'),
				'yapi_url' => $gateway->get_option('sandboxapiurl')
			);
		}
	}
	return array('yapi_key' => '', 'yapi_url' => ''); // Return empty values if gateway not found
}

// Check if WooCommerce is active
function yavin_is_woocommerce_active()
{
	return class_exists('WooCommerce');
}

if (yavin_is_woocommerce_active()) {
	flush_rewrite_rules();
	add_action('plugins_loaded', 'yavin_woocommerce_gateway_init', 11);
	add_action('init', 'yavin_payment_callback');

	function yavin_woocommerce_gateway_init()
	{
		if (! class_exists('WC_Payment_Gateway')) {
			return;
		}

		include_once 'includes/class-wc-gateway-yavin.php';

		add_filter('woocommerce_payment_gateways', 'add_yavin_gateway');
		function add_yavin_gateway($gateways)
		{
			$gateways[] = 'WC_Gateway_Yavin'; // Add Yavin gateway to WooCommerce
			return $gateways;
		}
	}

	// Register the custom endpoint
	function yavin_payment_callback()
	{

		if (isset($_GET['cartId']) && isset($_GET['status'])) {

			$orderID = sanitize_text_field($_GET['cartId']);
			$status  = sanitize_text_field($_GET['status']);

			yavinpayment_custom_logs($orderID);
			yavinpayment_custom_logs($status);
			// Process the callback
			yavin_process_payment_callback($orderID, $status);
		}
	}

	function yavin_process_payment_callback($orderID, $status)
	{

		$orderKey = explode("-", $orderID);
		$wooCommerceOrderID = $orderKey[1];
		if (!$wooCommerceOrderID) {
			// If order ID is not found, handle the error
			wp_die('Invalid order.');
		}

		$order = wc_get_order($wooCommerceOrderID);

		// Check the status from the callback URL
		if ($status === 'ok') {
			// Mark the order as completed
			$order->payment_complete();
			$order->reduce_order_stock();

			// Clear the cart
			WC()->cart->empty_cart();


			$tansactionDetails = getYavinTansactionDetails($orderID);
			if ($tansactionDetails['response']['status'] === 'ok' && !empty($tansactionDetails['response']['transactions'][0]['transaction_id'])) {
				$note = sprintf('Payment successfully completed via Yavin. Transaction ID: %s', $tansactionDetails['response']['transactions'][0]['transaction_id']);
				$order->add_order_note($note);
				$note = sprintf('Payment successfully completed via Yavin. Payment Link: %s', $tansactionDetails['response']['payment_link']);
				$order->add_order_note($note);
				$note = sprintf('Payment successfully completed via Yavin. Pan Detail: %s', $tansactionDetails['response']['transactions'][0]['pan']);
				$order->add_order_note($note);
			}

			// Redirect to the order received page
			$order_received_url = $order->get_checkout_order_received_url();
			wp_redirect($order_received_url);
			exit;
		} else {
			// If status is not ok, mark the order as failed
			$order->update_status('failed', __('Payment failed or cancelled', 'yavin-woocommerce-gateway'));
			//WC()->cart->empty_cart();
			// Redirect to a custom error page (optional)
			wp_redirect(wc_get_checkout_url());
			exit;
		}
	}
	function getYavinTansactionDetails($orderID)
	{
		$credentials = get_yavin_api_credentials();
		$api_url = $credentials['yapi_url'];
		$api_url = $api_url . '/api/v5/ecommerce/get_cart_information/';
		$api_key = $credentials['yapi_key'];
		$data = array(
			'cart_id' => $orderID,
		);

		// Make API request
		$response = wp_remote_post($api_url, array(
			'method'    => 'POST',
			'body'      => json_encode($data),
			'headers'   => array(
				'Content-Type' => 'application/json',
				'Yavin-Secret' => $api_key,
			),
		));

		// Get the response body (the actual content returned by the API)
		$response_body = wp_remote_retrieve_body($response);
		yavinpayment_custom_logs($response_body);

		// Decode the response body (if it's a JSON response)
		$decoded_response = json_decode($response_body, true);

		// Return the decoded response along with the status code
		return array(
			'response' => $decoded_response
		);
	}
	function yavinpayment_custom_logs($message)
	{

		if (is_array($message)) {
			$message = json_encode($message);
		}

		$upload = wp_upload_dir();
		$log_filename = $upload['basedir'] . "/yavinpayment-log";
		if (!file_exists($log_filename)) {
			mkdir($log_filename, 0777, true);
		}

		$log_file_data = $log_filename . '/yavinpayment-log-' . date('d-M-Y') . '.log';
		file_put_contents($log_file_data, "==============================" . date("Y-m-d h:i:sa") . "==============================\n", FILE_APPEND);
		file_put_contents($log_file_data, $message . "\n", FILE_APPEND);
		file_put_contents($log_file_data, "==============================" . date("Y-m-d h:i:sa") . "==============================\n", FILE_APPEND);
	}

	add_action('rest_api_init', 'register_yavin_webhook_endpoint');

	function register_yavin_webhook_endpoint()
	{
		register_rest_route('yavin/v1', '/webhook/', array(
			'methods' => 'POST',
			'callback' => 'handle_yavin_webhook_request',
			'permission_callback' => '__return_true', // No authentication required
		));
	}


	function handle_yavin_webhook_request($data)
	{
		// Get the incoming data from Yavin
		$body = $data->get_body();
		$json_data = json_decode($body, true);

		// Log the data for debugging
		yavinpayment_custom_logs("handle_yavin_webhook_request");
		yavinpayment_custom_logs("json_data" . json_encode($json_data));
		yavinpayment_custom_logs("POST" . json_encode($_POST));

		// Ensure required parameters are present
		if (isset($json_data['cart_id']) && isset($json_data['status'])) {
			$cart_id = sanitize_text_field($json_data['cart_id']);
			$status = sanitize_text_field($json_data['status']);

			$orderKey = explode("-", $cart_id);
			$wooCommerceOrderID = $orderKey[1];
			if (!$wooCommerceOrderID) {
				// If order ID is not found, handle the error
				wp_die('Invalid order.');
			}

			yavinpayment_custom_logs("handle_yavin_webhook_request" . $wooCommerceOrderID);
			$order = wc_get_order($wooCommerceOrderID);

			if ($order) {
				// Update order status based on Yavin's status
				if ($status === 'ok') {
					$order->payment_complete(); // Mark the order as completed
					$order->add_order_note('Payment confirmed via Yavin.');
				} else {
					$order->update_status('failed', 'Payment failed via Yavin.'); // Mark as failed
				}

				// Save order status and return a response
				$order->save();
				return new WP_REST_Response('Success', 200);
			} else {
				// If no order found, log the issue
				error_log('Order not found for cartId: ' . $cart_id);

				// Return error response
				return new WP_REST_Response('Order not found', 400);
			}
		} else {
			// Log the issue if cartId or status is missing
			error_log('Missing cartId or status in webhook request');

			// Return error response for missing data
			return new WP_REST_Response('Missing cartId or status', 400);
		}
	}


	// Get order by cart ID (used to map Yavin cart ID to WooCommerce order)
	function get_order_by_cart_id($cart_id)
	{
		$args = array(
			'post_type'   => 'shop_order',
			'meta_key'    => '_cart_id', // Assuming cart ID is stored as metadata
			'meta_value'  => $cart_id,
			'posts_per_page' => 1,
			'post_status' => array('wc-pending', 'wc-processing', 'wc-completed', 'wc-failed'),
		);

		$orders = get_posts($args);

		if (! empty($orders)) {
			return wc_get_order($orders[0]->ID);
		}

		return null;
	}
} else {
	// WooCommerce is not active, display a message or error
	add_action('admin_notices', 'yavin_woocommerce_not_active');
	function yavin_woocommerce_not_active()
	{
		echo '<div class="error"><p><strong>Yavin WooCommerce Gateway:</strong> WooCommerce plugin is not active. Please install and activate WooCommerce to use this payment gateway.</p></div>';
	}
}
