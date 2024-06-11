<?php


require_once __DIR__ . '/library/api/paymentmethods/in3/in3.php';
require_once __DIR__ . '/library/api/paymentmethods/in3/in3v2.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_In3 extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS       = BuckarooIn3::class;
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

		$this->set_icons();
		$this->addRefundSupport();
	}

	private function getTitleForVersion() {
		return $this->get_option( 'api_version' ) === self::VERSION2 ? self::IN3_V2_TITLE : self::IN3_V3_TITLE;
	}
	/**  @inheritDoc */
	protected function setProperties() {
		parent::setProperties();
		$this->type    = 'in3';
		$this->vattype = $this->get_option( 'vattype' );
	}
	/**
	 * Can the order be refunded
	 *
	 * @param integer $order_id
	 * @param integer $amount defaults to null
	 * @param string  $reason
	 * @return callable|string function or error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '', $line_item_qtys = null, $line_item_totals = null, $line_item_tax_totals = null, $originalTransactionKey = null ) {
		return $this->processDefaultRefund( $order_id, $amount, $reason );
	}

	/**
	 * Validate payment fields on the frontend.
	 *
	 * @access public
	 * @return void
	 */
	public function validate_fields() {
		$birthdate = $this->request( 'buckaroo-in3-birthdate' );

		$country = $this->request( 'billing_country' );
		if ( $country === null ) {
			$country = $this->country;
		}

		if ( $country === 'NL' && ! $this->validateDate( $birthdate, 'd-m-Y' ) ) {
			wc_add_notice( __( 'You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}

		if (
			$this->request( 'billing_phone' ) === null &&
			$this->request( 'buckaroo-in3-phone' ) === null
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

	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable|void fn_buckaroo_process_response() or void
	 */
	public function process_payment( $order_id ) {
		$this->setOrderCapture( $order_id, 'In3' );

		$order = getWCOrder( $order_id );

		$version = $this->get_option( 'api_version' );
		update_post_meta(
			$order->get_id(),
			self::VERSION_FLAG,
			$version
		);

		if ( $version === self::VERSION2 ) {
			return $this->pay_with_v2( $order );
		}

		$order_details = new Buckaroo_Order_Details( $order );
		/** @var BuckarooIn3 */
		$in3 = $this->createDebitRequest( $order );
		$in3->setData(
			$order_details,
			$this->get_products_for_payment( $order_details ),
			new Buckaroo_Http_Request()
		);

		$response = $in3->pay();
		return fn_buckaroo_process_response( $this, $response );
	}

	/**
	 * Set icons based on version
	 *
	 * @return void
	 */
	private function set_icons() {
		if (
			$this->get_option( 'api_version' ) === 'v2'
		) {
			$this->setIcon( 'svg/in3.svg', 'svg/in3.svg' );
			return;
		}
		$this->setIcon( 'svg/in3-ideal.svg', 'svg/in3-ideal.svg' );
	}

	/**
	 * Pay with old version
	 *
	 * @param WC_Order $order
	 *
	 * @return void
	 */
	private function pay_with_v2( $order ) {

		/** @var BuckarooIn3v2 */
		$in3 = $this->createDebitRequest( $order );

		$order_details = new Buckaroo_Order_Details( $order );

		$birthdate = date( 'Y-m-d', strtotime( $this->request( 'buckaroo-in3-birthdate' ) ) );

		$in3 = $this->get_billing_info( $order_details, $in3, $birthdate );

		$response = $in3->PayIn3(
			$this->get_products_for_payment( $order_details ),
			'PayInInstallments'
		);
		return fn_buckaroo_process_response( $this, $response, $this->mode );
	}

	/**
	 * Get billing info for pay request
	 *
	 * @param Buckaroo_Order_Details $order_details
	 * @param BuckarooIn3            $method
	 * @param string                 $birthdate
	 *
	 * @return BuckarooIn3v2  $method
	 */
	protected function get_billing_info( $order_details, $method, $birthdate ) {
		/** @var BuckarooIn3v2 */
		$method                   = $this->set_billing( $method, $order_details );
		$method->BillingInitials  = $order_details->getInitials(
			$order_details->getBilling( 'first_name' )
		);
		$method->BillingBirthDate = date( 'Y-m-d', strtotime( $birthdate ) );

		$phone = $this->request( 'buckaroo-in3-phone' );

		if ( is_scalar( $phone ) && trim( strlen( (string) $phone ) ) > 0 ) {
			$method->BillingPhoneNumber = $phone;
		}

		return $method;
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


	/**
	 * Create custom logo selector
	 *
	 * @param mixed $key
	 * @param mixed $data
	 *
	 * @return void
	 */
	public function generate_in3_logo_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);

		$data  = wp_parse_args( $data, $defaults );
		$value = $this->get_option( $key );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php
				echo $this->get_tooltip_html( $data ); // WPCS: XSS ok.
				?>
				</label>
			</th>
			<td>
				<fieldset>
					<div class="bk-in3-logo-wrap">
						<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
						<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
							<label class="bk-in3-logo" for="bk-logo-<?php echo esc_attr( $option_key ); ?>">
								<input type="radio" id="bk-logo-<?php echo esc_attr( $option_key ); ?>" name="<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $option_key ); ?>" <?php checked( (string) $option_key, esc_attr( $value ) ); ?>>
								<img src="<?php echo esc_url( $option_value ); ?>" / alt="<?php echo esc_attr( $option_key ); ?>">
							</label>
						<?php endforeach; ?>
					</div>
					<?php
					echo $this->get_description_html( $data ); // WPCS: XSS ok.
					?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Select the correct class in order to do the request
	 *
	 * @param WC_Order $order
	 * @param boolean  $isRefund
	 *
	 * @return void
	 */
	protected function get_payment_class( $order, $isRefund = false ) {
		if ( $isRefund ) {
			$orderIn3Version = get_post_meta(
				$order->get_id(),
				self::VERSION_FLAG,
				true
			);

			if ( $orderIn3Version === self::VERSION3 ) {
				return BuckarooIn3::class;
			}
			return BuckarooIn3v2::class;
		}

		if (
			$this->get_option( 'api_version' ) === self::VERSION2
		) {
			return BuckarooIn3v2::class;
		}

		return BuckarooIn3::class;
	}
}
