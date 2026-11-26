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
        $paymentData = $this->getWalletPaymentData('buckaroo_applepay_payment_data');

        return [
            'customerCardName' => $this->resolveWalletCustomerName($paymentData),
            'paymentData' => $this->get_payment_data($paymentData),
        ];
    }

    /**
     * The token sent to Buckaroo, or null when the payload has none. Apple Pay
     * sends the token as a structured object. Older Apple Pay JS versions send
     * the payment payload without wrapping it in a token, in which case the
     * whole payload is forwarded.
     *
     * @return mixed
     */
    public static function walletToken(array $data)
    {
        if (! empty($data['token'])) {
            return $data['token'];
        }

        if (! empty($data['paymentData'])) {
            return $data;
        }

        return null;
    }

    private function get_payment_data(array $data): string
    {
        $token = self::walletToken($data);

        return $token === null ? '' : $this->encodeWalletToken($token);
    }
}
