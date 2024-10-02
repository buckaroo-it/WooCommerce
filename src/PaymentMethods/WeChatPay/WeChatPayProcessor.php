<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\WeChatPay;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;

class WeChatPayProcessor extends PaymentProcessorHandler
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return [
            'locale' => $this->getLocaleCode($this->get_address('billing', 'country')),
        ];
    }

    private function getLocaleCode(string $country = null): string
    {
        if ($country == 'CN') {
            return 'zh-CN';
        }
        if ($country == 'TW') {
            return 'zh-TW';
        }
        return 'en-US';
    }
}