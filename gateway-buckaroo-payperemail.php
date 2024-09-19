<?php

require_once __DIR__ . '/library/api/paymentmethods/payperemail/payperemail.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_PayPerEmail extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooPayPerEmail::class;
	public $paymentmethodppe;
	public $frontendVisible;
	public function __construct() {
		$this->id           = 'buckaroo_payperemail';
		$this->title        = 'PayPerEmail';
		$this->has_fields   = true;
		$this->method_title = 'Buckaroo PayPerEmail';
		$this->setIcon( 'payperemail.png', 'svg/payperemail.svg' );

		parent::__construct();
	}
	/**  @inheritDoc */
	protected function setProperties() {
		parent::setProperties();
		$this->paymentmethodppe = $this->get_option( 'paymentmethodppe', '' );
		$this->frontendVisible  = $this->get_option( 'show_PayPerEmail_frontend', '' );
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
	 * Validate payment fields on the frontend.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		if ( $this->isVisibleOnFrontend() ) {
			if ( $this->request( 'buckaroo-payperemail-gender' ) === null ) {
				wc_add_notice( __( 'Please select gender', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}

			$gender = $this->request( 'buckaroo-payperemail-gender' );

			if ( ! in_array( $gender, array( '0', '1', '2', '9' ) ) ) {
				wc_add_notice( __( 'Unknown gender', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}

			if ( $this->request( 'buckaroo-payperemail-firstname' ) === null ) {
				wc_add_notice( __( 'Please enter firstname', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}

			if ( $this->request( 'buckaroo-payperemail-lastname' ) === null ) {
				wc_add_notice( __( 'Please enter lastname', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}
			if ( $this->request( 'buckaroo-payperemail-email' ) === null ) {
				wc_add_notice( __( 'Please enter email', 'wc-buckaroo-bpe-gateway' ), 'error' );
			} elseif ( ! is_email( $this->request( 'buckaroo-payperemail-email' ) ) ) {
				wc_add_notice( __( 'Please enter valid email', 'wc-buckaroo-bpe-gateway' ), 'error' );
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
	public function process_payment( $order_id, $paylink = false ) {
		$order = getWCOrder( $order_id );
		/** @var BuckarooPayPerEmail */
		$payperemail   = $this->createDebitRequest( $order );
		$order_details = new Buckaroo_Order_Details( $order );

		$customVars = array(
			'CustomerGender'    => 0,
			'CustomerFirstName' => $order_details->getBilling( 'first_name' ),
			'CustomerLastName'  => $order_details->getBilling( 'last_name' ),
			'Customeremail'     => $order_details->getBilling( 'email' ),
		);

		if ( $this->isVisibleOnFrontend() && ! is_admin() ) {
			$customVars['CustomerGender']    = $this->request( 'buckaroo-payperemail-gender' );
			$customVars['CustomerFirstName'] = $this->request( 'buckaroo-payperemail-firstname' );
			$customVars['CustomerLastName']  = $this->request( 'buckaroo-payperemail-lastname' );
			$customVars['Customeremail']     = $this->request( 'buckaroo-payperemail-email' );
		}

		if ( ! empty( $this->paymentmethodppe ) ) {
			$customVars['PaymentMethodsAllowed'] = implode( ',', $this->paymentmethodppe );
		}

		if ( $paylink ) {
			$customVars['merchantSendsEmail'] = 'true';
		}

		if ( ! empty( $this->settings['expirationDate'] ) ) {
			$customVars['ExpirationDate'] = date( 'Y-m-d', time() + $this->settings['expirationDate'] * 86400 );
		}

		$response = $payperemail->PaymentInvitation( $customVars );
		return fn_buckaroo_process_response( $this, $response );
	}

	public function isVisibleOnFrontend() {
		if ( ! empty( $this->frontendVisible ) && strtolower( $this->frontendVisible ) === 'yes' ) {
			return true;
		}

		return false;
	}
	/**
	 * Add fields to the form_fields() array, specific to this page.
	 *
	 * @access public
	 */
	public function init_form_fields() {

		parent::init_form_fields();

		$this->form_fields['show_PayPerEmail_frontend'] = array(
			'title'       => __( 'Show on Checkout page', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'checkbox',
			'description' => __( 'Show PayPerEmail on Checkout page', 'wc-buckaroo-bpe-gateway' ),
			'default'     => 'no',
		);

		$this->form_fields['show_PayLink'] = array(
			'title'       => __( 'Show PayLink', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Show PayLink in admin order actions', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'TRUE'  => __( 'Show', 'wc-buckaroo-bpe-gateway' ),
				'FALSE' => __( 'Hide', 'wc-buckaroo-bpe-gateway' ),
			),
			'default'     => 'TRUE',
		);

		$this->form_fields['show_PayPerEmail'] = array(
			'title'       => __( 'Show PayPerEmail', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Show PayPerEmail in admin order actions', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'TRUE'  => __( 'Show', 'wc-buckaroo-bpe-gateway' ),
				'FALSE' => __( 'Hide', 'wc-buckaroo-bpe-gateway' ),
			),
			'default'     => 'TRUE',
		);

		$this->form_fields['expirationDate'] = array(
			'title'       => __( 'Due date (in days)', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'text',
			'description' => __( 'The expiration date for the paylink.', 'wc-buckaroo-bpe-gateway' ),
			'default'     => '',
		);

		$this->form_fields['paymentmethodppe'] = array(
			'title'       => __( 'Allowed methods', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'multiselect',
			'options'     => array(
				'amex'               => 'American Express',
				'cartebancaire'      => 'Carte Bancaire',
				'cartebleuevisa'     => 'Carte Bleue',
				'dankort'            => 'Dankort',
				'mastercard'         => 'Mastercard',
				'postepay'           => 'PostePay',
				'visa'               => 'Visa',
				'visaelectron'       => 'Visa Electron',
				'vpay'               => 'Vpay',
				'maestro'            => 'Maestro',
				'bancontactmrcash'   => 'Bancontact / Mr Cash',
				'transfer'           => 'Bank Transfer',
				'giftcard'           => 'Giftcards',
				'ideal'              => 'iDEAL',
				'paypal'             => 'PayPal',
				'sepadirectdebit'    => 'SEPA Direct Debit',
				'sofortueberweisung' => 'Sofort Banking',
				'belfius'            => 'Belfius',
				'Przelewy24'         => 'P24',
			),
			'description' => __( 'Select which methods appear to the customer', 'wc-buckaroo-bpe-gateway' ),
			'default'     => array( 'amex', 'cartebancaire', 'cartebleuevisa', 'dankort', 'mastercard', 'postepay', 'visa', 'visaelectron', 'vpay', 'maestro', 'bancontactmrcash', 'transfer', 'giftcard', 'ideal', 'paypal', 'sepadirectdebit', 'sofortueberweisung', 'belfius', 'Przelewy24' ),
		);
	}
}
