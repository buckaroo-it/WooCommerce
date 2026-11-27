<?php

namespace Buckaroo\Woocommerce\Gateways\SepaDirectDebit;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;

class SepaDirectDebitProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        if (
            $this->request->input('buckaroo-sepadirectdebit-accountname') !== null &&
            $this->request->input('buckaroo-sepadirectdebit-iban') !== null
        ) {
            return [
                'iban' => $this->request->input('buckaroo-sepadirectdebit-iban'),
                'customer' => [
                    'name' => $this->request->input('buckaroo-sepadirectdebit-accountname'),
                ],
            ];
        }

        return [];
    }

    /**
     * Show the mandate to the customer in one order note. Buckaroo usually
     * answers a direct debit with 791 (pending processing), so this runs for
     * any status that carries the mandate (BTI-1880).
     */
    public function beforeReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        $lines = [];
        foreach (['MandateReference', 'MandateDate'] as $name) {
            $value = $responseParser->getServiceParameter($name);
            if (is_scalar($value) && (string) $value !== '') {
                $lines[] = $name . ': ' . $value;
            }
        }

        if ($lines !== []) {
            $this->get_order()->add_order_note(implode("\n", $lines), 1);
        }
    }
}
