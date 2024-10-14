<?php

namespace Buckaroo\Woocommerce\Gateways\SepaDirectDebit;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class SepaDirectDebitProcessor extends AbstractPaymentProcessor
{
    /** @inheritDoc */
    protected function getMethodBody(): array
    {
        if (
            $this->request->input('buckaroo-sepadirectdebit-accountname') !== null &&
            $this->request->input('buckaroo-sepadirectdebit-iban') !== null
        ) {
            return [
                'iban' => $this->request->input('buckaroo-sepadirectdebit-iban'),
                'customer' => [
                    'name' => $this->request->input('buckaroo-sepadirectdebit-accountname')
                ]
            ];
        }
        return [];
    }

}