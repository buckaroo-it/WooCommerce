<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class ApplepayProcessor extends AbstractPaymentProcessor
{
    protected $data;

    /** @inheritDoc */
    protected function getMethodBody(): array
    {
        $applePayInfo = $this->request->input('paymentData');

        if (!is_string($applePayInfo)) {
            return [];
        }

        $data = json_decode($applePayInfo);
        if ($data === false || !is_object($data)) {
            return [];
        }

        return [
            "customerCardName" => $this->get_customer_name($data),
            "paymentData" => $this->get_payment_data($data)
        ];
    }

    /**
     * @param mixed $data
     * @return string
     */
    private function get_customer_name($data): string
    {
        if (!is_object($data)) {
            return '';
        }
        if (
            !empty($data->billingContact) &&
            !empty($data->billingContact->givenName) &&
            !empty($data->billingContact->familyName)
        ) {
            return $data->billingContact->givenName . ' ' . $data->billingContact->familyName;
        }
        return '';
    }

    /**
     * @param mixed $data
     * @return string
     */
    private function get_payment_data($data): string
    {
        if (!is_object($data)) {
            return '';
        }
        if (empty($data->token)) {
            return '';
        }

        $data = json_encode($data->token);
        if ($data === false) {
            return '';
        }
        return base64_encode($data);
    }
}