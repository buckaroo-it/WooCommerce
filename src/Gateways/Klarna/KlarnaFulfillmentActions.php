<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use WC_Order;

class KlarnaFulfillmentActions
{
    public function __construct()
    {
        add_filter('woocommerce_order_actions', [$this, 'add_fulfillment_actions'], 10, 2);

        add_action('woocommerce_order_action_buckaroo_klarnapay_cancel_reservation', [$this, 'handle_cancel_reservation'], 10, 1);
    }

    /**
     * Add Klarna fulfillment actions to the WooCommerce order actions dropdown
     *
     * @param  array  $actions
     * @param  WC_Order|null  $order
     * @return array
     */
    public function add_fulfillment_actions($actions, $order = null)
    {
        global $theorder;

        if ($order === null) {
            if (! ($theorder instanceof WC_Order)) {
                return $actions;
            }
            $order = $theorder;
        }

        if (
            $order->get_payment_method() !== 'buckaroo_klarnapay' ||
            $order->get_meta('buckaroo_is_reserved') !== 'yes'
        ) {
            return $actions;
        }

        $actions['buckaroo_klarnapay_cancel_reservation'] = esc_html__('Klarna: Cancel reservation', 'wc-buckaroo-bpe-gateway');

        return $actions;
    }

    /**
     * Handle Cancel Reservation action
     *
     * @param  WC_Order  $order
     * @return void
     */
    public function handle_cancel_reservation(WC_Order $order)
    {
        $gateway = new KlarnaPayGateway();
        $gateway->cancel_reservation($order);

        wp_safe_redirect(admin_url("post.php?post={$order->get_id()}&action=edit"));
        exit;
    }
}
