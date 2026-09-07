<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Traits\HandlesWalletPaymentData;

class ApplepayProcessor extends AbstractPaymentProcessor
{
    use HandlesWalletPaymentData;

    protected $data;

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        $paymentData = $this->getWalletPaymentData();

        return [
            'customerCardName' => $this->resolveWalletCustomerName($paymentData),
            'paymentData' => $this->get_payment_data($paymentData),
        ];
    }

    /**
     * Apple Pay sends the token as a structured object. Older Apple Pay JS
     * versions send the payment payload without wrapping it in a token, in
     * which case the whole payload is forwarded.
     */
    private function get_payment_data(array $data): string
    {
        if (! empty($data['token'])) {
            return $this->encodeWalletToken($data['token']);
        }

        if (! empty($data['paymentData'])) {
            return $this->encodeWalletToken($data);
        }

        return '';
    }
}
