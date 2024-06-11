<?php

require_once __DIR__ . '/library/api/paymentmethods/payconiq/payconiq.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Payconiq extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooPayconiq::class;
	public function __construct() {
		$this->id           = 'buckaroo_payconiq';
		$this->title        = 'Payconiq';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Payconiq';
		$this->setIcon( '24x24/payconiq.png', 'svg/payconiq.svg' );

		parent::__construct();
		$this->addRefundSupport();
	}

	/**
	 * Can the order be refunded
	 *
	 * @param integer $order_id
	 * @param integer $amount defaults to null
	 * @param string  $reason
	 * @return callable|string function or error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return $this->processDefaultRefund( $order_id, $amount, $reason, true );
	}

	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable fn_buckaroo_process_response()
	 */
	public function process_payment( $order_id ) {
		$order = getWCOrder( $order_id );
		/** @var BuckarooPayconiq */
		$payconiq = $this->createDebitRequest( $order );
		$response = $payconiq->Pay();
		return fn_buckaroo_process_response( $this, $response );
	}

	/**
	 * Check response data
	 *
	 * @access public
	 */
	public function response_handler() {
		$GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
		$result               = fn_buckaroo_process_response( $this );
		$order_id             = isset( $_GET['order_id'] ) && is_scalar( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : false;
		if ( ! is_null( $result ) ) {
			wp_safe_redirect( $result['redirect'] );
		} elseif ( $order_id ) {
			// if we are here we are the redirect from the "cancel payment" link
			// So we have to cancel the payment.
			$order = new WC_Order( $order_id );
			if ( isset( $order ) ) {
				$order->update_status( 'cancelled', __( '890', 'wc-buckaroo-bpe-gateway' ) );
				wc_add_notice(
					__(
						'Payment cancelled. Please try again or choose another payment method.',
						'wc-buckaroo-bpe-gateway'
					),
					'error'
				);
				wp_safe_redirect( $order->get_cancel_order_url() );
			}
		}
		exit;
	}
}
