<?php

namespace Buckaroo\Woocommerce\Gateways\Trustly;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class TrustlyGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = TrustlyProcessor::class;

    protected array $supportedCurrencies = ['EUR', 'SEK', 'NOK', 'DKK', 'GBP'];

    public function __construct()
    {
        $this->id = 'buckaroo_trustly';
        $this->title = 'Trustly';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Trustly';
        $this->method_description = __('Trustly is a secure online banking payment method that allows customers to pay directly from their bank account.', 'wc-buckaroo-bpe-gateway');
        $this->setIcon('svg/trustly.svg');

        parent::__construct();
        $this->addRefundSupport();
    }
}
