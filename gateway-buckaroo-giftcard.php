<?php

require_once __DIR__ . '/library/api/paymentmethods/giftcard/giftcard.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Giftcard extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooGiftCard::class;
	public $giftcards;

	public function __construct() {
		$this->id           = 'buckaroo_giftcard';
		$this->title        = 'Giftcards';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Giftcards';
		$this->setIcon( '24x24/giftcard.gif', 'svg/giftcards.svg' );

		parent::__construct();
		// disabled refunds by request see BP-1337
		// $this->addRefundSupport();
	}
	/**  @inheritDoc */
	protected function setProperties() {
		parent::setProperties();
		$this->giftcards = $this->get_option( 'giftcards' );
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
		return $this->processDefaultRefund(
			$order_id,
			$amount,
			$reason,
			true,
			function ( $request ) {
				$request->version = 1;
			}
		);
	}

	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable fn_buckaroo_process_response()
	 */
	public function process_payment( $order_id ) {
		$order = getWCOrder( $order_id );
		/** @var BuckarooGiftCard */
		$giftcard = $this->createDebitRequest( $order );

		$customVars = array();

		$customVars['servicesSelectableByClient'] = $this->giftcards;

		$response = $giftcard->Pay( $customVars );
		return fn_buckaroo_process_response( $this, $response );
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
}
