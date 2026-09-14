<?php

namespace Buckaroo\Woocommerce\Gateways\Przelewy24;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class Przelewy24Gateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = Przelewy24Processor::class;

    protected array $supportedCurrencies = ['EUR', 'PLN'];
    protected array $supportedCountries = ['PL'];

    public function __construct()
    {
        $this->id = 'buckaroo_przelewy24';
        $this->title = 'Przelewy24';
        $this->method_description = __("Poland's leading online payment platform, connecting 165+ local banks and methods.", 'wc-buckaroo-bpe-gateway');
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Przelewy24';
        $this->setIcon('svg/przelewy24.svg');
        $this->migrateOldSettings('woocommerce_buckaroo_p24_settings');

        parent::__construct();
        $this->addRefundSupport();
    }
}
