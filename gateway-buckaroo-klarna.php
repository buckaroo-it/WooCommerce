<?php

require_once __DIR__ . '/library/api/paymentmethods/klarna/klarna.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Klarna extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooKlarna::class;
	protected $type;
	protected $vattype;
	protected $klarnaPaymentFlowId = '';

	public function __construct() {
		$this->has_fields = true;
		$this->type       = 'klarna';
		$this->setIcon( '24x24/klarna.svg', 'svg/klarna.svg' );
		$this->setCountry();

		parent::__construct();
		$this->addRefundSupport();
	}
	/**  @inheritDoc */
	protected function setProperties() {
		parent::setProperties();
		$this->vattype = $this->get_option( 'vattype' );
	}
	public function getKlarnaSelector() {
		return str_replace( '_', '-', $this->id );
	}

	public function getKlarnaPaymentFlow() {
		return $this->klarnaPaymentFlowId;
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
	 * @access public
	 * @return void
	 */
	public function validate_fields() {
		$gender = $this->request( $this->getKlarnaSelector() . '-gender' );

		if ( ! in_array( $gender, array( 'male', 'female' ) ) ) {
			wc_add_notice( __( 'Unknown gender', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}

		if ( $this->request( 'ship_to_different_address' ) !== null ) {
			$countryCode = $this->request( 'shipping_country' ) == 'NL' ? $this->request( 'shipping_country' ) : '';
			$countryCode = $this->request( 'billing_country' ) == 'NL' ? $this->request( 'billing_country' ) : $countryCode;
			if ( ! empty( $countryCode )
				&& strtolower( $this->klarnaPaymentFlowId ) !== 'pay' ) {

				return wc_add_notice( __( 'Payment method is not supported for country ' . '(' . esc_html( $countryCode ) . ')', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}
		} elseif (
				( $this->request( 'billing_country' ) == 'NL' )
				&& strtolower( $this->klarnaPaymentFlowId ) !== 'pay'
				) {

				return wc_add_notice( __( 'Payment method is not supported for country ' . '(' . esc_html( $this->request( 'billing_country' ) ) . ')', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}
	}

	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable|void fn_buckaroo_process_response() or void
	 */
	public function process_payment( $order_id ) {
		$this->setOrderCapture( $order_id, 'Klarna' );

		$order = getWCOrder( $order_id );
		/** @var BuckarooKlarna */
		$klarna = $this->createDebitRequest( $order );
		$klarna->setType( $this->type );

		$klarna->invoiceId = (string) getUniqInvoiceId(
			preg_replace( '/\./', '-', $order->get_order_number() )
		);

		$order_details = new Buckaroo_Order_Details( $order );

		$klarna = $this->get_billing_info( $order_details, $klarna );
		$klarna = $this->get_shipping_info( $order_details, $klarna );
		$klarna = $this->handleThirdPartyShippings( $klarna, $order, $this->country );

		$klarna->CustomerIPAddress = getClientIpBuckaroo();
		$klarna->Accept            = 'TRUE';

		$klarna->returnUrl = $this->notify_url;

		$klarna->setPaymentFlow( $this->getKlarnaPaymentFlow() );
		$response = $klarna->paymentAction(
			$this->get_products_for_payment( $order_details )
		);
		return fn_buckaroo_process_response( $this, $response, $this->mode );
	}
	/**
	 * Get billing info for pay request
	 *
	 * @param Buckaroo_Order_Details $order_details
	 * @param BuckarooKlarna         $method
	 * @param string                 $birthdate
	 *
	 * @return BuckarooKlarna  $method
	 */
	protected function get_billing_info( $order_details, $method ) {
		/** @var BuckarooKlarna */
		$method                   = $this->set_billing( $method, $order_details );
		$method->BillingGender    = $this->request( $this->getKlarnaSelector() . '-gender' ) ?? 'Unknown';
		$method->BillingFirstName = $order_details->getBilling( 'first_name' );
		if ( empty( $method->BillingPhoneNumber ) ) {
			$method->BillingPhoneNumber = $this->request( $this->getKlarnaSelector() . '-phone' );
		}

		$billingCompany = $order_details->getBilling( 'company' );
		$method->setBillingCategory( $billingCompany );
		$method->setShippingCategory( $billingCompany );

		return $method;
	}
	/**
	 * Get shipping info for pay request
	 *
	 * @param Buckaroo_Order_Details $order_details
	 * @param BuckarooKlarna         $method
	 *
	 * @return BuckarooKlarna $method
	 */
	protected function get_shipping_info( $order_details, $method ) {
		$method->AddressesDiffer = 'FALSE';
		if ( $this->request( $this->getKlarnaSelector() . '-shipping-differ' ) ) {
			$method->AddressesDiffer = 'TRUE';

			$shippingCompany = $order_details->getShipping( 'company' );
			$method->setShippingCategory( $shippingCompany );

			/** @var BuckarooKlarna */
			$method                    = $this->set_shipping( $method, $order_details );
			$method->ShippingFirstName = $order_details->getShipping( 'first_name' );
		}
		return $method;
	}

	public function getProductImage( $product ) {
		$imgTag = $product->get_image();
		$doc    = new DOMDocument();
		$doc->loadHTML( $imgTag );
		$xpath    = new DOMXPath( $doc );
		$imageUrl = $xpath->evaluate( 'string(//img/@src)' );

		return $imageUrl;
	}

	public function get_product_data( Buckaroo_Order_Item $order_item ) {
		$product = parent::get_product_data( $order_item );

		if ( $order_item->get_type() === 'line_item' ) {

			$img = $this->getProductImage( $order_item->get_order_item()->get_product() );

			if ( ! empty( $img ) ) {
				$product['imgUrl'] = $img;
			}

			$product['url'] = get_permalink( $order_item->get_id() );
		}
		return $product;
	}

	/** @inheritDoc */
	public function init_form_fields() {
		parent::init_form_fields();

		if ( $this->id !== 'buckaroo_klarnapii' ) {
			$this->add_financial_warning_field();
		}
	}
}
