<?php

namespace WC_Buckaroo\WooCommerce\Capture\Methods;

use Buckaroo_Http_Request;
use WC_Buckaroo\WooCommerce\Capture\Buckaroo_Capture_Items;
use WC_Buckaroo\WooCommerce\SDK\Buckaroo_Sdk_Payload_Interface;
use WC_Gateway_Buckaroo;
use WC_Order;

class Buckaroo_Default_Capture implements Buckaroo_Sdk_Payload_Interface
{
    protected WC_Gateway_Buckaroo $gateway;

    private Buckaroo_Http_Request $request;

    protected Buckaroo_Capture_Items $capture_service;

    protected float $amount;

    public function __construct(
        WC_Gateway_Buckaroo $gateway,
        Buckaroo_Capture_Items $capture_service,
        float               $amount
    )
    {
        $this->gateway = $gateway;
        $this->capture_service = $capture_service;
        $this->amount = $amount;
    }

    /**
     * Get request action
     *
     * @return string
     */
    public function get_action(): string
    {
        return 'capture';
    }

    /**
     * Get request mode: test|live
     *
     * @return string
     */
    public function request_mode(): string
    {
        return $this->gateway->get_option('mode');
    }

    /**
     * Get payment code required for sdk
     *
     * @return string
     */
    public function get_sdk_code(): string
    {
        return $this->gateway->get_sdk_code();
    }

    /**
     * Get request body
     *
     * @return array
     */
    public function get_body(): array
    {
        return array_merge(
            $this->get_method_body(),
            [
                'order' => (string)$this->get_order()->get_id(),
                'invoice' => $this->get_invoice_number(),
                'amountDebit' => number_format($this->amount, 2, '.', ''),
                'currency' => $this->get_order()->get_currency('edit'),
                'returnURL' => $this->get_return_url(),
                'cancelURL' => $this->get_return_url(),
                'pushURL' => $this->get_push_url(),
                'originalTransactionKey' => (string)$this->get_order()->get_transaction_id('edit'),
                'additionalParameters' => [
                    'real_order_id' => $this->get_order()->get_id(),
                ],

                'clientIP' => $this->get_ip(),
            ]
        );
    }

    protected function get_method_body(): array
    {
        return [
            'articles' => $this->capture_service->get_products(
                (float)number_format($this->amount, 2, '.', '')
            )
        ];
    }

    protected function request(string $key, $default = null)
    {
        $value = $this->request->request($key);
        return $value ?? $default;
    }

    protected function request_string(string $key, $default = null): ?string
    {
        $value = $this->request($key);
        if (!is_string($value) || empty(trim($value))) {
            return $default;
        }
        return $value;
    }

    private function get_invoice_number(): string
    {
        if (in_array($this->gateway->id, ["buckaroo_afterpaynew", "buckaroo_afterpay"])) {
            return (string)$this->get_order()->get_order_number() . time();
        }
        return (string)$this->get_order()->get_order_number();
    }

    /**
     * Get return url
     *
     * @return string
     */
    private function get_return_url(): string
    {
        return add_query_arg('wc-api', 'wc_buckaroo_return', home_url('/'));
    }

    /**
     * Get order
     *
     * @return WC_Order
     */
    protected function get_order(): WC_Order
    {
        return $this->capture_service->get_order_details()->get_order();
    }

    /**
     * Get ip
     *
     * @return string
     */
    protected function get_ip(): string
    {
        $ipaddress = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }
        $ex = explode(",", sanitize_text_field($ipaddress));
        if (filter_var($ex[0], FILTER_VALIDATE_IP)) {
            return trim($ex[0]);
        }
        return "";
    }


    /**
     * Get push url
     *
     * @return string
     */
    private function get_push_url(): string
    {
        return add_query_arg('wc-api', 'wc_push_buckaroo', home_url('/'));
    }
}
