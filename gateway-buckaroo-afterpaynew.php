<?php


require_once __DIR__ . '/library/api/paymentmethods/afterpaynew/afterpaynew.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Afterpaynew extends WC_Gateway_Buckaroo {

	const PAYMENT_CLASS = BuckarooAfterPayNew::class;
	public $type;
	public $b2b;
	public $vattype;
	public $country;
	public $sendimageinfo;
	public $afterpaynewpayauthorize;
	public $customer_type;

	public const CUSTOMER_TYPE_B2C  = 'b2c';
	public const CUSTOMER_TYPE_B2B  = 'b2b';
	public const CUSTOMER_TYPE_BOTH = 'both';

	public function __construct() {
		$this->id           = 'buckaroo_afterpaynew';
		$this->title        = 'Riverty';
		$this->has_fields   = false;
		$this->method_title = 'Buckaroo Riverty';
		$this->setIcon( 'afterpay.png', 'svg/afterpay.svg' );
		$this->setCountry();

		parent::__construct();
		$this->addRefundSupport();
	}
	/**  @inheritDoc */
	protected function setProperties() {
		parent::setProperties();
		$this->afterpaynewpayauthorize = $this->get_option( 'afterpaynewpayauthorize' );
		$this->sendimageinfo           = $this->get_option( 'sendimageinfo' );
		$this->vattype                 = $this->get_option( 'vattype' );
		$this->type                    = 'afterpay';
		$this->customer_type           = $this->get_option( 'customer_type', self::CUSTOMER_TYPE_BOTH );
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
		$action = ucfirst( isset( $this->afterpaynewpayauthorize ) ? $this->afterpaynewpayauthorize : 'pay' );
		return $this->process_refund_common( $action, $order_id, $amount, $reason );
	}

	/**
	 * Can the order be refunded
	 *
	 * @param integer $order_id
	 * @param integer $amount defaults to null
	 * @param string  $reason
	 * @return callable|string function or error
	 */
	public function process_partial_refunds( $order_id, $amount = null, $reason = '', $line_item_qtys = null, $line_item_totals = null, $line_item_tax_totals = null, $originalTransactionKey = null ) {
		$order = wc_get_order( $order_id );

		if ( ! $this->can_refund_order( $order ) ) {
			return new WP_Error( 'error_refund_trid', __( 'Refund failed: Order not in ready state, Buckaroo transaction ID do not exists.' ) );
		}

		update_post_meta( $order_id, '_pushallowed', 'busy' );

		/** @var BuckarooAfterPayNew */
		$afterpay = $this->createCreditRequest( $order, $amount, $reason );

		if ( $originalTransactionKey !== null ) {
			$afterpay->OriginalTransactionKey = $originalTransactionKey;
		}

		// add items to refund call for afterpay
		$issuer = get_post_meta( $order_id, '_wc_order_payment_issuer', true );

		$products         = array();
		$items            = $order->get_items();
		$itemsTotalAmount = 0;

		if ( $line_item_qtys === null ) {
			$line_item_qtys = buckaroo_request_sanitized_json( 'line_item_qtys' );
		}

		if ( $line_item_totals === null ) {
			$line_item_totals = buckaroo_request_sanitized_json( 'line_item_totals' );
		}

		if ( $line_item_tax_totals === null ) {
			$line_item_tax_totals = buckaroo_request_sanitized_json( 'line_item_tax_totals' );
		}

		$orderDataForChecking = $afterpay->getOrderRefundData( $order );

		foreach ( $items as $item ) {
			if ( isset( $line_item_qtys[ $item->get_id() ] ) && $line_item_qtys[ $item->get_id() ] > 0 ) {
				$product = new WC_Product( $item['product_id'] );

				$tax      = new WC_Tax();
				$taxes    = $tax->get_rates( $product->get_tax_class() );
				$rates    = array_shift( $taxes );
				$itemRate = number_format( array_shift( $rates ), 2 );

				$tmp['ArticleDescription'] = $item['name'];
				$tmp['ArticleId']          = $item['product_id'];
				$tmp['ArticleQuantity']    = $line_item_qtys[ $item->get_id() ];
				$tmp['ArticleUnitprice']   = number_format( number_format( $item['line_total'] + $item['line_tax'], 4, '.', '' ) / $item['qty'], 2, '.', '' );
				$itemsTotalAmount         += number_format( $tmp['ArticleUnitprice'] * $line_item_qtys[ $item->get_id() ], 2, '.', '' );
				$tmp['ArticleVatcategory'] = $itemRate;
				$products[]                = $tmp;
			} elseif ( ! empty( $orderDataForChecking[ $item->get_id() ]['tax'] ) ) {
				$product = new WC_Product( $item['product_id'] );
				$tax     = new WC_Tax();
				$taxes   = $tax->get_rates( $product->get_tax_class() );
				$taxId   = 3; // Standard tax
				foreach ( $taxes as $taxIdItem => $taxItem ) {
					$taxId = $taxIdItem;
				}

				$rates    = array_shift( $taxes );
				$itemRate = number_format( array_shift( $rates ), 2 );

				$tmp['ArticleDescription'] = $rates['label'];
				$tmp['ArticleId']          = $taxId;
				$tmp['ArticleQuantity']    = 1;
				$tmp['ArticleUnitprice']   = number_format( $orderDataForChecking[ $item->get_id() ]['tax'], 2, '.', '' );

				$itemsTotalAmount += $tmp['ArticleUnitprice'];

				$tmp['ArticleVatcategory'] = $itemRate;
				$products[]                = $tmp;
			}
		}

		$fees = $order->get_fees();

		foreach ( $fees as $key => $item ) {
			if ( ! empty( $line_item_totals[ $key ] ) ) {
				$feeTaxRate = $this->getProductTaxRate( $item );

				$tmp['ArticleDescription'] = $item['name'];
				$tmp['ArticleId']          = $key;
				$tmp['ArticleQuantity']    = 1;
				$tmp['ArticleUnitprice']   = number_format( ( $item['line_total'] + $item['line_tax'] ), 2 );
				$tmp['ArticleVatcategory'] = $feeTaxRate;
				$products[]                = $tmp;
				$itemsTotalAmount         += $tmp['ArticleUnitprice'];
			}
		}

		// Add shippingCosts
		$shippingInfo = $this->getAfterPayShippingInfo( 'afterpay-new', 'partial_refunds', $order, $line_item_totals, $line_item_tax_totals );
		if ( $shippingInfo['costs'] > 0 ) {
			$products[]        = $shippingInfo['shipping_virtual_product'];
			$itemsTotalAmount += $shippingInfo['costs'];
		}

		if ( $orderDataForChecking['totalRefund'] != $itemsTotalAmount ) {
			if ( number_format( $orderDataForChecking['totalRefund'] - $itemsTotalAmount, 2 ) >= 0.01 ) {
				$tmp['ArticleDescription'] = 'Remaining Price';
				$tmp['ArticleId']          = 'remaining_price';
				$tmp['ArticleQuantity']    = 1;
				$tmp['ArticleUnitprice']   = number_format( $orderDataForChecking['totalRefund'] - $itemsTotalAmount, 2 );
				$tmp['ArticleVatcategory'] = 0;
				$products[]                = $tmp;
				$itemsTotalAmount         += 0.01;
			} elseif ( number_format( $itemsTotalAmount - $orderDataForChecking['totalRefund'], 2 ) >= 0.01 ) {
				$tmp['ArticleDescription'] = 'Remaining Price';
				$tmp['ArticleId']          = 'remaining_price';
				$tmp['ArticleQuantity']    = 1;
				$tmp['ArticleUnitprice']   = number_format( $orderDataForChecking['totalRefund'] - $itemsTotalAmount, 2 );
				$tmp['ArticleVatcategory'] = 0;
				$products[]                = $tmp;
				$itemsTotalAmount         -= 0.01;
			}
		}
		// end add items

		$ref_amount = $this->request( 'refund_amount' );
		if ( $ref_amount !== null && $itemsTotalAmount == 0 ) {
			$afterpay->amountCredit = $ref_amount;
		} else {
			$amount                 = $itemsTotalAmount;
			$afterpay->amountCredit = $amount;
		}

		if ( ! ( count( $products ) > 0 ) ) {
			return new WP_Error( 'error_refund_afterpay_no_products', __( 'To refund an Riverty transaction you need to refund at least one product.' ) );
		}

		try {
			$afterpay->checkRefundData( $orderDataForChecking );
			$response = $afterpay->AfterPayRefund( $products, $issuer );

		} catch ( Exception $e ) {
			update_post_meta( $order_id, '_pushallowed', 'ok' );
			return new WP_Error( 'refund_error', __( $e->getMessage() ) );
		}

		$final_response = fn_buckaroo_process_refund( $response, $order, $amount, $this->currency );

		return $final_response;
	}

	public function process_capture() {
		$order_id = $this->request( 'order_id' );

		if ( $order_id === null || ! is_numeric( $order_id ) ) {
			return $this->create_capture_error( __( 'A valid order number is required' ) );
		}

		$capture_amount = $this->request( 'capture_amount' );
		if ( $capture_amount === null || ! is_scalar( $capture_amount ) ) {
			return $this->create_capture_error( __( 'A valid capture amount is required' ) );
		}

		$previous_captures = get_post_meta( $order_id, '_wc_order_captures' ) ? get_post_meta( $order_id, '_wc_order_captures' ) : false;

		$woocommerce = getWooCommerceObject();

		$order = getWCOrder( $order_id );
		/** @var BuckarooAfterPayNew */
		$afterpay                         = $this->createDebitRequest( $order );
		$afterpay->amountDedit            = str_replace( ',', '.', $capture_amount );
		$afterpay->OriginalTransactionKey = $order->get_transaction_id();
		$afterpay->invoiceId              = (string) getUniqInvoiceId( $woocommerce->order ? $woocommerce->order->get_order_number() : $order_id ) . ( is_array( $previous_captures ) ? '-' . count( $previous_captures ) : '' );

		// add items to capture call for afterpay
		$customVars['payment_issuer'] = get_post_meta( $order_id, '_wc_order_payment_issuer', true );

		$products         = array();
		$items            = $order->get_items();
		$itemsTotalAmount = 0;

		$line_item_qtys       = buckaroo_request_sanitized_json( 'line_item_qtys' );
		$line_item_totals     = buckaroo_request_sanitized_json( 'line_item_totals' );
		$line_item_tax_totals = buckaroo_request_sanitized_json( 'line_item_tax_totals' );

		foreach ( $items as $item ) {
			if ( isset( $line_item_qtys[ $item->get_id() ] ) && $line_item_qtys[ $item->get_id() ] > 0 ) {
				$product = new WC_Product( $item['product_id'] );

				$tax                       = new WC_Tax();
				$taxes                     = $tax->get_rates( $product->get_tax_class() );
				$rates                     = array_shift( $taxes );
				$itemRate                  = number_format( array_shift( $rates ), 2 );
				$tmp['ArticleDescription'] = $item['name'];
				$tmp['ArticleId']          = $item['product_id'];
				$tmp['ArticleQuantity']    = $line_item_qtys[ $item->get_id() ];
				$tmp['ArticleUnitprice']   = (float) number_format( number_format( $item['line_total'] + $item['line_tax'], 4, '.', '' ) / $item['qty'], 2, '.', '' );
				$itemsTotalAmount         += $tmp['ArticleUnitprice'] * $item['qty'];
				$tmp['ArticleVatcategory'] = $itemRate;
				$products[]                = $tmp;
			}
		}

		if ( ! $previous_captures ) {
			$fees = $order->get_fees();
			foreach ( $fees as $key => $item ) {
				$feeTaxRate                = $this->getProductTaxRate( $item );
				$tmp['ArticleDescription'] = $item['name'];
				$tmp['ArticleId']          = $key;
				$tmp['ArticleQuantity']    = 1;
				$tmp['ArticleUnitprice']   = number_format( ( $item['line_total'] + $item['line_tax'] ), 2, '.', '' );
				$itemsTotalAmount         += $tmp['ArticleUnitprice'];
				$tmp['ArticleVatcategory'] = $feeTaxRate;
				$products[]                = $tmp;
			}
		}

		// Add shippingCosts
		$shippingInfo = $this->getAfterPayShippingInfo( 'afterpay', 'capture', $order, $line_item_totals, $line_item_tax_totals );
		if ( $shippingInfo['costs'] > 0 ) {
			$products[] = $shippingInfo['shipping_virtual_product'];
		}

		// end add items

		$response         = $afterpay->Capture( $customVars, $products );
		$process_response = fn_buckaroo_process_capture( $response, $order, $this->currency, $products );

		return $process_response;
	}

	/**
	 * Validate payment fields on the frontend.
	 *
	 * @access public
	 * @return void
	 */
	public function validate_fields() {
		$country = $this->request( 'billing_country' );
		if ( $country === null ) {
			$country = $this->country;
		}

		$birthdate = $this->parseDate(
			$this->request( 'buckaroo-afterpaynew-birthdate' )
		);

		if ( ! ( $this->validateDate( $birthdate, 'd-m-Y' ) && $this->validateBirthdate( $birthdate ) ) && in_array( $country, array( 'NL', 'BE' ) ) ) {
			wc_add_notice( __( 'You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}

		if ( $this->request( 'buckaroo-afterpaynew-accept' ) === null ) {
			wc_add_notice( __( 'Please accept licence agreements', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}

		if (
			self::CUSTOMER_TYPE_B2C !== $this->customer_type &&
			$country === 'NL' &&
			$this->request( 'billing_company' ) !== null
		) {
			if ( $this->request( 'buckaroo-afterpaynew-company-coc-registration' ) === null ) {
				wc_add_notice( __( 'Company registration number is required', 'wc-buckaroo-bpe-gateway' ), 'error' );
			}
		}

		if ( $this->request( 'buckaroo-afterpaynew-phone' ) === null && $this->request( 'billing_phone' ) === null ) {
			wc_add_notice( __( 'Please enter phone number', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}

		if (
			$this->is_house_number_invalid( 'billing' )
		) {
			wc_add_notice( __( 'Invalid billing address, cannot find house number', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}

		if (
			$this->is_house_number_invalid( 'shipping' ) &&
			$this->request( 'ship_to_different_address' ) == 1
		) {
			wc_add_notice( __( 'Invalid shipping address, cannot find house number', 'wc-buckaroo-bpe-gateway' ), 'error' );
		}

		parent::validate_fields();
	}

	private function is_house_number_invalid( $type ) {
		$components = Buckaroo_Order_Details::getAddressComponents(
			$this->request( $type . '_address_1' ) . ' ' . $this->request( $type . '_address_2' )
		);

		return ! is_string( $components['house_number'] ) || empty( trim( $components['house_number'] ) );
	}

	/**
	 * Process payment
	 *
	 * @param integer $order_id
	 * @return callable|void fn_buckaroo_process_response() or void
	 */
	public function process_payment( $order_id ) {
		$this->setOrderCapture( $order_id, 'Afterpaynew' );
		$order = getWCOrder( $order_id );
		/** @var BuckarooAfterPayNew */
		$afterpay = $this->createDebitRequest( $order );
		$afterpay->setType( $this->type );
		$afterpay->invoiceId = (string) getUniqInvoiceId(
			preg_replace( '/\./', '-', $order->get_order_number() )
		);
		$order_details       = new Buckaroo_Order_Details( $order );

		$birthdate = $this->parseDate( $this->request( 'buckaroo-afterpaynew-birthdate' ) );

		$afterpay = $this->get_billing_info( $order_details, $afterpay, $birthdate );
		$afterpay = $this->get_shipping_info( $order_details, $afterpay );

		/** @var BuckarooAfterPayNew */
		$afterpay = $this->handleThirdPartyShippings( $afterpay, $order, $this->country );

		$afterpay->CustomerIPAddress = getClientIpBuckaroo();
		$afterpay->Accept            = 'TRUE';
		$afterpay->CustomerType      = $this->customer_type;

		if ( $this->request( 'buckaroo-afterpaynew-identification-number' ) !== null ) {
			$afterpay->IdentificationNumber = $this->request( 'buckaroo-afterpaynew-identification-number' );
		}

		if ( $this->request( 'buckaroo-afterpaynew-company-coc-registration' ) !== null ) {
			$afterpay->IdentificationNumber = $this->request( 'buckaroo-afterpaynew-company-coc-registration' );
		}

		$afterpay->returnUrl = $this->notify_url;

		$action = ucfirst( isset( $this->afterpaynewpayauthorize ) ? $this->afterpaynewpayauthorize : 'pay' );

		if ( $action == 'Authorize' ) {
			update_post_meta( $order_id, '_wc_order_authorized', 'yes' );
		}

		$response = $afterpay->PayOrAuthorizeAfterpay(
			$this->get_products_for_payment( $order_details ),
			$action
		);
		return fn_buckaroo_process_response( $this, $response, $this->mode );
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
	/**
	 * Get billing info for pay request
	 *
	 * @param Buckaroo_Order_Details $order_details
	 * @param BuckarooAfterPayNew    $method
	 * @param string                 $birthdate
	 *
	 * @return BuckarooAfterPayNew  $method
	 */
	protected function get_billing_info( $order_details, $method, $birthdate ) {
		/** @var BuckarooAfterPayNew */
		$method                   = $this->set_billing( $method, $order_details );
		$method->BillingInitials  = $order_details->getInitials(
			$order_details->getBilling( 'first_name' )
		);
		$method->BillingBirthDate = date( 'Y-m-d', strtotime( $birthdate ) );
		if ( empty( $method->BillingPhoneNumber ) ) {
			$method->BillingPhoneNumber = $this->request( 'buckaroo-afterpaynew-phone' );
		}

		if ( strlen( $order_details->getBilling( 'company' ) ) ) {
			$method->BillingCompanyName = $order_details->getBilling( 'company' );
		}

		return $method;
	}
	/**
	 * Get shipping info for pay request
	 *
	 * @param Buckaroo_Order_Details $order_details
	 * @param BuckarooAfterPayNew    $method
	 *
	 * @return BuckarooAfterPayNew $method
	 */
	protected function get_shipping_info( $order_details, $method ) {
		$method->AddressesDiffer = 'FALSE';
		if ( $this->request( 'buckaroo-afterpaynew-shipping-differ' ) !== null ) {
			$method->AddressesDiffer = 'TRUE';
			/** @var BuckarooAfterPayNew */
			$method                   = $this->set_shipping( $method, $order_details );
			$method->ShippingInitials = $order_details->getInitials(
				$order_details->getShipping( 'first_name' )
			);

			if ( strlen( $order_details->getShipping( 'company' ) ) ) {
				$method->ShippingCompanyName = $order_details->getShipping( 'company' );
			}
		}
		return $method;
	}

	/**
	 * Check response data
	 *
	 * @access public
	 */
	public function response_handler() {
		fn_buckaroo_process_response( $this );
		exit;
	}

	/**
	 * Add fields to the form_fields() array, specific to this page.
	 *
	 * @access public
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->add_financial_warning_field();
		$this->form_fields['afterpaynewpayauthorize'] = array(
			'title'       => __( 'Riverty Pay or Capture', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'pay'       => 'Pay',
				'authorize' => 'Authorize',
			),
			'default'     => 'pay',
		);

		$this->form_fields['sendimageinfo'] = array(
			'title'       => __( 'Send image info', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Image info will be sent to BPE gateway inside ImageUrl parameter', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'0' => 'No',
				'1' => 'Yes',
			),
			'default'     => 'pay',
			'desc_tip'    => 'Product images are only shown when they are available in JPG or PNG format',
		);
		$this->form_fields['customer_type'] = array(
			'title'       => __( 'Riverty customer type', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'This setting determines whether you accept Riverty payments for B2C, B2B or both customer types. When B2B is selected, this method is only shown when a company name is entered in the checkout process.', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				self::CUSTOMER_TYPE_BOTH => __( 'Both' ),
				self::CUSTOMER_TYPE_B2C  => __( 'B2C (Business-to-consumer)' ),
				self::CUSTOMER_TYPE_B2B  => __( 'B2B ((Business-to-Business)' ),
			),
			'default'     => self::CUSTOMER_TYPE_BOTH,
		);
		$this->form_fields['b2b_min_value'] = array(
			'title'             => __( 'Min order amount  for B2B', 'wc-buckaroo-bpe-gateway' ),
			'type'              => 'number',
			'custom_attributes' => array( 'step' => '0.01' ),
			'description'       => __( 'The payment method shows only for orders with an order amount greater than the minimum amount.', 'wc-buckaroo-bpe-gateway' ),
			'default'           => '0',
		);
		$this->form_fields['b2b_max_value'] = array(
			'title'             => __( 'Max order amount  for B2B', 'wc-buckaroo-bpe-gateway' ),
			'type'              => 'number',
			'custom_attributes' => array( 'step' => '0.01' ),
			'description'       => __( 'The payment method shows only for orders with an order amount smaller than the maximum amount.', 'wc-buckaroo-bpe-gateway' ),
			'default'           => '0',
		);
	}

	public function getProductImage( $product ) {

		if ( $this->sendimageinfo ) {
			$src = get_the_post_thumbnail_url( $product->get_id() );
			if ( ! $src ) {
				$imgTag = $product->get_image();
				$doc    = new DOMDocument();
				$doc->loadHTML( $imgTag );
				$xpath = new DOMXPath( $doc );
				$src   = $xpath->evaluate( 'string(//img/@src)' );
			}

			if ( strpos( $src, '?' ) !== false ) {
				$src = substr( $src, 0, strpos( $src, '?' ) );
			}

			if ( $srcInfo = @getimagesize( $src ) ) {
				if ( ! empty( $srcInfo['mime'] ) && in_array( $srcInfo['mime'], array( 'image/png', 'image/jpeg' ) ) ) {
					if ( ! empty( $srcInfo[0] ) && ( $srcInfo[0] >= 100 ) && ( $srcInfo[0] <= 1280 ) ) {
						return $src;
					}
				}
			}
		}
	}



	/**
	 * Show payment if available
	 *
	 * @param float $cartTotal
	 *
	 * @return boolean
	 */
	public function isAvailable( float $cartTotal ) {
		if ( $this->customer_type !== self::CUSTOMER_TYPE_B2B ) {
			return $this->isAvailableB2B( $cartTotal );
		}

		return true;
	}
	/**
	 * Check if payment is available for b2b
	 *
	 * @param float $cartTotal
	 *
	 * @return boolean
	 */
	public function isAvailableB2B( float $cartTotal ) {
		$b2bMin = $this->get_option( 'b2b_min_value', 0 );
		$b2bMax = $this->get_option( 'b2b_max_value', 0 );

		if ( $b2bMin == 0 && $b2bMax == 0 ) {
			return true;
		}

		return ( $b2bMin > 0 && $cartTotal > $b2bMin ) || ( $b2bMax > 0 && $cartTotal < $b2bMax );
	}
}
