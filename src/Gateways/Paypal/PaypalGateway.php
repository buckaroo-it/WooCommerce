<?php

namespace Buckaroo\Woocommerce\Gateways\Paypal;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressCart;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressOrder;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressShipping;
use WC_Order;

class PaypalGateway extends AbstractPaymentGateway {

	const PAYMENT_CLASS = PaypalProcessor::class;

	public $sellerprotection;

	protected $express_order_id = null;

	protected array $supportedCurrencies = array(
		'AUD',
		'BRL',
		'CAD',
		'CHF',
		'DKK',
		'EUR',
		'GBP',
		'HKD',
		'HUF',
		'ILS',
		'JPY',
		'MYR',
		'NOK',
		'NZD',
		'PHP',
		'PLN',
		'SEK',
		'SGD',
		'THB',
		'TRL',
		'TWD',
		'USD',
	);

	public function __construct() {
		$this->id           = 'buckaroo_paypal';
		$this->title        = 'PayPal';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo PayPal';
		$this->setIcon( 'svg/paypal.svg' );

		parent::__construct();
		$this->addRefundSupport();
	}

	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable fn_buckaroo_process_response()
	 */
	public function process_payment( $order_id ) {
		$this->setOrderContribution( new WC_Order( $order_id ) );
		return parent::process_payment( $order_id );
	}

	private function setOrderContribution( WC_Order $order ) {
		$prefix = (string) apply_filters(
			'wc_order_attribution_tracking_field_prefix',
			'wc_order_attribution_'
		);

		// Remove leading and trailing underscores.
		$prefix = trim( $prefix, '_' );

		// Ensure the prefix ends with _, and set the prefix.
		$prefix = "_{$prefix}_";

		$order->add_meta_data( $prefix . 'source_type', 'typein' );
		$order->add_meta_data( $prefix . 'utm_source', '(direct)' );
		$order->save();
	}

	/**
	 * Add fields to the form_fields() array, specific to this page.
	 *
	 * @access public
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		$this->form_fields['sellerprotection']    = array(
			'title'       => __( 'Seller Protection', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Sends customer address information to PayPal to enable PayPal seller protection.', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'TRUE'  => __( 'Enabled', 'wc-buckaroo-bpe-gateway' ),
				'FALSE' => __( 'Disabled', 'wc-buckaroo-bpe-gateway' ),
			),
			'default'     => 'TRUE',
		);
		$this->form_fields['express_merchant_id'] = array(
			'title'       => __( 'PayPal express merchant id', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'text',
			'description' => __( 'PayPal merchant id required for paypal express', 'wc-buckaroo-bpe-gateway' ),
		);
		$this->form_fields['express']             = array(
			'title'       => __( 'PayPal express', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'multiselect',
			'description' => __( 'Enable PayPal express for the following pages.', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				PaypalExpressController::LOCATION_NONE     => __( 'None', 'wc-buckaroo-bpe-gateway' ),
				PaypalExpressController::LOCATION_PRODUCT  => __( 'Product page', 'wc-buckaroo-bpe-gateway' ),
				PaypalExpressController::LOCATION_CART     => __( 'Cart page', 'wc-buckaroo-bpe-gateway' ),
				PaypalExpressController::LOCATION_CHECKOUT => __( 'Checkout page', 'wc-buckaroo-bpe-gateway' ),
			),
			'default'     => 'none',
		);
	}

	public function get_express_order_id() {
		return $this->express_order_id;
	}

	/**
	 * Set paypal express id
	 *
	 * @param string $express_order_id
	 *
	 * @return void
	 */
	public function set_express_order_id( $express_order_id ) {
		$this->express_order_id = $express_order_id;
	}

	/**
	 * Init class fields from settings
	 *
	 * @return void
	 */
	protected function setProperties() {
		parent::setProperties();
		$this->sellerprotection = $this->get_option( 'sellerprotection', 'TRUE' );
	}

	public function handleHooks() {
		new PaypalExpressController(
			new PaypalExpressShipping(),
			new PaypalExpressOrder(),
			new PaypalExpressCart()
		);
	}
}
