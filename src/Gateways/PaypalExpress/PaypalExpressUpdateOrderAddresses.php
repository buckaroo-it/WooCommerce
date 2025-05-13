<?php

namespace Buckaroo\Woocommerce\Gateways\PaypalExpress;

use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use WC_Order;

/**
 * PayPal express update billing and shipping address from push data
 * php version 7.4
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @version   GIT: 3.0.0
 *
 * @link      https://www.buckaroo.eu/
 */
class PaypalExpressUpdateOrderAddresses
{
    private WC_Order $order;

    private ResponseParser $response;

    public function __construct(WC_Order $order, ResponseParser $response)
    {
        $this->order = $order;
        $this->response = $response;
    }

    public function update()
    {
        if ($this->response->getAdditionalInformation('is_paypal_express')) {
            $this->update_billing_address();
            $this->update_shipping_address();
            $this->order->save();
        }
    }

    private function update_billing_address()
    {
        $this->order->set_billing_first_name($this->get('payerFirstname'));
        $this->order->set_billing_last_name($this->get('payerLastname'));
        $this->order->set_billing_address_1($this->get('address_line_1'));
        $this->order->set_billing_city($this->get('admin_area_2'));
        $this->order->set_billing_postcode($this->get('postal_code'));
        $this->order->set_billing_country($this->get('payerCountry'));
        $email = $this->get('payerEmail');
        if (is_email($email)) {
            $this->order->set_billing_email($email);
        }
    }

    private function update_shipping_address()
    {
        $this->order->set_shipping_first_name($this->get('payerFirstname'));
        $this->order->set_shipping_last_name($this->get('payerLastname'));
        $this->order->set_shipping_address_1($this->get('address_line_1'));
        $this->order->set_shipping_city($this->get('admin_area_2'));
        $this->order->set_shipping_postcode($this->get('postal_code'));
        $this->order->set_shipping_country($this->get('payerCountry'));
    }

    private function get(string $key): string
    {
        return $this->response->getService($key) ?? '';
    }
}
