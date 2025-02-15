<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * BucksBus Payments Blocks integration
 *
 * @since 1.0.0
 */
class BucksBus_Gateway_Blocks_Support extends AbstractPaymentMethodType
{

	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_BucksBus
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = '';

	private $cryptoCurrency = '';

	public function __construct($cryptoCurrency)
	{
		$this->cryptoCurrency = $cryptoCurrency;
		$this->name = 'bucksbus_' . strtolower(str_replace(".", "", $this->cryptoCurrency));
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize()
	{
		$this->settings = get_option('woocommerce_' . $this->name . '_settings', []);
		$this->gateway  = new BucksBus_Gateway_Base($this->cryptoCurrency);
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active()
	{
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles()
	{
		$script_path       = '/assets/js/frontend/' . $this->cryptoCurrency . '.js';
		$script_asset_path = BucksBus::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
		$script_asset      = file_exists($script_asset_path)
			? require($script_asset_path)
			: array(
				'dependencies' => array(),
				'version'      => '1.0.0'
			);
		$script_url        = BucksBus::plugin_url() . $script_path;

		wp_register_script(
			'wc-' . $this->name . 'payments-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if (function_exists('wp_set_script_translations')) {
			wp_set_script_translations('wc-bucksbus-payments-blocks', 'woocommerce-gateway-bucksbus', BucksBus::plugin_abspath() . 'languages/');
		}

		return ['wc-' . $this->name . 'payments-blocks'];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data()
	{
		return [
			'name'        => $this->name,
			'title'       => $this->get_setting('title'),
			'description' => $this->get_setting('description'),
			'supports'    => array_filter($this->gateway->supports, [$this->gateway, 'supports'])
		];
	}
}
