<?php
require_once __DIR__ . '/../abstract.php';
require_once __DIR__ . '/../soap.php';
require_once __DIR__ . '/responsefactory.php';

/**
 * @package Buckaroo
 */
abstract class BuckarooPaymentMethod extends BuckarooAbstract {

	const TYPE_PAY     = 'pay';
	const TYPE_CAPTURE = 'capture';
	const TYPE_REFUND  = 'refund';
	const VERSION_ZERO = 0;
	const VERSION_ONE  = 1;
	const VERSION_TWO  = 2;
	protected $type;
	public $currency;
	public $amountDedit;
	public $amountCredit = 0;
	public $orderId;
	public $invoiceId;
	public $description;
	public $OriginalTransactionKey;
	public $OriginalInvoiceNumber;
	public $AmountVat;
	public $returnUrl;
	public $mode;
	public $version;
	public $sellerprotection = 0;
	public $CreditCardDataEncrypted;
	public $real_order_id;
	protected $data = array();

	protected $requestType = 'TransactionRequest';

	public const REQUEST_TYPE_DATA_REQUEST = 'DataRequest';
	/**
	 * @param mixed $type
	 */
	public function setType( $type ) {
		$this->type = $type;
	}

	public function setRequestType( string $type ) {
		$this->requestType = $type;
	}

	/**
	 * @return mixed
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Set request parameter
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return string $value
	 */
	public function setParameter( $key, $value ) {
		$this->data[ $key ] = $value;
		return $value;
	}

	/**
	 * Set service param key and value
	 *
	 * @param string      $key
	 * @param string      $value
	 * @param string|null $type
	 *
	 * @return string $value
	 */
	public function setService( $key, $value ) {
		return $this->setServiceOfType( $key, $value );
	}

	/**
	 * Set service param for specific type
	 *
	 * @param string      $key
	 * @param string      $value
	 * @param string|null $type
	 *
	 * @return string $value
	 */
	public function setServiceOfType( $key, $value, $type = null ) {
		if ( $type === null ) {
			$type = $this->type;
		}

		if ( ! isset( $this->data['services'] ) ) {
			$this->data['services'] = array();
		}

		if ( ! isset( $this->data['services'][ $type ] ) ) {
			$this->data['services'][ $type ] = array();
		}

		$this->data['services'][ $type ][ $key ] = $value;

		return $value;
	}

	/**
	 * Set custom param key and value
	 *
	 * @param string|array $keyOrValues Key of the value or associative array of values
	 * @param mixed|null   $value
	 * @param string|null  $group
	 * @param string|null  $type
	 *
	 * @return string $value
	 */
	public function setCustomVar( $keyOrValues, $value = null, $group = null ) {
		return $this->setCustomVarOfType( $keyOrValues, $value, $group );
	}

	/**
	 * Set custom params for specific type
	 *
	 * @param string|array $key Key of the value or associative array of values
	 * @param string|null  $value
	 * @param string|null  $group
	 * @param string|null  $type
	 *
	 * @return string|array $value
	 */
	public function setCustomVarOfType( $keyOrValues, $value = null, $group = null, $type = null ) {
		if ( $type === null ) {
			$type = $this->type;
		}

		if ( ! isset( $this->data['customVars'] ) ) {
			$this->data['customVars'] = array();
		}

		if ( $type === false ) {
			return $this->setCustomVarWithoutType( $keyOrValues, $value );
		}

		if ( ! isset( $this->data['customVars'][ $type ] ) ) {
			$this->data['customVars'][ $type ] = array();
		}

		if ( is_array( $keyOrValues ) ) {

			if ( $group !== null ) {
				$keyOrValues = array_map(
					function ( $value ) use ( $group ) {
						return array(
							'value' => $value,
							'group' => $group,
						);
					},
					$keyOrValues
				);
			}

			$this->data['customVars'][ $type ] = array_merge(
				$this->data['customVars'][ $type ],
				$keyOrValues
			);
			return $keyOrValues;

		} else {
			if ( $group !== null ) {
				$value = array(
					'value' => $value,
					'group' => $group,
				);
			}
			$this->data['customVars'][ $type ][ $keyOrValues ] = $value;
		}
		return $value;
	}

	/**
	 * Set custom param at specific position in array
	 *
	 * @param string  $key
	 * @param string  $value
	 * @param integer $position
	 * @param string  $group
	 * @param string  $type
	 *
	 * @return void
	 */
	public function setCustomVarAtPosition( $key, $value, $position = 0, $group = null, $type = null ) {
		if ( $type === null ) {
			$type = $this->type;
		}

		if ( ! isset( $this->data['customVars'] ) ) {
			$this->data['customVars'] = array();
		}
		if ( ! isset( $this->data['customVars'][ $type ] ) ) {
			$this->data['customVars'][ $type ] = array();
		}
		if ( $group !== null ) {
			$value = array(
				'value' => $value,
				'group' => $group,
			);
		}
		$this->data['customVars'][ $type ][ $key ][ $position ] = $value;
	}

	/**
	 * Set custom value for position fro array of values
	 *
	 * @param array       $values
	 * @param int         $position
	 * @param string|null $group
	 * @param string|null $type
	 *
	 * @return void
	 */
	public function setCustomVarsAtPosition( $values, $position, $group = null, $type = null ) {
		foreach ( $values as $key => $value ) {
			$this->setCustomVarAtPosition(
				$key,
				$value,
				$position,
				$group,
				$type
			);
		}
	}

	/**
	 * Set custom param without type key
	 *
	 * @param string|array $keyOrValues
	 * @param string|null  $value
	 *
	 * @return $value
	 */
	public function setCustomVarWithoutType( $keyOrValues, $value = null ) {
		if ( is_array( $keyOrValues ) ) {
			if ( ! isset( $this->data['customVars'] ) ) {
				$this->data['customVars'] = $keyOrValues;
			}

			$this->data['customVars'] = array_merge(
				$this->data['customVars'],
				$keyOrValues
			);
			return $keyOrValues;
		}
		$this->data['customVars'][ $keyOrValues ] = $value;
		return $value;
	}
	/**
	 * Set additional param
	 *
	 * @param string|array $keyOrValues
	 * @param string|null  $value
	 *
	 * @return $value
	 */
	public function setAdditionalParameters( $keyOrValues, $value = null ) {
		if ( is_array( $keyOrValues ) ) {
			if ( ! isset( $this->data['customParameters'] ) ) {
				$this->data['customParameters'] = $keyOrValues;
			}

			$this->data['customParameters'] = array_merge(
				$this->data['customParameters'],
				$keyOrValues
			);
			return $keyOrValues;
		}
		if ( is_scalar( $keyOrValues ) ) {
			$this->data['customParameters'][ $keyOrValues ] = $value;
		}
		return $value;
	}

	/**
	 * Set Service action and function
	 *
	 * @param string      $action
	 * @param string|null $version
	 *
	 * @return void
	 */
	protected function setServiceActionAndVersion( $action, $version = null ) {
		if ( $version === null ) {
			$version = $this->version;
		}
		$this->setService( 'action', $action );
		$this->setService( 'version', $version );
	}

	/**
	 * Set service type, action and version
	 *
	 * @param string      $type
	 * @param string|null $action
	 * @param int|null    $version
	 *
	 * @return void
	 */
	protected function setServiceTypeActionAndVersion( $type, $action = null, $version = null ) {
		$this->setType( $type );

		if ( $action === null ) {
			return;
		}

		$this->setServiceActionAndVersion( $action, $version );
	}

	/**
	 * Set main parameters for type
	 *
	 * @param string $type pay|capture|refund
	 *
	 * @return void
	 */
	private function setMainParametersForRequestType( $type = self::TYPE_PAY ) {
		if ( is_int( $this->real_order_id ) ) {
			$this->setAdditionalParameters( 'real_order_id', $this->real_order_id );
		}
		$this->setParameter( 'currency', $this->currency );
		$this->setParameter( 'amountDebit', $this->amountDedit );
		$this->setParameter( 'amountCredit', $this->amountCredit );
		$this->setParameter( 'invoice', $this->invoiceId );
		$this->setParameter( 'order', $this->orderId );
		$this->setParameter(
			'description',
			preg_replace( '/\{invoicenumber\}/', $this->invoiceId, $this->description )
		);
		$this->setParameter( 'returnUrl', $this->returnUrl );
		$this->setParameter( 'mode', $this->mode );
		$this->setParameter( 'channel', $this->channel );

		if ( in_array( $type, array( self::TYPE_REFUND, self::TYPE_CAPTURE ) ) ) {
			$this->setParameter( 'OriginalTransactionKey', $this->OriginalTransactionKey );
		}
		if ( $type === self::TYPE_REFUND ) {
			$this->setParameter( 'invoice', $this->getInvoiceNumber() );
		}
	}

	/**
	 * Get real order id
	 *
	 * @return int
	 */
	public function getRealOrderId() {
		if ( is_int( $this->real_order_id ) ) {
			return $this->real_order_id;
		}
		return $this->orderId;
	}

	/**
	 * Populate generic fields for a PayInInstallments
	 *
	 * @access public
	 * @return callable $this->PayGlobal()
	 */
	public function PayInInstallments() {
		$this->setServiceActionAndVersion( 'PayInInstallments' );
		return $this->PayGlobal();
	}
	/**
	 * Populate generic fields for a authorize
	 *
	 * @access public
	 * @return callable $this->RefundGlobal()
	 */
	public function Authorize() {
		$this->setServiceActionAndVersion( 'Authorize' );
		return $this->PayGlobal();
	}

	/**
	 * Populate generic fields in $customVars() array
	 *
	 * @access public
	 * @param array $customeVars defaults to empty array
	 * @return callable $this->PayGlobal()
	 */
	public function Pay() {
		$this->setServiceActionAndVersion( 'Pay' );
		return $this->PayGlobal();
	}

	/**
	 * Populate generic fields for a refund
	 *
	 * @access public
	 * @return callable $this->RefundGlobal()
	 */
	public function Refund() {
		$this->setServiceActionAndVersion( 'Refund' );
		return $this->RefundGlobal();
	}

	/**
	 * Send request and get response
	 *
	 * @param string $action pay | capture | refund
	 *
	 * @return BuckarooResponse
	 */
	private function executeRequestOfType( $type ) {
		$this->setMainParametersForRequestType( $type );
		return BuckarooResponseFactory::getResponse(
			( new BuckarooSoap( $this->data ) )->transactionRequest( $this->requestType )
		);
	}

	/**
	 * Build soap request for payment and get response
	 *
	 * @access protected
	 * @return callable BuckarooResponseFactory::getResponse($soap->transactionRequest())
	 */
	protected function PayGlobal() {
		add_action( 'woocommerce_before_checkout_process', array( $this, 'order_number_shortcode' ) );
		return $this->executeRequestOfType( self::TYPE_PAY );
	}

	/**
	 * Build soap request for payment and get response
	 *
	 * @access protected
	 * @return callable BuckarooResponseFactory::getResponse($soap->transactionRequest())
	 */
	protected function CaptureGlobal() {
		return $this->executeRequestOfType( self::TYPE_CAPTURE );
	}

	/**
	 * Build soap request for refund and get response
	 *
	 * @access protected
	 * @return callable BuckarooResponseFactory::getResponse($soap->transactionRequest())
	 */
	protected function RefundGlobal() {
		return $this->executeRequestOfType( self::TYPE_REFUND );
	}

	/**
	 * Calculate checksum from iban and confirm validity of iban
	 *
	 * @access public
	 * @param string $iban
	 * @return boolean
	 */
	public static function isIBAN( $iban ) {
		// Normalize input (remove spaces and make upcase)
		$iban = strtoupper( str_replace( ' ', '', $iban ) );

		if ( preg_match( '/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $iban ) ) {
			$country = substr( $iban, 0, 2 );
			$check   = intval( substr( $iban, 2, 2 ) );
			$account = substr( $iban, 4 );

			// To numeric representation
			$search = range( 'A', 'Z' );
			foreach ( range( 10, 35 ) as $tmp ) {
				$replace[] = strval( $tmp );
			}
			$numstr = str_replace( $search, $replace, $account . $country . '00' );

			// Calculate checksum
			$checksum = intval( substr( $numstr, 0, 1 ) );
			for ( $pos = 1; $pos < strlen( $numstr ); $pos++ ) {
				$checksum *= 10;
				$checksum += intval( substr( $numstr, $pos, 1 ) );
				$checksum %= 97;
			}

			return ( ( 98 - $checksum ) == $check );
		} else {
			return false;
		}
	}

	/**
	 * @param $data
	 * @throws Exception
	 */
	public function checkRefundData( $data ) {
		// Check if order is refundable
		$order         = wc_get_order( $this->getRealOrderId() );
		$items         = $order->get_items();
		$shippingItems = $order->get_items( 'shipping' );
		$feeItems      = $order->get_items( 'fee' );

		$shippingCostWithoutTax  = (float) $order->get_shipping_total();
		$shippingTax             = (float) $order->get_shipping_tax();
		$shippingCosts           = roundAmount( $shippingCostWithoutTax ) + roundAmount( $shippingTax );
		$shippingRefundedCosts   = 0.00;
		$shippingAlreadyRefunded = $order->get_total_shipping_refunded();

		foreach ( $items as $item_id => $item_data ) {
			if ( $items[ $item_id ] instanceof WC_Order_Item_Product && isset( $data[ $item_id ] ) ) {

				$orderItemRefunded = $order->get_total_refunded_for_item( $item_id );
				$itemTotal         = $items[ $item_id ]->get_total();
				$itemQuantity      = $items[ $item_id ]->get_quantity();

				$tax   = $items[ $item_id ]->get_taxes();
				$taxId = 3;

				if ( ! empty( $tax['total'] ) ) {
					foreach ( $tax['total'] as $key => $value ) {
						$taxId = $key;
					}
				}

				$itemTax         = $items[ $item_id ]->get_total_tax();
				$itemRefundedTax = $order->get_tax_refunded_for_item( $item_id, $taxId );

				if ( $itemTotal < $orderItemRefunded ) {
					throw new Exception( 'Incorrect entered product price. Please check refund product price' );
				}

				if ( $itemTax < $itemRefundedTax ) {
					throw new Exception( 'Incorrect entered product price. Please check refund tax amount' );
				}

				if ( empty( $itemRefundedTax ) && ! empty( $data[ $item_id ]['tax'] ) ) {
					throw new Exception( 'Incorrect entered product price. Please check refund tax amount' );
				}

				$this->checkRefundDataCommon2( $data, $order, $item_id, $itemQuantity, $itemRefundedTax, $itemTax );
			}
		}

		$this->checkRefundDataCommon( $data, $shippingItems, $shippingRefundedCosts, $feeItems, $order, $shippingAlreadyRefunded, $shippingCosts, false );
	}

	/**
	 *
	 */
	public function getOrderRefundData( $order = null, $line_item_totals = null, $line_item_tax_totals = null, $line_item_qtys = null ) {

		$orderRefundData = array();

		if ( $line_item_qtys === null ) {
			$line_item_qtys = buckaroo_request_sanitized_json( 'line_item_qtys' );
		}

		if ( $line_item_totals === null ) {
			$line_item_totals = buckaroo_request_sanitized_json( 'line_item_totals' );
		}

		if ( $line_item_tax_totals === null ) {
			$line_item_tax_totals = buckaroo_request_sanitized_json( 'line_item_tax_totals' );
		}

		foreach ( $line_item_totals as $key => $value ) {
			if ( ! empty( $value ) ) {
				$orderRefundData[ $key ]['total'] = $value;
			}
		}

		foreach ( $line_item_tax_totals as $key => $keyItem ) {
			foreach ( $keyItem as $taxItem => $taxItemValue ) {
				if ( ! empty( $taxItemValue ) ) {
					if ( empty( $order ) ) {
						$order = wc_get_order( $this->getRealOrderId() );
					}
					$item             = $order->get_item( $key );
					$taxItemFromOrder = $item->get_taxes();

					if ( ! isset( $taxItemFromOrder['total'][ $taxItem ] ) ) {
						throw new Exception( 'Incorrect entered product price. Please check refund tax amount' );
					}
					if ( ! isset( $orderRefundData[ $key ]['tax'] ) ) {
						$orderRefundData[ $key ]['tax'] = 0;
					}
					$orderRefundData[ $key ]['tax'] += $taxItemValue;
				}
			}
		}
		if ( ! empty( $line_item_qtys ) ) {
			foreach ( $line_item_qtys as $key => $value ) {
				if ( empty( $value ) ) {
					continue;
				}
				$orderRefundData[ $key ]['qty'] = $value;
			}
		}

		$orderRefundData['totalRefund'] = 0;
		foreach ( $orderRefundData as $key => $item ) {
			if ( $key != 'totalRefund' ) {
				if ( ! isset( $orderRefundData[ $key ]['tax'] ) ) {
					$orderRefundData[ $key ]['tax'] = 0;
				}
				$orderRefundData['totalRefund'] += $orderRefundData[ $key ]['total'] + $orderRefundData[ $key ]['tax'];
			}
		}

		return $orderRefundData;
	}

	/**
	 * Get invoice number for refund
	 *
	 * @return string
	 */
	private function getInvoiceNumber() {
		return $this->invoiceId;
	}

	public function order_number_shortcode() {
		return $this->data['description'] . ' ' . $this->invoiceId;
	}

	protected function PayOrAuthorizeCommon( $products, $billing, $shipping ) {
		$this->setCustomVarsAtPosition( $billing, 0, 'BillingCustomer' );
		$this->setCustomVarsAtPosition( $shipping, 1, 'ShippingCustomer' );

		// Merge products with same SKU
		$mergedProducts = array();
		foreach ( $products as $product ) {
			if ( ! isset( $mergedProducts[ $product['ArticleId'] ] ) ) {
				$mergedProducts[ $product['ArticleId'] ] = $product;
			} else {
				$mergedProducts[ $product['ArticleId'] ]['ArticleQuantity'] += 1;
			}
		}

		return $mergedProducts;
	}

	public function checkRefundDataAp( $data ) {
		// Check if order is refundable
		foreach ( $data as $itemKey ) {
			if ( empty( $itemKey['total'] ) && ! empty( $itemKey['tax'] ) ) {
				throw new Exception( 'Tax only cannot be refund' );
			}
		}
		$order         = wc_get_order( $this->getRealOrderId() );
		$items         = $order->get_items();
		$shippingItems = $order->get_items( 'shipping' );
		$feeItems      = $order->get_items( 'fee' );

		$shippingCostWithoutTax  = (float) $order->get_shipping_total();
		$shippingTax             = (float) $order->get_shipping_tax();
		$shippingCosts           = roundAmount( $shippingCostWithoutTax ) + roundAmount( $shippingTax );
		$shippingRefundedCosts   = 0.00;
		$shippingAlreadyRefunded = $order->get_total_shipping_refunded();

		foreach ( $items as $item_id => $item_data ) {
			if ( $items[ $item_id ] instanceof WC_Order_Item_Product && isset( $data[ $item_id ] ) ) {

				$itemTotal    = $items[ $item_id ]->get_total();
				$itemQuantity = $items[ $item_id ]->get_quantity();
				$itemPrice    = roundAmount( $itemTotal / $itemQuantity );

				$tax   = $items[ $item_id ]->get_taxes();
				$taxId = 3;

				if ( ! empty( $tax['total'] ) ) {
					foreach ( $tax['total'] as $key => $value ) {
						$taxId = $key;
					}
				}

				$itemTax         = $items[ $item_id ]->get_total_tax();
				$itemRefundedTax = $order->get_tax_refunded_for_item( $item_id, $taxId );
				// FOR AFTERPAY
				if ( empty( $data[ $item_id ]['qty'] ) ) {
					throw new Exception( 'Product quantity doesn`t choose' );
				}

				// FOR AFTERPAY
				if ( (float) $itemPrice * $data[ $item_id ]['qty'] !== (float) roundAmount( $data[ $item_id ]['total'] ) ) {
					throw new Exception( 'Incorrect entered product price. Please check refund product price and tax amounts' );
				}

				$this->checkRefundDataCommon2( $data, $order, $item_id, $itemQuantity, $itemRefundedTax, $itemTax );
			}
		}

		$this->checkRefundDataCommon( $data, $shippingItems, $shippingRefundedCosts, $feeItems, $order, $shippingAlreadyRefunded, $shippingCosts, true );
	}

	private function checkRefundDataCommon( $data, $shippingItems, $shippingRefundedCosts, $feeItems, $order, $shippingAlreadyRefunded, $shippingCosts, $checkFeeRefunded ) {
		foreach ( $shippingItems as $shipping_item_id => $item_data ) {
			if ( $shippingItems[ $shipping_item_id ] instanceof WC_Order_Item_Shipping && isset( $data[ $shipping_item_id ] ) ) {
				if ( array_key_exists( 'total', $data[ $shipping_item_id ] ) ) {
					$shippingRefundedCosts += $data[ $shipping_item_id ]['total'];
				}
				if ( array_key_exists( 'tax', $data[ $shipping_item_id ] ) ) {
					$shippingRefundedCosts += $data[ $shipping_item_id ]['tax'];
				}
			}
		}

		foreach ( $feeItems as $item_id => $item_data ) {
			$feeRefunded = $order->get_qty_refunded_for_item( $item_id, 'fee' );
			$feeCost     = $feeItems[ $item_id ]->get_total();
			$feeTax      = $feeItems[ $item_id ]->get_taxes();
			if ( ! empty( $feeTax['total'] ) ) {
				foreach ( $feeTax['total'] as $taxFee ) {
					$feeCost += roundAmount( (float) $taxFee );
				}
			}
			if ( $checkFeeRefunded && ( $feeRefunded > 1 ) ) {
				throw new Exception( 'Payment fee already refunded' );
			}
			if ( ! empty( $data[ $item_id ]['total'] ) ) {
				$totalFeePrice = roundAmount( (float) $data[ $item_id ]['total'] + (float) $data[ $item_id ]['tax'] );
				if ( abs( $totalFeePrice ) - abs( $feeCost ) < 0 && abs( $totalFeePrice - $feeCost ) > 0.01 ) {
					throw new Exception( 'Enter valid payment fee:' . $feeCost . esc_attr( get_woocommerce_currency() ) );
				} elseif ( abs( $feeCost ) - abs( $totalFeePrice ) < 0 && abs( $feeCost - $totalFeePrice ) > 0.01 ) {
					$balance = $feeCost - $totalFeePrice;
					throw new Exception( 'Please add ' . $balance . ' ' . esc_attr( get_woocommerce_currency() ) . ' to full refund payment fee cost' );
				}
			}
		}
		if ( $shippingAlreadyRefunded > $shippingCosts ) {
			throw new Exception( 'Shipping price already refunded' );
		}
		if ( ( (float) $shippingCosts !== (float) $shippingRefundedCosts || abs( $shippingCosts - $shippingRefundedCosts ) > 0.01 ) && ! empty( $shippingRefundedCosts ) ) {
			throw new Exception( 'Incorrect refund shipping price. Please check refund shipping price and tax amounts' );
		}
	}

	private function checkRefundDataCommon2( $data, $order, $item_id, $itemQuantity, $itemRefundedTax, $itemTax ) {
		if ( ! empty( $data[ $item_id ]['qty'] ) ) {
			$item_refunded = $order->get_qty_refunded_for_item( $item_id );
			if ( $itemQuantity === abs( $item_refunded ) - $data[ $item_id ]['qty'] ) {
				throw new Exception( 'Product already refunded' );
			} elseif ( $itemQuantity < abs( $item_refunded ) ) {
				$availableRefundQty = $itemQuantity - ( abs( $item_refunded ) - $data[ $item_id ]['qty'] );
				$message            = $availableRefundQty . ' item(s) can be refund';
				throw new Exception( $message );
			}
		}

		if ( roundAmount( $itemRefundedTax ) - roundAmount( $itemTax ) > 0.01 ) {
			throw new Exception( 'Incorrect refund tax price' );
		}
	}
}
