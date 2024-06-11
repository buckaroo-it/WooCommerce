<?php

require_once __DIR__ . '/library/api/paymentmethods/multibanco/multibanco.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Multibanco extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooMultibanco::class;
	public function __construct() {
		$this->id           = 'buckaroo_multibanco';
		$this->title        = 'Multibanco';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Multibanco';
		$this->setIcon( 'svg/multibanco.svg', 'svg/multibanco.svg' );

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
		/** @var BuckarooMultibanco */
		$request = $this->createDebitRequest( $order );
		return fn_buckaroo_process_response( $this, $request->Pay() );
	}
}
