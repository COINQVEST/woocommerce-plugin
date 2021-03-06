<?php

/**
 * Plugin Name: COINQVEST for WooCommerce
 * Description: Enterprise Cryptocurrency Payment Processor for WooCommerce - Accept Bitcoin (BTC), Ethereum (ETH), Ripple (XRP), Stellar Lumens (XLM) and Litecoin (LTC) from your clients and instantly settle in your preferred payout currency like USD, EUR, NGN, BRL or cryptocurrency (BTC, ETH, LTC, XLM, XRP).
 * Author: COINQVEST
 * Author URI: https://www.coinqvest.com/
 * Version: 1.0.10
 * License: Apache 2.0
 * License URI: https://www.apache.org/licenses/LICENSE-2.0
 * Text domain: coinqvest-for-woocommerce
 * Domain Path: /languages
 *
 * WC tested up to: 4.9.2
 */

namespace WC_COINQVEST;

defined('ABSPATH') or exit;

/**
 * Define Constants
 */
define(__NAMESPACE__ . '\NS', __NAMESPACE__ . '\\');
define(NS . 'PLUGIN_NAME', 'coinqvest-for-woocommerce');
define(NS . 'PLUGIN_VERSION', '1.0.10');
define(NS . 'PLUGIN_NAME_DIR', plugin_dir_path(__FILE__));
define(NS . 'PLUGIN_NAME_URL', plugin_dir_url(__FILE__));
define(NS . 'PLUGIN_BASENAME', plugin_basename(__FILE__));
define(NS . 'PLUGIN_TEXT_DOMAIN', 'coinqvest-for-woocommerce');

/**
 * Autoload Classes
 */
require_once(PLUGIN_NAME_DIR . 'inc/libraries/autoloader.php');

/**
 * Register Activation and Deactivation Hooks
 */
register_activation_hook(__FILE__, array(NS . 'Inc\Core\Activator', 'activate'));
register_deactivation_hook(__FILE__, array(NS . 'Inc\Core\Deactivator', 'deactivate'));


/**
 * Plugin Singleton Container
 */
class WC_COINQVEST {

	static $init;

	public static function init() {

		if (null == self::$init) {
			self::$init = new Inc\Core\Init();
			self::$init->run();
		}

		return self::$init;
	}

}

function cqInit() {
	return WC_COINQVEST::init();
}

$min_php = '5.6.0';

// Check the minimum required PHP version and run the plugin.
if (version_compare(PHP_VERSION, $min_php, '>=')) {
	cqInit();
}