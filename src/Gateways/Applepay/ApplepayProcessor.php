<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class ApplepayProcessor extends AbstractPaymentProcessor
{
    protected $data;

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {

        $raw = $this->request->input('paymentData');
        if ($raw === null || $raw === '') {
            $raw = $this->request->input('paymentdata');
        }

        $paymentData = $this->normalize_payment_data($raw);

        $customerCardName = $this->get_customer_name($paymentData);
        if ($customerCardName === '') {
            $customerCardName = trim(
                $this->getAddress('billing', 'first_name') . ' ' . $this->getAddress('billing', 'last_name')
            );
        }

        return [
            'customerCardName' => $customerCardName,
            'paymentData' => $this->get_payment_data($paymentData),
        ];
    }

    /**
     * Normalise the Apple Pay payment payload to an array.
     *
     * @param  mixed  $data
     * @return array
     */
    private function normalize_payment_data($data): array
    {
        if (is_string($data)) {
            $decoded = json_decode(wp_unslash($data), true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  mixed  $data
     */
    private function get_customer_name($data): string
    {
        if (
            isset($data['billingContact']) &&
            isset($data['billingContact']['givenName']) &&
            isset($data['billingContact']['familyName'])
        ) {
            return $data['billingContact']['givenName'] . ' ' . $data['billingContact']['familyName'];
        }

        return '';
    }

    /**
     * @param  mixed  $data
     */
    private function get_payment_data($data): string
    {
        if (! isset($data['token']) || empty($data['token'])) {
            return '';
        }

        return base64_encode(json_encode($data['token']));
    }
}
