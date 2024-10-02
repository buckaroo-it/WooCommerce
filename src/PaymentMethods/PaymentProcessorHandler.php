<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods;

use Buckaroo_Http_Request;
use WC_Buckaroo\WooCommerce\Payment\OrderArticles;
use WC_Buckaroo\WooCommerce\Payment\OrderDetails;
use WC_Buckaroo\WooCommerce\SDK\Buckaroo_Sdk_Payload_Interface;
use WC_Order;

class PaymentProcessorHandler implements Buckaroo_Sdk_Payload_Interface
{
    protected PaymentGatewayHandler $gateway;

    private Buckaroo_Http_Request $request;

    protected OrderDetails $order_details;

    protected OrderArticles $order_articles;

    public function __construct(
        PaymentGatewayHandler $gateway,
        Buckaroo_Http_Request $request,
        OrderDetails          $order_details,
        OrderArticles         $order_articles
    )
    {
        $this->gateway = $gateway;
        $this->request = $request;
        $this->order_details = $order_details;
        $this->order_articles = $order_articles;
    }

    /**
     * Get request action
     *
     * @return string
     */
    public function get_action(): string
    {
        return 'pay';
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
                'amountDebit' => number_format((float)$this->get_order()->get_total('edit'), 2, '.', ''),
                'currency' => get_woocommerce_currency(),
                'returnURL' => $this->get_return_url(),
                'cancelURL' => $this->get_return_url(),
                'pushURL' => $this->get_push_url(),
                'additionalParameters' => [
                    'real_order_id' => $this->get_order()->get_id(),
                ],

                'description' => $this->get_description(),
                'clientIP' => $this->get_ip(),
            ]
        );
    }

    protected function get_method_body(): array
    {
        return array();
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
     * Get the parsed label, we replace the template variables with the values
     *
     * @return string
     */
    private function get_description(): string
    {
        $label = $this->gateway->get_option('transactiondescription', 'Order #' . $this->get_order()->get_order_number());

        $label = preg_replace('/\{order_number\}/', $this->get_order()->get_order_number(), $label);
        $label = preg_replace('/\{shop_name\}/', get_bloginfo('name'), $label);

        $products = $this->get_order()->get_items('line_item');
        if (count($products)) {
            $label = preg_replace('/\{product_name\}/', array_values($products)[0]->get_name(), $label);
        }

        $label = preg_replace("/\r?\n|\r/", '', $label);

        return mb_substr($label, 0, 244);
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

    /**
     * Get order
     *
     * @return WC_Order
     */
    protected function get_order(): WC_Order
    {
        return $this->order_details->get_order();
    }

    /**
     * Get order articles
     *
     * @return array
     */
    protected function get_articles(): array
    {
        return $this->order_articles->get_products_for_payment();
    }

    /**
     * Get address component
     *
     * @param string $type
     * @param string $key
     * @param string $default
     *
     * @return mixed
     */
    protected function get_address(string $type, string $key, $default = '')
    {
        return $this->order_details->get($type . "_" . $key, $default);
    }
}
