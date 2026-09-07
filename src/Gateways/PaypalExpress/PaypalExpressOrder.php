<?php

namespace Buckaroo\Woocommerce\Gateways\PaypalExpress;

use WC_Order;
use Throwable;

/**
 * PayPal express order class
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @version   GIT: 3.0.0
 *
 * @link      https://www.buckaroo.eu/
 */
class PaypalExpressOrder
{
    /**
     * Create order from cart and send it to buckaroo
     *
     * @param string $paypal_order_id Approved PayPal order id.
     * @return array
     *
     * @throws PaypalExpressException When WooCommerce cannot create the order.
     */
    public function create_and_send($paypal_order_id)
    {
        $payment_method_id = 'buckaroo_paypal';

        $customer = WC()->customer;
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        if (! isset($available_gateways[$payment_method_id])) {
            throw new PaypalExpressException('PayPal payment method is unavailable');
        }
        $payment_method = $available_gateways[$payment_method_id];
        $order = null;

        try {
            $order_id = WC()->checkout()->create_order(['payment_method' => $payment_method_id]);
            if (is_wp_error($order_id)) {
                throw new PaypalExpressException($order_id->get_error_message());
            }

            $order = new WC_Order($order_id);

            $order->set_payment_method($payment_method);
            $order->set_address($customer->get_billing());
            $order->set_address($customer->get_shipping(), 'shipping');

            $order->save();

            if (method_exists($payment_method, 'set_express_order_id')) {
                $payment_method->set_express_order_id($paypal_order_id);
            }

            return $payment_method->process_payment($order_id);
        } catch (Throwable $th) {
            if ($order instanceof WC_Order) {
                $order->update_status(
                    'failed',
                    __('PayPal Express payment processing failed.', 'wc-buckaroo-bpe-gateway')
                );
            }

            throw $th;
        }
    }
}
