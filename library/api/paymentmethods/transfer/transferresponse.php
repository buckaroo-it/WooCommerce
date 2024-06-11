<?php

require_once __DIR__ . '/../response.php';

/**
 * @package Buckaroo
 */
class BuckarooTransferResponse extends BuckarooResponse {
	public $BIC                  = '';
	public $IBAN                 = '';
	public $accountHolderName    = '';
	public $accountHolderCountry = '';
	public $paymentReference     = '';
	public $consumerMessage      = array(
		'MustRead'    => '',
		'CultureName' => '',
		'Title'       => '',
		'PlainText'   => '',
		'HtmlText'    => '',
	);

	/**
	 * @access protected
	 */
	protected function _parseSoapResponseChild() {
		if ( isset( $this->_response->Services->Service->ResponseParameter ) && isset( $this->_response->Services->Service->Name ) ) {
			if ( $this->_response->Services->Service->Name == 'transfer' && $this->_response->Services->Service->ResponseParameter[5]->Name == 'PaymentReference' ) {

				$this->BIC                  = $this->_response->Services->Service->ResponseParameter[0]->_;
				$this->IBAN                 = $this->_response->Services->Service->ResponseParameter[1]->_;
				$this->accountHolderName    = $this->_response->Services->Service->ResponseParameter[2]->_;
				$this->accountHolderCity    = $this->_response->Services->Service->ResponseParameter[3]->_;
				$this->accountHolderCountry = $this->_response->Services->Service->ResponseParameter[4]->_;
				$this->paymentReference     = $this->_response->Services->Service->ResponseParameter[5]->_;
			}
		}

		if ( isset( $this->_response->ConsumerMessage ) ) {
			if ( isset( $this->_response->ConsumerMessage->MustRead ) ) {
				$this->consumerMessage['MustRead'] = $this->_response->ConsumerMessage->MustRead;
			}
			if ( isset( $this->_response->ConsumerMessage->CultureName ) ) {
				$this->consumerMessage['CultureName'] = $this->_response->ConsumerMessage->CultureName;
			}
			if ( isset( $this->_response->ConsumerMessage->Title ) ) {
				$this->consumerMessage['Title'] = $this->_response->ConsumerMessage->Title;
			}
			if ( isset( $this->_response->ConsumerMessage->PlainText ) ) {
				$this->consumerMessage['PlainText'] = $this->_response->ConsumerMessage->PlainText;
			}
			if ( isset( $this->_response->ConsumerMessage->HtmlText ) ) {
				$this->consumerMessage['HtmlText'] = $this->_response->ConsumerMessage->HtmlText;
			}
		}
	}

	/**
	 * @access protected
	 */
	protected function _parsePostResponseChild() {

		if ( isset( $_POST['brq_ordernumber'] ) ) {
			$order_id = $this->_setPostVariable( 'brq_ordernumber' );

			if ( isset( $_POST['brq_service_transfer_bic'] ) ) {
				update_post_meta( $order_id, 'buckaroo_BIC', $this->_setPostVariable( 'brq_service_transfer_bic' ) );
			}

			if ( isset( $_POST['brq_service_transfer_iban'] ) ) {
				update_post_meta( $order_id, 'buckaroo_IBAN', $this->_setPostVariable( 'brq_service_transfer_iban' ) );
			}

			if ( isset( $_POST['brq_service_transfer_accountholdername'] ) ) {
				update_post_meta( $order_id, 'buckaroo_accountHolderName', $this->_setPostVariable( 'brq_service_transfer_accountholdername' ) );
			}

			if ( isset( $_POST['brq_service_transfer_bankaccount'] ) ) {
				update_post_meta( $order_id, 'buckaroo_bankAccount', $this->_setPostVariable( 'brq_service_transfer_bankaccount' ) );
			}

			if ( isset( $_POST['brq_service_transfer_accountholdercity'] ) ) {
				update_post_meta( $order_id, 'buckaroo_accountHolderCity', $this->_setPostVariable( 'brq_service_transfer_accountholdercity' ) );
			}

			if ( isset( $_POST['brq_service_transfer_accountholdercountry'] ) ) {
				update_post_meta( $order_id, 'buckaroo_accountHolderCountry', $this->_setPostVariable( 'brq_service_transfer_accountholdercountry' ) );
			}

			if ( isset( $_POST['brq_service_transfer_paymentreference'] ) ) {
				update_post_meta( $order_id, 'buckaroo_paymentReference', $this->_setPostVariable( 'brq_service_transfer_paymentreference' ) );
			}
		}
	}
}
