<?php

require_once __DIR__ . '/library/api/paymentmethods/p24/p24.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_P24 extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooP24::class;
	public function __construct() {
		$this->id           = 'buckaroo_przelewy24';
		$this->title        = 'Przelewy24';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Przelewy24';
		$this->setIcon( '24x24/p24.png', 'svg/przelewy24.svg' );
		$this->migrateOldSettings( 'woocommerce_buckaroo_p24_settings' );

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
		/** @var BuckarooP24 */
		$p24           = $this->createDebitRequest( $order );
		$order_details = new Buckaroo_Order_Details( $order );
		$response      = $p24->Pay(
			array(
				'Customeremail'     => $order_details->getBilling( 'email' ),
				'CustomerFirstName' => $order_details->getBilling( 'first_name' ),
				'CustomerLastName'  => $order_details->getBilling( 'last_name' ),
			)
		);
		return fn_buckaroo_process_response( $this, $response );
	}
}
