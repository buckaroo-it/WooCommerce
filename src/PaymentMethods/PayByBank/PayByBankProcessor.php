<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\PayByBank;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;

class PayByBankProcessor extends PaymentProcessorHandler
{
    protected function get_method_body(): array
    {
        return [
            'issuer' => $this->request_string('buckaroo-paybybank-issuer')
        ];
    }
}