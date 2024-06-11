<?php

/**
 * PayPal express cart class
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

class Buckaroo_Paypal_Express_Cart {

	/**
	 * Cart content for restoring cart in product page
	 *
	 * @var array
	 */
	protected $cart = null;
	/**
	 * Set cart data to be restored
	 *
	 * @return void
	 */
	public function store_current() {
		$cart = WC()->cart;

		$this->cart = array(
			'cart_contents'         => $cart->get_cart_contents(),
			'applied_coupons'       => $cart->get_applied_coupons(),
			'removed_cart_contents' => $cart->get_removed_cart_contents(),
		);
	}
	/**
	 * Restore cart
	 *
	 * @return void
	 */
	public function restore() {
		if ( $this->cart !== null ) {
			$cart = WC()->cart;
			$cart->empty_cart();
			$cart->set_cart_contents( $this->cart['cart_contents'] );
			$cart->set_applied_coupons( $this->cart['applied_coupons'] );
			$cart->set_removed_cart_contents( $this->cart['removed_cart_contents'] );
			$cart->calculate_totals();
		}
	}
}
