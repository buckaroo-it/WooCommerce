<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\GiftCard;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;

class GiftCardProcessor extends PaymentProcessorHandler
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return [
            'continueOnIncomplete' => 'RedirectToHTML',
            'servicesSelectableByClient' => $this->gateway->get_option('giftcards', '')
        ];
    }

    /** @inheritDoc */
    public function get_action(): string
    {
        return 'payRedirect';
    }
}