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
    /**
     * Order address fields and the push parameters that carry them.
     */
    private const ADDRESS_FIELDS = [
        'first_name' => 'payerFirstname',
        'last_name' => 'payerLastname',
        'address_1' => 'address_line_1',
        'city' => 'admin_area_2',
        'postcode' => 'postal_code',
        'state' => 'admin_area_1',
    ];

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
        $this->set_address('billing', self::ADDRESS_FIELDS + ['country' => 'payerCountry']);
        $email = $this->get('payerEmail');
        if (is_email($email)) {
            $this->order->set_billing_email($email);
        }
    }

    private function update_shipping_address()
    {
        // payerCountry is the PayPal account's country; the shipping country stays the PayPal ship-to country set on the order.
        $this->set_address('shipping', self::ADDRESS_FIELDS);
    }

    /**
     * Write only the fields the push carries. The order already holds the address approved in PayPal,
     * so a push without a field keeps the order's value.
     */
    private function set_address(string $type, array $fields): void
    {
        foreach ($fields as $field => $parameter) {
            $value = $this->get($parameter);
            if ($value !== '') {
                $this->order->set_props(["{$type}_{$field}" => $value]);
            }
        }
    }

    private function get(string $key): string
    {
        return $this->response->getServiceParameter($key) ?? '';
    }
}
