<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\SepaDirectDebit;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;

class SepaDirectDebitProcessor extends PaymentProcessorHandler
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        if (
            $this->request_string('buckaroo-sepadirectdebit-accountname') !== null &&
            $this->request_string('buckaroo-sepadirectdebit-iban') !== null
        ) {
            return [
                'iban' => $this->request_string('buckaroo-sepadirectdebit-iban'),
                'customer' => [
                    'name' => $this->request_string('buckaroo-sepadirectdebit-accountname')
                ]
            ];
        }
        return [];
    }

}