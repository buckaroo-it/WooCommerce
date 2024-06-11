<?php

require_once __DIR__ . '/library/api/paymentmethods/giropay/giropay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Giropay extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooGiropay::class;
	public function __construct() {
		$this->id           = 'buckaroo_giropay';
		$this->title        = 'Giropay';
		$this->has_fields   = true;
		$this->method_title = 'Buckaroo Giropay';
		$this->setIcon( '24x24/giropay.gif', 'svg/giropay.svg' );

		parent::__construct();
		$this->addRefundSupport();
		apply_filters( 'buckaroo_init_payment_class', $this );
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
		return $this->processDefaultRefund( $order_id, $amount, $reason );
	}


	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable fn_buckaroo_process_response()
	 */
	public function process_payment( $order_id ) {
		$order = getWCOrder( $order_id );
		/** @var BuckarooGiropay */
		$giropay  = $this->createDebitRequest( $order );
		$response = $this->apply_filters_or_error( 'buckaroo_before_payment_request', $order, $giropay );
		if ( $response ) {
			return $response;
		}

		$response = $giropay->Pay();
		return fn_buckaroo_process_response( $this, $response );
	}
}
