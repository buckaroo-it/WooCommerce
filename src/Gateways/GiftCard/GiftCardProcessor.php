<?php

namespace Buckaroo\Woocommerce\Gateways\GiftCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class GiftCardProcessor extends AbstractPaymentProcessor
{
    /** @inheritDoc */
    public function getAction(): string
    {
        return 'payRedirect';
    }

    /** @inheritDoc */
    protected function getMethodBody(): array
    {
        return [
            'continueOnIncomplete' => '1',
            'servicesSelectableByClient' => $this->gateway->get_option('giftcards', '')
        ];
    }
}