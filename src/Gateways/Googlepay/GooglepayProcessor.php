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

        // Express payments carry the amount approved in the wallet. Only send
        // the order total when it matches that approval, including fees and tax.
        $amount = $this->request->input('amount');
        if ($this->request->exists('amount')) {
            $orderAmount = number_format((float) $this->get_order()->get_total('edit'), 2, '.', '');
            if (
                ! is_scalar($amount) ||
                ! preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $amount) ||
                ! is_finite((float) $amount) ||
                number_format((float) $amount, 2, '.', '') !== $orderAmount
            ) {
                throw new \InvalidArgumentException(
                    __('Google Pay amount has changed. Please refresh the page and approve the updated total.', 'wc-buckaroo-bpe-gateway')
                );
            }
        }

        return $body;
    }
}
