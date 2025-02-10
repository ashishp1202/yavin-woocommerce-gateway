<?php

/**
 * Plugin Name: Yavin WooCommerce Gateway
 * Description: Custom WooCommerce payment gateway integration with Yavin API
 * Version: 1.1
 * Author: Your Name
 * Text Domain: yavin-woocommerce-gateway
 */

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// Check if WooCommerce is active
function yavin_is_woocommerce_active()
{
	return class_exists('WooCommerce');
}

if (yavin_is_woocommerce_active()) {
	add_action('plugins_loaded', 'yavin_woocommerce_gateway_init', 11);

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
} else {
	// WooCommerce is not active, display a message or error
	add_action('admin_notices', 'yavin_woocommerce_not_active');
	function yavin_woocommerce_not_active()
	{
		echo '<div class="error"><p><strong>Yavin WooCommerce Gateway:</strong> WooCommerce plugin is not active. Please install and activate WooCommerce to use this payment gateway.</p></div>';
	}
}
