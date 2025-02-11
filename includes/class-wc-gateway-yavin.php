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
		$api_url = YAVIN_API_URL . '/api/v5/ecommerce/generate_link/';
		$api_key = YAVIN_API_KEY; // Replace with your actual Yavin API key
		$data = array(
			'cart_id' => $order->get_id(),
			'amount' => intval($order->get_total()),
			'return_url_success' => wc_get_checkout_url(),
			'return_url_cancelled' => wc_get_checkout_url(),
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

		// Return the decoded response along with the status code
		return array(
			'status_code' => $status_code,
			'response' => $decoded_response
		);
	}
}
