<?php

namespace WC_Buckaroo\WooCommerce\Payment;

use Buckaroo_Http_Request;
use WC_Buckaroo\WooCommerce\SDK\Buckaroo_Sdk_Payload_Interface;
use WC_Gateway_Buckaroo;
use WC_Order;

require_once dirname(__FILE__) . "/class-buckaroo-address-components.php";
require_once dirname(__FILE__) . "/class-buckaroo-order-articles.php";
require_once dirname(__FILE__) . "/class-buckaroo-order-details.php";

require_once dirname(__FILE__) . "/methods/class-buckaroo-default-method.php";

require_once dirname(__FILE__) . "/methods/class-buckaroo-afterpay.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-afterpay-old.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-alipay.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-applepay.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-billink.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-credicard.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-giftcard.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-ideal.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-in3-v2.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-in3.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-klarna.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-klarnain.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-p24.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-paybybank.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-paypal.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-paypermail.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-sepa.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-trustly.php";
require_once dirname(__FILE__) . "/methods/class-buckaroo-wechatpay.php";
require_once dirname(__FILE__) . "/methods/class-bukcaroo-transfer.php";

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
class Buckaroo_Payment_Factory
{
    private static array $classes = array(
        'afterpay' => Methods\Buckaroo_Afterpay::class,
        'afterpaydigiaccept' => Methods\Buckaroo_Afterpay_Old::class,
        'alipay' => Methods\Buckaroo_Alipay::class,
        'applepay' => Methods\Buckaroo_ApplePay::class,
        'billink' => Methods\Buckaroo_Billink::class,
        'credicard' => Methods\Buckaroo_CreditCard::class,
        'giftcard' => Methods\Buckaroo_Giftcard::class,
        'ideal' => Methods\Buckaroo_Ideal::class,
        'in3' => Methods\Buckaroo_In3::class,
        'klarna' => Methods\Buckaroo_Klarna::class,
        'klarnain' => Methods\Buckaroo_KlarnaIn::class,
        'p24' => Methods\Buckaroo_P24::class,
        'paybybank' => Methods\Buckaroo_PayByBank::class,
        'paypal' => Methods\Buckaroo_Paypal::class,
        'payperemail' => Methods\Buckaroo_PayPerEmail::class,
        'sepadirectdebit' => Methods\Buckaroo_Sepa::class,
        'trustly' => Methods\Buckaroo_Trustly::class,
        'wechatpay' => Methods\Buckaroo_Wechatpay::class,
        'transfer' => Methods\Buckaroo_Transfer::class,
    );

    public static function get_payment(WC_Gateway_Buckaroo $gateway, int $order_id): Buckaroo_Sdk_Payload_Interface
    {
        $order_details = new Buckaroo_Order_Details(new WC_Order($order_id));
        $class = Methods\Buckaroo_Default_Method::class;

        $code = strtolower($gateway->get_sdk_code());
        if (array_key_exists($code, self::$classes)) {
            $class = self::$classes[$code];
        }
        return new $class(
            $gateway,
            new Buckaroo_Http_Request(),
            $order_details,
            new Buckaroo_Order_Articles($order_details, $gateway)
        );
    }
}
