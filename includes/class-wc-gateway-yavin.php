<?php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class WC_Gateway_Yavin extends WC_Payment_Gateway
{

	public function __construct()
	{
		$this->id = 'yavin';
		$this->icon = $this->get_icon(); // You can add a custom icon here
		$this->has_fields = false;
		$this->method_title = 'Yavin Payment Gateway';
		$this->method_description = 'Accept payments via Yavin API';
		$this->init_form_fields();
		$this->init_settings();

		// Define user settings
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->enabled = $this->get_option('enabled');
		$this->logos  = $this->get_option('logos');
		$this->environment = $this->get_option('environment');
		$this->liveapikey  = $this->get_option('liveapikey');
		$this->liveapiurl  = $this->get_option('liveapiurl');
		$this->sandboxapikey  = $this->get_option('sandboxapikey');
		$this->sandboxapiurl  = $this->get_option('sandboxapiurl');

		// Hook to save settings
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
	}

	private function get_yavin_api_credentials()
	{
		$environment = $this->get_option('environment');
		return array(
			'yapi_key' => ($environment === 'live') ? $this->get_option('liveapikey') : $this->get_option('sandboxapikey'),
			'yapi_url' => ($environment === 'live') ? $this->get_option('liveapiurl') : $this->get_option('sandboxapiurl'),
		);
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
			'logos' => array(
				'title'       => __('Yavin Payment Logos', 'yavin-woocommerce-gateway'),
				'type'        => 'textarea',
				'description' => __('Enter the URL of the payment logos (CB, Visa, Mastercard).', 'yavin-woocommerce-gateway'),
				'default'     => '', // Default logo path
				'css'         => 'width: 400px;',
			),
			'environment' => array(
				'title'       => __('API Environment', 'woocommerce'),
				'type'        => 'select',
				'description' => __('Choose whether to use the Live or Sandbox API.', 'woocommerce'),
				'default'     => 'sandbox',
				'desc_tip'    => true,
				'options'     => array(
					'live'    => __('Live', 'woocommerce'),
					'sandbox' => __('Sandbox', 'woocommerce')
				)
			),
			'sandboxapikey' => array(
				'title'       => __('Add Sandbox API Key', 'yavin-woocommerce-gateway'),
				'type'        => 'text',
				'description' => __('Enter the  Sandbox API key.', 'yavin-woocommerce-gateway'),
				'default'     => '',
			),
			'sandboxapiurl' => array(
				'title'       => __('Add Sandbox API URL', 'yavin-woocommerce-gateway'),
				'type'        => 'text',
				'description' => __('Enter Sandbox the API URL.', 'yavin-woocommerce-gateway'),
				'default'     => '',
			),
			'liveapikey' => array(
				'title'       => __('Add Live API Key', 'yavin-woocommerce-gateway'),
				'type'        => 'text',
				'description' => __('Enter the  Live API key.', 'yavin-woocommerce-gateway'),
				'default'     => '',
			),
			'liveapiurl' => array(
				'title'       => __('Add Live API URL', 'yavin-woocommerce-gateway'),
				'type'        => 'text',
				'description' => __('Enter Live the API URL.', 'yavin-woocommerce-gateway'),
				'default'     => '',
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

		// Fetch API credentials dynamically
		$credentials = $this->get_yavin_api_credentials();
		$api_key = $credentials['yapi_key'];
		$yapi_url = $credentials['yapi_url'];
		$api_url = $yapi_url . '/api/v5/ecommerce/generate_link/';

		$data = array(
			'cart_id' => bin2hex(random_bytes(8)) . "-" . $order->get_id(),
			'amount' => intval($order->get_total() * 100),
			'return_url_success' => wc_get_checkout_url(),
			'return_url_cancelled' => wc_get_checkout_url(),
			'order_number' => $order->get_order_number(),
			'currency' => get_woocommerce_currency(),
		);
		$this->yavinpayment_custom_logs($data);

		// Make API request
		$response = wp_remote_post($api_url, array(
			'method'    => 'POST',
			'body'      => json_encode($data),
			'headers'   => array(
				'Content-Type' => 'application/json',
				'Yavin-Secret' => $api_key,
			),
		));
		$this->yavinpayment_custom_logs($response);
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

	public function yavinpayment_custom_logs($message)
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

	public function get_icon()
	{
		$logo_urls = $this->get_option('logos');
		if (!empty($logo_urls)) {
			$logos = explode("\n", $logo_urls); // Split URLs by new line
			$logo_html = '';

			foreach ($logos as $logo) {
				$logo = trim($logo); // Remove spaces
				if (!empty($logo)) {
					$logo_html .= '<img src="' . esc_url($logo) . '" alt="Payment Logo" style="max-width: 50px; height: auto;" />';
				}
			}

			$logo_html .= '';
			return $logo_html;
		}

		return parent::get_icon();
	}
}
