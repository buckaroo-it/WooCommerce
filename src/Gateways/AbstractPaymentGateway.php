<?php

namespace Buckaroo\Woocommerce\Gateways;

use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaKpGateway;
use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureAction;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\PayAction;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\RefundAction;
use Buckaroo\Woocommerce\PaymentProcessors\ReturnProcessor;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\Request;
use Buckaroo\Woocommerce\Services\SessionHandler;
use Exception;
use WC_Order;
use WC_Payment_Gateway;
use WC_Tax;
use WP_Error;

class AbstractPaymentGateway extends WC_Payment_Gateway {

	const PAYMENT_CLASS = null;
	const REFUND_CLASS  = null;

	public $notify_url;
	public $minvalue;
	public $maxvalue;
	public $showpayproc = false;
	public $currency;
	public $mode;
	public $country;
	public $channel;
	protected Request $request;
	protected array $supportedCurrencies = array( 'EUR' );
	public bool $capturable              = false;

	public function __construct() {
		// Load the form fields
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();

		$this->setProperties();

		if ( version_compare( PHP_VERSION, '7.3.0' ) >= 0 ) {
			add_filter( 'woocommerce_session_handler', array( $this, 'woocommerce_session_handler' ) );
		}

		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_filter( 'woocommerce_order_button_html', array( $this, 'replace_order_button_html' ) );
		}

		// [JM] Compatibility with WC3.6+
		add_action( 'woocommerce_checkout_process', array( $this, 'action_woocommerce_checkout_process' ) );

		$this->addGatewayHooks( 'WC_Gateway_' . ucfirst( $this->id ) );
		$this->request = new Request();
	}

	/**
	 * Initialize Gateway Settings Form Fields
	 *
	 * @access public
	 */
	public function init_form_fields() {
		$charset        = strtolower( ini_get( 'default_charset' ) );
		$addDescription = '';
		if ( $charset != 'utf-8' ) {
			$addDescription = '<fieldset style="border: 1px solid #ffac0e; padding: 10px;"><legend><b style="color: #ffac0e">' . __( 'Warning', 'wc-buckaroo-bpe-gateway' ) . '!</b></legend>' . __( 'default_charset is not set.<br>This might cause a problems on receiving push message.<br>Please set default_charset="UTF-8" in your php.ini and add AddDefaultCharset UTF-8 to .htaccess file.', 'wc-buckaroo-bpe-gateway' ) . '</fieldset>';
		}

		$this->title       = ( ! isset( $this->title ) ? '' : $this->title );
		$this->id          = ( ! isset( $this->id ) ? '' : $this->id );
		$this->form_fields = array(
			'buckaroo_notice'   => array(
				'type' => 'buckaroo_notice',
			),
			'enabled'           => array(
				'title'       => __( 'Enable/Disable', 'wc-buckaroo-bpe-gateway' ),
				'label'       => sprintf( __( 'Enable %s Payment Method', 'wc-buckaroo-bpe-gateway' ), ( isset( $this->method_title ) ? $this->method_title : '' ) ),
				'type'        => 'checkbox',
				'description' => $addDescription,
				'default'     => 'no',
			),
			'mode'              => array(
				'title'       => __( 'Transaction mode', 'wc-buckaroo-bpe-gateway' ),
				'type'        => 'select',
				'description' => __( 'Transaction mode used for processing orders', 'wc-buckaroo-bpe-gateway' ),
				'options'     => array(
					'live' => 'Live',
					'test' => 'Test',
				),
				'default'     => 'test',
			),
			'title'             => array(
				'title'       => __( 'Front-end label', 'wc-buckaroo-bpe-gateway' ),
				'type'        => 'text',
				'description' => __(
					'Determines how the payment method is named in the checkout.',
					'wc-buckaroo-bpe-gateway'
				),
				'default'     => __( $this->title, 'wc-buckaroo-bpe-gateway' ),
			),
			'description'       => array(
				'title'       => __( 'Description', 'wc-buckaroo-bpe-gateway' ),
				'type'        => 'textarea',
				'description' => __(
					'This controls the description which the user sees during checkout.',
					'wc-buckaroo-bpe-gateway'
				),
				'default'     => $this->getPaymentDescription(),
			),
			'extrachargeamount' => array(
				'title'       => __( 'Payment fee', 'wc-buckaroo-bpe-gateway' ),
				'type'        => 'text',
				'description' => __( 'Specify static (e.g. 1.50) or percentage amount (e.g. 1%). Decimals must be separated by a dot (.)', 'wc-buckaroo-bpe-gateway' ),
				'default'     => '0',
			),
			'minvalue'          => array(
				'title'             => __( 'Minimum order amount allowed', 'wc-buckaroo-bpe-gateway' ),
				'type'              => 'number',
				'custom_attributes' => array( 'step' => '0.01' ),
				'description'       => __( 'Specify minimum order amount allowed to show the current method. Zero or empty value means no rule will be applied.', 'wc-buckaroo-bpe-gateway' ),
				'default'           => '0',
			),
			'maxvalue'          => array(
				'title'             => __( 'Maximum order amount allowed', 'wc-buckaroo-bpe-gateway' ),
				'type'              => 'number',
				'custom_attributes' => array( 'step' => '0.01' ),
				'description'       => __( 'Specify maximum order amount allowed to show the current method. Zero or empty value means no rule will be applied.', 'wc-buckaroo-bpe-gateway' ),
				'default'           => '0',
			),
		);
	}

	/**
	 * Get checkout payment description field
	 *
	 * @return string
	 */
	public function getPaymentDescription() {
		$desc = $this->get_option( 'description', '' );
		if ( strlen( $desc ) === 0 ) {
			$desc = sprintf( __( 'Pay with %s', 'wc-buckaroo-bpe-gateway' ), $this->title );
		}
		return $desc;
	}

	public function init_settings() {
		parent::init_settings();

		// merge with master settings
		$options = get_option( 'woocommerce_buckaroo_mastersettings_settings', null );
		if ( is_array( $options ) ) {
			unset(
				$options['enabled'],
				$options['title'],
				$options['mode'],
				$options['description'],
			);
			$this->settings = array_replace( $this->settings, $options );
		}
	}

	/**
	 * Init class fields from settings
	 *
	 * @return void
	 */
	protected function setProperties() {
		$GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
		$this->setTitle();
		$this->description = $this->getPaymentDescription();
		$this->currency    = get_woocommerce_currency();
		$this->mode        = $this->get_option( 'mode' );
		$this->minvalue    = $this->get_option( 'minvalue', 0 );
		$this->maxvalue    = $this->get_option( 'maxvalue', 0 );
	}

	/**
	 * Set title with fee
	 *
	 * @return void
	 */
	public function setTitle() {
		$feeText       = '';
		$fee           = $this->get_option( 'extrachargeamount', 0 );
		$is_percentage = strpos( $fee, '%' ) !== false;
		$fee           = floatval( str_replace( '%', '', $fee ) );

		if ( $fee != 0 ) {
			if ( $is_percentage ) {
				$fee = str_replace(
					'&nbsp;',
					'',
					wc_price(
						$fee,
						array(
							'currency' => 'null',
						)
					)
				) . '%';
			} else {
				$fee = wc_price( $fee + $this->getPaymentFeeVat( $fee ) );
			}

			$feeText = ' (+ ' . $fee . ')';
		}

		$this->title = strip_tags( $this->get_option( 'title', $this->title ?? '' ) . $feeText );
	}

	/**
	 * Get Payment fee VAT
	 */
	public function getPaymentFeeVat( $amount ) {
		// Allow this to run only on checkout page
		if ( ! is_checkout() ) {
			return 0;
		}

		// Get selected tax rate
		$taxRate = $this->get_option( 'feetax', '' );

		$vatIncluded = $this->get_option( 'paymentfeevat', 'on' );

		$location = array(
			'country'  => WC()->customer->get_shipping_country() ? WC()->customer->get_shipping_country() : WC()->customer->get_billing_country(),
			'state'    => WC()->customer->get_shipping_state() ? WC()->customer->get_shipping_state() : WC()->customer->get_billing_state(),
			'city'     => WC()->customer->get_shipping_city() ? WC()->customer->get_shipping_city() : WC()->customer->get_billing_city(),
			'postcode' => WC()->customer->get_shipping_postcode() ? WC()->customer->get_shipping_postcode() : WC()->customer->get_billing_postcode(),
		);

		// Loop through tax classes
		foreach ( wc_get_product_tax_class_options() as $tax_class => $tax_class_label ) {

			$tax_rates = WC_Tax::find_rates( array_merge( $location, array( 'tax_class' => $tax_class ) ) );

			if ( ! empty( $tax_rates ) && $tax_class == $taxRate && $vatIncluded == 'off' ) {
				return WC_Tax::get_tax_total( WC_Tax::calc_exclusive_tax( $amount, $tax_rates ) );
			}
		}
		return 0;
	}

	/**
	 * Add the gateway hooks
	 *
	 * @param string $class Gateway Class name
	 *
	 * @return void
	 */
	protected function addGatewayHooks( $class ) {
		$this->showpayproc = isset( $this->settings['showpayproc'] ) && $this->settings['showpayproc'] == 'TRUE';

		$this->notify_url = home_url( '/' );
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {

			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' )
			);

			add_action(
				'woocommerce_api_' . strtolower( wc_clean( $class ) ),
				array( $this, 'response_handler' )
			);

			if ( $this->showpayproc ) {
				add_action(
					'woocommerce_thankyou_' . $this->id,
					array( $this, 'thankyou_description' )
				);
			}

			$this->notify_url = add_query_arg( 'wc-api', $class, $this->notify_url );
		}
	}

	public function woocommerce_session_handler() {
		return SessionHandler::class;
	}

	/**
	 * Get gateway icon
	 *
	 * @return string
	 */
	public function getIcon() {
		return $this->icon;
	}

	public function thankyou_description() {
		// not implemented
	}

	public function replace_order_button_html( $button ) {
		if ( ! IdinProcessor::checkCurrentUserIsVerified() ) {
			return '';
		}
		return $button;
	}

	public function action_woocommerce_checkout_process() {
		if ( version_compare( WC()->version, '3.6', '>=' ) ) {
			Helper::resetOrder();
		}
	}

	public function generate_buckaroo_notice_html( $key, $data ) {
		// Add Warning, if currency set in Buckaroo is unsupported
		if ( isset( $_GET['section'] ) && $this->id == sanitize_text_field( $_GET['section'] ) && ! $this->checkCurrencySupported() && is_admin() ) {
			$message = esc_html__( 'This payment method is not supported for the selected currency ', 'wc-buckaroo-bpe-gateway' ) . '(' . esc_html( get_woocommerce_currency() ) . ')';
			return printf( '<div class="error notice"><p>%s</p></div>', $message );
		}
	}

	/**
	 * Check response data
	 *
	 * @access public
	 */
	public function response_handler() {
		$GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
		$result               = ( new ReturnProcessor( $_POST ?? $_GET ) )->handle( $this );

		if ( ! is_null( $result ) ) {
			wp_safe_redirect( $result['redirect'] );
		} else {
			wp_safe_redirect( $this->get_failed_url() );
		}
		exit;
	}

	public function get_failed_url() {
		$thanks_page_id = wc_get_page_id( 'checkout' );
		if ( $thanks_page_id ) :
			$return_url = get_permalink( $thanks_page_id );
		else :
			$return_url = home_url();
		endif;
		if ( is_ssl() || get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ) {
			$return_url = str_replace( 'http:', 'https:', $return_url );
		}

		return apply_filters( 'woocommerce_get_return_url', $return_url );
	}

	/**
	 * Payment form on checkout page
	 *
	 * @return void
	 */
	public function payment_fields() {
		$this->renderTemplate();
	}

	/**
	 * Render the gateway template
	 *
	 * @return void
	 */
	protected function renderTemplate( $id = null ) {
		if ( is_null( $id ) ) {
			$id = $this->id;
		}

		$name = str_replace( 'buckaroo_', '', $id );

		do_action( 'buckaroo_before_render_gateway_template_' . $name, $this );

		$this->getPaymentTemplate( 'global' );
		$this->getPaymentTemplate( $name );

		do_action( 'buckaroo_after_render_gateway_template_' . $name, $this );
	}

	/**
	 * Get the template for the payment gateway if exists
	 *
	 * @param string $name Template name / payment id.
	 *
	 * @return void
	 */
	protected function getPaymentTemplate( $name ) {
		$location = dirname( BK_PLUGIN_FILE ) . '/templates/gateways/';
		$file     = $location . $name . '.php';

		if ( file_exists( $file ) ) {
			include $file;
		}
	}

	/**
	 *
	 *
	 * @access public
	 * @param string $key
	 * @return boolean
	 */
	public function validate_number_field( $key, $text ) {
		if ( in_array( $key, array( 'minvalue', 'maxvalue' ) ) ) {
			// [9Yrds][2017-05-03][JW] WooCommerce 2.2 & 2.3 compatability
			$field = $this->plugin_id . $this->id . '_' . $key;

			if ( isset( $_POST[ $field ] ) ) {
				$text = wp_kses_post( trim( stripslashes( $_POST[ $field ] ) ) );
				if ( ! is_float( $text ) && ! is_numeric( $text ) ) {
					$this->errors[] = __( 'Please provide valid payment fee' );
					return false;
				}
			}
		}
		return parent::validate_text_field( $key, $text );
	}

	/**
	 * Validate fields
	 *
	 * @return void;
	 */
	public function validate_fields() {
		if ( version_compare( WC()->version, '3.6', '<' ) ) {
			Helper::resetOrder();
		}
		return;
	}

	/**
	 * Return properly filter if exists or null
	 *
	 * @param $tag
	 * @param $value
	 * @param mixed ...$args
	 * @return array | null
	 */
	function apply_filters_or_error( $tag, $value, ...$args ) {
		if ( ! has_filter( $tag ) ) {
			return null;
		}
		$response = apply_filters( $tag, $value, ...$args );

		return ( isset( $response['result'] ) && $response['result'] === 'no_subscription' ) ? null : $response;
	}

	/**
	 * Return properly filter if exists or null
	 *
	 * @param string $message
	 *
	 * @return array | null
	 */
	function apply_filter_or_error( $tag, $value ) {
		if ( has_filter( $tag ) ) {
			return apply_filters( $tag, $value );
		}
		return null;
	}

	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable fn_buckaroo_process_response()
	 */
	public function process_payment( $order_id ) {
		return ( new PayAction( $this->newPaymentProcessorInstance( $order_id ), $order_id ) )->process();
	}

	/**
	 * Process capture
	 *
	 * @param integer $order_id
	 * @return array|array[]|false|WP_Error
	 * @throws Exception
	 */
	public function process_capture( $order_id ) {
		if ( ! $this->capturable || ! $this->canShowCaptureForm( $order_id ) ) {
			return $this->create_capture_error( __( 'This order cannot be captured' ) );
		}

		if ( $order_id === null || ! is_numeric( $order_id ) ) {
			return $this->create_capture_error( __( 'A valid order number is required' ) );
		}

		$capture_amount = $this->request->input( 'capture_amount' );
		if ( $capture_amount === null || ! is_scalar( $capture_amount ) ) {
			return $this->create_capture_error( __( 'A valid capture amount is required' ) );
		}

		$order     = Helper::findOrder( $order_id );
		$processor = $this->newPaymentProcessorInstance( $order );
		$payment   = new BuckarooClient( $this->getMode() );

        $capturePayload = array(
            'amountDebit'            => $capture_amount,
            'originalTransactionKey' => $order->get_transaction_id(),
        );

        if ( $this instanceof KlarnaKpGateway ) {
            unset( $capturePayload['originalTransactionKey'] );
        }

		$res = $payment->process( $processor, $capturePayload );

		return ( new CaptureAction() )->handle(
			$res,
			$order,
			$this->currency,
		);
	}

	/**
	 * Can the order be refunded
	 *
	 * @param integer $order_id
	 * @param integer $amount defaults to null
	 * @param string  $reason
	 * @return callable|string function or error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '', $transactionId = null ) {
		return ( new RefundAction( $this->newRefundProcessorInstance( $order_id, $amount, $reason ), $order_id, $transactionId ) )->process();
	}

	public function getServiceCode( ?AbstractProcessor $processor = null ) {
		return str_replace( 'buckaroo_', '', $this->id );
	}

	/**
	 * Set gateway icon
	 *
	 * @param string $path New image path
	 *
	 * @return void
	 */
	protected function setIcon( $path ): void {
		$this->icon = apply_filters(
			'woocommerce_' . $this->id . '_icon',
			$this->getIconPath( $path )
		);
	}

	public function getIconPath( $path ): string {
		return plugins_url( 'library/buckaroo_images/' . $path, dirname( __DIR__ ) );
	}

	/**
	 * Set country field
	 *
	 * @return void
	 */
	protected function setCountry(): void {
		global $woocommerce;

		$country = null;
		if ( ! empty( $woocommerce->customer ) ) {
			$country = get_user_meta( $woocommerce->customer->get_id(), 'shipping_country', true );
		}
		$this->country = $country;
	}

	/**
	 * Add refund support
	 *
	 * @return void
	 */
	protected function addRefundSupport() {
		$this->supports = array(
			'products',
			'refunds',
		);
	}

	/**
	 * Migrate old named setting to new name
	 *
	 * @param string $oldKey Old settings key
	 *
	 * @return void
	 */
	protected function migrateOldSettings( $oldKey ) {
		if (
			! get_option( 'woocommerce_' . $this->id . '_settings' ) &&
			( $oldSettings = get_option( $oldKey ) )
		) {
			add_option( 'woocommerce_' . $this->id . '_settings', $oldSettings );
			delete_option( $oldKey );// clean the table
		}
	}

	/**
	 * Return properly formated capture error
	 *
	 * @param string $message
	 *
	 * @return array
	 */
	protected function create_capture_error( $message ) {
		return array(
			'errors' => array(
				'error_capture' => array(
					array( $message ),
				),
			),
		);
	}

	/**
	 * Add financial warning field to the setting page
	 *
	 * @return void
	 */
	protected function add_financial_warning_field() {
		$this->form_fields['financial_warning'] = array(
			'title'       => __( 'Consumer Financial Warning' ),
			'type'        => 'select',
			'description' => __( 'Due to the regulations for BNPL methods in The Netherlands you’ll  have to warn customers about using a BNPL plan because it can be easy to get into debt. When enabled a warning will be showed in the checkout. Please note that this setting only applies for customers in The Netherlands.', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'enable'  => 'Enable',
				'disable' => 'Disable',
			),
			'default'     => 'enable',
		);
	}

	protected function can_show_financial_warining(): bool {
		$country = $this->getScalarCheckoutField( 'billing_country' );
		return $this->get_option( 'financial_warning' ) !== 'disable' && $country === 'NL';
	}

	/**
	 * Get checkout field values
	 *
	 * @param string $key Input name
	 *
	 * @return mixed
	 */
	protected function getScalarCheckoutField( $key ) {
		$value     = '';
		$post_data = array();
		if ( ! empty( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			parse_str(
				$_POST['post_data'],
				$post_data
			);
		}

		if ( isset( $post_data[ $key ] ) && is_scalar( $post_data[ $key ] ) ) {
			$value = $post_data[ $key ];
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Set order capture
	 *
	 * @param int         $order_id Order id
	 * @param string      $paymentName Payment name
	 * @param string|null $paymentType Payment type
	 *
	 * @return void
	 */
	protected function set_order_capture( $order_id, $paymentName, $paymentType = null ) {
		update_post_meta( $order_id, '_wc_order_selected_payment_method', $paymentName );
		update_post_meta( $order_id, '_wc_order_payment_issuer', $paymentType );
	}

	public function getMode() {
		return $this->get_option( 'mode' );
	}

	/**
	 * Get payment class
	 *
	 * @param WC_Order $order
	 * @param boolean  $isRefund
	 *
	 * @return string
	 */
	protected function get_payment_class( $order, $isRefund = false ) {
		return static::PAYMENT_CLASS ?: AbstractPaymentProcessor::class;
	}

	public function newPaymentProcessorInstance( $order ) {
		if ( is_scalar( $order ) ) {
			$order = Helper::findOrder( $order );
		}

		$processorClass = $this->get_payment_class( $order );
        Logger::log( __METHOD__ . '|1|', $processorClass );

		return new $processorClass(
			$this,
			$order_details = new OrderDetails( $order ),
			new OrderArticles( $order_details, $this )
		);
	}

	public function newRefundProcessorInstance( $order, $amount, $reason ) {
		if ( is_scalar( $order ) ) {
			$order = Helper::findOrder( $order );
		}

		$processorClass = static::REFUND_CLASS ?: AbstractRefundProcessor::class;
		return new $processorClass(
			$this,
			new OrderDetails( $order ),
			$amount,
			$reason
		);
	}

	public function checkCurrencySupported(): bool {
		return (bool) in_array( get_woocommerce_currency(), $this->supportedCurrencies );
	}

	public function canShowCaptureForm( $order ): bool {
		return false;
	}

    public function isVisibleInCheckout(): bool {
        return $this->enabled == 'yes' && $this->checkCurrencySupported();
    }
}
