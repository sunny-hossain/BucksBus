<?php

/**
 * WC_Gateway_BucksBus class
 *
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
 * @class    WC_Gateway_BucksBus
 * @version  1.0.0
 */
class BucksBus_Gateway_Base extends WC_Payment_Gateway
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


	public string $cryptoCurrency;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct($cryptoCurrency)
	{
		$this->cryptoCurrency = $cryptoCurrency;
		$this->id                 = 'bucksbus_' . strtolower(str_replace(".", "", $this->cryptoCurrency));
		$this->icon               = apply_filters('woocommerce_gateway_icon', '');
		$this->has_fields         = false;
		$this->supports           = array(
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'multiple_subscriptions'
		);

		$this->method_title       = _x('BucksBus', 'BucksBus', 'bucksbus');
		$this->method_description = 'Accept payments in ' . $this->cryptoCurrency;

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title                    = $this->cryptoCurrency;
		$this->description              = $this->get_option('description');
		$this->instructions             = $this->get_option('instructions', $this->description);
		$this->hide_for_non_admin_users = $this->get_option('hide_for_non_admin_users');
		$this->debug      				= 'yes' === $this->get_option('debug', 'no');

		// self::$log_enabled = true;

		// Actions.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'process_subscription_payment'), 10, 2);
		add_action('woocommerce_api_wc_gateway_' . $this->id,  array($this, 'handle_webhook'));
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

	public function process_admin_options()
	{
		parent::process_admin_options();

		$default_prefix = "bucksbus_";

		$common_fields = array("api_key", "api_secret", "webhook_secret");

		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ($gateways) {
			foreach ($gateways as $gateway) {
				if (substr($gateway->id, 0, strlen($default_prefix)) === $default_prefix && $gateway->id !== $this->id) {
					foreach ($common_fields as $field) {
						$gateway->update_option($field, $this->get_option($field));
					}
				}
			}
		}
	}

	// public function get_option_key()
	// {
	// 	return 'woocommerce_bucksbus_settings';
	// }

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __('Enable/Disable', 'bucksbus'),
				'type'    => 'checkbox',
				'label'   => sprintf(
					// translators: %s: Name of a crypto currency
					__(
						'Enable BucksBus Payments in %s',
						'bucksbus'
					),
					$this->cryptoCurrency
				),
				'default' => 'yes',
			),
			'hide_for_non_admin_users' => array(
				'type'    => 'checkbox',
				'label'   => __('Hide at checkout for non-admin users', 'bucksbus'),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __('Title', 'bucksbus'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'bucksbus'),
				'default'     => sprintf(
					// translators: %s: Name of a crypto currency
					__(
						'Pay with %s',
						'bucksbus'
					),
					$this->cryptoCurrency
				),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __('Description', 'bucksbus'),
				'type'        => 'textarea',
				'description' => __('Payment method description that the customer will see on your checkout.', 'bucksbus'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'warning'        => array(
				'title'       => __('API Settings', 'bucksbus'),
				'type'        => 'title',
				'default'     => '',
				'description' => sprintf(
					// translators: Description field for API on settings page. Includes external link.
					__(
						'WARNING: When updating the API settings, the API settings on ALL your BucksBus payment gateway coins will be updated.',
						'bucksbus'
					),
					esc_url('https://merchant.bucksbus.com/')
				),
			),
			'api_key'        => array(
				'title'       => __('API Key', 'bucksbus'),
				'type'        => 'text',
				'default'     => '',
				'description' => sprintf(
					// translators: Description field for API on settings page. Includes external link.
					__(
						'You can manage your API keys within the BucksBus Settings page, available here: %s',
						'bucksbus'
					),
					esc_url('https://merchant.bucksbus.com/')
				),
			),
			'api_secret'        => array(
				'title'       => __('API Secret', 'bucksbus'),
				'type'        => 'text',
				'default'     => '',
				'description' => sprintf(
					// translators: Description field for API on settings page. Includes external link.
					__(
						'You can manage your API keys within the BucksBus Settings page, available here: %s',
						'bucksbus'
					),
					esc_url('https://merchant.bucksbus.com/')
				),
			),
			'webhook_secret' => array(
				'title'       => __('Webhook Secret', 'bucksbus'),
				'type'        => 'text',
				'description' =>

				// translators: Instructions for setting up 'webhook shared secrets' on settings page.
				__('Using webhooks allows BucksBus to send payment confirmation messages to the website. To fill this out:', 'bucksbus')

					. '<br /><br />' .

					__('1. On your BucksBus store settings page, go to the \'Webhooks\' section.', 'bucksbus')

					. '<br />' .

					// translators: %s: Webhook URL
					sprintf(__('2. Fill in the \'Webhook URL\' line with the following URL: %s', 'bucksbus'), add_query_arg('wc-api', 'WC_Gateway_BucksBus', home_url('/', 'https')))

					. '<br />' .

					__('3. Generate a \'Webhook Secret\' with the click of a button.', 'bucksbus')

					. '<br />' .

					__('4. Make sure to select all events to receive all payment updates.', 'bucksbus')

					. '<br />' .

					__('5. Click button \'Save\'.', 'bucksbus'),

			),
		);
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);

		// Create description for charge based on order's products. Ex: 1 x Product1, 2 x Product2
		try {
			$order_items = array_map(function ($item) {
				return $item['quantity'] . ' x ' . $item['name'];
			}, $order->get_items());

			$description = mb_substr(implode(', ', $order_items), 0, 200);
		} catch (Exception $e) {
			$description = null;
		}

		$this->init_api();

		// Create a new charge.
		$metadata = array(
			'order_id'  => $order->get_id(),
			'order_key' => $order->get_order_key(),
			'source' => 'woocommerce'
		);
		$result   = BucksBus_API_Handler::create_payment(
			$order->get_total(),
			get_woocommerce_currency(),
			$this->cryptoCurrency,
			$metadata,
			$this->get_return_url($order),
			$order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			$order->get_billing_email(),
			$description,
			$this->get_cancel_url($order)
		);

		if (!$result[0]) {
			return array('result' => 'fail');
		}

		$payment = $result[1];

		$order->update_meta_data('_bucksbus_payment_id', $payment['payment_id']);
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $payment['payment_url'],
		);
	}

	/**
	 * Get the cancel url.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_cancel_url($order)
	{
		$return_url = $order->get_cancel_order_url();

		if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
			$return_url = str_replace('http:', 'https:', $return_url);
		}

		/** DOCBLOCK - Makes linter happy.
		 *
		 * @since today
		 */
		return apply_filters('woocommerce_get_cancel_url', $return_url, $order);
	}

	/**
	 * Check payment statuses on orders and update order statuses.
	 */
	public function check_orders()
	{
		$this->init_api();

		// Check the status of non-archived BucksBus orders.
		$orders = wc_get_orders(
			array(
				'bucksbus_archived' => false,
				'status' => array('wc-pending'),
				'meta_query' => array(
					array(
						'key' => '_bucksbus_archived',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key' => '_bucksbus_payment_id',
						'compare' => 'EXISTS',
					)
				)
			)
		);

		foreach ($orders as $order) {
			$payment_id = $order->get_meta('_bucksbus_payment_id');

			usleep(300000);  // Ensure we don't hit the rate limit.
			$result = BucksBus_API_Handler::send_request('payment/' . $payment_id);

			if (!$result[0]) {
				self::log('Failed to fetch order updates for: ' . $order->get_id());
				continue;
			}

			$status = $result[1]['status'];
			self::log('Timeline: ' . print_r($status, true));
			$this->update_order_status($order, $status);
		}
	}

	/**
	 * Handle requests sent to webhook.
	 */
	public function handle_webhook($payload)
	{
		if (!empty($payload) && $this->validate_webhook($payload)) {
			self::log('Webhook is valid');
			$data       = json_decode($payload, true);
			$event_data = json_decode($data['payment']['custom'], true);

			self::log('Webhook received event: ' . print_r($data, true));

			if (!isset($event_data['order_id'])) {
				// Probably a charge not created by us.
				exit;
			}

			$order_id = $event_data['order_id'];

			$this->update_order_status(wc_get_order($order_id), $data['payment']['status']);

			exit;  // 200 response for acknowledgement.
		}

		wp_die('BucksBus Webhook Request Failure', 'BucksBus Webhook', array('response' => 500));
	}

	/**
	 * Check BucksBus webhook request is valid.
	 *
	 * @param  string $payload
	 */
	public function validate_webhook($payload)
	{
		self::log('Validate webhook');

		$header = 'HTTP_X_WEBHOOK_HMAC_SHA256';
		$secret = $this->get_option('webhook_secret');

		$sig = (!isset($_SERVER[$header]) || empty($_SERVER[$header]))  ? '' : sanitize_text_field($_SERVER[$header]);

		$sig2 = hash_hmac('sha256', $payload, $secret);

		return $sig === $sig2;
	}

	/**
	 * Init the API class and set the API key etc.
	 */
	protected function init_api()
	{
		include_once dirname(__FILE__) . '/class-bucksbus-api-handler.php';

		BucksBus_API_Handler::$log     = get_class($this) . '::log';
		BucksBus_API_Handler::$api_key = $this->get_option('api_key');
		BucksBus_API_Handler::$api_secret = $this->get_option('api_secret');
	}

	/**
	 * Update the status of an order.
	 *
	 * @param  WC_Order $order
	 * @param  string    $status
	 */
	public function update_order_status($order, $status)
	{
		$prev_status = $order->get_meta('_bucksbus_status');


		if ($status !== $prev_status) {
			$order->update_meta_data('_bucksbus_status', $status);

			if ('EXPIRED' === $status && 'pending' == $order->get_status()) {
				$order->update_status('cancelled', __('BucksBus payment expired.', 'bucksbus'));
			} elseif ('CANCEL' === $status) {
				$order->update_status('cancelled', __('BucksBus payment cancelled.', 'bucksbus'));
			} elseif ('UNRESOLVED' === $status) {
				if ('OVERPAID' === $last_update['context']) {
					$order->update_status('processing', __('BucksBus payment was successfully processed.', 'bucksbus'));
					$order->payment_complete();
				} else {
					// translators: BucksBus error status for "unresolved" payment. Includes error status.
					$order->update_status('failed', sprintf(__('BucksBus payment unresolved, reason: %s.', 'bucksbus'), $last_update['context']));
				}
			} elseif ('PENDING' === $status) {
				$order->update_status('blockchainpending', __('BucksBus payment detected, but awaiting blockchain confirmation.', 'bucksbus'));
			} elseif ('RESOLVED' === $status) {
				// We don't know the resolution, so don't change order status.
				$order->add_order_note(__('BucksBus payment marked as resolved.', 'bucksbus'));
			} elseif ('COMPLETE' === $status) {
				$order->update_status('processing', __('BucksBus payment was successfully processed.', 'bucksbus'));
				$order->payment_complete();
			}
		}

		// Archive if in a resolved state and idle more than timeout.
		if (
			in_array($status, array('EXPIRED', 'COMPLETE', 'RESOLVED'), true) &&
			$order->get_date_modified() < $this->timeout
		) {
			self::log('Archiving order: ' . $order->get_order_number());
			$order->update_meta_data('_bucksbus_archived', true);
		}
	}

	/**
	 * Handle a custom 'bucksbus_archived' query var to get orders
	 * payed through BucksBus with the '_bucksbus_archived' meta.
	 *
	 * @param array $query - Args for WP_Query.
	 * @param array $query_vars - Query vars from WC_Order_Query.
	 * @return array modified $query
	 */
	public function _custom_query_var($query, $query_vars)
	{
		if (array_key_exists('bucksbus_archived', $query_vars)) {
			$query['meta_query'][] = array(
				'key'     => '_bucksbus_archived',
				'compare' => $query_vars['bucksbus_archived'] ? 'EXISTS' : 'NOT EXISTS',
			);
			// Limit only to orders payed through BucksBus.
			$query['meta_query'][] = array(
				'key'     => '_bucksbus_payment_id',
				'compare' => 'EXISTS',
			);
		}

		return $query;
	}
}
