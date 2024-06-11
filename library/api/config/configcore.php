<?php

/**
 * @package Buckaroo
 */
abstract class BuckarooConfigCore {
	const WSDL_URL           = 'https://checkout.buckaroo.nl/soap/soap.svc?wsdl';
	const WSDL_FILE          = '/wsdl/Buckaroo.wsdl';
	const CHANNEL            = 'Web';
	const CHANNEL_BACKOFFICE = 'BackOffice';
	const LOCATION           = 'https://checkout.buckaroo.nl/soap/';
	const LOCATION_TEST      = 'https://testcheckout.buckaroo.nl/soap/';
	const CERTIFICATE_PATH   = 'certificate/';

	public static function get( $key ) {
		$value = '';
		switch ( $key ) {
			case 'BUCKAROO_TEST':
				$value = '1';
				break;
			case 'BUCKAROO_MERCHANT_KEY':
				$value = '';
				break;
			case 'BUCKAROO_SECRET_KEY':
				$value = '';
				break;
			case 'BUCKAROO_CERTIFICATE_THUMBPRINT':
				$value = '';
				break;
			case 'BUCKAROO_CERTIFICATE_PATH':
				$value = '';
				break;
			case 'CULTURE':
				$value = 'en-US';
				break;
			case 'BUCKAROO_CREDITCARD_CARDS':
				$value = 'amex,cartebancaire,cartebleuevisa,dankort,mastercard,postepay,visa,visaelectron,vpay';
				break;
			case 'BUCKAROO_CREDITCARD_ALLOWED_CARDS':
				$value = 'amex,cartebancaire,cartebleuevisa,dankort,mastercard,postepay,visa,visaelectron,vpay';
				break;
			case 'BUCKAROO_GIFTCARD_ALLOWED_CARDS':
				$value = 'westlandbon,ideal,ippies,babygiftcard,bancontact,babyparkgiftcard,beautywellness,boekenbon,boekenvoordeel,designshopsgiftcard,fashioncheque,fashionucadeaukaart,fijncadeau,koffiecadeau,kokenzo,kookcadeau,nationaleentertainmentcard,naturesgift,podiumcadeaukaart,shoesaccessories,webshopgiftcard,wijncadeau,wonenzo,yourgift,vvvgiftcard,parfumcadeaukaart';
				break;
		}
		return $value;
	}

	public static function getSoftware() {
		$Software                  = new Software();
		$Software->PlatformName    = 'API';
		$Software->PlatformVersion = '1';
		$Software->ModuleSupplier  = 'Buckaroo';
		$Software->ModuleName      = 'Plugin';
		$Software->ModuleVersion   = '0.8';
		return $Software;
	}
}
