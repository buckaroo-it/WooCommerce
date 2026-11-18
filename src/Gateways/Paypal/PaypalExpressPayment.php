<?php

namespace Buckaroo\Woocommerce\Gateways\Paypal;

use BuckarooDeps\Buckaroo\PaymentMethods\Paypal\Paypal;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;

/**
 * The SDK's PayPal method with a payment for a PayPal order the customer
 * already approved with the express button. The SDK sends the PayPal order
 * ID only with the Pay action and the seller protection data only with
 * ExtraInfo; here both go in one Pay,ExtraInfo request.
 */
class PaypalExpressPayment extends Paypal
{
    /**
     * Processor action that pays an approved PayPal order with seller protection data.
     */
    public const ACTION = 'payExpressWithExtraInfo';

    /**
     * @return TransactionResponse|PaypalExpressPayment The response, or the payment itself when built manually.
     */
    public function payExpressWithExtraInfo()
    {
        $this->setPayPayload();

        $this->setServiceList('Pay,ExtraInfo', new PaypalExpressPayParameters($this->payload));

        return $this->postRequest();
    }
}
