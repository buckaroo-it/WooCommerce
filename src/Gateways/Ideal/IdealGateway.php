<?php

namespace Buckaroo\Woocommerce\Gateways\Ideal;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class IdealGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = IdealProcessor::class;

    public function __construct()
    {
        $this->id = 'buckaroo_ideal';
        $this->title = 'iDEAL | Wero';
        $this->has_fields = true;
        $this->method_title = 'Buckaroo iDEAL | Wero';
        $this->setIcon('svg/ideal-wero.svg');

        parent::__construct();
        $settings = $this->settings ?? [];
        $optionKey = 'woocommerce_buckaroo_ideal_settings';
        $rawSettings = get_option($optionKey);

        // Normalize title both in runtime and (if needed) in stored settings,
        // but only when merchants are still using legacy or empty values.
        $needsUpdate = false;

        if (
            ! isset($settings['title']) ||
            $settings['title'] === '' ||
            $settings['title'] === 'iDEAL'
        ) {
            $this->title = 'iDEAL | Wero';
            if (is_array($rawSettings)) {
                $rawSettings['title'] = 'iDEAL | Wero';
                $needsUpdate = true;
            }
        }

        if (
            ! isset($settings['description']) ||
            $settings['description'] === '' ||
            stripos($settings['description'], 'ideal') !== false
        ) {
            $this->description = sprintf(
                __('Pay with %s', 'wc-buckaroo-bpe-gateway'),
                $this->title
            );

            if (is_array($rawSettings)) {
                $rawSettings['description'] = $this->description;
                $needsUpdate = true;
            }
        }

        if ($needsUpdate && is_array($rawSettings)) {
            update_option($optionKey, $rawSettings, 'no');
        }

        $this->addRefundSupport();
        apply_filters('buckaroo_init_payment_class', $this);
    }
}
