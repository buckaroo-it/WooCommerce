<?php

namespace Buckaroo\Woocommerce\Gateways\Transfer;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class TransferGateway extends AbstractPaymentGateway {

	const PAYMENT_CLASS = TransferProcessor::class;
	public $datedue;
	public $sendemail;
	public $showpayproc;

	protected array $supportedCurrencies = array( 'EUR', 'GBP', 'PLN' );

	public function __construct() {
		$this->id           = 'buckaroo_transfer';
		$this->title        = 'Bank Transfer';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Bank Transfer';
		$this->setIcon( 'svg/sepa-credittransfer.svg' );

		parent::__construct();
		$this->addRefundSupport();
	}

	/**
	 * Print thank you description to the screen.
	 *
	 * @access public
	 */
	public function thankyou_description() {
		if ( ! session_id() ) {
			@session_start();
		}

		print wp_kses(
			$_SESSION['buckaroo_response'],
			array(
				'table' => array( 'class' => true ),
				'td'    => array(
					'class' => true,
					'id'    => true,
				),
				'tr'    => array(),
				'br'    => array(),
				'b'     => array(),
			)
		);
	}

	/**
	 * Add fields to the form_fields() array, specific to this page.
	 *
	 * @access public
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		$this->form_fields['datedue']     = array(
			'title'       => __( 'Number of days till order expire', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'text',
			'description' => __( 'Number of days to the date that the order should be payed.', 'wc-buckaroo-bpe-gateway' ),
			'default'     => '14',
		);
		$this->form_fields['sendmail']    = array(
			'title'       => __( 'Send email', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Buckaroo sends an email to the customer with the payment procedures.', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'TRUE'  => __( 'Yes', 'wc-buckaroo-bpe-gateway' ),
				'FALSE' => __( 'No', 'wc-buckaroo-bpe-gateway' ),
			),
			'default'     => 'FALSE',
		);
		$this->form_fields['showpayproc'] = array(
			'title'       => __( 'Show payment procedures', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Show payment procedures on the thank you page after payment confirmation.', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'TRUE'  => __( 'Yes', 'wc-buckaroo-bpe-gateway' ),
				'FALSE' => __( 'No', 'wc-buckaroo-bpe-gateway' ),
			),
			'default'     => 'FALSE',
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function setProperties() {
		parent::setProperties();
		$this->datedue     = $this->get_option( 'datedue' );
		$this->sendemail   = $this->get_option( 'sendmail' );
		$this->showpayproc = $this->get_option( 'showpayproc' ) == 'TRUE';
	}
}
