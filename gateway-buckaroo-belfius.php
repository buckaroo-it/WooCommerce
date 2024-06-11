<?php

require_once __DIR__ . '/library/api/paymentmethods/belfius/belfius.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Belfius extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooBelfius::class;
	public function __construct() {
		$this->id           = 'buckaroo_belfius';
		$this->title        = 'Belfius';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Belfius';
		$this->setIcon( '24x24/belfius.png', 'svg/belfius.svg' );

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
		/** @var BuckarooBelfius */
		$belfius = $this->createDebitRequest( $order );

		$response = $belfius->Pay();

		return fn_buckaroo_process_response( $this, $response );
	}
}
