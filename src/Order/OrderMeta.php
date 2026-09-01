<?php

namespace Buckaroo\Woocommerce\Order;

use WC_Abstract_Order;
use WC_Meta_Data;

/**
 * Order meta accessor.
 *
 * Under HPOS the *_post_meta functions read and write the `shop_order_placehold` post
 * row instead of the order, so the data is invisible to the order APIs. Everything
 * here goes through the WooCommerce order meta API and falls back to post meta when
 * the argument is not an order. The fallback is permanent: non-HPOS stores need it.
 */
class OrderMeta
{
    /**
     * @param  int|WC_Abstract_Order  $order
     * @return mixed
     */
    public static function get($order, string $key, bool $single = true)
    {
        $resolved = self::resolve($order);

        if ($resolved === null) {
            return get_post_meta(self::fallbackId($order), $key, $single);
        }

        // 'edit' skips the woocommerce_order_get_<key> filter. get_post_meta had no
        // per-key filter, and these values decide whether a payment is captured.
        if ($single) {
            return $resolved->get_meta($key, true, 'edit');
        }

        // get_post_meta returns meta_id order; the HPOS meta query has no ORDER BY.
        // AbstractRefundProcessor picks its transaction key with end($captures).
        // Position is the tie breaker, because usort is not stable on PHP 7.4.
        $ordered = [];
        $position = 0;

        foreach ($resolved->get_meta($key, false, 'edit') as $meta) {
            $ordered[] = [self::metaSortKey($meta), $position++, $meta];
        }

        usort(
            $ordered,
            function ($a, $b) {
                return [$a[0], $a[1]] <=> [$b[0], $b[1]];
            }
        );

        return array_map(
            function ($row) {
                return $row[2] instanceof WC_Meta_Data ? $row[2]->value : $row[2];
            },
            $ordered
        );
    }

    /**
     * Unsaved meta has no id and is the newest, so it sorts last.
     *
     * @param  WC_Meta_Data|mixed  $meta
     */
    private static function metaSortKey($meta): int
    {
        if (! $meta instanceof WC_Meta_Data || empty($meta->id)) {
            return PHP_INT_MAX;
        }

        return (int) $meta->id;
    }

    /**
     * @param  int|WC_Abstract_Order  $order
     * @param  mixed  $value
     */
    public static function update($order, string $key, $value): bool
    {
        $resolved = self::resolve($order);

        if ($resolved === null) {
            return (bool) update_post_meta(self::fallbackId($order), $key, $value);
        }

        $resolved->update_meta_data($key, $value);
        $resolved->save_meta_data();

        return true;
    }

    /**
     * @param  int|WC_Abstract_Order  $order
     * @param  mixed  $value
     * @param  bool  $unique  Write nothing and return false when the key exists,
     *                        which add_meta_data does not do on its own
     */
    public static function add($order, string $key, $value, bool $unique = false): bool
    {
        $resolved = self::resolve($order);

        if ($resolved === null) {
            return (bool) add_post_meta(self::fallbackId($order), $key, $value, $unique);
        }

        if ($unique && $resolved->meta_exists($key)) {
            return false;
        }

        $resolved->add_meta_data($key, $value, $unique);
        $resolved->save_meta_data();

        return true;
    }

    /**
     * @param  int|WC_Abstract_Order  $order
     */
    public static function delete($order, string $key): bool
    {
        $resolved = self::resolve($order);

        if ($resolved === null) {
            return (bool) delete_post_meta(self::fallbackId($order), $key);
        }

        $resolved->delete_meta_data($key);
        $resolved->save_meta_data();

        return true;
    }

    /**
     * @param  int|WC_Abstract_Order  $order
     * @return WC_Abstract_Order|null
     */
    protected static function resolve($order)
    {
        if ($order instanceof WC_Abstract_Order) {
            return $order;
        }

        // A non-positive id would make wc_get_order() fall back to the global $post.
        if (! function_exists('wc_get_order') || ! is_numeric($order) || (int) $order <= 0) {
            return null;
        }

        $resolved = wc_get_order((int) $order);

        return $resolved instanceof WC_Abstract_Order ? $resolved : null;
    }

    /**
     * @param  int|WC_Abstract_Order  $order
     */
    protected static function fallbackId($order): int
    {
        if ($order instanceof WC_Abstract_Order) {
            return $order->get_id();
        }

        return is_numeric($order) ? (int) $order : 0;
    }
}
