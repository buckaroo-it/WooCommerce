<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooAfterPayNew extends BuckarooPaymentMethod {

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
	public $CustomerType;
	public $Accept;
	public $CompanyCOCRegistration;
	public $BillingCompanyName;
	public $ShippingCompanyName;
	public $CostCentre;
	public $VatNumber;

    public $channel;

	/**
	 * @access public
	 * @param string $type
	 */
	public function __construct( $type = 'afterpay' ) {
		$this->type    = $type;
		$this->version = '1';
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @return void
	 */
	public function Pay( $customVars = array() ) {
		return null;
	}

	/**
	 * @access public
	 * @param array $products
	 * @return callable parent::Pay();
	 */
	public function PayOrAuthorizeAfterpay( $products, $action ) {

		$billing         = array(
			'Category'     => 'Person',
			'FirstName'    => $this->BillingInitials,
			'LastName'     => $this->BillingLastName,
			'Street'       => $this->BillingStreet,
			'StreetNumber' => $this->BillingHouseNumber . ' ',
			'PostalCode'   => $this->BillingPostalCode,
			'City'         => $this->BillingCity,
			'Country'      => $this->BillingCountry,
			'Email'        => $this->BillingEmail,
		);
		$shippingCountry = $this->diffAddress( $this->ShippingCountryCode, $this->BillingCountry );
		$shipping        = array(
			'Category'     => 'Person',
			'FirstName'    => $this->diffAddress( $this->ShippingInitials, $this->BillingInitials ),
			'LastName'     => $this->diffAddress( $this->ShippingLastName, $this->BillingLastName ),
			'Street'       => $this->diffAddress( $this->ShippingStreet, $this->BillingStreet ),
			'StreetNumber' => $this->diffAddress( $this->ShippingHouseNumber, $this->BillingHouseNumber ) . ' ',
			'PostalCode'   => $this->diffAddress( $this->ShippingPostalCode, $this->BillingPostalCode ),
			'City'         => $this->diffAddress( $this->ShippingCity, $this->BillingCity ),
			'Country'      => $shippingCountry,
			'Email'        => $this->BillingEmail,
		);

		if ( WC_Gateway_Buckaroo_Afterpaynew::CUSTOMER_TYPE_B2C != $this->CustomerType ) {
			if ( $this->BillingCompanyName !== null && $this->BillingCountry === 'NL' ) {
				$billing = array_merge(
					$billing,
					array(
						'Category'             => 'Company',
						'CompanyName'          => $this->BillingCompanyName,
						'IdentificationNumber' => $this->IdentificationNumber,
					)
				);
			}

			$shippingCompanyName = $this->diffAddress( $this->ShippingCompanyName, $this->BillingCompanyName );
			if ( $shippingCompanyName !== null && $this->ShippingCountryCode === 'NL' ) {
				$shipping = array_merge(
					$shipping,
					array(
						'Category'             => 'Company',
						'CompanyName'          => $shippingCompanyName,
						'IdentificationNumber' => $this->IdentificationNumber,
					)
				);
			}
		}

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

		if ( ( isset( $this->ShippingCountryCode ) && in_array( $this->ShippingCountryCode, array( 'NL', 'BE' ) ) ) || ( ! isset( $this->ShippingCountryCode ) && in_array( $this->BillingCountry, array( 'NL', 'BE' ) ) ) ) {
			// Send parameters (Salutation, BirthDate, MobilePhone and Phone) if shipping country is NL || BE.
			$billing  = array_merge(
				$billing,
				array(
					'BirthDate'   => $this->BillingBirthDate,
					'MobilePhone' => $this->BillingPhoneNumber,
					'Phone'       => $this->BillingPhoneNumber,
				)
			);
			$shipping = array_merge(
				$shipping,
				array(
					'BirthDate'   => $this->BillingBirthDate,
					'MobilePhone' => $this->BillingPhoneNumber,
					'Phone'       => $this->BillingPhoneNumber,
				)
			);
		}

		if ( ( isset( $this->ShippingCountryCode ) && ( $this->ShippingCountryCode == 'FI' ) ) || ( ! isset( $this->ShippingCountryCode ) && ( $this->BillingCountry == 'FI' ) ) ) {
			$shipping['IdentificationNumber'] = $this->IdentificationNumber;
			$billing['IdentificationNumber']  = $this->IdentificationNumber;
		}

		$this->setCustomVarsAtPosition( $billing, 0, 'BillingCustomer' );
		$this->setCustomVarsAtPosition( $shipping, 1, 'ShippingCustomer' );

		foreach ( $products as $pos => $product ) {
			$this->setDefaultProductParams( $product, $pos );
		}

		return parent::$action();
	}
	private function diffAddress( $shippingField, $billingField ) {
		if ( $this->AddressesDiffer == 'TRUE' ) {
			return $shippingField;
		}
		return $billingField;
	}


	/**
	 * Populate generic fields for a refund
	 *
	 * @access public
	 * * @param array $products
	 * @throws Exception
	 * @return callable $this->RefundGlobal()
	 */
	public function AfterPayRefund( $products, $issuer ) {
		$this->setServiceTypeActionAndVersion(
			$issuer,
			'Refund',
			BuckarooPaymentMethod::VERSION_ONE
		);

		foreach ( array_values( $products ) as $pos => $product ) {
			$this->setDefaultProductArticleParams( $product, $pos );
			$this->setCustomVarAtPosition(
				'RefundType',
				( $product['ArticleId'] == BuckarooConfig::SHIPPING_SKU ? 'Refund' : 'Return' ),
				$pos,
				'Article'
			);
		}
		return $this->RefundGlobal();
	}

	/**
	 * @access public
	 * @param array $customVars
	 * * @param array $products
	 * @return callable parent::PayGlobal()
	 */
	public function Capture( $customVars = array(), $products = array() ) {

		$this->setServiceTypeActionAndVersion(
			$customVars['payment_issuer'],
			'Capture',
			BuckarooPaymentMethod::VERSION_ONE
		);

		foreach ( array_values( $products ) as $pos => $product ) {
			$this->setDefaultProductArticleParams( $product, $pos );
		}

		return $this->CaptureGlobal();
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

	private function setDefaultProductArticleParams( $product, $position ) {
		$productData = array(
			'Description'    => $product['ArticleDescription'],
			'Identifier'     => $product['ArticleId'],
			'Quantity'       => $product['ArticleQuantity'],
			'GrossUnitprice' => $product['ArticleUnitprice'],
			'VatPercentage'  => $product['ArticleVatcategory'],
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
	public function checkRefundData( $data ) {
		$this->checkRefundDataAp( $data );
	}
}
