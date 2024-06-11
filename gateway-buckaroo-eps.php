<?php

require_once __DIR__ . '/library/api/paymentmethods/eps/eps.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_EPS extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooEPS::class;
	public function __construct() {
		$this->id           = 'buckaroo_eps';
		$this->title        = 'EPS';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo EPS';
		$this->setIcon( '24x24/eps.png', 'svg/eps.svg' );

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
		/** @var BuckarooEPS */
		$eps        = $this->createDebitRequest( $order );
		$customVars = array();

		$response = $eps->Pay( $customVars );
		return fn_buckaroo_process_response( $this, $response );
	}
}
