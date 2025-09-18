<?php

namespace Buckaroo\Woocommerce\Gateways\GiftCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;

class GiftCardProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    public function getAction(): string
    {
        return 'payRedirect';
    }

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        return [
            'continueOnIncomplete' => '1',
            'servicesSelectableByClient' => $this->gateway->get_option('giftcards', ''),
        ];
    }

    public function unsuccessfulReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        if ($responseParser->isFailed()) {
            if ($responseParser->getSubCodeMessage() === 'Failed') {
                $errorMessage = sprintf(
                    __('Card number or pin is incorrect for %s', 'wc-buckaroo-bpe-gateway'),
                    $responseParser->getPaymentMethod()
                );
            } else {
                $errorMessage = __($responseParser->getStatusMessage(), 'wc-buckaroo-bpe-gateway');
            }

            wc_add_notice($errorMessage, 'error');

            return [
                'redirect' => $redirectUrl . '?bck_err=' . $errorMessage,
                'result' => 'failure',
            ];
        }
    }
}
