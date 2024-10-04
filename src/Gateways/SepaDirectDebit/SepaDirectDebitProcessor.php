<?php

namespace Buckaroo\Woocommerce\Gateways\SepaDirectDebit;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class SepaDirectDebitProcessor extends AbstractPaymentProcessor
{
    /** @inheritDoc */
    protected function getMethodBody(): array
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