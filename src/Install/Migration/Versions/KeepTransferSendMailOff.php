<?php

namespace Buckaroo\Woocommerce\Install\Migration\Versions;

use Buckaroo\Woocommerce\Install\Migration\Migration;

class KeepTransferSendMailOff implements Migration
{
    public $version = '4.11.1';

    public function execute()
    {
        $optionKey = 'woocommerce_buckaroo_transfer_settings';
        $settings = get_option($optionKey);

        if (! is_array($settings) || array_key_exists('sendmail', $settings)) {
            return;
        }

        $settings['sendmail'] = 'FALSE';
        update_option($optionKey, $settings, 'no');
    }
}
