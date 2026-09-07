<?php

namespace Buckaroo\Woocommerce\Services;

use WC_Order;

class NamedLock
{
    public static function acquire(string $purpose, WC_Order $order, string $key, int $waitSeconds = 0): bool
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT GET_LOCK(%s, %d)',
                self::name($purpose, $order, $key),
                max(0, $waitSeconds)
            )
        ) === 1;
    }

    public static function release(string $purpose, WC_Order $order, string $key): void
    {
        global $wpdb;

        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::name($purpose, $order, $key)));
    }

    private static function name(string $purpose, WC_Order $order, string $key): string
    {
        return 'buckaroo_' . $purpose . '_' . substr(hash('sha256', $order->get_id() . ':' . $key), 0, 40);
    }
}
