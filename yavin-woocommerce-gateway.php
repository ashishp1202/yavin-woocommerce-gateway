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
define('YAVIN_API_URL', 'https://api.sandbox.yavin.com');
define('YAVIN_API_KEY', '8H3pMUetTnAIiqRtxxRZonAsSYdm1lavQXjFyAHEipbI516AP0');

// Check if WooCommerce is active
function yavin_is_woocommerce_active()
{
	return class_exists('WooCommerce');
}

if (yavin_is_woocommerce_active()) {
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
		if (!$orderID) {
			// If order ID is not found, handle the error
			wp_die('Invalid order.');
		}

		$order = wc_get_order($orderID);

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
			WC()->cart->empty_cart();
			// Redirect to a custom error page (optional)
			wp_redirect(wc_get_checkout_url());
			exit;
		}
	}
	function getYavinTansactionDetails($orderID)
	{
		$api_url = YAVIN_API_URL . '/api/v5/ecommerce/get_cart_information/';
		$api_key = YAVIN_API_KEY; // Replace with your actual Yavin API key
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
} else {
	// WooCommerce is not active, display a message or error
	add_action('admin_notices', 'yavin_woocommerce_not_active');
	function yavin_woocommerce_not_active()
	{
		echo '<div class="error"><p><strong>Yavin WooCommerce Gateway:</strong> WooCommerce plugin is not active. Please install and activate WooCommerce to use this payment gateway.</p></div>';
	}
}
