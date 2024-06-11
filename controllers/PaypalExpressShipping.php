<?php

/**
 * PayPal express shipping class
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

class Buckaroo_Paypal_Express_Shipping {

	/**
	 * Create new cart if button was pressed in product page
	 *
	 * @return void
	 */
	public function create_cart_for_product_page() {
		$order_data = $this->get_order_data();
		$cart       = WC()->cart;

		$cart->empty_cart();

		$cart->add_to_cart(
			$this->get_product_id( $order_data ),
			$this->get_required_value( $order_data, 'quantity' )
		);
		$this->apply_paypal_fee( $cart );
	}
	/**
	 * Get cart total brakdown by items, shipping & tax
	 *
	 * @param WC_Cart $cart
	 *
	 * @return array
	 */
	public function get_cart_total_breakdown() {

		$address_data = $this->get_address_data();

		WC()->customer->set_shipping_location(
			$this->get_required_value( $address_data, 'country_code' ),
			$this->get_required_value( $address_data, 'state' ),
			$this->get_required_value( $address_data, 'postal_code' ),
			$this->get_required_value( $address_data, 'city' )
		);

		$cart = WC()->cart;
		$this->apply_paypal_fee( $cart );

		WC()->cart->calculate_shipping();

		$total      = $cart->get_total( false );
		$tax_total  = $cart->get_total_tax();
		$shipping   = $cart->get_shipping_total();
		$item_total = $total - $tax_total - $shipping;
		$currency   = get_woocommerce_currency();

		return array(
			'breakdown'     => array(
				'item_total' => array(
					'currency_code' => $currency,
					'value'         => $this->number_format( $item_total ),
				),
				'shipping'   => array(
					'currency_code' => $currency,
					'value'         => $this->number_format( $shipping ),
				),
				'tax_total'  => array(
					'currency_code' => $currency,
					'value'         => $this->number_format( $tax_total ),
				),
			),
			'currency_code' => $currency,
			'value'         => $this->number_format( $total ),
		);
	}
	/**
	 * Format numbers to 2 decimals
	 *
	 * @param float|string $value
	 *
	 * @return float
	 */
	public function number_format( $value ) {
		return number_format( $value, 2 );
	}
	/**
	 * Apply payment fee on cart
	 *
	 * @return void
	 */
	protected function apply_paypal_fee( $cart ) {
		WC()->session->set( 'chosen_payment_method', 'buckaroo_paypal' );
		$cart->calculate_totals();

		do_action(
			'buckaroo_cart_calculate_fees',
			$cart,
			$this->settings['extrachargeamount'] ?? 0,
			$this->settings['feetax'] ?? ''
		);

		$this->store_fee_for_order( $cart );
	}
	/**
	 * Store the fee result in session to use in order
	 *
	 * @param WC_Cart $cart
	 *
	 * @return void
	 */
	protected function store_fee_for_order( $cart ) {
		$fee = null;

		$fees = $cart->get_fees();
		if ( count( $fees ) ) {
			$fee = array_pop( $fees );
		}
		WC()->session->set( 'buckaroo_paypal_fee', $fee );
	}
	/**
	 * Get product id for simple and variable product
	 *
	 * @param array $order_data
	 *
	 * @return void
	 */
	protected function get_product_id( $order_data ) {
		$variation_id = $this->get_value( $order_data, 'variation_id' );

		if ( ! empty( $variation_id ) || $variation_id != 0 ) {
			return $variation_id;
		}
		return $this->get_required_value( $order_data, 'add-to-cart' );
	}
	/**
	 * Get required values or throw exception
	 *
	 * @param array  $data
	 * @param string $key
	 *
	 * @return mixed
	 * @throws Exception
	 */
	protected function get_required_value( $data, $key ) {
		if ( ! isset( $data[ $key ] ) ) {
			throw new Buckaroo_Paypal_Express_Exception( 'Field is required ' . $key );
		}
		return $this->get_value( $data, $key );
	}
	/**
	 * Get value from array with a default
	 *
	 * @param array  $data
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	protected function get_value( $data, $key, $default = null ) {
		return $data[ $key ] ?? $default;
	}
	/**
	 * Get address data from frontend
	 *
	 * @return array
	 */
	protected function get_address_data() {
		if ( ! isset( $_POST['shipping_data'] ) || ! isset( $_POST['shipping_data']['shipping_address'] ) ) {
			throw new Buckaroo_Paypal_Express_Exception( 'Shipping address is required' );
		}
		return wc_clean( $_POST['shipping_data']['shipping_address'] );
	}
	/**
	 * Get formatted order data from frontend
	 *
	 * @return array
	 */
	protected function get_order_data() {
		if ( ! isset( $_POST['order_data'] ) || count( $_POST['order_data'] ) === 0 ) {
			throw new Buckaroo_Paypal_Express_Exception( 'Empty cart, cannot create order' );
		}
		$request = array();
		foreach ( wc_clean( $_POST['order_data'] ) as $data ) {
			if ( ! isset( $data['name'] ) || ! isset( $data['value'] ) ) {
				throw new Buckaroo_Paypal_Express_Exception( 'Invalid data format' );
			}
			$request[ $data['name'] ] = $data['value'];
		}
		return $request;
	}
}
