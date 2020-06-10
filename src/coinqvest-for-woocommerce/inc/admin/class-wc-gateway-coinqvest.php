<?php
namespace WC_COINQVEST\Inc\Admin;
use WC_COINQVEST\Inc\Libraries\Api;
use WC_Payment_Gateway;

defined( 'ABSPATH' ) or exit;

class WC_Gateway_Coinqvest extends WC_Payment_Gateway {

	private $plugin_name_url;
	private $plugin_basename;
	private $version;
	private $api_key;
	private $api_secret;

	public function __construct($plugin_name_url, $plugin_basename, $version) {

		$this->plugin_name_url = $plugin_name_url;
		$this->plugin_basename = $plugin_basename;
		$this->version = $version;

		$this->id = 'wc_coinqvest';
		$this->has_fields = false;
		$this->order_button_text  = __( 'Proceed to COINQVEST', 'coinqvest' );
		$this->method_title = 'COINQVEST';
		$this->method_description = __( 'Accept payments in crypto (BTC, ETH, XRP, XLM, LTC) and instantly settle in your local currency (USD, EUR, CAD, NGN).', 'coinqvest' );

		// Define user set variables.
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->api_key = $this->get_option('api_key');
		$this->api_secret = $this->get_option('api_secret');
		$this->debug = 'yes' === $this->get_option( 'debug', 'no' );
		Api\CQ_Logging_Service::$log_enabled = $this->debug;

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_coinqvest', array( $this, 'handle_webhook' ) );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'plugin_action_links' ) );
        add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_coinqvest_payment_data_in_order') );

    }

    public function get_order_by_checkout_id( $query, $query_vars ) {

        if ( ! empty( $query_vars['_coinqvest_checkout_id'] ) ) {
            $query['meta_query'][] = array(
                'key' => '_coinqvest_checkout_id',
                'value' => esc_attr( $query_vars['_coinqvest_checkout_id'] ),
            );
        }

        return $query;
    }


	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

        $form_fields = new WC_Coinqvest_Admin_Form();
		$this->form_fields = $form_fields->form_fields($this->api_key, $this->api_secret);
	}

    /**
     * Settings form fields input validation
     */
    public function validate_api_key_field( $key, $value ) {

        $value = sanitize_text_field($value);
        if (!empty($value) && strlen($value) != 12) {
            \WC_Admin_Settings::add_error(esc_html(__('API Key seems to be wrong. Please double check.', 'coinqvest')));
        }
        return $value;
    }

    public function validate_api_secret_field( $key, $value ) {

        $value = sanitize_text_field($value);
        if (!empty($value) && strlen($value) != 29) {
            \WC_Admin_Settings::add_error(esc_html(__('API Secret seems to be wrong. Please double check.', 'coinqvest')));
        }
        return $value;
    }

	/**
	 * Get gateway icon
	 */
	public function get_icon() {

		if ( $this->get_option( 'show_icons' ) === 'no' ) {
			return '';
		}

		$url = $this->plugin_name_url . 'assets/images/wc-cq-logo.png';
		$icon = '<img class="coinqvest-checkout-logo" src="' . esc_attr( $url ) . '" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Add settings link on plugin page
	 */
	public function plugin_action_links($links) {

		$plugin_links = array(
			'<a href="admin.php?page=wc-settings&tab=checkout&section=wc_coinqvest">' . esc_html__( 'Settings', 'coinqvest-for-woocommerce' ) . '</a>',
		);
		return array_merge($plugin_links, $links);
	}

	/**
	 * Init settings for gateways
	 */
	public function init_settings() {

		parent::init_settings();
		$this->enabled = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
	}

	/**
	 * Create the checkout
	 */
	public function process_payment($order_id) {

		$options['api_key'] = $this->api_key;
		$options['api_secret'] = $this->api_secret;
		$options['settlement_currency'] = $this->get_option('settlement_currency');

		$charge = new WC_Coinqvest_Checkout();
		return $charge->create_checkout($order_id, $options);
	}

	/**
	 * Handle requests sent to webhook
	 */
	public function handle_webhook() {

		$webhook_handler = new WC_Coinqvest_Webhook_Handler($this->api_secret);
		$webhook_handler->handle_webhook();
	}

    /**
     * Adds extra fields in the admin order view
     */
    function display_coinqvest_payment_data_in_order( $order ){

        $cq_checkout_id = null;
        $cq_payment_id = null;

        $meta_data = $order->get_meta_data();

        foreach ($meta_data as $item) {
            if ($item->key == '_coinqvest_checkout_id') {
                $cq_checkout_id = $item->value;
            }
            if ($item->key == '_coinqvest_payment_id') {
                $cq_payment_id = $item->value;
            }
        }

        if ($cq_checkout_id) {

            ?>

            <p class="form-field form-field-wide">
                <br />
                <h4><?php echo __('COINQVEST Payment Details', 'coinqvest'); ?></h4>
                <p>
                    <?php
                    echo  __('Checkout Id', 'coinqvest') . ': ' . esc_html($cq_checkout_id);
                    if ($cq_payment_id) {
                        echo '<br>' . __('Payment Id', 'coinqvest') . ': ' . esc_html($cq_payment_id);
                        echo '<br>' . __('View payment details ', 'coinqvest') . ' <a href="' . esc_attr('https://www.coinqvest.com/en/payment/' . $cq_payment_id) . '" target="_blank">' . __('here', 'coinqvest') . '</a>';
                    }
                    ?>
                </p>


            </p>
            <?php
        }
    }

    public function payment_scripts() {

        wp_register_style( 'coinqvest_styles', $this->plugin_name_url . 'assets/css/wc-coinqvest.css',  array(), $this->version, 'all' );
        wp_enqueue_style( 'coinqvest_styles' );
    }

}