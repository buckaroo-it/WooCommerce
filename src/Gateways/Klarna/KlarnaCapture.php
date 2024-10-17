<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Order\OrderCapture;
use Buckaroo\Woocommerce\Order\OrderDetails;

/**
 * Core class for order capture form
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 3.3.0
 * @link      https://www.buckaroo.eu/
 */
class KlarnaCapture
{
    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_box_form'), 10, 2);
    }

    public function output($order)
    {
        $order_capture = new OrderCapture(
            new OrderDetails($order),
        );
        include 'capture-form.php';
    }

    public function add_meta_box_form($post_type, $order)
    {
        if ($post_type != 'woocommerce_page_wc-orders') {
            return;
        }

        if (
            (($order->get_payment_method() === 'buckaroo_klarnakp') &&
                get_post_meta($order->get_id(), 'buckaroo_is_reserved', true) === 'yes') ||
            (
                $order->get_payment_method() === 'buckaroo_afterpay' &&
                get_post_meta($order->get_id(), '_wc_order_authorized', true)
            )
        ) {
            add_meta_box(
                'buckaroo-order-klarnakp-capture',
                __('Capture & refund order', 'woocommerce'),
                array($this, 'output'),
                'woocommerce_page_wc-orders',
                'normal',
                'default'
            );
        }
    }

    /**
     * Get items available to capture by type
     *
     * @param OrderCapture $order_capture
     *
     * @return array
     */
    protected function get_available_to_capture_by_type(OrderCapture $order_capture)
    {
        $available_to_capture = $order_capture->get_available_to_capture();

        $available_to_capture_by_type = array();
        foreach ($available_to_capture as $item) {
            $item_type = $item->get_type();
            if (!isset($available_to_capture_by_type[$item_type])) {
                $available_to_capture_by_type[$item_type] = array();
            }
            $available_to_capture_by_type[$item_type][] = $item;
        }
        return $available_to_capture_by_type;
    }

    /**
     * Get refunded captures for $order_id
     *
     * @param integer $order_id
     *
     * @return array
     */
    protected function get_refunded_captures(int $order_id)
    {
        $refunded_captures = get_post_meta($order_id, 'buckaroo_captures_refunded', true);
        if (is_string($refunded_captures)) {
            $refunded_captures_decoded = json_decode($refunded_captures);
            if (is_array($refunded_captures_decoded)) {
                return $refunded_captures_decoded;
            }
        }
        return array();
    }
}