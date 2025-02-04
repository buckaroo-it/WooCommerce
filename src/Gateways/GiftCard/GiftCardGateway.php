<?php

namespace Buckaroo\Woocommerce\Gateways\GiftCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class GiftCardGateway extends AbstractPaymentGateway {

	const PAYMENT_CLASS = GiftCardProcessor::class;
	public $giftcards;

	public function __construct() {
		$this->id           = 'buckaroo_giftcard';
		$this->title        = 'Giftcards';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Giftcards';
		$this->setIcon( 'svg/giftcards.svg' );

		parent::__construct();
		// disabled refunds by request see BP-1337
		// $this->addRefundSupport();
	}

	/**
	 * Add fields to the form_fields() array, specific to this page.
	 *
	 * @access public
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		$this->form_fields['giftcards'] = array(
			'title'       => __( 'List of authorized giftcards', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'text',
			'description' => __( 'Giftcards must be comma separated', 'wc-buckaroo-bpe-gateway' ),
			'default'     => 'vvvgiftcard,boekenbon,ideal,bancontact,boekenvoordeel,fashioncheque,yourgift,webshopgiftcard',
		);
	}

	/**  @inheritDoc */
	protected function setProperties() {
		parent::setProperties();
		$this->giftcards = $this->get_option( 'giftcards' );
	}
}
