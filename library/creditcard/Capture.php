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

class Buckaroo_Creditcard_Capture_Form
{
    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_box_form'), 10, 2);
    }

    public function output( $order )
    {
        // Convert WP_Post to WC_Order if necessary.
        if ( $order instanceof WP_Post ) {
            $order = wc_get_order( $order->ID );
        }

        $order_capture = new Buckaroo_Order_Capture(
            new Buckaroo_Order_Details( $order ),
            new Buckaroo_Http_Request()
        );

        include 'capture-form.php';
    }

    /**
     * Add meta box to order pages for credit card capture and refund functionality.
     *
     * @param string  $post_type     The current post type.
     * @param object  $post_or_order Post or order object.
     *
     * @return void
     */
    public function add_meta_box_form( $post_type, $post_or_order )
    {
        // Handle both HPOS and traditional post-based orders.
        $is_order_page = in_array( $post_type, array( 'woocommerce_page_wc-orders', 'shop_order' ), true );

        if ( ! $is_order_page ) {
            return;
        }

        // Get order object by looking for a post or order.
        $order = (
            $post_or_order instanceof WC_Order ||
            $post_or_order instanceof Automattic\WooCommerce\Admin\Overrides\Order
        )
        ? $post_or_order
        : wc_get_order( $post_or_order->ID );

        if (
            ! $order instanceof WC_Order &&
            ! $order instanceof Automattic\WooCommerce\Admin\Overrides\Order
        ) {
            return;
        }

        if (
            str_contains( $order->get_payment_method(), 'buckaroo_creditcard' )
            && $order->get_meta( '_wc_order_authorized' )
        ) {
            add_meta_box(
                'buckaroo-order-creditcard-capture',
                esc_html__( 'Capture & refund order', 'woocommerce' ),
                array( $this, 'output' ),
                $post_type,
                'normal',
                'default'
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