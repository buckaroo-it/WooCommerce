<?php

require_once __DIR__ . '/library/api/paymentmethods/sepadirectdebit/sepadirectdebit.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_SepaDirectDebit extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooSepaDirectDebit::class;
	public function __construct() {
		$this->id           = 'buckaroo_sepadirectdebit';
		$this->title        = 'SEPA Direct Debit';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo SEPA Direct Debit';
		$this->setIcon( '24x24/directdebit.png', 'svg/sepa-directdebit.svg' );

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
		return $this->processDefaultRefund(
			$order_id,
			$amount,
			$reason,
			false,
			function ( $request ) {
				$request->channel = BuckarooConfig::CHANNEL_BACKOFFICE;
			}
		);
	}

	/**
	 * Validate frontend fields.
	 *
	 * Validate payment fields on the frontend.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		$iban = $this->request( 'buckaroo-sepadirectdebit-iban' );
		if (
			$this->request( 'buckaroo-sepadirectdebit-accountname' ) === null ||
			$iban === null
			) {
			wc_add_notice( __( 'Please fill in all required fields', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}
		$GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
		if ( ! BuckarooSepaDirectDebit::isIBAN( $iban ) ) {
			wc_add_notice( __( 'Wrong IBAN number', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}

		parent::validate_fields();
	}

	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable fn_buckaroo_process_response()
	 */
	public function process_payment( $order_id ) {

		$order = getWCOrder( $order_id );
		/** @var BuckarooSepaDirectDebit */
		$sepadirectdebit = $this->createDebitRequest( $order );

		if ( ! $sepadirectdebit->isIBAN( $this->request( 'buckaroo-sepadirectdebit-iban' ) ) ) {
			wc_add_notice( __( 'Wrong IBAN number', 'wc-buckaroo-bpe-gateway' ), 'error' );
			return;
		}

		$sepadirectdebit->customeraccountname = $this->request( 'buckaroo-sepadirectdebit-accountname' );
		$sepadirectdebit->CustomerBIC         = $this->request( 'buckaroo-sepadirectdebit-bic' );
		$sepadirectdebit->CustomerIBAN        = $this->request( 'buckaroo-sepadirectdebit-iban' );

		$sepadirectdebit->returnUrl = $this->notify_url;
		$response                   = $sepadirectdebit->PayDirectDebit();
		return fn_buckaroo_process_response( $this, $response, $this->mode );
	}

	/**
	 * Check response data
	 *
	 * @access public
	 */
	public function response_handler() {
		fn_buckaroo_process_response( $this );
		exit;
	}
}
