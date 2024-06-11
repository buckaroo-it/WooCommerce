<?php

require_once __DIR__ . '/library/api/paymentmethods/paybybank/paybybank.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_PayByBank extends WC_Gateway_Buckaroo {


	const PAYMENT_CLASS = BuckarooPayByBank::class;
	public function __construct() {
		$this->id           = 'buckaroo_paybybank';
		$this->title        = 'PayByBank';
		$this->has_fields   = true;
		$this->method_title = 'Buckaroo PayByBank';
		$this->setIcon( '24x24/paybybank.gif', 'svg/paybybank.gif' );

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
	 * Validate frontend fields.
	 *
	 * Validate payment fields on the frontend.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		$issuer = $this->request( 'buckaroo-paybybank-issuer' );

		if ( $issuer === null ) {
			wc_add_notice( __( '<strong>PayByBank </strong> is a required field.', 'wc-buckaroo-bpe-gateway' ), 'error' );
		} elseif ( ! in_array( $issuer, array_keys( BuckarooPayByBank::getIssuerList() ) ) ) {
			wc_add_notice( __( 'A valid PayByBank is required.', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}
		parent::validate_fields();
	}

	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable fn_buckaroo_process_response()
	 */
	function process_payment( $order_id ) {
		$order = getWCOrder( $order_id );
		/** @var BuckarooPayByBank */
		$payByBank         = $this->createDebitRequest( $order );
		$payByBank->issuer = $this->request( 'buckaroo-paybybank-issuer' );

		$response = $this->apply_filters_or_error( 'buckaroo_before_payment_request', $order, $payByBank );
		if ( $response ) {
			return $response;
		}

		$response = $payByBank->Pay();
		return fn_buckaroo_process_response( $this, $response );
	}

	public function init_form_fields() {
		parent::init_form_fields();

		$this->form_fields['displaymode'] = array(
			'title'   => __( 'Bank selection display', 'wc-buckaroo-bpe-gateway' ),
			'type'    => 'select',
			'options' => array(
				'radio'    => __( 'Radio button' ),
				'dropdown' => __( 'Dropdown' ),
			),
			'default' => 'radio',
		);

		unset( $this->form_fields['extrachargeamount'] ); // no fee for this payment method
	}
}
