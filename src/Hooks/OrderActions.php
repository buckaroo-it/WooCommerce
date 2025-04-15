<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Core\PaymentGatewayRegistry;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Order\OrderCaptureRefund;
use WC_Cart;

class OrderActions {

	public function __construct() {
		add_action( 'edit_form_top', array( $this, 'handleOrderInTestMode' ) );
		add_action( 'wp_ajax_woocommerce_cart_calculate_fees', array( $this, 'calculate_order_fees' ) );
		add_action( 'wp_ajax_nopriv_woocommerce_cart_calculate_fees', array( $this, 'calculate_order_fees' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'calculate_order_fees' ) );
		add_action( 'buckaroo_cart_calculate_fees', array( $this, 'add_fee_to_cart' ), 10, 3 );

		add_action( 'wp_ajax_order_capture', array( $this, 'handleOrderCapture' ) );
		new OrderCaptureRefund();
	}

	public function handleOrderInTestMode( $post ): void {
		if ( $post->post_type === 'shop_order' ) {
			$order_in_test_mode = get_post_meta( $post->ID, '_buckaroo_order_in_test_mode', true );
			if ( $order_in_test_mode === '1' ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'The payment for this order was made in test mode' ) . '</p></div>';
			}
		}
	}

	public function handleOrderCapture(): void {
		if ( ! isset( $_POST['order_id'] ) ) {
			wp_send_json(
				array(
					'errors' => array(
						'error_capture' => array(
							array( esc_html__( 'A valid order number is required' ) ),
						),
					),
				)
			);
		}

		$paymentMethod = get_post_meta( (int) sanitize_text_field( $_POST['order_id'] ), '_wc_order_selected_payment_method', true );

		$gateway = ( new PaymentGatewayRegistry() )->newGatewayInstance( $paymentMethod );

		if ( $gateway->capturable && $gateway->canShowCaptureForm( $_POST['order_id'] ) ) {
			wp_send_json(
				$gateway->process_capture( $_POST['order_id'] )
			);
		}
		exit;
	}

	/**
	 * Calculates fees on items in shopping cart. (e.g. Taxes)
	 *
	 * @access public
	 * @return void
	 */
	public function calculate_order_fees() {
		if ( isset( $_POST['method'] ) ) {
			WC()->session->set( 'chosen_payment_method', $_POST['method'] );
		}

		$cart                  = WC()->cart;
		$available_gateways    = WC()->payment_gateways->get_available_payment_gateways();
		$chosen_payment_method = WC()->session->chosen_payment_method;

		// no payments available
		if ( empty( $available_gateways ) ) {
			return;
		}

		// no gateway found or not ours
		if (
			! isset( $available_gateways[ $chosen_payment_method ] ) ||
			! $available_gateways[ $chosen_payment_method ] instanceof AbstractPaymentGateway
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
	 * Check if extrachangeamount is valid
	 *
	 * @param string $value
	 *
	 * @return boolean
	 */
	public function is_extrachargeamount_valid( $value ) {
		return (bool) preg_match( '/^\d+(?:\.\d+)?%?$/', $value );
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
}
