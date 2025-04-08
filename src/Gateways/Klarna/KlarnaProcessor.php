<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class KlarnaProcessor extends AbstractPaymentProcessor {

	/** @inheritDoc */
	protected function getMethodBody(): array {
		return array_merge_recursive(
			$this->getBilling(),
			$this->getShipping(),
			array( 'articles' => $this->getArticles() )
		);
	}

	public function getAction(): string {
		if ( $this->gateway instanceof KlarnaPiiGateway ) {
			return 'payInInstallments';
		}

		return parent::getAction();
	}

	/**
	 * @return array<mixed>
	 */
	protected function getBilling(): array {
		$streetParts = $this->order_details->get_billing_address_components();
		return array(
			'billing' => array(
				'recipient' => array(
					'category'  => $this->getCategory( 'billing' ),
					'firstName' => $this->getAddress( 'billing', 'first_name' ),
					'lastName'  => $this->getAddress( 'billing', 'last_name' ),
					'gender'    => $this->request->input( $this->gateway->getKlarnaSelector() . '-gender', 'male' ),
				),
				'address'   => array(
					'street'                => $streetParts->get_street(),
					'houseNumber'           => $streetParts->get_house_number(),
					'houseNumberAdditional' => $streetParts->get_number_additional(),
					'zipcode'               => $this->getAddress( 'billing', 'postcode' ),
					'city'                  => $this->getAddress( 'billing', 'city' ),
					'country'               => $this->getAddress( 'billing', 'country' ),
				),
				'phone'     => array(
					'mobile' => $this->getPhone( $this->order_details->get_billing_phone() ),
				),
				'email'     => $this->getAddress( 'billing', 'email' ),
			),
		);
	}

	/**
	 * Get shipping address data
	 *
	 * @return array<mixed>
	 */
	protected function getShipping(): array {
		$streetParts = $this->order_details->get_shipping_address_components();
		return array(
			'shipping' => array(
				'recipient' => array(
					'category'  => $this->getCategory( 'shipping' ),
					'firstName' => $this->getAddress( 'shipping', 'first_name' ),
					'lastName'  => $this->getAddress( 'shipping', 'last_name' ),
					'gender'    => $this->request->input( $this->gateway->getKlarnaSelector() . '-gender', 'male' ),
				),
				'address'   => array(
					'street'                => $streetParts->get_street(),
					'houseNumber'           => $streetParts->get_house_number(),
					'houseNumberAdditional' => $streetParts->get_number_additional(),
					'zipcode'               => $this->getAddress( 'shipping', 'postcode' ),
					'city'                  => $this->getAddress( 'shipping', 'city' ),
					'country'               => $this->getAddress( 'shipping', 'country' ),
				),
				'email'     => $this->getAddress( 'shipping', 'email' ) ?: $this->getAddress( 'billing', 'email' ),
			),
		);
	}

	private function getPhone( string $phone ): string {
		$input_phone = $this->order_details->cleanup_phone(
			$this->request->input( $this->gateway->getKlarnaSelector() . '-phone' )
		);
		if ( strlen( trim( $input_phone ) ) > 0 ) {
			return $input_phone;
		}
		return $phone;
	}

	/**
	 * Get type of request b2b or b2c
	 *
	 * @return string
	 */
	private function getCategory( string $address_type ): string {
		if ( ! $this->isCompanyEmpty( $this->getAddress( $address_type, 'company' ) ) ) {
			return 'B2B';
		}
		return 'B2C';
	}

	/**
	 * Check if company is empty
	 *
	 * @param string $company
	 *
	 * @return boolean
	 */
	public function isCompanyEmpty( string $company = null ): bool {
		return null === $company || strlen( trim( $company ) ) === 0;
	}
}
