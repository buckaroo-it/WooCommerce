<?php

namespace WC_Buckaroo\WooCommerce\Idin;

use BuckarooConfig;
use WC_Buckaroo\WooCommerce\SDK\Buckaroo_Sdk_Payload_Interface;

class Buckaroo_Idin_Payload implements Buckaroo_Sdk_Payload_Interface
{

    protected string $issuer;

    public function __construct(
        string $issuer
    )
    {
        $this->issuer = $issuer;
    }

    /**
     * Get request action
     *
     * @return string
     */
    public function get_action(): string
    {
        return 'verify';
    }

    /**
     * Get request mode: test|live
     *
     * @return string
     */
    public function request_mode(): string
    {
        return BuckarooConfig::getIdinMode();
    }

    /**
     * Get payment code required for sdk
     *
     * @return string
     */
    public function get_sdk_code(): string
    {
        return 'idin';
    }

    /**
     * Get request body
     *
     * @return array
     */
    public function get_body(): array
    {
        return [
            // 'order'         => '0',
            // 'invoice'       => '0',
            // 'amountDebit'   => '0',
            // 'currency'      => 'EUR',
            'returnURL' => $this->get_return_url(),
            'cancelURL' => $this->get_return_url(),
            'pushURL' => $this->get_push_url(),
            'clientIP' => $this->get_ip(),
            'issuer' => $this->issuer
        ];
    }

    /**
     * Get return url
     *
     * @return string
     */
    private function get_return_url(): string
    {
        return add_query_arg(
            array(
                'wc-api' => 'WC_Gateway_Buckaroo_idin-return',
                'bk_redirect' => wc_get_checkout_url(),
            ),
            home_url('/')
        );
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
