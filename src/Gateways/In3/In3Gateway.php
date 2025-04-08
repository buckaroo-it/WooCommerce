<?php

namespace Buckaroo\Woocommerce\Gateways\In3;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\PayAction;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Traits\HasDateValidation;

class In3Gateway extends AbstractPaymentGateway {

	use HasDateValidation;

	const PAYMENT_CLASS       = In3Processor::class;
	public const VERSION_FLAG = 'buckaroo_in3_version';
	public const VERSION3     = 'v3';
	public const VERSION2     = 'v2';
	public const IN3_V2_TITLE = 'In3';
	public const IN3_V3_TITLE = 'iDEAL In3';

	public $type;
	public $vattype;
	public $country;

	public function __construct() {
		$this->id           = 'buckaroo_in3';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo In3';

		$this->title = $this->getTitleForVersion();

		$this->setCountry();

		parent::__construct();

		$this->setIcons();
		$this->addRefundSupport();
	}

	private function getTitleForVersion() {
		return $this->get_option( 'api_version' ) === self::VERSION2 ? self::IN3_V2_TITLE : self::IN3_V3_TITLE;
	}

	/**
	 * Set icons based on version
	 *
	 * @return void
	 */
	private function setIcons() {
		if (
			$this->get_option( 'api_version' ) === 'v2'
		) {
			$this->setIcon( 'svg/in3.svg' );
			return;
		}
		$this->setIcon( 'svg/in3-ideal.svg' );
	}

	public function getServiceCode( ?AbstractProcessor $processor = null ) {
		return $this->get_option( 'api_version' ) === self::VERSION2 ? 'in3Old' : 'in3';
	}

	/**
	 * Validate payment fields on the frontend.
	 *
	 * @access public
	 * @return void
	 */
	public function validate_fields() {
		$birthdate = $this->request->input( 'buckaroo-in3-birthdate' );

		$country = $this->request->input( 'billing_country' );
		if ( $country === null ) {
			$country = $this->country;
		}

		if ( $country === 'NL' && ! $this->validateDate( $birthdate, 'd-m-Y' ) ) {
			wc_add_notice( __( 'You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}

		if (
			$this->request->input( 'billing_phone' ) === null &&
			$this->request->input( 'buckaroo-in3-phone' ) === null
		) {
			wc_add_notice(
				sprintf(
					__( 'Please fill in a phone number for %s. This is required in order to use this payment method.', 'wc-buckaroo-bpe-gateway' ),
					$this->getTitleForVersion()
				),
				'error'
			);
		}

		parent::validate_fields();
	}


	public function process_payment( $order_id ) {
		if ( $this->get_option( 'api_version' ) === 'v2' ) {
			return ( new PayAction( $this->getV2Payload( (int) $order_id ), $order_id ) )->process();
		}
		return parent::process_payment( $order_id );
	}

	private function getV2Payload( int $order_id ) {
		$order = Helper::findOrder( $order_id );

		return new In3V2Processor(
			$this,
			$order_details = new OrderDetails( $order ),
			new OrderArticles( $order_details, $this )
		);
	}

	/**
	 * Add fields to the form_fields() array, specific to this page.
	 *
	 * @access public
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		$this->add_financial_warning_field();
		$this->form_fields['api_version'] = array(
			'title'       => __( 'Api version', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Chose the api version for this payment method.', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				self::VERSION3 => __( 'V3 (iDEAL In3)' ),
				self::VERSION2 => __( 'V2 (Capayabel/In3)' ),
			),
			'default'     => self::VERSION3,
		);
	}


	/**  @inheritDoc */
	protected function setProperties() {
		parent::setProperties();
		$this->type    = 'in3';
		$this->vattype = $this->get_option( 'vattype' );
	}
}
