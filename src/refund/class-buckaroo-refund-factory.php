<?php


require_once dirname(__FILE__) . "/methods/class-buckaroo-default-refund.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-afterpay-refund.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-billink-refund.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-klarna-refund.php";
require_once dirname(__FILE__) . "/class-buckaroo-refund-processor.php";


/**
 * Core class for payment factory
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
class Buckaroo_Refund_Factory
{
    private static array $classes = array(
        'afterpay' => Buckaroo_Afterpay_Refund::class,
        'billink' => Buckaroo_Billink_Refund::class,
        'klarna' => Buckaroo_Klarna_Refund::class,
    );

    public static function get_refund(WC_Gateway_Buckaroo $gateway, int $order_id, float $amount, string $reason): Buckaroo_Sdk_Payload_Interface
    {
        $order_details = new Buckaroo_Order_Details(new WC_Order($order_id));
        $class = Buckaroo_Default_Refund::class;

        $code = strtolower($gateway->get_sdk_code());
        if (array_key_exists($code, self::$classes)) {
            $class = self::$classes[$code];
        }
        return new $class(
            $gateway,
            $order_details,
            $amount,
            $reason
        );
    }
}
