<?php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class WC_Gateway_Yavin extends WC_Payment_Gateway
{

	public function __construct()
	{
		$this->id = 'yavin';
		$this->icon = ''; // You can add a custom icon here
		$this->has_fields = false;
		$this->method_title = 'Yavin Payment Gateway';
		$this->method_description = 'Accept payments via Yavin API';
		$this->init_form_fields();
		$this->init_settings();

		// Define user settings
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->enabled = $this->get_option('enabled');

		// Hook to save settings
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('init', array($this, 'yavin_payment_callback'));
	}

	// Setup settings fields for the gateway
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __('Enable/Disable', 'yavin-woocommerce-gateway'),
				'type'    => 'checkbox',
				'label'   => __('Enable Yavin Payment Gateway', 'yavin-woocommerce-gateway'),
				'default' => 'no'
			),
			'title' => array(
				'title'       => __('Title', 'yavin-woocommerce-gateway'),
				'type'        => 'text',
				'description' => __('This controls the title of the gateway during checkout.', 'yavin-woocommerce-gateway'),
				'default'     => 'Yavin Payment'
			),
			'description' => array(
				'title'       => __('Description', 'yavin-woocommerce-gateway'),
				'type'        => 'textarea',
				'description' => __('This controls the description of the gateway during checkout.', 'yavin-woocommerce-gateway'),
				'default'     => 'Pay using Yavin'
			),
		);
	}

	// Process the payment (call the Yavin API here)
	public function process_payment($order_id)
	{

		$order = wc_get_order($order_id);

		// Call Yavin API to generate the payment link
		$api_result = $this->call_yavin_api($order);
		$status_code = $api_result['status_code'];
		$response = $api_result['response'];
		if ($status_code === 201) {
			$payment_link = $response['payment_link'];
			if (!empty($payment_link)) {
				return array(
					'result'   => 'success',
					'redirect' => $payment_link,
				);
			}
		} else {
			if ($response['errors']['cart_id'] && ($response['errors']['cart_id'][0] === 'This cart_id already exists')) {
				return array(
					'result'   => 'success',
					'redirect' => $response['errors']['cart_id'][1],
				);;
				exit();
			} else {
				// Payment failed
				wc_add_notice(__('Payment failed. Please try again.', 'yavin-woocommerce-gateway'), 'error');
				return array(
					'result'   => 'failure',
					'redirect' => '',
				);
			}
		}
	}

	// Call Yavin API (replace with actual implementation)
	private function call_yavin_api($order)
	{
		$api_url = 'https://api.sandbox.yavin.com/api/v5/ecommerce/generate_link/';
		$api_key = '8H3pMUetTnAIiqRtxxRZonAsSYdm1lavQXjFyAHEipbI516AP0'; // Replace with your actual Yavin API key
		$data = array(
			'cart_id' => $order->get_id(),
			'amount' => intval($order->get_total()),
			'return_url_success' => home_url() . '/checkout/',
			'return_url_cancelled' => home_url() . '/checkout/',
			'order_number' => $order->get_order_number(),
			'currency' => get_woocommerce_currency(),
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

		// Get HTTP status code
		$status_code = wp_remote_retrieve_response_code($response);

		// Get the response body (the actual content returned by the API)
		$response_body = wp_remote_retrieve_body($response);


		// Decode the response body (if it's a JSON response)
		$decoded_response = json_decode($response_body, true);
		/* echo "<pre>";
		print_r($decoded_response);
		echo "<pre>";
		print_r($status_code);
		exit(); */
		// Return the decoded response along with the status code
		return array(
			'status_code' => $status_code,
			'response' => $decoded_response
		);
	}

	// Register the custom endpoint

	function yavin_payment_callback()
	{
		if (isset($_GET['cartId']) && isset($_GET['status'])) {
			$orderID = sanitize_text_field($_GET['cartId']);
			$status  = sanitize_text_field($_GET['status']);

			// Process the callback
			$this->yavin_process_payment_callback($orderID, $status);
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

			// Redirect to the order received page
			$order_received_url = $order->get_checkout_order_received_url();
			wp_redirect($order_received_url);
			exit;
		} else {
			// If status is not ok, mark the order as failed
			$order->update_status('failed', __('Payment failed or cancelled', 'yavin-woocommerce-gateway'));

			// Redirect to a custom error page (optional)
			wp_redirect(site_url('/payment-failed'));
			exit;
		}
	}
}
