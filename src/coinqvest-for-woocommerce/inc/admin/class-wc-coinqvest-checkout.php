<?php
namespace WC_COINQVEST\Inc\Admin;
use WC_COINQVEST\Inc\Libraries\Api;

defined('ABSPATH') or exit;

class WC_Coinqvest_Checkout {

	public function __construct() {

	}

	public function create_checkout($order_id, $options) {

		$order = new \WC_Order($order_id);

		/**
		 * Init the COINQVEST API
		 */

		$client = new Api\CQ_Merchant_Client(
			$options['api_key'],
			$options['api_secret'],
			true
		);

		/**
		 * Create a customer first
		 */

		$customer = array(
			'email' => sanitize_email($order->get_billing_email()),
			'firstname' => sanitize_text_field($order->get_billing_first_name()),
			'lastname' => sanitize_text_field($order->get_billing_last_name()),
			'company' => sanitize_text_field($order->get_billing_company()),
			'adr1' => sanitize_text_field($order->get_billing_address_1()),
			'adr2' => sanitize_text_field($order->get_billing_address_2()),
			'zip' => sanitize_text_field($order->get_billing_postcode()),
			'city' => sanitize_text_field($order->get_billing_city()),
			'countrycode' => sanitize_text_field($order->get_billing_country()),
			'phonenumber' => sanitize_text_field($order->get_billing_phone()),
			'meta' => array(
				'source' => 'Woocommerce'
			)
		);

		$response = $client->post('/customer', array('customer' => $customer));

		if ($response->httpStatusCode != 200) {

			wc_add_notice(esc_html(__('Failed to create customer. ', 'coinqvest') . $response->responseBody, 'error'));

			return array(
				'result' => 'error'
			);
		}

		$data = json_decode($response->responseBody, true);
		$customer_id = $data['customerId']; // use this to associate a checkout with this customer


		/**
		 * Build the checkout array
		 * Global settings overwrite JSON parameters
		 */

		$lineItems = array();

		foreach($order->get_items() as $order_item) {

			$lineItem = array(
				"description" => $order_item['name'],
				"netAmount" => $order_item['subtotal'] / $order_item['quantity'],
				"quantity" => $order_item['quantity'],
				"productId" =>  (string) $order_item['product_id']
			);

			array_push($lineItems, $lineItem);
		}

		/**
		 * Discounts
		 */

		$order_coupons = $order->get_items('coupon');

		$discountItems = array();

		foreach ($order_coupons as $coupon) {

			$discountItem = array(
				"description" => $coupon['code'],
				"netAmount" => $coupon['discount']
			);

			array_push($discountItems, $discountItem);
		}

		/**
		 * Shipping Costs
		 */

		$order_shipping_items = $order->get_items('shipping');

		$shippingCostItems = array();

		foreach ($order_shipping_items as $shipping_item) {

			$shippingCostItem = array(
				"description" => $shipping_item['name'],
				"netAmount" => $shipping_item['total'],
				"taxable" => $shipping_item['total_tax'] == 0 ? false : true
			);

			array_push($shippingCostItems, $shippingCostItem);
		}

		/**
		 * Taxes
		 */

		$order_tax_items = $order->get_items('tax');

		$taxItems = array();

		foreach ($order_tax_items as $tax_item) {

			$taxItem = array(
				"name" => $tax_item['label'],
				"percent" => $tax_item['rate_percent'] / 100
			);

			array_push($taxItems, $taxItem);
		}

		/**
		 * Put it all together
		 */

		$checkout = array(

			"charge" => array(
				"customerId" => $customer_id,
				"currency" => $order->get_currency(),
				"lineItems" => $lineItems,
				"discountItems" => !empty($discountItems) ? $discountItems : null,
				"shippingCostItems" => !empty($shippingCostItems) ? $shippingCostItems : null,
				"taxItems" => !empty($taxItems) ? $taxItems : null
			)
		);

		$settlement_currency = $options['settlement_currency'];

		if (isset($settlement_currency) && $settlement_currency != "0") {
			$checkout['settlementCurrency'] = $settlement_currency;
		}

        $checkout_language = $options['checkout_language'];

        if (isset($checkout_language) && $checkout_language != "0") {
            $checkout['checkoutLanguage'] = $checkout_language;
        }

		$checkout['webhook'] = $this->get_webhook_url();

		$checkout['links']['returnUrl'] = $this->get_return_url($order);

		$checkout['links']['cancelUrl'] = $this->get_cancel_url($order);


        /**
         * Post the checkout
         */

		$response = $client->post('/checkout/hosted', $checkout);

		if ($response->httpStatusCode != 200) {

			wc_add_notice(esc_html(__('Failed to create checkout. ', 'coinqvest') . $response->responseBody, 'error'));

			return array(
				'result' => 'error'
			);
		}

		/**
		 * The checkout was created, redirect user to hosted checkout page
		 */

		$data = json_decode($response->responseBody, true);
		$id = $data['id'];
		$url = $data['url'];

		$order->update_meta_data('_coinqvest_checkout_id', esc_attr($id));
		$order->save();

		return array(
			'result' => 'success',
			'redirect' => $url
		);
	}

	/**
	 * Get the return url (thank you page).
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_return_url($order = null) {
		if ($order) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url('order-received', '', wc_get_checkout_url());
		}

		return apply_filters('woocommerce_get_return_url', $return_url, $order);
	}

	/**
	 * Get the cancel url.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_cancel_url($order) {
		$return_url = $order->get_cancel_order_url();

		if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
			$return_url = str_replace('http:', 'https:', $return_url);
		}

		return apply_filters('woocommerce_get_cancel_url', $return_url, $order);
	}

	/**
	 * Ge the webhook url.
	 *
	 * @return string
	 */
	public function get_webhook_url() {
		return add_query_arg('wc-api', 'WC_COINQVEST', trailingslashit(get_home_url()));
	}


}