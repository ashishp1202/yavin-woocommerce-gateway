# Yavin WooCommerce Payment Gateway Plugin

## Overview

The **Yavin WooCommerce Payment Gateway Plugin** integrates the Yavin API with WooCommerce, allowing merchants to accept payments via Yavin. This plugin supports custom payment processing, order management, and payment status updates.

With this plugin, you can:
- Integrate Yavin as a payment method in your WooCommerce store.
- Process payments via Yavin.
- Automatically update WooCommerce orders with payment information, including the transaction ID.
- Add payment notes to WooCommerce orders to track successful transactions.

## Features
- **Payment Gateway Integration**: Add Yavin as a payment method during checkout.
- **Order Management**: Automatically mark orders as completed or failed based on the payment status.
- **Transaction Notes**: Add payment transaction details (such as the Yavin transaction ID) to the order notes.
- **Custom Payment Link**: Generate a payment link for users to complete their transactions on Yavin's platform.

## Requirements
- **WooCommerce**: This plugin requires WooCommerce to be installed and activated.
- **PHP 7.4+**: Make sure your hosting environment supports PHP 7.4 or higher.

## Installation

1. **Download the Plugin**: Download the plugin files and extract them on your local machine.
2. **Upload the Plugin**: Upload the plugin folder to your WooCommerce `wp-content/plugins/` directory.
3. **Activate the Plugin**: Go to the WordPress dashboard, navigate to **Plugins**, find the **Yavin WooCommerce Gateway** plugin, and click **Activate**.
4. **Configure the Gateway**: After activation, go to **WooCommerce** → **Settings** → **Payments** and enable the **Yavin Payment Gateway**.

## Configuration

1. **Enable the Payment Gateway**:
   - In the WooCommerce settings page under **Payments**, you should see the **Yavin Payment Gateway**.
   - Enable the gateway and configure the title, description, and other settings.

2. **API Key**:
   - You'll need to set up your **Yavin API Key** to authenticate requests. This is typically provided by Yavin when you sign up for their API.
   - Make sure to replace `'YAVIN_API_KEY'` in the code with your actual API key for communication with Yavin’s servers.

3. **Customizing Payment Flow**:
   - The plugin includes the ability to store the cart ID and the transaction ID. You can customize the order status transition and payment flow based on the Yavin API response.

4. **Handle Callback**:
   - Yavin will send a callback with the payment status (`ok`) and transaction details (`transactionId`). This is handled by the plugin and is automatically mapped to the corresponding WooCommerce order.

5. **Order Notes**:
   - After successful payment, the transaction ID from Yavin is logged in the order notes for tracking purposes.

## Usage

### Frontend:
- When users proceed to checkout and select **Yavin** as the payment method, they will be redirected to Yavin’s platform to complete the payment.
- After completing the payment, the user will be redirected back to your site based on the callback URL and the payment status.

### Admin:
- The WooCommerce order will be automatically marked as **completed** if the payment is successful (status: `ok`).
- The order will include a note like:
