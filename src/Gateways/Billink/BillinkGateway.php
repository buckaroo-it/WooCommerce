<?php

namespace Buckaroo\Woocommerce\Gateways\Billink;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use WC_Order;

class BillinkGateway extends AbstractPaymentGateway
{
    protected array $supportedCountries = ['NL', 'BE'];

    public const PAYMENT_CLASS = BillinkProcessor::class;

    public $type;

    public $b2b;

    public $vattype;

    public $country;

    public bool $capturable = false;

    public function __construct()
    {
        $this->id = 'buckaroo_billink';
        $this->title = 'Billink';
        $this->method_description = __('Dutch pay-after-delivery by invoice with a 14-day term, for private and business customers.', 'wc-buckaroo-bpe-gateway');
        $this->has_fields = true;
        $this->method_title = 'Buckaroo Billink';
        $this->setIcon('svg/billink.svg');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * A business order (billing company set on the order) needs a CoC number.
     *
     * @return array<string>
     */
    public function getOrderValidationErrors(WC_Order $order): array
    {
        if (
            ! $this->isBlank($order->get_billing_company()) &&
            $this->isBlank($this->request->input('buckaroo-billink-company-coc-registration'))
        ) {
            return [__('Please enter correct COC (KvK) number', 'wc-buckaroo-bpe-gateway')];
        }

        return [];
    }

    /**  {@inheritDoc} */
    protected function setProperties()
    {
        parent::setProperties();
        $this->type = 'billink';
        $this->vattype = $this->get_option('vattype');
    }
}
