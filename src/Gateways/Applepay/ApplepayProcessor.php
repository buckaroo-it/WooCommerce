<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class ApplepayProcessor extends AbstractPaymentProcessor
{

    protected $data;

    /** @inheritDoc */
    protected function getMethodBody(): array
    {
        return array(
            'customerCardName' => $this->get_customer_name($this->request->input('paymentData')),
            'paymentData' => $this->get_payment_data($this->request->input('paymentData')),
        );
    }

    /**
     * @param mixed $data
     * @return string
     */
    private function get_customer_name($data): string
    {
        if (
            isset($data['billingContact']) &&
            isset($data['billingContact']['givenName']) &&
            $data['billingContact']['familyName']
        ) {
            return $data['billingContact']['givenName'] . ' ' . $data['billingContact']['familyName'];
        }
        return '';
    }

    /**
     * @param mixed $data
     * @return string
     */
    private function get_payment_data($data): string
    {
        if (!isset($data['token']) || empty($data['token'])) {
            return '';
        }

        return base64_encode($data);
    }
}
