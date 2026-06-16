<?php

namespace Buckaroo\Woocommerce\Gateways\Googlepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class GooglepayProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        return [
            'amountDebit' => number_format($this->request->input('amount'), 2, '.', ''),
            'customerCardName' => $this->get_customer_name($this->request->input('paymentData')),
            'paymentData' => $this->get_payment_data($this->request->input('paymentData')),
        ];
    }

    /**
     * @param  mixed  $data
     */
    private function get_customer_name($data): string
    {
        $contacts = ['billingContact', 'shippingContact'];

        foreach ($contacts as $contactKey) {
            if (
                isset($data[$contactKey]['givenName']) &&
                isset($data[$contactKey]['familyName'])
            ) {
                $name = trim($data[$contactKey]['givenName'] . ' ' . $data[$contactKey]['familyName']);
                if ($name !== '') {
                    return $name;
                }
            }
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

        $token = $data['token'];

        if (is_array($token)) {
            $token = json_encode($token);
        } else {
            $token = wp_unslash($token);
        }

        return base64_encode($token);
    }
}
