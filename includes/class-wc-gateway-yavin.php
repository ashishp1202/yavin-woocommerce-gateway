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
		$response = $this->call_yavin_api($order);

		if (isset($response['status']) && $response['status'] == 'ok') {
			// Payment link generated successfully
			$payment_link = $response['payment_link'];
			wp_redirect($payment_link);
			exit();
			return array(
				'result'   => 'success',
				'redirect' => $payment_link, // Redirect to Yavin's payment page
			);
		} else {
			// Payment failed
			wc_add_notice(__('Payment failed. Please try again.', 'yavin-woocommerce-gateway'), 'error');
			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}
	}

	// Call Yavin API (replace with actual implementation)
	private function call_yavin_api($order)
	{
		$api_url = 'https://api.sandbox.yavin.com/api/v5/ecommerce/generate_link/';
		$api_key = '8H3pMUetTnAIiqRtxxRZonAsSYdm1lavQXjFyAHEipbI516AP0'; // Replace with your actual Yavin API key
		$data = array(
			'cart_id' => 'my_custom_cart_id_' . $order->get_id(),
			'amount' => $order->get_total(), // Convert to cents
			'return_url_success' => 'https://stg-yavinshop-testshop.kinsta.cloud/checkout',
			'return_url_cancelled' => 'https://stg-yavinshop-testshop.kinsta.cloud/checkout',
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
		echo "<pre>";
		print_r($order->get_total());
		echo "<pre>";
		print_r($response);
		exit();

		return json_decode(wp_remote_retrieve_body($response), true);
	}
}
