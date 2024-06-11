<?php

require_once __DIR__ . '/controllers/ApplePayController.php';
require_once __DIR__ . '/library/api/paymentmethods/applepay/applepay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Applepay extends WC_Gateway_Buckaroo {

	protected $paymentData;

	protected $CustomerCardName;

	protected $amount;

	const PAYMENT_CLASS = BuckarooApplepay::class;
	public function __construct() {
		$this->id               = 'buckaroo_applepay';
		$this->title            = 'Apple Pay';
		$this->has_fields       = true;
		$this->method_title     = 'Buckaroo Apple Pay';
		$this->CustomerCardName = '';
		$this->setIcon( 'svg/applepay.svg', 'svg/applepay.svg' );

		parent::__construct();
		$this->addRefundSupport();
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			$this->registerControllers();
		}
	}
	private function registerControllers() {
		$namespace = 'woocommerce_api_wc_gateway_buckaroo_applepay';

		add_action( "{$namespace}-get-items-from-detail-page", array( ApplePayController::class, 'getItemsFromDetailPage' ) );
		add_action( "{$namespace}-get-items-from-cart", array( ApplePayController::class, 'getItemsFromCart' ) );
		add_action( "{$namespace}-get-shipping-methods", array( ApplePayController::class, 'getShippingMethods' ) );
		add_action( "{$namespace}-get-shop-information", array( ApplePayController::class, 'getShopInformation' ) );
		add_action( "{$namespace}-create-transaction", array( $this, 'createTransaction' ) );
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
		return $this->processDefaultRefund( $order_id, $amount, $reason, true );
	}

	/**
	 * Validate fields
	 *
	 * @return void;
	 */
	public function validate_fields() {
		resetOrder();
		return;
	}

	public function createTransaction() {
		Buckaroo_Logger::log( __METHOD__ . '|1|', $_POST );

		$this->paymentData = $this->request( 'paymentData' );

		if ( ! is_array( $this->paymentData ) ) {
			$this->error_response( 'ApplePay data is invalid.' );
		}

		if (
			! isset( $this->paymentData['billingContact'] ) ||
			! isset( $this->paymentData['shippingContact'] ) ||
			! is_array( $this->paymentData['billingContact'] ) ||
			! is_array( $this->paymentData['shippingContact'] )
			) {
			$this->error_response( 'ApplePay data is invalid.' );
		}

		$items = $this->request( 'items' );
		if ( $items === null || ! is_array( $items ) ) {
			$this->error_response( 'ApplePay data is invalid.' );
		}

		$shipping_method = $this->request( 'selected_shipping_method' );
		if ( $shipping_method === null || ! is_scalar( $shipping_method ) ) {
			$this->error_response( 'Invalid shipping method.' );
		}
		$amount = $this->request( 'amount' );
		if ( $amount === null || ! is_scalar( $amount ) ) {
			$this->error_response( 'Invalid amount.' );
		}
		if (
			count(
				array_diff(
					array( 'givenName', 'familyName', 'emailAddress', 'addressLines', 'locality', 'postalCode', 'countryCode' ),
					array_keys( $this->paymentData['shippingContact'] )
				)
			)
		) {
			$this->error_response( 'Invalid shipping address format.' );
		}
		if (
			count(
				array_diff(
					array( 'givenName', 'familyName', 'addressLines', 'locality', 'postalCode', 'countryCode' ),
					array_keys( $this->paymentData['billingContact'] )
				)
			)
		) {
			$this->error_response( 'Invalid billing address format.' );
		}

		$this->CustomerCardName = implode(
			' ',
			array(
				sanitize_text_field( $this->paymentData['billingContact']['givenName'] ),
				sanitize_text_field( $this->paymentData['billingContact']['familyName'] ),
			)
		);

		$this->amount = $amount;

		try {
			$orderResult = $this->createOrder(
				$this->paymentData['billingContact'],
				$this->paymentData['shippingContact'],
				$items,
				$shipping_method
			);

			if ( $orderResult ) {
				$this->process_payment( $orderResult['data']['id'] );
			} else {
				$this->error_response( 'Error while creation of WooCommerce order' );
			}
		} catch ( \Throwable $th ) {
			$this->error_response( $th->getMessage() );
		}
	}
	public function error_response( $errorMessage ) {
		wp_send_json(
			array(
				'status'  => 'fail',
				'message' => $errorMessage,
			)
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
		/** @var BuckarooApplepay */
		$applepay = $this->createDebitRequest( $order );

		$customVars                     = array();
		$customVars['PaymentData']      = base64_encode( json_encode( $this->paymentData['token'] ) );
		$customVars['CustomerCardName'] = $this->CustomerCardName;

		$response          = $applepay->Pay( $customVars );
		$buckaroo_response = fn_buckaroo_process_response( $this, $response );

		if ( $response->status === BuckarooAbstract::STATUS_COMPLETED ) {
			$order->update_status( 'processing', 'Order paid with Apple pay' );
		} else {
			$buckaroo_response = array(
				'status'  => 'fail',
				'message' => $response->message,
			);
		}

		echo json_encode( $buckaroo_response );
		exit;
	}

	public function createOrder( $billing_addresses, $shipping_addresses, $items, $selected_method_id ) {
		Buckaroo_Logger::log( __METHOD__ . '|1|' );

		$order = wc_create_order();

		$wc_methods = self::createFakeCart(
			$items,
			function () {
				$packages = WC()->cart->get_shipping_packages();

				return WC()
				->shipping
				->calculate_shipping_for_package( current( $packages ) )['rates'];
			}
		);

		try {
			$products = array_filter(
				$items,
				function ( $item ) {
					return isset( $item['type'] ) && $item['type'] === 'product';
				}
			);

			$coupons = array_filter(
				$items,
				function ( $item ) {
					return isset( $item['type'] ) && $item['type'] === 'coupon';
				}
			);

			$extra_charge = array_filter(
				$items,
				function ( $item ) {
					return isset( $item['type'] ) && $item['type'] === 'extra_charge';
				}
			);

			foreach ( $products as $product ) {
				if ( isset( $product['id'] ) && isset( $product['quantity'] ) ) {
					$order->add_product( wc_get_product( $product['id'] ), $product['quantity'] );
				}
			}

			foreach ( $coupons as $coupon ) {

				if ( isset( $coupon['name'] ) && is_string( $coupon['name'] ) ) {
					preg_match( '/coupon\:\s(.*)/i', $coupon['name'], $matches );
					$order->apply_coupon( $matches[1] );
				}
			}

			foreach ( $extra_charge as $charge ) {
				$taxable = $charge['attributes']['taxable']
				? 'taxable'
				: 'none';

				$item_fee = new WC_Order_Item_Fee();
				$item_fee->set_name( $charge['name'] );
				$item_fee->set_amount( (string) $charge['price'] );
				$item_fee->set_tax_status( 'taxable' );
				$item_fee->set_tax_class( '' );
				$item_fee->set_tax_status( $taxable );
				$item_fee->set_total( (string) $charge['price'] );
				$order->add_item( $item_fee );
			}

			$order->set_address( self::orderAddresses( $billing_addresses ), 'billing' );
			$order->set_address( self::orderAddresses( $shipping_addresses ), 'shipping' );

			// set email
			$billingEmail = '';
			if ( ! empty( $shipping_addresses['emailAddress'] ) ) {
				$billingEmail = $shipping_addresses['emailAddress'];
			}
			if ( ! empty( $billing_addresses['emailAddress'] ) ) {
				$billingEmail = $billing_addresses['emailAddress'];
			}
			if ( $billingEmail ) {
				$order->set_billing_email( $billingEmail );
			}

			// set phone
			$billingPhone = '';
			if ( ! empty( $shipping_addresses['phoneNumber'] ) ) {
				$billingPhone = $shipping_addresses['phoneNumber'];
			}
			if ( ! empty( $billing_addresses['phoneNumber'] ) ) {
				$billingPhone = $billing_addresses['phoneNumber'];
			}
			if ( $billingPhone ) {
				$order->set_billing_phone( $billingPhone );
			}

			if ( ! empty( $selected_method_id ) && ! preg_match( '/free/', $selected_method_id ) ) {
				$order->add_shipping( $wc_methods[ $selected_method_id ] );
			}

			update_post_meta( $order->get_id(), '_payment_method', $this->id );
			update_post_meta( $order->get_id(), '_payment_method_title', $this->title );
			$this->set_order_contribution( $order );

			$order->calculate_totals();
			$order->update_status( 'pending payment', 'Order created using Apple pay', true );
		} catch ( \Exception $e ) {
			return false;
		}

		return array(
			'success' => true,
			'data'    => array(
				'id'    => $order->get_id(),
				'key'   => $order->get_order_key(),
				'items' => $items,
			),
		);
	}

	private function set_order_contribution( WC_Order $order ) {
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

	private static function orderAddresses( $address ) {
		return array(
			'first_name' => $address['givenName'],
			'last_name'  => $address['familyName'],
			'email'      => $address['emailAddress'],
			'address_1'  => $address['addressLines'][0],
			'city'       => $address['locality'],
			'postcode'   => $address['postalCode'],
			'country'    => $address['countryCode'],
		);
	}
	/**
	 * Add fields to the form_fields() array, specific to this page.
	 *
	 * @access public
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		unset( $this->form_fields['title'] );
		unset( $this->form_fields['description'] );

		$this->form_fields['button_product'] = array(
			'title'       => __( 'Button on product page', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Show the Apple pay button on the product page', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'TRUE'  => __( 'Show', 'wc-buckaroo-bpe-gateway' ),
				'FALSE' => __( 'Hide', 'wc-buckaroo-bpe-gateway' ),
			),
			'default'     => 'TRUE',
		);

		$this->form_fields['button_cart'] = array(
			'title'       => __( 'Button on cart page', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Show the Apple pay button on the cart page', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'TRUE'  => __( 'Show', 'wc-buckaroo-bpe-gateway' ),
				'FALSE' => __( 'Hide', 'wc-buckaroo-bpe-gateway' ),
			),
			'default'     => 'TRUE',
		);

		$this->form_fields['button_checkout'] = array(
			'title'       => __( 'Button on checkout page', 'wc-buckaroo-bpe-gateway' ),
			'type'        => 'select',
			'description' => __( 'Show the Apple pay button on the checkout page', 'wc-buckaroo-bpe-gateway' ),
			'options'     => array(
				'TRUE'  => __( 'Show', 'wc-buckaroo-bpe-gateway' ),
				'FALSE' => __( 'Hide', 'wc-buckaroo-bpe-gateway' ),
			),
			'default'     => 'TRUE',
		);
		$this->set_guid_after_usemaster();
	}
	/**
	 * Set merchand_guid after master settings checkbox
	 *
	 * @return void
	 */
	protected function set_guid_after_usemaster() {
		$new_form_fields = array();
		foreach ( $this->form_fields as $k => $value ) {
			$new_form_fields[ $k ] = $value;
			if ( $k === 'mode' ) {
				$new_form_fields['merchant_guid'] = array(
					'title'       => __( 'GUID', 'wc-buckaroo-bpe-gateway' ),
					'type'        => 'text',
					'description' => __( 'The Buckaroo GUID which can be found in the Buckaroo Plaza > My Buckaroo > General.', 'wc-buckaroo-bpe-gateway' ),
					'default'     => '0',
				);
			}
		}
		$this->form_fields = $new_form_fields;
	}

	private static function createFakeCart( $items, $callback ) {

		global $woocommerce;
		$cart = $woocommerce->cart;

		$original_cart_products = array_map(
			function ( $product ) {
				return array(
					'product_id'   => $product['product_id'],
					'variation_id' => $product['variation_id'],
					'quantity'     => $product['quantity'],
				);
			},
			$cart->get_cart_contents()
		);

		$original_applied_coupons = array_map(
			function ( $coupon ) {
				return array(
					'coupon_id' => $coupon->get_id(),
					'code'      => $coupon->get_code(),
				);
			},
			$cart->get_coupons()
		);

		$cart->empty_cart();

		foreach ( $items as $item ) {
			if (
				count(
					array_diff(
						array( 'id', 'quantity' ),
						array_keys( $item )
					)
				) === 0
			) {
				$cart->add_to_cart(
					$item['id'],
					$item['quantity'],
				);
			}
		}

		foreach ( $original_applied_coupons as $original_applied_coupon ) {
			$cart->apply_coupon( $original_applied_coupon['code'] );
		}

		$fake_cart_result = call_user_func( $callback );

		$cart->empty_cart();

		foreach ( $original_cart_products as $original_product ) {
			$cart->add_to_cart(
				$original_product['product_id'],
				$original_product['quantity'],
				$original_product['variation_id']
			);
		}

		foreach ( $original_applied_coupons as $original_applied_coupon ) {
			$cart->apply_coupon( $original_applied_coupon['code'] );
		}

		wc_clear_notices();

		return $fake_cart_result;
	}
}
