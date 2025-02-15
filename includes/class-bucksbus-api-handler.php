<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Sends API requests to BucksBus.
 */
class BucksBus_API_Handler
{

	/**
	 * Log variable function
	 *
	 * @var string/array Log variable function.
	 * */
	public static $log;
	/**
	 * Call the $log variable function.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log($message, $level = 'info')
	{
		return call_user_func(self::$log, $message, $level);
	}

	/**
	 * BucksBus API url
	 *
	 * @var string BucksBus API url.
	 * */
	public static $api_url = 'https://api.bucksbus.com/int/';

	/**
	 * BucksBus API key
	 *
	 * @var string BucksBus API key.
	 * */
	public static $api_key;

	/**
	 * BucksBus API secret
	 *
	 * @var string BucksBus API secret.
	 * */
	public static $api_secret;

	/**
	 * Get the response from an API request.
	 *
	 * @param  string $endpoint
	 * @param  array  $params
	 * @param  string $method
	 * @return array
	 */
	public static function send_request($endpoint, $params = array(), $method = 'GET')
	{
		// phpcs:ignore
		self::log('BucksBus Request Args for ' . $endpoint . ': ' . print_r($params, true));
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode(self::$api_key . ':' . self::$api_secret),
				'Content-Type' => 'application/json'
			)
		);

		$url = self::$api_url . $endpoint;

		if (in_array($method, array('POST', 'PUT'))) {
			$args['body'] = wp_json_encode($params);
		} else {
			$url = add_query_arg($params, $url);
		}
		$response = wp_remote_request(esc_url_raw($url), $args);

		self::log('WP response: ' . print_r($response, true));

		if (is_wp_error($response)) {
			self::log('WP response error: ' . print_r($response, true));
			return array(false, $response->get_error_message());
		} else {
			$result = json_decode($response['body'], true);
			if (!empty($result['warnings'])) {
				foreach ($result['warnings'] as $warning) {
					self::log('API Warning: ' . $warning);
				}
			}

			$code = $response['response']['code'];

			if (in_array($code, array(200, 201), true)) {
				return array(true, $result);
			} else {
				self::log('WP response error: ' . print_r($result, true));
				$e      = empty($result['error']['message']) ? '' : $result['error']['message'];
				$errors = array(
					400 => 'Error response from API: ' . $e,
					401 => 'Authentication error, please check your API key.',
					429 => 'BucksBus API rate limit exceeded.',
				);

				if (array_key_exists($code, $errors)) {
					$msg = $errors[$code];
				} else {
					$msg = 'Unknown response from API: ' . $code;
				}

				return array(false, $code);
			}
		}
	}

	/**
	 * Check if authentication is successful.
	 *
	 * @return bool|string
	 */
	public static function check_auth()
	{
		$result = self::send_request('checkouts', array('limit' => 0));

		if (!$result[0]) {
			return 401 === $result[1] ? false : 'error';
		}

		return true;
	}


	/**
	 * Create a new payment request.
	 *
	 * @param  int    $amount
	 * @param  string $currency
	 * @param  array  $metadata
	 * @param  string $redirect
	 * @param  string $name
	 * @param  string $desc
	 * @param  string $cancel
	 * @return array
	 */
	public static function create_payment(
		$amount = null,
		$currency = null,
		$cryptoCurrency = null,
		$metadata = null,
		$redirect = null,
		$name = null,
		$email = null,
		$desc = null,
		$cancel = null
	) {
		$args = array(
			'payer_name'  => is_null($name) ? get_bloginfo('name') : $name,
			'description' => is_null($desc) ? get_bloginfo('description') : $desc,
		);
		$args['payer_name'] = sanitize_text_field($args['payer_name']);
		$args['payer_email'] = $email;
		$args['payer_lang'] = 'en';
		$args['payment_asset_id'] = $cryptoCurrency;
		$args['description'] = sanitize_text_field($args['description']);

		if (is_null($amount)) {
			$args['payment_type'] = 'OPEN_AMOUNT';
		} elseif (is_null($currency)) {
			self::log('Error: if amount is given, currency must be given (in create_payment()).', 'error');
			return array(false, 'Missing currency.');
		} else {
			$args['payment_type'] = 'FIXED_AMOUNT';
			$args['amount'] = floatval($amount);
			$args['asset_id'] = $currency;
		}

		if (!is_null($metadata)) {
			$args['custom'] = wp_json_encode($metadata);
		}
		if (!is_null($redirect)) {
			$args['success_url'] = $redirect;
		}
		if (!is_null($cancel)) {
			$args['cancel_url'] = $cancel;
		}

		$result = self::send_request('payment', $args, 'POST');

		// Cache last-known available payment methods.
// 		if (!empty($result[1]['data']['addresses'])) {
// 			update_option(
// 				'bucksbus_payment_methods',
// 				array_keys($result[1]['data']['addresses']),
// 				false
// 			);
// 		}

		return $result;
	}
}
