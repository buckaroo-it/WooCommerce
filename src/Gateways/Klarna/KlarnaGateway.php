<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class KlarnaGateway extends AbstractPaymentGateway {

	const PAYMENT_CLASS = KlarnaProcessor::class;
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

	/**
	 * Validate payment fields on the frontend.
	 *
	 * @access public
	 * @return void
	 */
	public function validate_fields() {
		$gender = $this->request->input( $this->getKlarnaSelector() . '-gender' );

		if ( ! in_array( $gender, array( 'male', 'female' ) ) ) {
			wc_add_notice( __( 'Unknown gender', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}

		if ( $this->request->input( 'ship_to_different_address' ) !== null ) {
			$countryCode = $this->request->input( 'shipping_country' ) == 'NL' ? $this->request->input( 'shipping_country' ) : '';
			$countryCode = $this->request->input( 'billing_country' ) == 'NL' ? $this->request->input( 'billing_country' ) : $countryCode;
			if ( ! empty( $countryCode )
				&& strtolower( $this->klarnaPaymentFlowId ) !== 'pay' ) {

				return wc_add_notice( __( 'Payment method is not supported for country ' . '(' . esc_html( $countryCode ) . ')', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}
		} elseif (
			( $this->request->input( 'billing_country' ) == 'NL' )
			&& strtolower( $this->klarnaPaymentFlowId ) !== 'pay'
		) {

			return wc_add_notice( __( 'Payment method is not supported for country ' . '(' . esc_html( $this->request->input( 'billing_country' ) ) . ')', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}
	}

	public function getKlarnaSelector() {
		return str_replace( '_', '-', $this->id );
	}


	/** @inheritDoc */
	public function init_form_fields() {
		parent::init_form_fields();

		if ( $this->id !== 'buckaroo_klarnapii' ) {
			$this->add_financial_warning_field();
		}
	}

	/**  @inheritDoc */
	protected function setProperties() {
		parent::setProperties();
		$this->vattype = $this->get_option( 'vattype' );
	}
}
