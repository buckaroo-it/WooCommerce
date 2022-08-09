<?php

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

class Buckaroo_Capture_Form
{

    public function __construct()
    {
        add_action('add_meta_boxes_shop_order', [$this, 'add_meta_box_form']);
    }

    public function output(WP_POST $post)
    {

        $order = wc_get_order($post->ID);

        $order_capture = new Buckaroo_Order_Capture(
            new Buckaroo_Order_Details($order),
            new Buckaroo_Http_Request()
        );
        include 'capture-form.php';
    }

    public function add_meta_box_form($post)
    {
        $order = wc_get_order($post->ID);
        if (
            $order->get_payment_method() === 'buckaroo_klarnakp' &&
            get_post_meta($order->get_id(), 'bukaroo_is_reserved', true) === 'yes'
        ) {
            add_meta_box(
                'buckaroo-order-klarnakp-capture',
                __('Capture & refund order', 'woocommerce'),
                [$this, 'output'],
                'shop_order',
                'normal',
                'low'
            );
        }
    }

    /**
     * Get items available to capture by type
     *
     * @param Buckaroo_Order_Capture $order_capture
     *
     * @return array
     */
    protected function get_available_to_capture_by_type(Buckaroo_Order_Capture $order_capture)
    {
        $available_to_capture = $order_capture->get_available_to_capture();

        $available_to_capture_by_type = [];
        foreach ($available_to_capture as $item) {
            $item_type = $item->get_type();
            if (!isset($available_to_capture_by_type[$item_type])) {
                $available_to_capture_by_type[$item_type] = [];
            }
            $available_to_capture_by_type[$item_type][] = $item;
        }
        return  $available_to_capture_by_type;
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
        return [];
    }
}
