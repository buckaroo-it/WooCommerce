<?php
require_once __DIR__ . '/../paymentmethod.php';

/**
 * @package Buckaroo
 */
class BuckarooAfterPay extends BuckarooPaymentMethod {

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
	public $ShippingCosts;
	public $CustomerAccountNumber;
	public $CustomerIPAddress;
	public $Accept;
	public $B2B;
	public $CompanyCOCRegistration;
	public $CompanyName;
	public $CostCentre;
	public $VatNumber;

	/**
	 * @access public
	 * @param string $type
	 */
	public function __construct( $type = 'afterpaydigiaccept' ) {
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
		$this->data['customVars'][ $this->type ]['BillingInitials']          = $this->BillingInitials;
		$this->data['customVars'][ $this->type ]['BillingLastName']          = $this->BillingLastName;
		$this->data['customVars'][ $this->type ]['BillingBirthDate']         = ( $this->B2B ) ? '01-01-1900' : $this->BillingBirthDate;
		$this->data['customVars'][ $this->type ]['BillingStreet']            = $this->BillingStreet;
		$this->data['customVars'][ $this->type ]['BillingHouseNumber']       = isset( $this->BillingHouseNumber ) ? $this->BillingHouseNumber . ' ' : $this->BillingHouseNumber;
		$this->data['customVars'][ $this->type ]['BillingHouseNumberSuffix'] = $this->BillingHouseNumberSuffix;
		$this->data['customVars'][ $this->type ]['BillingPostalCode']        = $this->BillingPostalCode;
		$this->data['customVars'][ $this->type ]['BillingCity']              = $this->BillingCity;
		$this->data['customVars'][ $this->type ]['BillingCountry']           = $this->BillingCountry;
		$this->data['customVars'][ $this->type ]['BillingEmail']             = $this->BillingEmail;
		$this->data['customVars'][ $this->type ]['BillingPhoneNumber']       = $this->BillingPhoneNumber;
		$this->data['customVars'][ $this->type ]['BillingLanguage']          = $this->BillingLanguage;
		$this->data['customVars'][ $this->type ]['AddressesDiffer']          = $this->AddressesDiffer;
		if ( $this->AddressesDiffer == 'TRUE' ) {
			$this->setCommonShippingInfo();
		}
		if ( $this->B2B == 'TRUE' ) {
			$this->data['customVars'][ $this->type ]['B2B']                    = $this->B2B;
			$this->data['customVars'][ $this->type ]['CompanyCOCRegistration'] = $this->CompanyCOCRegistration;
			$this->data['customVars'][ $this->type ]['CompanyName']            = $this->CompanyName;
			$this->data['customVars'][ $this->type ]['CostCentre']             = $this->CostCentre;
			$this->data['customVars'][ $this->type ]['VatNumber']              = $this->VatNumber;
		}
		$this->data['customVars'][ $this->type ]['ShippingLanguage'] = $this->ShippingLanguage;
		if ( $this->type == 'afterpayacceptgiro' ) {
			$this->data['customVars'][ $this->type ]['CustomerAccountNumber'] = $this->CustomerAccountNumber;
		}
		if ( $this->ShippingCosts > 0 ) {
			$this->data['customVars'][ $this->type ]['ShippingCosts'] = $this->ShippingCosts;
		}
		$this->data['customVars'][ $this->type ]['CustomerIPAddress'] = $this->CustomerIPAddress;
		$this->data['customVars'][ $this->type ]['Accept']            = $this->Accept;

		foreach ( $products as $pos => $product ) {
			if ( isset( $product['type'] ) && $product['type'] === 'shipping' ) {
				continue;
			}

			$this->setDefaultProductParams( $product, $pos );
		}

		$this->setCommonShippingInfo();

		return parent::$action();
	}

	private function setDefaultProductParams( $product, $position ) {

		$productData = array(
			'ArticleDescription' => $product['description'],
			'ArticleId'          => $product['identifier'],
			'ArticleQuantity'    => $product['quantity'],
			'ArticleUnitprice'   => $product['price'],
			'ArticleVatcategory' => $product['vatCategory'],

		);

		$this->setCustomVarsAtPosition(
			$productData,
			$position,
			'Article'
		);
	}

	private function setProducts( $products, $i ) {
		foreach ( $products as $p ) {
			$this->data['customVars'][ $this->type ]['ArticleDescription'][ $i - 1 ]['value'] = $p['ArticleDescription'];
			$this->data['customVars'][ $this->type ]['ArticleDescription'][ $i - 1 ]['group'] = $i;
			$this->data['customVars'][ $this->type ]['ArticleId'][ $i - 1 ]['value']          = $p['ArticleId'];
			$this->data['customVars'][ $this->type ]['ArticleId'][ $i - 1 ]['group']          = $i;
			$this->data['customVars'][ $this->type ]['ArticleQuantity'][ $i - 1 ]['value']    = $p['ArticleQuantity'];
			$this->data['customVars'][ $this->type ]['ArticleQuantity'][ $i - 1 ]['group']    = $i;
			$this->data['customVars'][ $this->type ]['ArticleUnitprice'][ $i - 1 ]['value']   = $p['ArticleUnitprice'];
			$this->data['customVars'][ $this->type ]['ArticleUnitprice'][ $i - 1 ]['group']   = $i;
			$this->data['customVars'][ $this->type ]['ArticleVatcategory'][ $i - 1 ]['value'] = $p['ArticleVatcategory'];
			$this->data['customVars'][ $this->type ]['ArticleVatcategory'][ $i - 1 ]['group'] = $i;
			++$i;
		}
	}

	private function setCommonShippingInfo() {
		$this->data['customVars'][ $this->type ]['ShippingInitials']          = $this->ShippingInitials ?? $this->BillingInitials;
		$this->data['customVars'][ $this->type ]['ShippingLastName']          = $this->ShippingLastName ?? $this->BillingLastName;
		$this->data['customVars'][ $this->type ]['ShippingBirthDate']         = $this->ShippingBirthDate;
		$this->data['customVars'][ $this->type ]['ShippingStreet']            = $this->ShippingStreet;
		$this->data['customVars'][ $this->type ]['ShippingHouseNumber']       = isset( $this->ShippingHouseNumber ) ? $this->ShippingHouseNumber . ' ' : $this->ShippingHouseNumber;
		$this->data['customVars'][ $this->type ]['ShippingHouseNumberSuffix'] = $this->ShippingHouseNumberSuffix;
		$this->data['customVars'][ $this->type ]['ShippingPostalCode']        = $this->ShippingPostalCode;
		$this->data['customVars'][ $this->type ]['ShippingCity']              = $this->ShippingCity;
		$this->data['customVars'][ $this->type ]['ShippingCountryCode']       = $this->ShippingCountryCode;
		$this->data['customVars'][ $this->type ]['ShippingEmail']             = $this->ShippingEmail;
		$this->data['customVars'][ $this->type ]['ShippingPhoneNumber']       = $this->ShippingPhoneNumber;
		$this->data['customVars'][ $this->type ]['ShippingLanguage']          = $this->ShippingLanguage;
	}

	/**
	 * Populate generic fields for a refund
	 *
	 * @access public
	 * * @param array $products
	 * @return callable $this->RefundGlobal()
	 */
	public function AfterPayRefund( $products, $issuer ) {
		$this->setServiceTypeActionAndVersion(
			$issuer,
			'Refund',
			BuckarooPaymentMethod::VERSION_ONE
		);

		// Refunds have to be done on the captures (if authorize/capture is enabled)
		$i = 1;
		$this->setProducts( $products, $i );

		return $this->RefundGlobal();
	}

	/**
	 * @access public
	 * @param array $customVars
	 * @param array $products
	 * @return callable parent::PayGlobal()
	 */
	public function Capture( $customVars = array(), $products = array() ) {
		$this->setServiceTypeActionAndVersion(
			$customVars['payment_issuer'],
			'Capture',
			BuckarooPaymentMethod::VERSION_ONE
		);

		$i = 1;
		$this->setProducts( $products, $i );

		return $this->CaptureGlobal();
	}

	/**
	 * @access public
	 * @return callable parent::checkRefundData($data);
	 * @param $data array
	 * @throws Exception
	 */
	public function checkRefundData( $data ) {
		$this->checkRefundDataAp( $data );
	}
}
