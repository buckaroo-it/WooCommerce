<?php

namespace Buckaroo\Woocommerce\Gateways\PaypalExpress;

use Buckaroo\Woocommerce\Gateways\ExpressProductCart;
use WC_Order;
use WC_Shipping_Rate;
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
     * @param WC_Shipping_Rate|null $shipping_rate Shipping rate approved in PayPal, null when the order needs no shipping.
     * @param string|null $approved_total Total approved in PayPal, formatted with two decimals.
     * @param array $approved_contact Billing and shipping fields from the approved PayPal order.
     * @return array
     *
     * @throws PaypalExpressException When WooCommerce cannot create the order or its total differs from the approved total.
     */
    public function create_and_send(
        $paypal_order_id,
        ?WC_Shipping_Rate $shipping_rate = null,
        ?string $approved_total = null,
        array $approved_contact = []
    ) {
        $payment_method_id = 'buckaroo_paypal';

        $customer = WC()->customer;
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        if (! isset($available_gateways[$payment_method_id])) {
            throw new PaypalExpressException('PayPal payment method is unavailable');
        }
        $payment_method = $available_gateways[$payment_method_id];
        $order = null;
        $failure_note = __('PayPal Express payment processing failed.', 'wc-buckaroo-bpe-gateway');

        $order_data = ['payment_method' => $payment_method_id];
        $billing_email = self::billing_email($approved_contact);
        if ($billing_email !== '') {
            // WooCommerce holds the applied coupons only when it gets the billing e-mail (BTI-1874).
            $order_data['billing_email'] = $billing_email;
        }

        try {
            $order_id = WC()->checkout()->create_order($order_data);
            if (is_wp_error($order_id)) {
                throw new PaypalExpressException($order_id->get_error_message());
            }

            $order = new WC_Order($order_id);

            $order->set_payment_method($payment_method);
            $order->set_address($customer->get_billing());
            $order->set_address($customer->get_shipping(), 'shipping');

            // The shipping line follows the rate approved in PayPal, not the session's chosen method.
            $order->remove_order_items('shipping');
            if ($shipping_rate !== null) {
                ExpressProductCart::addShippingLine($order, $shipping_rate);
            }
            $order->update_taxes();
            $order->calculate_totals(false);

            $order->save();

            if (
                $approved_total !== null
                && number_format((float) $order->get_total('edit'), 2, '.', '') !== $approved_total
            ) {
                $failure_note = __(
                    'The order total does not match the amount approved in PayPal. The payment was not started.',
                    'wc-buckaroo-bpe-gateway'
                );
                throw new PaypalExpressException($failure_note);
            }

            // Set after the totals, which stay those of the approved quote; the push updates the addresses again.
            foreach (['billing', 'shipping'] as $type) {
                if (! empty($approved_contact[$type]) && is_array($approved_contact[$type])) {
                    $order->set_address($approved_contact[$type], $type);
                }
            }
            if (! empty($approved_contact)) {
                $order->save();
            }

            if (method_exists($payment_method, 'set_express_order_id')) {
                $payment_method->set_express_order_id($paypal_order_id);
            }

            return $payment_method->process_payment($order_id);
        } catch (Throwable $th) {
            if ($order instanceof WC_Order) {
                $order->update_status('failed', $failure_note);
            }

            throw $th;
        }
    }

    /**
     * The e-mail the order is placed with: the one approved in PayPal,
     * otherwise the customer's billing e-mail.
     *
     * @param array $approved_contact Billing and shipping fields from the approved PayPal order.
     */
    public static function billing_email(array $approved_contact): string
    {
        $email = $approved_contact['billing']['email'] ?? '';
        if ((! is_string($email) || $email === '') && WC()->customer) {
            $email = WC()->customer->get_billing_email();
        }

        return is_string($email) ? $email : '';
    }
}
