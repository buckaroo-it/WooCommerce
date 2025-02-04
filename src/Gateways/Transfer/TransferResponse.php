<?php

namespace Buckaroo\Woocommerce\Gateways\Transfer;

use Buckaroo\Woocommerce\ResponseParser\IGatewayResponse;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;

class TransferResponse implements IGatewayResponse {

	protected ResponseParser $responseParser;

	public function __construct( ResponseParser $responseParser ) {
		$this->responseParser = $responseParser;
		$this->updateMeta();
	}

	/**
	 * @access protected
	 */
	protected function updateMeta(): void {

		if ( isset( $_POST['brq_ordernumber'] ) ) {
			$order_id = $this->responseParser->getOrderNumber();

			if ( $val = $this->responseParser->getService( 'bic' ) ) {
				update_post_meta( $order_id, 'buckaroo_BIC', $val );
			}

			if ( $val = $this->responseParser->getService( 'iban' ) ) {
				update_post_meta( $order_id, 'buckaroo_IBAN', $val );
			}

			if ( $val = $this->responseParser->getService( 'accountholdername' ) ) {
				update_post_meta( $order_id, 'buckaroo_accountHolderName', $val );
			}

			if ( $val = $this->responseParser->getService( 'bankaccount' ) ) {
				update_post_meta( $order_id, 'buckaroo_bankAccount', $val );
			}

			if ( $val = $this->responseParser->getService( 'accountholdercity' ) ) {
				update_post_meta( $order_id, 'buckaroo_accountHolderCity', $val );
			}

			if ( $val = $this->responseParser->getService( 'accountholdercountry' ) ) {
				update_post_meta( $order_id, 'buckaroo_accountHolderCountry', $val );
			}

			if ( $val = $this->responseParser->getService( 'paymentreference' ) ) {
				update_post_meta( $order_id, 'buckaroo_paymentReference', $val );
			}
		}
	}

	public function toResponse(): ResponseParser {
		return $this->responseParser;
	}
}
