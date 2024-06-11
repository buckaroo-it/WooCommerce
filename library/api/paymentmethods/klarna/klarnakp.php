<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooKlarnaKp extends BuckarooPaymentMethod {


	public function __construct() {
		$this->type    = 'klarnakp';
		$this->version = 0;
	}
	/**
	 * @var Buckaroo_Order_Details
	 */
	protected $order_details;

	/**
	 * @var Buckaroo_Http_Request
	 */
	protected $request;

	/**
	 * Reserve order
	 *
	 * @param Buckaroo_Order_Details $order_details
	 * @param Buckaroo_Http_Request  $request
	 *
	 * @return void
	 */
	public function reserve(
		Buckaroo_Order_Details $order_details,
		Buckaroo_Http_Request $request
	) {
		$this->order_details = $order_details;
		$this->request       = $request;

		$this->setServiceActionAndVersion( 'Reserve' );
		$this->setRequestType( BuckarooPaymentMethod::REQUEST_TYPE_DATA_REQUEST );

		$this->setCustomVar(
			array_merge(
				$this->get_billing(),
				$this->get_shipping_same_as_billing()
			)
		);
		if ( ! $this->is_shipping_same_as_billing() ) {
			$this->setCustomVar(
				$this->get_shipping()
			);
		}

		$articles = array_merge(
			$this->get_article_list(),
			$this->get_fees(),
			$this->get_shipping_fees()
		);

		foreach ( $articles as $key => $article ) {
			$this->setCustomVarsAtPosition( $article, $key, 'Article' );
		}
		return $this->PayGlobal();
	}

	/**
	 * Cancel reservation order
	 *
	 * @param Buckaroo_Order_Capture $order_capture
	 * @param string                 $reservation_number
	 *
	 * @return void
	 */
	public function cancel_reservation(
		string $reservation_number
	) {
		$this->setRequestType( BuckarooPaymentMethod::REQUEST_TYPE_DATA_REQUEST );
		$this->setServiceActionAndVersion( 'CancelReservation' );
		$this->setCustomVar( 'ReservationNumber', $reservation_number );
		return $this->PayGlobal();
	}
	/**
	 * Capture order
	 *
	 * @param Buckaroo_Order_Capture $order_capture
	 * @param string                 $reservation_number
	 *
	 * @return void
	 */
	public function capture(
		Buckaroo_Order_Capture $order_capture,
		string $reservation_number
	) {
		foreach ( $this->get_capture_items( $order_capture ) as $key => $item ) {
			$this->setCustomVarsAtPosition( $item, $key, 'Article' );
		}

		$this->setCustomVar( 'ReservationNumber', $reservation_number );
		return $this->Pay();
	}

	/**
	 * Get items that are ready for capture
	 *
	 * @param Buckaroo_Order_Capture $order_capture
	 *
	 * @return array
	 */
	public function get_capture_items( Buckaroo_Order_Capture $order_capture ) {
		$items = array();
		foreach ( $order_capture->get_form_items() as $item ) {

			$qty = $order_capture->get_item_qty( $item->get_line_item_id() );
			if ( $qty > 0 ) {
				$items[] = array(
					'ArticleNumber'   => $item->get_id(),
					'ArticleQuantity' => $qty,
				);
			}
		}

		return $items;
	}
	/**
	 * Get order fees
	 *
	 * @return array
	 */
	protected function get_fees() {
		$fees = array();
		foreach ( $this->order_details->get_fees() as $fee ) {
			$fees[] = array(
				'ArticleTitle'    => $fee->get_title(),
				'ArticleNumber'   => $fee->get_id(),
				'ArticleQuantity' => $fee->get_quantity(),
				'ArticlePrice'    => $fee->get_unit_price(),
				'ArticleVat'      => $fee->get_vat(),
				'ArticleType'     => 'HandlingFee',
			);
		}
		return $fees;
	}

	/**
	 * Get shipping fee
	 *
	 * @return array
	 */
	protected function get_shipping_fees() {
		$fees = array();
		foreach ( $this->order_details->get_shipping_items() as $fee ) {
			$fees[] = array(
				'ArticleTitle'    => $fee->get_title(),
				'ArticleNumber'   => $fee->get_id(),
				'ArticleQuantity' => $fee->get_quantity(),
				'ArticlePrice'    => $fee->get_unit_price(),
				'ArticleVat'      => $fee->get_vat(),
				'ArticleType'     => 'ShipmentFee',
			);
		}
		return $fees;
	}

	/**
	 * Get articles from order
	 *
	 * @return array
	 */
	protected function get_article_list() {
		$articles = array();
		foreach ( $this->order_details->get_products() as $article ) {
			$articles[] = array(
				'ArticleTitle'    => $article->get_title(),
				'ArticleNumber'   => $article->get_id(),
				'ArticleQuantity' => $article->get_quantity(),
				'ArticlePrice'    => $article->get_unit_price(),
				'ArticleVat'      => $article->get_vat(),
				'ArticleType'     => 'General',
			);
		}
		return $articles;
	}
	/**
	 * Get billing data
	 *
	 * @return array
	 */
	protected function get_billing() {
		$address = $this->order_details->getShippingAddressComponents();
		$billing = array(
			'BillingFirstName'   => $this->order_details->getBilling( 'first_name' ),
			'BillingLastName'    => $this->order_details->getBilling( 'last_name' ),
			'BillingStreet'      => $address['street'],
			'BillingPostalCode'  => $this->order_details->getShipping( 'postcode' ),
			'BillingCity'        => $this->order_details->getBilling( 'city' ),
			'BillingCountry'     => $this->order_details->getBilling( 'country' ),
			'BillingPhoneNumber' => $this->order_details->getBillingPhone(),
			'BillingEmail'       => $this->order_details->getBilling( 'email', '' ),
			'OperatingCountry'   => $this->order_details->getBilling( 'country' ),
		);

		if ( strlen( $address['house_number'] ) ) {
			$billing['BillingHouseNumber'] = $address['house_number'];
		}

		if ( strlen( $address['number_addition'] ) ) {
			$billing['BillingHouseNumberSuffix'] = $address['number_addition'];
		}
		return $billing;
	}

	/**
	 * Get shipping data
	 *
	 * @return array
	 */
	protected function get_shipping() {
		$address  = $this->order_details->getShippingAddressComponents();
		$shipping = array(
			'ShippingFirstName'   => $this->order_details->getShipping( 'first_name' ),
			'ShippingLastName'    => $this->order_details->getShipping( 'last_name' ),
			'ShippingStreet'      => $address['street'],
			'ShippingPostalCode'  => $this->order_details->getShipping( 'postcode' ),
			'ShippingCity'        => $this->order_details->getShipping( 'city' ),
			'ShippingCountry'     => $this->order_details->getShipping( 'country' ),
			'ShippingPhoneNumber' => $this->order_details->getShippingPhone(),
			'ShippingEmail'       => $this->order_details->getShipping( 'email', '' ),
		);

		if ( strlen( $address['house_number'] ) ) {
			$shipping['ShippingHouseNumber'] = $address['house_number'];
		}

		if ( strlen( $address['number_addition'] ) ) {
			$shipping['ShippingHouseNumberSuffix'] = $address['number_addition'];
		}
		return $shipping;
	}

	/**
	 * Get shipping same as billing
	 *
	 * @return array
	 */
	public function get_shipping_same_as_billing() {
		return array(
			'ShippingSameAsBilling' => $this->is_shipping_same_as_billing() ? 'true' : 'false',
		);
	}

	/**
	 * Is shipping same as billing
	 *
	 * @return boolean
	 */
	public function is_shipping_same_as_billing() {
		return $this->request->request( 'ship_to_different_address' ) === null;
	}
}
