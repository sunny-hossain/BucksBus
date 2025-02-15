<?php

/**
 * Plugin Name: BucksBus
 * Description: Adds BucksBus to your WooCommerce website.
 * Version: 1.1.0
 *
 * Author: BucksBus
 * Author URI: https://www.bucksbus.com/
 *
 * Text Domain: bucksbus
 * Domain Path: /languages
 *
 * Copyright: © 2024 BucksBus.
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * WC BucksBus Payment gateway plugin class.
 *
 * @class BucksBus
 */
class BucksBus
{
	public static $currencies = array("BTC", "LTC", "ETH", "TRX", "USDT.TRC20", "USDT.ERC20", "POL", "USDT.POLY", "USDC.POLY", "USDC.ERC20");

	/**
	 * Plugin bootstrapping.
	 */
	public static function init()
	{
		// BucksBus Payments gateway class.
		add_action('plugins_loaded', array(__CLASS__, 'includes'), 0);

		// Make the BucksBus Payments gateway available to WC.
		add_filter('woocommerce_payment_gateways', array(__CLASS__, 'add_gateway'));

		// Registers WooCommerce Blocks integration.
		add_action('woocommerce_blocks_loaded', array(__CLASS__, 'woocommerce_bucksbus_block_support'));

		require_once 'includes/class-wc-gateway-bucksbus-handler.php';
	}

	/**
	 * Add the BucksBus Payment gateway to the list of available gateways.
	 *
	 * @param array
	 */
	public static function add_gateway($gateways)
	{
		foreach (BucksBus::$currencies as $currency) {
			$options = get_option('woocommerce_bucksbus_' . strtolower(str_replace(".", "", $currency)) . 'settings', array());

			if (isset($options['hide_for_non_admin_users'])) {
				$hide_for_non_admin_users = $options['hide_for_non_admin_users'];
			} else {
				$hide_for_non_admin_users = 'no';
			}

			if (('yes' === $hide_for_non_admin_users && current_user_can('manage_options')) || 'no' === $hide_for_non_admin_users) {
				foreach (BucksBus::$currencies as $currency) {
					$gateways[] = 'BucksBus_Gateway_' . str_replace(".", "_", $currency);
				}
			}
		}

		return $gateways;
	}

	/**
	 * Plugin includes.
	 */
	public static function includes()
	{
		// Make the WC_Gateway_BucksBus class available.
		if (class_exists('WC_Payment_Gateway')) {
			require_once 'includes/class-wc-gateway-bucksbus-base.php';
			foreach (BucksBus::$currencies as $currency) {
				require_once 'includes/class-wc-gateway-bucksbus-' . $currency . '.php';
			}
		}
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url()
	{
		return untrailingslashit(plugins_url('/', __FILE__));
	}

	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_abspath()
	{
		return trailingslashit(plugin_dir_path(__FILE__));
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 *
	 */
	public static function woocommerce_bucksbus_block_support()
	{
		if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
			require_once 'includes/blocks/class-wc-bucksbus-payments-blocks.php';

			foreach (BucksBus::$currencies as $currency) {
				require_once 'includes/blocks/class-wc-bucksbus-payments-blocks-' . $currency . '.php';
			}

			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
					foreach (BucksBus::$currencies as $currency) {
						$payment_method_registry->register(new BucksBus_Gateway_Blocks_Support($currency));
					}
				}
			);
		}
	}
}

BucksBus::init();
