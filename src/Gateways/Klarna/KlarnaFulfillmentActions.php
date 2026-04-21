<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Services\BuckarooClient;
use WC_Order;

class KlarnaFulfillmentActions
{
    public function __construct()
    {
        add_filter('woocommerce_order_actions', [$this, 'add_fulfillment_actions'], 10, 2);

        add_action('woocommerce_order_action_buckaroo_klarnapay_cancel_reservation', [$this, 'handle_cancel_reservation'], 10, 1);
        add_action('woocommerce_order_action_buckaroo_klarnapay_update_reservation', [$this, 'handle_update_reservation'], 10, 1);
        add_action('woocommerce_order_action_buckaroo_klarnapay_extend_reservation', [$this, 'handle_extend_reservation'], 10, 1);
        add_action('woocommerce_order_action_buckaroo_klarnapay_add_shipping_info', [$this, 'handle_add_shipping_info'], 10, 1);
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
        $actions['buckaroo_klarnapay_update_reservation'] = esc_html__('Klarna: Update reservation', 'wc-buckaroo-bpe-gateway');
        $actions['buckaroo_klarnapay_extend_reservation'] = esc_html__('Klarna: Extend reservation', 'wc-buckaroo-bpe-gateway');
        $actions['buckaroo_klarnapay_add_shipping_info'] = esc_html__('Klarna: Add shipping info', 'wc-buckaroo-bpe-gateway');

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

    /**
     * Handle Update Reservation action
     *
     * @param  WC_Order  $order
     * @return void
     */
    public function handle_update_reservation(WC_Order $order)
    {
        $this->dispatch_data_request($order, 'updateReserve', 'UpdateReservation');
    }

    /**
     * Handle Extend Reservation action
     *
     * @param  WC_Order  $order
     * @return void
     */
    public function handle_extend_reservation(WC_Order $order)
    {
        $this->dispatch_data_request($order, 'extendReservation', 'ExtendReservation');
    }

    /**
     * Handle Add Shipping Info action
     *
     * @param  WC_Order  $order
     * @return void
     */
    public function handle_add_shipping_info(WC_Order $order)
    {
        $this->dispatch_data_request($order, 'addShippingInfo', 'AddShippingInfo');
    }

    /**
     * Dispatch a Klarna data request action and set an admin transient notice
     *
     * @param  WC_Order  $order
     * @param  string  $sdkMethod   Method name on the KlarnaPay SDK object
     * @param  string  $actionLabel Human-readable action name for notices
     * @return void
     */
    protected function dispatch_data_request(WC_Order $order, string $sdkMethod, string $actionLabel)
    {
        $gateway = new KlarnaPayGateway();
        $processor = $gateway->newPaymentProcessorInstance($order);
        $payment = new BuckarooClient($gateway->getMode());

        $dataRequestKey = get_post_meta($order->get_id(), KlarnaProcessor::DATA_REQUEST_META_KEY, true);

        if (! is_string($dataRequestKey) || strlen($dataRequestKey) === 0) {
            set_transient(
                get_current_user_id() . 'buckarooAdminNotice',
                [
                    'type' => 'warning',
                    'message' => sprintf(
                        /* translators: %1$s: action name, %2$s: order number */
                        __('Cannot perform Klarna %1$s for order #%2$s: Data Request key not found', 'wc-buckaroo-bpe-gateway'),
                        $actionLabel,
                        $order->get_order_number()
                    ),
                ]
            );

            return;
        }

        $response = $payment->method($gateway->getServiceCode($processor))->{$sdkMethod}(
            array_merge(
                $processor->getBody(),
                ['originalTransactionKey' => $dataRequestKey]
            )
        );

        if ($response->isSuccess()) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: action name */
                    __('Klarna %s successfully processed.', 'wc-buckaroo-bpe-gateway'),
                    $actionLabel
                )
            );

            set_transient(
                get_current_user_id() . 'buckarooAdminNotice',
                [
                    'type' => 'success',
                    'message' => sprintf(
                        /* translators: %1$s: action name, %2$s: order number */
                        __('Klarna %1$s for order #%2$s was successfully processed', 'wc-buckaroo-bpe-gateway'),
                        $actionLabel,
                        $order->get_order_number()
                    ),
                ]
            );
        } else {
            set_transient(
                get_current_user_id() . 'buckarooAdminNotice',
                [
                    'type' => 'warning',
                    'message' => sprintf(
                        /* translators: %1$s: action name, %2$s: order number */
                        __('Cannot perform Klarna %1$s for order #%2$s', 'wc-buckaroo-bpe-gateway'),
                        $actionLabel,
                        $order->get_order_number()
                    ),
                ]
            );
        }
    }
}
