<?php

namespace Buckaroo\Woocommerce\Gateways\Ideal;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class IdealGateway extends AbstractPaymentGateway
{
    private const COBRANDED_LABEL = 'iDEAL | Wero';

    public const PAYMENT_CLASS = IdealProcessor::class;

    public function __construct()
    {
        $this->id = 'buckaroo_ideal';
        $this->title = self::getBrandingDisplayName();
        $this->has_fields = true;
        $this->method_title = 'Buckaroo ' . self::getBrandingDisplayName();
        $this->setIcon(self::getBrandingIconPath());

        parent::__construct();
        $this->addRefundSupport();
        apply_filters('buckaroo_init_payment_class', $this);
    }

    public static function getBrandingDisplayName(): string
    {
        return self::COBRANDED_LABEL;
    }

    public static function getBrandingIconPath(): string
    {
        return 'svg/ideal-wero.svg';
    }

    protected function setProperties()
    {
        parent::setProperties();

        $this->title = self::getBrandingDisplayName();

        if (empty($this->description) || stripos($this->description, 'ideal') !== false) {
            $this->description = sprintf(
                __('Pay with %s', 'wc-buckaroo-bpe-gateway'),
                $this->title
            );
        }
    }
}
