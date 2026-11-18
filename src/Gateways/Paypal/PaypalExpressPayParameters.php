<?php

namespace Buckaroo\Woocommerce\Gateways\Paypal;

use BuckarooDeps\Buckaroo\PaymentMethods\Paypal\Models\ExtraInfo;

/**
 * Service parameters of a PayPal Express payment with seller protection: the
 * approved PayPal order and the customer name, address and phone.
 */
class PaypalExpressPayParameters extends ExtraInfo
{
    /**
     * @var string
     */
    protected string $payPalOrderId;
}
