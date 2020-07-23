<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2012-2020 Wooppay
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @copyright   Copyright (c) 2012-2020 Wooppay
 * @author      Wooppay ©
 * @version     2.0.0
 */

class WC_Gateway_Wooppay extends WC_Payment_Gateway
{

	public function __construct()
	{
		$this->id = 'wooppay';
		$this->icon = apply_filters('woocommerce_wooppay_icon', plugins_url() . '/wooppay-2.0/assets/images/wpk_logo.png');
		$this->has_fields = false;
		$this->method_title = __('WOOPPAY', 'Wooppay');
		$this->init_form_fields();
		$this->init_settings();
		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->instructions = $this->get_option('instructions');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
		add_action('woocommerce_api_wc_gateway_wooppay', array($this, 'check_response'));
	}

	public function check_response()
	{
		if (isset($_REQUEST['id_order']) && isset($_REQUEST['key'])) {
			$order = wc_get_order((int)$_REQUEST['id_order']);
			if ($order && $order->key_is_valid($_REQUEST['key'])) {
				try {
					include_once('WooppaySoapClient.php');
					$client = new WooppaySoapClient($this->get_option('api_url'), array('login' => 'test', 'password' => 'gjvtyzkgfhjkm!', 'trace' => true));
					if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
						$orderPrefix = $this->get_option('order_prefix');
						$serviceName = $this->get_option('service_name');
						$orderId = $order->id;
						if ($orderPrefix) {
							$orderId = $orderPrefix . '_' . $orderId;
						}
						$invoiceAmount = ($order->currency == 'KZT')? $order->order_total : $this->getKztInvoiceValue($order->order_total, $order->currency);
						$invoice = $client->createInvoice($orderId, '', '', $invoiceAmount, $serviceName);
						if ($client->getOperationData((int)$invoice->response->operationId)->response->records[0]->status == WooppayOperationStatus::OPERATION_STATUS_DONE) {
							$order->update_status('processing', __('Payment processing.', 'woocommerce'));
							die('{"data":1}');
						} else {
							$order->update_status('failed', __('Payment failed.', 'woocommerce'));
						}
					}
				} catch (Exception $e) {
					$order->update_status('failed', __('Payment failed.', 'woocommerce'));
					$this->add_log($e->getMessage());
					wc_add_notice(__('Wooppay error:', 'woocommerce') . $e->getMessage() . print_r($order, true), 'error');
				}
			} else {
				$this->add_log('Error order key: ' . print_r($_REQUEST, true));
			}
		} else {
			$this->add_log('Error call back: ' . print_r($_REQUEST, true));
		}
		die('{"data":1}');
	}

	/* Admin Panel Options.*/
	public function admin_options()
	{
		?>
		<h3><?php _e('Wooppay', 'wooppay'); ?></h3>
		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table> <?php
	}

	/* Initialise Gateway Settings Form Fields. */
	public function init_form_fields()
	{
		global $woocommerce;

		$shipping_methods = array();

		if (is_admin())
			foreach ($woocommerce->shipping->load_shipping_methods() as $method) {
				$shipping_methods[$method->id] = $method->get_title();
			}

		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'wooppay'),
				'type' => 'checkbox',
				'label' => __('Enable Wooppay Gateway', 'wooppay'),
				'default' => 'no'
			),
			'title' => array(
				'title' => __('Title', 'wooppay'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'wooppay'),
				'desc_tip' => true,
				'default' => __('Wooppay Gateway', 'wooppay')
			),
			'description' => array(
				'title' => __('Description', 'wooppay'),
				'type' => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'wooppay'),
				'default' => __('Desctiptions for Wooppay Gateway.', 'wooppay')
			),
			'instructions' => array(
				'title' => __('Instructions', 'wooppay'),
				'type' => 'textarea',
				'description' => __('Instructions that will be added to the thank you page.', 'wooppay'),
				'default' => __('Instructions for Wooppay Gateway.', 'wooppay')
			),
			'api_details' => array(
				'title' => __('API Credentials', 'wooppay'),
				'type' => 'title',
			),
			'api_url' => array(
				'title' => __('API URL', 'wooppay'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay')
			),
			'api_username' => array(
				'title' => __('API Username', 'wooppay'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay')
			),
			'api_password' => array(
				'title' => __('API Password', 'wooppay'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay')
			),
			'order_prefix' => array(
				'title' => __('Order prefix', 'wooppay'),
				'type' => 'text',
				'description' => __('Order prefix', 'wooppay'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay')
			),
			'service_name' => array(
				'title' => __('Service name', 'wooppay'),
				'type' => 'text',
				'description' => __('Service name', 'wooppay'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay')
			),
		);

	}

	function process_payment($order_id)
	{
		include_once('WooppaySoapClient.php');
		global $woocommerce;
		$order = new WC_Order($order_id);
		try {
			$client = new WooppaySoapClient($this->get_option('api_url'), array('login' => 'test', 'password' => 'gjvtyzkgfhjkm!', 'trace' => true));
			if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
				$requestUrl = WC()->api_request_url('WC_Gateway_Wooppay') . '?id_order=' . $order_id . '&key=' . $order->order_key;
				$backUrl = $this->get_return_url($order);
				$orderPrefix = $this->get_option('order_prefix');
				$serviceName = $this->get_option('service_name');
				$invoiceAmount = ($order->currency == 'KZT')? $order->order_total : $this->getKztInvoiceValue($order->order_total, $order->currency);
				$invoice = $client->createInvoice($orderPrefix . '_' . $order->id, $backUrl, $requestUrl, $invoiceAmount, $serviceName, 'Оплата заказа №' . $order->id, '', '', $order->billing_email, $order->billing_phone);
				$woocommerce->cart->empty_cart();
				$order->update_status('pending', __('Payment Pending.', 'woocommerce'));
				//$order->payment_complete($invoice->response->operationId);
				$this->cancelOldOrders();
				return array(
					'result' => 'success',
					'redirect' => $invoice->response->operationUrl
				);
			}
		} catch (Exception $e) {
			$this->add_log($e->getMessage());
			wc_add_notice(__('Wooppay error:', 'woocommerce') . $e->getCode(), 'error');
		}
	}

	function thankyou()
	{
		echo $this->instructions != '' ? wpautop($this->instructions) : '';
	}

	function add_log($message)
	{
		if ($this->debug == 'yes') {
			if (empty($this->log)) {
				$this->log = new WC_Logger();
			}
			$this->log->add('Wooppay', $message);
		}
	}

	/**
	 * Requests KZT course of provided currency from National Bank API. If result exists then returns value else throws an exception
	 * @param  float $orderTotal
	 * @param  string $currencyCode
	 * @return float
	 */
	private function getKztInvoiceValue($orderTotal, $currencyCode)
	{
		$date = date('d.m.Y');
		$url = "https://nationalbank.kz/rss/get_rates.cfm?fdate={$date}&switch=russian";
		try {
			$currencyListXml = file_get_contents($url);
			$currencyObjectList = simplexml_load_string($currencyListXml);

			foreach ($currencyObjectList->item as $item) {
				if ($item->title == $currencyCode) {
					//Костыль для Pure Beauty. В обычных кейсах передавать $orderTotal как есть для вычисления $invoiceAmount
					$delta = $orderTotal / 7.0422535211;
					$orderTotal += $delta;
					//
					$invoiceAmount = $orderTotal * $item->description / $item->quant;
					return $invoiceAmount;
				}
			}
			throw new Exception("Invalid currency code or currency not found", 1);
		} catch (Exception $e) {
			$this->add_log($e->getMessage());
			wc_add_notice(__('Wooppay error:', 'woocommerce') . $e->getMessage(), 'error');
		}
	}

	/**
	 * Requests orders with pending status and creation date elder then 15min then updates status on canceled
	 */
	public function cancelOldOrders()
	{
		try {
			$brakPoint15min = date("Y-m-d H:i:s", mktime(date("H")+6, date("i")-15, date("s"), date("m"), date("d"), date("Y")));

			$orderQuery = new WC_Order_Query(array(
				'status' => array('wc-pending'),
				'type' => wc_get_order_types( 'view-orders'),
				'date_created' => date("Y-m-d") . "..." . date("Y-m-d")
			));
			$orderList = $orderQuery->get_orders();
			foreach ($orderList as $order) {
				if ($order->get_date_created()->date("Y-m-d H:i:s") < $brakPoint15min) {
					$order->update_status('cancelled', 'no payment during 15 minutes', true);
				}
			}
		} catch (Exception $e) {
			$this->add_log($e->getMessage());
			wc_add_notice(__('Wooppay error:', 'woocommerce') . $e->getMessage(), 'error');
		}
	}
}
