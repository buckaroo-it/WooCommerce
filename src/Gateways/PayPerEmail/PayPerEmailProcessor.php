<?php

namespace Buckaroo\Woocommerce\Gateways\PayPerEmail;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class PayPerEmailProcessor extends AbstractPaymentProcessor {

	/** @inheritDoc */
	public function getAction(): string {
		return 'paymentInvitation';
	}

	/** @inheritDoc */
	protected function getMethodBody(): array {
        $payload = array(
            'email'                 => $this->request->input(
                'buckaroo-payperemail-email',
                $this->getAddress( 'billing', 'email' ),
            ),
            'customer'              => array(
                'firstName' => $this->request->input(
                    'buckaroo-payperemail-firstname',
                    $this->getAddress( 'billing', 'first_name' ),
                ),
                'lastName'  => $this->request->input(
                    'buckaroo-payperemail-lastname',
                    $this->getAddress( 'billing', 'last_name' ),
                ),
                'gender'    => $this->request->input( 'buckaroo-payperemail-gender', 0 ),

            ),
            'expirationDate'        => $this->getExpirationDate(),
            'paymentMethodsAllowed' => $this->getAllowedMethods(),
        );

        if ( isset( $this->gateway->usePayPerLink ) && $this->gateway->usePayPerLink === true ) {
            $payload['merchantSendsEmail'] = true;
        }

        return $payload;
	}

	private function getExpirationDate(): string {
		$payperemailExpireDays = $this->gateway->get_option( 'expirationDate' );

		if ( ! is_scalar( $payperemailExpireDays ) ) {
			return '';
		}

		return date( 'Y-m-d', time() + (int) $payperemailExpireDays * 86400 );
	}

	private function getAllowedMethods(): string {
		$methods = $this->gateway->get_option( 'paymentmethodppe' );
		if ( is_array( $methods ) ) {
			return implode( ',', $methods );
		}
		return '';
	}
}
