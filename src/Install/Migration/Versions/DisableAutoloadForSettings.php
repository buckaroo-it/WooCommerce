<?php

namespace Buckaroo\Woocommerce\Install\Migration\Versions;

use Buckaroo\Woocommerce\Install\Migration\Migration;

class DisableAutoloadForSettings implements Migration
{
    public $version = '4.5.1';

    public function execute()
    {
        global $wpdb;

        if (! isset($wpdb)) {
            return;
        }

        $likeClauses = [
            $wpdb->esc_like('woocommerce_buckaroo_') . '%',
        ];

        foreach ($likeClauses as $pattern) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->options} SET autoload = 'off' WHERE option_name LIKE %s",
                    $pattern
                )
            );
        }
    }
}
