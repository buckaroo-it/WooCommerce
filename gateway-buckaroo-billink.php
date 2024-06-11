<?php

require_once __DIR__ . '/library/api/paymentmethods/billink/billink.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Billink extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooBillink::class;
	public $type;
	public $b2b;
	public $vattype;
	public $country;

	public function __construct() {

		$this->id           = 'buckaroo_billink';
		$this->title        = 'Billink';
		$this->has_fields   = true;
		$this->method_title = 'Buckaroo Billink';
		$this->setIcon( '24x24/billink.png', 'svg/billink.svg' );
		$this->setCountry();

		parent::__construct();
		$this->addRefundSupport();
	}
	/**  @inheritDoc */
	protected function setProperties() {
		parent::setProperties();
		$this->type    = 'billink';
		$this->vattype = $this->get_option( 'vattype' );
	}
	/**
	 * Validate fields
	 *
	 * @return void;
	 */
	public function validate_fields() {
		if ( $this->request( 'billing_company' ) !== null ) {
			if ( $this->request( 'buckaroo-billink-company-coc-registration' ) === null ) {
				wc_add_notice( __( 'Please enter correct COC (KvK) number', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}
		} else {
			if ( ! $this->validateDate( $this->request( 'buckaroo-billink-birthdate' ), 'd-m-Y' )
			) {
				wc_add_notice( __( 'Please enter correct birth date', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}
			if ( ! in_array( $this->request( 'buckaroo-billink-gender' ), array( 'Male', 'Female', 'Unknown' ) ) ) {
				wc_add_notice( __( 'Unknown gender', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}
		}

		if ( $this->request( 'buckaroo-billink-accept' ) === null ) {
			wc_add_notice( __( 'Please accept license agreements', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}
		parent::validate_fields();
	}
	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable|void fn_buckaroo_process_response() or void
	 */
	public function process_payment( $order_id ) {
		$this->setOrderCapture( $order_id, 'Billink' );

		$order = getWCOrder( $order_id );
		/** @var BuckarooBillink */
		$billink            = $this->createDebitRequest( $order );
		$billink->invoiceId = (string) getUniqInvoiceId(
			preg_replace( '/\./', '-', $order->get_order_number() )
		);

		$order_details = new Buckaroo_Order_Details( $order );
		$billink->B2B  = $order_details->getBilling( 'company' );

		$billink->setCategory( ! empty( $billink->B2B ) ? 'B2B' : 'B2C' );
		$billink->setCompany( ! empty( $billink->B2B ) ? $billink->B2B : '' );

		if ( $billink->B2B ) {
			$billink->CompanyCOCRegistration = $this->request( 'buckaroo-billink-company-coc-registration' );
			$var_number                      = $this->request( 'buckaroo-billink-VatNumber' );
			if ( $var_number !== null ) {
				$billink->VatNumber = $var_number;
			}
		} else {
			$billink->BillingBirthDate = $this->request( 'buckaroo-billink-birthdate' );

		}

		$billink = $this->getBillingInfo( $order_details, $billink );
		$billink = $this->getShippingInfo( $order_details, $billink );

		$billink->CustomerIPAddress = getClientIpBuckaroo();
		$billink->Accept            = 'TRUE';
		$billink->returnUrl         = $this->notify_url;

		$response = $billink->PayOrAuthorizeBillink(
			$this->get_products_for_payment( $order_details ),
			'Pay'
		);
		return fn_buckaroo_process_response( $this, $response, $this->mode );
	}
	/**
	 * Get billing info for pay request
	 *
	 * @param Buckaroo_Order_Details $order_details
	 * @param BuckarooBillink        $method
	 * @param string                 $birthdate
	 *
	 * @return BuckarooBillink  $method
	 */
	protected function getBillingInfo( $order_details, $method ) {
		/** @var BuckarooBillink */
		$method                = $this->set_billing( $method, $order_details );
		$method->BillingGender = $this->request( 'buckaroo-billink-gender' );
		$method->setBillingFirstName(
			$order_details->getBilling( 'first_name' )
		);
		$method->BillingInitials = $order_details->getInitials(
			$method->getBillingFirstName()
		);

		return $method;
	}
	/**
	 * Get shipping info for pay request
	 *
	 * @param Buckaroo_Order_Details $order_details
	 * @param BuckarooBillink        $method
	 *
	 * @return BuckarooBillink $method
	 */
	protected function getShippingInfo( $order_details, $method ) {
		$method->AddressesDiffer = 'FALSE';
		if ( $this->request( 'buckaroo-billink-shipping-differ' ) !== null ) {
			$method->AddressesDiffer = 'TRUE';

			/** @var BuckarooBillink */
			$method                    = $this->set_shipping( $method, $order_details );
			$method->ShippingFirstName = $order_details->getShipping( 'first_name' );
			$method->ShippingInitials  = $order_details->getInitials(
				$method->ShippingFirstName
			);
		}
		return $method;
	}

	/**
	 * Check that a date is valid.
	 *
	 * @param String $date A date expressed as a string
	 * @param String $format The format of the date
	 * @return Object Datetime
	 * @return Boolean Format correct returns True, else returns false
	 */
	public function validateDate( $date, $format = 'Y-m-d H:i:s' ) {
		$d = DateTime::createFromFormat( $format, $date );
		return $d && ( $d->format( $format ) == $date );
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

	/** @inheritDoc */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->add_financial_warning_field();
	}
}
