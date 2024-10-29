<?php

/**
 * Core class for order fee
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */

class Buckaroo_Order_Fee {

	public function __construct() {
        add_action('wp_ajax_nopriv_woocommerce_cart_calculate_fees', array($this, 'calculate_order_fees'));
        add_action('wp_ajax_woocommerce_cart_calculate_fees', array($this, 'calculate_order_fees'));
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_order_fees' ) );
		add_action(
			'buckaroo_cart_calculate_fees',
			array( $this, 'add_fee_to_cart' ),
			10,
			3
		);
	}

	/**
	 * Calculates fees on items in shopping cart. (e.g. Taxes)
	 *
	 * @access public
	 * @return void
	 */
    public function calculate_order_fees()
    {
        if (isset($_POST['method'])) {
            WC()->session->set('chosen_payment_method', $_POST['method']);
        }

        $cart = WC()->cart;
		$available_gateways    = WC()->payment_gateways->get_available_payment_gateways();
		$chosen_payment_method = WC()->session->chosen_payment_method;

		// no payments available
		if ( empty( $available_gateways ) ) {
			return;
		}

		// no gateway found or not ours
		if (
			! isset( $available_gateways[ $chosen_payment_method ] ) ||
			! $available_gateways[ $chosen_payment_method ] instanceof WC_Gateway_Buckaroo
		) {
			return;
		}

		$gateway = $available_gateways[ $chosen_payment_method ];

		$this->add_fee_to_cart(
			$cart,
			$gateway->get_option( 'extrachargeamount', 0 ),
			$gateway->get_option( 'feetax', '' )
		);
	}
	/**
	 * Add fee to cart
	 *
	 * @param WC_Cart $cart
	 * @param string  $gateway_extrachargeamount
	 * @param string  $gateway_feetax
	 *
	 * @return void
	 */
	public function add_fee_to_cart( $cart, $gateway_extrachargeamount, $gateway_feetax ) {
		// no fee available
		if (
			! is_scalar( $gateway_extrachargeamount ) ||
			empty( $gateway_extrachargeamount ) ||
			(float) $gateway_extrachargeamount === 0
		) {
			return;
		}

		// not a valid value
		if ( ! $this->is_extrachargeamount_valid( $gateway_extrachargeamount ) ) {
			return;
		}

		$subtotal            = $cart->get_cart_contents_total();
		$is_percentage       = strpos( $gateway_extrachargeamount, '%' ) !== false;
		$extra_charge_amount = (float) str_replace( '%', '', $gateway_extrachargeamount );

		// percentage not 0
		if ( $extra_charge_amount === 0 ) {
			return;
		}

		if ( $is_percentage ) {
			$extra_charge_amount = number_format( $subtotal * $extra_charge_amount / 100, 2 );
		}

		$feedName = __( 'Payment fee', 'wc-buckaroo-bpe-gateway' );
		$feedId   = sanitize_title( $feedName );

		$fee = $this->get_fee( $cart, $feedId );

		if ( $fee === null ) {
			$cart->add_fee(
				$feedName,
				$extra_charge_amount,
				true,
				$gateway_feetax
			);
		} else {
			$fee->amount = $extra_charge_amount;
		}
	}
	/**
	 * Get fee from cart by id
	 *
	 * @param WC_Cart $cart
	 * @param string  $id
	 *
	 * @return array|null
	 */
	protected function get_fee( $cart, $id ) {
		$fees = $cart->get_fees();
		foreach ( $fees as $id => $fee ) {
			if ( $fee->id === $id ) {
				return $fee;
			}
		}
	}
	/**
	 * Check if extrachangeamount is valid
	 *
	 * @param string $value
	 *
	 * @return boolean
	 */
	public function is_extrachargeamount_valid( $value ) {
		return (bool) preg_match( '/^\d+(?:\.\d+)?%?$/', $value );
	}
}
