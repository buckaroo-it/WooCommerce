<?php

namespace Buckaroo\Woocommerce\Gateways\PayByBank;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class PayByBankGateway extends AbstractPaymentGateway {

	const PAYMENT_CLASS = PayByBankProcessor::class;

	public function __construct() {
		$this->id           = 'buckaroo_paybybank';
		$this->title        = 'PayByBank';
		$this->has_fields   = true;
		$this->method_title = 'Buckaroo PayByBank';
		$this->setIcon( 'svg/paybybank.svg' );

		parent::__construct();
		$this->addRefundSupport();
		apply_filters( 'buckaroo_init_payment_class', $this );
	}

	/**
	 * Validate frontend fields.
	 *
	 * Validate payment fields on the frontend.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		$issuer = $this->request->input( 'buckaroo-paybybank-issuer' );

		if ( $issuer === null ) {
			wc_add_notice( __( '<strong>PayByBank </strong> is a required field.', 'wc-buckaroo-bpe-gateway' ), 'error' );
		} elseif ( ! in_array( $issuer, array_keys( PayByBankProcessor::getIssuerList() ) ) ) {
			wc_add_notice( __( 'A valid PayByBank is required.', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}
		parent::validate_fields();
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
