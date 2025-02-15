<?php

/**
 * @author   BucksBus
 * @package  WooCommerce BucksBus
 * @since    1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * BucksBus.
 *
 * @class    BucksBus_Gateway_Handler
 * @version  1.0.0
 */
class BucksBus_Gateway_Handler
{
	/**
	 * Log_enabled - whether or not logging is enabled
	 *
	 * @var bool	Whether or not logging is enabled
	 */
	public static $log_enabled = false;

	/**
	 * WC_Logger Logger instance
	 *
	 * @var WC_Logger Logger instance
	 * */
	public static $log = false;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{
		// self::$log_enabled = true;

		// Actions.
		add_action('woocommerce_api_wc_gateway_bucksbus',  array($this, 'handle_webhook'));
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log($message, $level = 'debug')
	{
		if (self::$log_enabled) {
			if (empty(self::$log)) {
				self::$log = wc_get_logger();
			}
			self::$log->log($level, $message, array('source' => 'bucksbus'));
		}
	}

	/**
	 * Handle requests sent to webhook.
	 */
	public function handle_webhook($request)
	{
		if (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') {
			wp_die('Only POST method is allowed', 'BucksBus Webhook', array('response' => 401));
		}

		$payload = !empty(file_get_contents('php://input')) ? sanitize_text_field(file_get_contents('php://input')) : '';
		if (!empty($payload)) {
			$data = json_decode($payload, true);
			if (
				json_last_error() === JSON_ERROR_NONE &&
				!empty($data) &&
				!empty($data['payment']) &&
				!empty($data['payment']['payment_asset_id'])
			) {
				$gateway = new BucksBus_Gateway_Base($data['payment']['payment_asset_id']);
				$gateway->handle_webhook($payload);
			}
		}

		wp_die('Invalid webhook', 'BucksBus Webhook', array('response' => 401));
	}
}

new BucksBus_Gateway_Handler();
