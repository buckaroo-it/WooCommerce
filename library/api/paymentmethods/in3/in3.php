<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooIn3 extends BuckarooPaymentMethod {

	/**
	 * @var Buckaroo_Order_Details
	 */
	protected $order_details;

	/**
	 * @var Buckaroo_Http_Request
	 */
	protected $request;

	protected $articles;

	/**
	 * @access public
	 * @param string $type
	 */
	public function __construct() {
		$this->type    = 'In3';
		$this->version = '1';
	}

	public function setData(
		Buckaroo_Order_Details $order_details,
		array $articles,
		Buckaroo_Http_Request $request
	) {
		$this->order_details = $order_details;
		$this->articles      = $articles;
		$this->request       = $request;
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return void
	 */
	public function Pay( $customVars = array() ) {
		if ( ! $this->order_details instanceof Buckaroo_Order_Details ) {
			return;
		}

		$address = $this->order_details->getShippingAddressComponents();

		$data = array(
			'Street'       => $address['street'],
			'StreetNumber' => $address['house_number'],
			'PostalCode'   => $this->order_details->getShipping( 'postcode' ),
			'City'         => $this->order_details->getShipping( 'city' ),
			'CountryCode'  => $this->order_details->getShipping( 'country' ),
		);

		if ( ! empty( $address['number_addition'] ) ) {
			$data['StreetNumberSuffix'] = $address['number_addition'];
		}

		$this->setCustomVarsAtPosition( $data, 1, 'ShippingCustomer' );

		$address = $this->order_details->getBillingAddressComponents();

		$data = array(

			'CustomerNumber' => get_current_user_id(),
			'FirstName'      => $this->order_details->getBilling( 'first_name' ),
			'LastName'       => $this->order_details->getBilling( 'last_name' ),
			'Initials'       => $this->order_details->getInitials(
				$this->order_details->getBilling( 'first_name' ) . ' ' . $this->order_details->getBilling( 'last_name' )
			),
			'BirthDate'      => date( 'Y-m-d', strtotime( $this->request->request( 'buckaroo-in3-birthdate' ) ) ),
			'Phone'          => $this->getPhoneNumber(),
			'Email'          => $this->order_details->getBilling( 'email' ),
			'Category'       => 'B2C',

			'Street'         => $address['street'],
			'StreetNumber'   => $address['house_number'],
			'PostalCode'     => $this->order_details->getBilling( 'postcode' ),
			'City'           => $this->order_details->getBilling( 'city' ),
			'CountryCode'    => $this->order_details->getBilling( 'country' ),
		);

		if ( ! empty( $address['number_addition'] ) ) {
			$data['StreetNumberSuffix'] = $address['number_addition'];
		}

		$this->setCustomVarsAtPosition( $data, 0, 'BillingCustomer' );

		foreach ( $this->articles as $pos => $product ) {
			$this->setDefaultProductParams( $product, $pos );
		}

		return parent::pay();
	}

	private function getPhoneNumber() {
		$phone = $this->request->request( 'buckaroo-in3-phone' );

		if ( is_scalar( $phone ) && trim( strlen( (string) $phone ) ) > 0 ) {
			return $phone;
		}

		return $this->order_details->getBillingPhone();
	}

	private function setDefaultProductParams( $product, $position ) {

		$productData = array(
			'Description'    => $product['description'],
			'Identifier'     => $product['identifier'],
			'Quantity'       => $product['quantity'],
			'GrossUnitPrice' => $product['price'],
			'VatPercentage'  => $product['vatPercentage'],
		);

		$this->setCustomVarsAtPosition(
			$productData,
			$position,
			'Article'
		);
	}

	/**
	 * Populate generic fields for a refund
	 *
	 * @access public
	 * * @param array $products
	 * @throws Exception
	 * @return callable $this->RefundGlobal()
	 */
	public function In3Refund() {
		$this->setServiceTypeActionAndVersion(
			'In3',
			'Refund',
			BuckarooPaymentMethod::VERSION_ONE
		);

		return $this->RefundGlobal();
	}
}
