<?php

namespace Buckaroo\Woocommerce\Install\Migration\Versions;

use Buckaroo\Woocommerce\Install\Migration\Migration;

class IdealWeroBranding implements Migration
{
    /**
     * Version from which this migration is effective.
     *
     * @var string
     */
    public $version = '4.7.0';

    public function execute()
    {
        $optionKey = 'woocommerce_buckaroo_ideal_settings';
        $settings = get_option($optionKey);

        if (! is_array($settings)) {
            return;
        }

        // Normalise the stored title to the new co-branded name when it is
        // still using the legacy iDEAL label or is empty.
        if (
            ! isset($settings['title']) ||
            $settings['title'] === '' ||
            $settings['title'] === 'iDEAL'
        ) {
            $settings['title'] = 'iDEAL | Wero';
        }

        // Normalise the description when it is empty or still refers to iDEAL.
        if (
            ! isset($settings['description']) ||
            $settings['description'] === '' ||
            stripos($settings['description'], 'ideal') !== false
        ) {
            $settings['description'] = sprintf(
                __('Pay with %s', 'buckaroo-woocommerce'),
                $settings['title']
            );
        }

        update_option($optionKey, $settings, 'false');
    }
}

