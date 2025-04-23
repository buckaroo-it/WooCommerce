<?php

namespace Buckaroo\Woocommerce\ResponseParser;

class FormDataParser extends ResponseParser {

	public function getAmountDebit(): ?float {
		return $this->formatAmount( $this->get( 'brq_amount_debit' ) );
	}

	public function getAmountCredit(): ?float {
		return $this->formatAmount( $this->get( 'brq_amount_credit' ) );
	}

	public function getAmount(): ?float {
		return $this->formatAmount( $this->get( 'brq_amount' ) );
	}

	public function hasRedirect(): bool {
		return $this->get( 'brq_redirect_url' ) === true;
	}

	public function getRedirectUrl(): string {
		return $this->get( 'brq_redirect_url' );
	}

	public function getCurrency(): ?string {
		return $this->get( 'brq_currency' );
	}

	public function getCustomerName(): ?string {
		return $this->get( 'brq_customer_name' );
	}

	public function getDescription() {
		return $this->get( 'brq_description' );
	}

	public function getInvoice(): ?string {
		return $this->get( 'brq_invoicenumber' );
	}

	public function getOrderNumber(): ?string {
		return $this->get( 'brq_ordernumber' );
	}

	public function getMutationType() {
		return $this->get( 'brq_mutationtype' );
	}

	public function getSubCodeMessage(): ?string {
		return $this->get( 'brq_statusmessage' );
	}

	public function getTransactionMethod() {
		return $this->get( 'brq_transaction_method' );
	}

	public function getTransactionType() {
		return $this->get( 'brq_transaction_type' );
	}

	public function getTransactionKey(): ?string {
		return $this->get( 'brq_transactions' );
	}

	public function getDataRequest(): ?string {
		return $this->get( 'brq_datarequest' );
	}

	public function getRelatedTransactionPartialPayment(): ?string {
		return $this->get( 'brq_relatedtransaction_partialpayment' );
	}

	public function isRefund(): bool {
		return $this->getRefundParentKey() !== null;
	}

	public function getRefundParentKey(): ?string {
		return $this->get( 'brq_relatedtransaction_refund' );
	}

	public function getStatusCode(): ?int {
		return $this->get( 'brq_statuscode' );
	}

	public function getService( $name ) {
        return $this->getServiceParameter( $name );
	}

    public function getServiceParameter( $name, $service = null ) {
        $service = strtolower( $this->getPaymentMethod() ?? $this->getPrimaryService() );

        return $this->get( 'brq_SERVICE_' . $service . '_' . $name );
    }

    public function getPaymentMethod(): ?string {
		return $this->get( 'brq_transaction_method' ) ?? $this->get( 'brq_payment_method' );
	}

	public function getPrimaryService(): ?string {
		return $this->get( 'brq_primary_service' );
	}

	public function getSubStatusCode(): ?string {
		return $this->get( 'brq_statuscode_detail' );
	}

	public function getPayerHash(): ?string {
		return $this->get( 'brq_payer_hash' );
	}

	public function getPaymentKey(): ?string {
		return $this->get( 'brq_payment' );
	}

	public function isTest(): bool {
		return filter_var( $this->get( 'brq_test' ), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	}

	public function getRealOrderId() {
		return $this->getAdditionalInformation( 'real_order_id' );
	}

	public function getAdditionalInformation( $propertyName ) {
		return $this->get( 'add_' . mb_strtolower( $propertyName ) );
	}
}
