<?php


require_once __DIR__ . '/library/api/paymentmethods/klarna/klarnakp.php';

class WC_Gateway_Buckaroo_KlarnaKp extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooKlarnaKp::class;
	public $type;

	public function __construct() {
		$this->id           = 'buckaroo_klarnakp';
		$this->title        = 'Klarna: Pay later';
		$this->method_title = 'Buckaroo Klarna Pay later (authorize/capture)';
		$this->has_fields   = true;
		$this->type         = 'klarnakp';
		$this->setIcon( '24x24/klarna.svg', 'svg/klarna.svg' );
		$this->setCountry();
		parent::__construct();
		$this->addRefundSupport();
	}

	/**
	 * Process order
	 *
	 * @param integer $order_id
	 * @param integer $amount defaults to null
	 * @param string  $reason
	 * @return callable|string function or error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '', $transaction_id = null ) {
		return $this->processDefaultRefund(
			$order_id,
			$amount,
			$reason,
			false,
			function ( $request ) use ( $transaction_id ) {
				if ( $transaction_id != null ) {
					$request->OriginalTransactionKey = $transaction_id;
				}
			}
		);
	}


	public function cancel_reservation( WC_Order $order ) {
		/** @var BuckarooKlarnaKp */
		$klarna = $this->createDebitRequest( $order );

		$reservation_number = get_post_meta(
			$order->get_id(),
			'_buckaroo_klarnakp_reservation_number',
			true
		);

		if ( ! is_string( $reservation_number ) || strlen( $reservation_number ) === 0 ) {
			return $this->create_capture_error( __( 'Cannot perform capture, reservation_number not found' ) );
		}

		return fn_buckaroo_process_reservation_cancel(
			$klarna->cancel_reservation(
				$reservation_number
			),
			$order
		);

		// todo flash success/failed message
	}

	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable|void fn_buckaroo_process_response() or void
	 */
	public function process_payment( $order_id ) {

		update_post_meta( $order_id, '_wc_order_authorized', 'yes' );
		$this->setOrderCapture( $order_id, 'KlarnaKp' );

		$order = getWCOrder( $order_id );
		/** @var BuckarooKlarnaKp */
		$klarna = $this->createDebitRequest( $order );
		return fn_buckaroo_process_response(
			$this,
			$klarna->reserve(
				new Buckaroo_Order_Details( $order ),
				new Buckaroo_Http_Request()
			),
			$this->mode
		);
	}

	/**
	 * Send capture request
	 *
	 * @return void
	 */
	public function process_capture() {
		$order_id = $this->request( 'order_id' );

		if ( $order_id === null || ! is_numeric( $order_id ) ) {
			return $this->create_capture_error( __( 'A valid order number is required' ) );
		}

		$capture_amount = $this->request( 'capture_amount' );
		if ( $capture_amount === null || ! is_scalar( $capture_amount ) ) {
			return $this->create_capture_error( __( 'A valid capture amount is required' ) );
		}

		$order = getWCOrder( $order_id );
		/** @var BuckarooKlarnaKp */
		$klarna              = $this->createDebitRequest( $order );
		$klarna->amountDedit = str_replace( wc_get_price_decimal_separator(), '.', $capture_amount );
		$reservation_number  = get_post_meta(
			$order_id,
			'_buckaroo_klarnakp_reservation_number',
			true
		);

		if ( ! is_string( $reservation_number ) || strlen( $reservation_number ) === 0 ) {
			return $this->create_capture_error( __( 'Cannot perform capture, reservation_number not found' ) );
		}

		return fn_buckaroo_process_capture(
			$klarna->capture(
				new Buckaroo_Order_Capture(
					new Buckaroo_Order_Details( $order ),
					new Buckaroo_Http_Request()
				),
				$reservation_number
			),
			$order,
			$this->currency,
		);
	}
	/** @inheritDoc */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->add_financial_warning_field();
	}
}
