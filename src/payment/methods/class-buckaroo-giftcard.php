<?php

namespace WC_Buckaroo\WooCommerce\Payment\Methods;
class Buckaroo_Giftcard extends Buckaroo_Default_Method
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
