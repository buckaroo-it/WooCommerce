<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooKlarna extends BuckarooPaymentMethod {
	public $BillingGender;
	public $BillingInitials;
	public $BillingLastName;
	public $BillingBirthDate;
	public $BillingStreet;
	public $BillingHouseNumber;
	public $BillingHouseNumberSuffix;
	public $BillingPostalCode;
	public $BillingCity;
	public $BillingCountry;
	public $BillingEmail;
	public $BillingPhoneNumber;
	public $BillingLanguage;
	public $IdentificationNumber;
	public $AddressesDiffer;
	public $ShippingGender;
	public $ShippingInitials;
	public $ShippingLastName;
	public $ShippingBirthDate;
	public $ShippingStreet;
	public $ShippingHouseNumber;
	public $ShippingHouseNumberSuffix;
	public $ShippingPostalCode;
	public $ShippingCity;
	public $ShippingCountryCode;
	public $ShippingEmail;
	public $ShippingPhoneNumber;
	public $ShippingLanguage;
	public $CustomerIPAddress;
	public $Accept;
	public $BillingFirstName;
	public $ShippingFirstName;

	private $paymentFlow;
	private $billingCategory;
	private $shippingCategory;
	/**
	 * @access public
	 * @param string $type
	 */
	public function __construct( $type = 'klarna' ) {
		$this->type    = $type;
		$this->version = '0';
	}

	public function setPaymentFlow( $paymentFlow ) {
		$this->paymentFlow = $paymentFlow;
	}
	public function getPaymentFlow() {
		return $this->paymentFlow;
	}

	public function setBillingCategory( $category ) {
		$this->billingCategory = $category;
	}

	public function getBillingCategory() {
		return $this->billingCategory;
	}

	public function setShippingCategory( $category ) {
		$this->shippingCategory = $category;
	}

	public function getShippingCategory() {
		return $this->shippingCategory;
	}
	/**
	 * @access public
	 * @param array $products
	 * @return callable parent::Pay();
	 */
	public function paymentAction( $products = array() ) {

		$this->setServiceActionAndVersion( $this->getPaymentFlow() );

		$billing  = array(
			'Category'     => ! empty( $this->getBillingCategory() ) ? 'B2B' : 'B2C',
			'FirstName'    => $this->BillingFirstName,
			'LastName'     => $this->BillingLastName,
			'Street'       => $this->BillingStreet,
			'StreetNumber' => $this->BillingHouseNumber . ' ',
			'PostalCode'   => $this->BillingPostalCode,
			'City'         => $this->BillingCity,
			'Country'      => $this->BillingCountry,
			'Email'        => $this->BillingEmail,
			'Gender'       => $this->BillingGender,
			'Phone'        => $this->BillingPhoneNumber,
		);
		$shipping = array(
			'Category'     => ! empty( $this->getShippingCategory() ) ? 'B2B' : 'B2C',
			'FirstName'    => $this->diffAddress( $this->ShippingFirstName, $this->BillingFirstName ),
			'LastName'     => $this->diffAddress( $this->ShippingLastName, $this->BillingLastName ),
			'Street'       => $this->diffAddress( $this->ShippingStreet, $this->BillingStreet ),
			'StreetNumber' => $this->diffAddress( $this->ShippingHouseNumber, $this->BillingHouseNumber ) . ' ',
			'PostalCode'   => $this->diffAddress( $this->ShippingPostalCode, $this->BillingPostalCode ),
			'City'         => $this->diffAddress( $this->ShippingCity, $this->BillingCity ),
			'Country'      => $this->diffAddress( $this->ShippingCountryCode, $this->BillingCountry ),
			'Email'        => $this->BillingEmail,
			'Gender'       => $this->diffAddress( $this->ShippingGender, $this->BillingGender ),
			'Phone'        => $this->BillingPhoneNumber,
		);

		if ( ! empty( $this->BillingHouseNumberSuffix ) ) {
			$billing['StreetNumberAdditional'] = $this->BillingHouseNumberSuffix;
		} else {
			unset( $this->BillingHouseNumberSuffix );
		}

		if ( ( $this->AddressesDiffer == 'TRUE' ) && ! empty( $this->ShippingHouseNumberSuffix ) ) {
			$shipping['StreetNumberAdditional'] = $this->ShippingHouseNumberSuffix;
		} elseif ( $this->AddressesDiffer !== 'TRUE' && ! empty( $this->BillingHouseNumberSuffix ) ) {
			$shipping['StreetNumberAdditional'] = $this->BillingHouseNumberSuffix;
		} else {
			unset( $this->ShippingHouseNumberSuffix );
		}

		$this->setCustomVarsAtPosition( $billing, 0, 'BillingCustomer' );
		$this->setCustomVarsAtPosition( $shipping, 1, 'ShippingCustomer' );

		foreach ( $products as $pos => $product ) {
			$this->setDefaultProductParams( $product, $pos );
		}

		return parent::PayGlobal();
	}

	private function diffAddress( $shippingField, $billingField ) {
		if ( $this->AddressesDiffer == 'TRUE' ) {
			return $shippingField;
		}
		return $billingField;
	}

	private function setDefaultProductParams( $product, $position ) {

		$productData = array(
			'Description'    => $product['description'],
			'Identifier'     => $product['identifier'],
			'Quantity'       => $product['quantity'],
			'GrossUnitprice' => $product['price'],
			'VatPercentage'  => $product['vatPercentage'],

		);

		if ( isset( $product['url'] ) && ! empty( trim( $product['url'] ) ) ) {
			$productData['Url'] = $product['url'];
		}

		if ( isset( $product['imgUrl'] ) && ! empty( $product['imgUrl'] ) ) {
			$productData['ImageUrl'] = $product['imgUrl'];
		}

		$this->setCustomVarsAtPosition(
			$productData,
			$position,
			'Article'
		);
	}
}
