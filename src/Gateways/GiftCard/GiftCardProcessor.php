<?php

namespace Buckaroo\Woocommerce\Gateways\GiftCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\CheckoutError;

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
                    /* translators: %s: Gift card payment method. */
                    __('Card number or pin is incorrect for %s', 'wc-buckaroo-bpe-gateway'),
                    $responseParser->getPaymentMethod()
                );
            } else {
                $errorMessage = $responseParser->getStatusMessage();
            }

            wc_add_notice($errorMessage, 'error');

            return [
                'redirect' => CheckoutError::addToUrl($redirectUrl),
                'result' => 'failure',
            ];
        }
    }
}
