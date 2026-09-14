<?php

namespace Buckaroo\Woocommerce\Gateways\Googlepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Traits\HandlesWalletPaymentData;

class GooglepayProcessor extends AbstractPaymentProcessor
{
    use HandlesWalletPaymentData;

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        $paymentData = $this->getWalletPaymentData();

        $body = [
            'customerCardName' => $this->resolveWalletCustomerName($paymentData),
            'paymentData' => $this->encodeWalletToken($paymentData['token'] ?? ''),
        ];

        // The Express Checkout button authorises a specific amount and posts it
        // along. The standard checkout method does not: there the order total
        // computed by AbstractPaymentProcessor is authoritative.
        $amount = $this->request->input('amount');
        if ($amount !== null && $amount !== '' && is_scalar($amount)) {
            $body['amountDebit'] = number_format((float) $amount, 2, '.', '');
        }

        return $body;
    }
}
