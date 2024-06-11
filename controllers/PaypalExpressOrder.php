<?php

/**
 * PayPal express order class
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 3.0.0
 * @link      https://www.buckaroo.eu/
 */

class Buckaroo_Paypal_Express_Order {

	/**
	 * Create order from cart and send it to buckaroo
	 *
	 * @return WC_Order $order
	 */
	public function create_and_send( $paypal_order_id ) {
		$payment_method_id = 'buckaroo_paypal';

		$customer = WC()->customer;
		$order_id = WC()->checkout()->create_order( array() );

		$order = new WC_Order( $order_id );

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$payment_method     = $available_gateways[ $payment_method_id ];

		$order->set_payment_method( $payment_method );
		$order->set_address( $customer->get_billing() );
		$order->set_address( $customer->get_shipping(), 'shipping' );

		$order = $this->set_fee_on_order(
			$order,
			WC()->session->get( 'buckaroo_paypal_fee' )
		);

		$order->save();

		if ( method_exists( $payment_method, 'set_express_order_id' ) ) {
			$payment_method->set_express_order_id( $paypal_order_id );
		}

		return $payment_method->process_payment( $order_id );
	}
	/**
	 * Set fees on order
	 *
	 * @param Wc_Order        $order
	 * @param stdClass | null $fee
	 *
	 * @return Wc_Order $order
	 */
	protected function set_fee_on_order( $order, $fee ) {
		if ( $fee === null ) {
			return $order;
		}
		// Get a new instance of the WC_Order_Item_Fee Object
		$item_fee = new WC_Order_Item_Fee();

		$item_fee->set_name( $fee->name );
		$item_fee->set_amount( $fee->amount );
		$item_fee->set_tax_class( $fee->tax_class );
		$item_fee->set_tax_status( $fee->taxable );
		$item_fee->set_total( $fee->total );

		// Calculating Fee taxes
		$item_fee->calculate_taxes( $order->get_address( 'shipping' ) );

		// Add Fee item to the order
		$order->add_item( $item_fee );

		return $order;
	}
}
