<?php

/**
 * Core class for klarnakp cancel reservation
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

class Buckaroo_Cancel_Reservation {

	public function __construct() {
		add_filter( 'woocommerce_order_actions', array( $this, 'add_cancel_option' ), 10, 2 );
		add_action( 'woocommerce_order_action_buckaroo_klarnakp_cancel_reservation', array( $this, 'cancel_reservation' ), 10, 1 );
	}

	/**
	 * Hook into order actions, add cancel reservation option for klarnakp
	 *
	 * @param array         $actions
	 * @param WC_Order|null $order
	 *
	 * @return array
	 */
	public function add_cancel_option( $actions, $order = null ) {
		global $theorder;
		if ( $order == null ) {
			if ( ! ( $theorder instanceof WC_Order ) ) {
				return $actions;
			}
			$order = $theorder;
		}

		if (
			$order->get_payment_method() === 'buckaroo_klarnakp' &&
			get_post_meta( $order->get_id(), 'buckaroo_is_reserved', true ) === 'yes'
		) {
			$actions['buckaroo_klarnakp_cancel_reservation'] = esc_html__( 'Cancel reservation', 'woocommerce' );
		}
		return $actions;
	}


	/**
	 * Cancel reservation and redirect back with flash message
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	public function cancel_reservation( $order ) {

		$gateway = new WC_Gateway_Buckaroo_KlarnaKp();
		if ( isset( $gateway ) ) {
			$gateway->cancel_reservation( $order );
		}
		wp_redirect(
			admin_url( "post.php?post={$order->get_id()}&action=edit" )
		);
	}
}
