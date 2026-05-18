<?php

namespace Buckaroo\Woocommerce\Install\Migration\Versions;

use Buckaroo\Woocommerce\Install\Migration\Migration;

/**
 * Cleans up legacy "Klarna: Pay later" front-end label and description values
 * stored for the Klarna (MoR) gateway (`buckaroo_klarnapay`) in earlier plugin
 * versions, before the gateway was renamed to just "Klarna".
 */
class MigrateKlarnaPayLaterLabels implements Migration
{
    public $version = '4.7.3';

    public function execute()
    {
        $optionKey = 'woocommerce_buckaroo_klarnapay_settings';
        $settings = get_option($optionKey);

        if (! is_array($settings)) {
            return;
        }

        $needsUpdate = false;

        if (isset($settings['title']) && stripos($settings['title'], 'Pay later') !== false) {
            $settings['title'] = 'Klarna';
            $needsUpdate = true;
        }

        if (isset($settings['description']) && stripos($settings['description'], 'Pay later') !== false) {
            $settings['description'] = 'Pay with Klarna';
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            update_option($optionKey, $settings, 'no');
        }
    }
}
