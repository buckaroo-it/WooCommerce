<?php

namespace Buckaroo\Woocommerce\Install\Migration\Versions;

use Buckaroo\Woocommerce\Install\Migration\Migration;

class MigrateIdealToIdealWero implements Migration
{
    public $version = '4.7.0';

    public function execute()
    {
        $optionKey = 'woocommerce_buckaroo_ideal_settings';
        $settings = get_option($optionKey);

        if (! is_array($settings)) {
            return;
        }

        $needsUpdate = false;

        // Migrate legacy title "iDEAL" to "iDEAL | Wero"
        if (isset($settings['title']) && $settings['title'] === 'iDEAL') {
            $settings['title'] = 'iDEAL | Wero';
            $needsUpdate = true;
        }

        // Reset legacy description so getPaymentDescription() regenerates it
        // with proper translation support
        if (
            isset($settings['description']) &&
            in_array($settings['description'], [
                'Pay with iDEAL',
                'Betaal met iDEAL',
                'Zahlen mit iDEAL',
                'Payer avec iDEAL',
            ], true)
        ) {
            $settings['description'] = '';
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            update_option($optionKey, $settings, 'no');
        }
    }
}
