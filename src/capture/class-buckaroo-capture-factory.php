<?php

namespace WC_Buckaroo\WooCommerce\Capture;

use Buckaroo_Http_Request;
use WC_Buckaroo\WooCommerce\Capture\Methods\Buckaroo_Creditcard_Capture;
use WC_Buckaroo\WooCommerce\Capture\Methods\Buckaroo_Default_Capture;
use WC_Buckaroo\WooCommerce\Payment\Buckaroo_Order_Details;
use WC_Buckaroo\WooCommerce\SDK\Buckaroo_Sdk_Payload_Interface;
use WC_Gateway_Buckaroo;
use WC_Order;

require_once dirname(__FILE__) . "/methods/class-buckaroo-default-capture.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-creditcard-capture.php";

/**
 * Core class for capture factory
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */
class Buckaroo_Capture_Factory
{
    private static array $classes = array(
        "creditcard" => Buckaroo_Creditcard_Capture::class
    );

    public static function get_payment(WC_Gateway_Buckaroo $gateway, int $order_id, float $amount): Buckaroo_Sdk_Payload_Interface
    {
        $order_details = new Buckaroo_Order_Details(new WC_Order($order_id));
        $class = Buckaroo_Default_Capture::class;

        $code = strtolower($gateway->get_sdk_code());
        if (array_key_exists($code, self::$classes)) {
            $class = self::$classes[$code];
        }
        return new $class(
            $gateway,
            new Buckaroo_Capture_Items($order_details, new Buckaroo_Http_Request(), $gateway),
            $amount
        );
    }
}
