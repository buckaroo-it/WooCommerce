<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\Ideal;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;

class IdealProcessor extends PaymentProcessorHandler
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        if (!$this->show_issuers()) {
            return [
                'continueOnIncomplete' => true
            ];
        }
        return [
            'issuer' => $this->request_string('buckaroo-ideal-issuer')
        ];
    }

    private function show_issuers(): bool
    {
        return $this->gateway->get_option('show_issuers') !== 'no';
    }
}