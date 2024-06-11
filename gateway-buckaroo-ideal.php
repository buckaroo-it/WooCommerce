<?php

require_once __DIR__ . '/library/api/paymentmethods/ideal/ideal.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Ideal extends WC_Gateway_Buckaroo {


	const PAYMENT_CLASS = BuckarooIDeal::class;
	public function __construct() {
		$this->id           = 'buckaroo_ideal';
		$this->title        = 'iDEAL';
		$this->has_fields   = true;
		$this->method_title = 'Buckaroo iDEAL';
		$this->setIcon( '24x24/ideal.png', 'svg/ideal.svg' );

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
		if ( $this->canShowIssuers() ) {
			$issuer = $this->request( 'buckaroo-ideal-issuer' );

			if ( $issuer === null ) {
				wc_add_notice( __( '<strong>iDEAL bank </strong> is a required field.', 'wc-buckaroo-bpe-gateway' ), 'error' );
			} elseif ( ! in_array( $issuer, array_keys( BuckarooIDeal::getIssuerList() ) ) ) {
				wc_add_notice( __( 'A valid iDEAL bank is required.', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}
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
		/** @var BuckarooIDeal */
		$ideal = $this->createDebitRequest( $order );

		if ( $this->canShowIssuers() ) {
			$ideal->issuer = $this->request( 'buckaroo-ideal-issuer' );
		}

		$response = $this->apply_filters_or_error( 'buckaroo_before_payment_request', $order, $ideal );
		if ( $response ) {
			return $response;
		}

		$response = $ideal->Pay();
		return fn_buckaroo_process_response( $this, $response );
	}

	public function canShowIssuers() {
		return $this->get_option( 'show_issuers' ) !== 'no';
	}

	/**
	 * Add fields to the form_fields() array, specific to this page.
	 *
	 * @access public
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		$this->form_fields['show_issuers'] = array(
			'title'       => __( 'Show Issuer Selection in the Checkout', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'When the "NO" option is selected, the issuer selection for iDEAL will not be displayed in the checkout. Instead, customers will be redirected to a separate page where they can choose their iDEAL issuer (i.e., their bank). On the other hand, selecting the "Yes" option will display the issuer selection directly in the checkout. It\'s important to note that enabling this option will incur additional costs from Buckaroo, estimated at around €0.002 for each transaction. For precise cost details, please reach out to <a href="mailto:wecare@buckaroo.nl">Buckaroo</a> directly.', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'yes' => __( 'Yes' ),
				'no'  => __( 'No' ),
			),
			'default'     => 'yes',
		);
	}
}
