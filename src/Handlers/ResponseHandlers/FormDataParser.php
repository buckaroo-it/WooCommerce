<?php

namespace Buckaroo\Woocommerce\Handlers\ResponseHandlers;


use Buckaroo\Resources\Constants\ResponseStatus;
use Buckaroo\Woocommerce\Constants\BuckarooTransactionStatus;

class FormDataParser extends ResponseParser
{
    public function getAmountDebit(): ?float
    {
        return $this->formatAmount($this->items['brq_amount_debit'] ?? null);
    }

    public function getAmountCredit(): ?float
    {
        return $this->formatAmount($this->items['brq_amount_credit'] ?? null);
    }

    public function getAmount(): ?float
    {
        return $this->formatAmount($this->items['brq_amount'] ?? null);
    }

    public function hasRedirect(): bool
    {
        return !empty($this->items['brq_redirect_url']);
    }

    public function getRedirectUrl(): string
    {
        return $this->items['brq_redirect_url'] ?? '';
    }

    public function getCurrency(): ?string
    {
        return $this->items['brq_currency'] ?? null;
    }

    public function getCustomerName(): ?string
    {
        return $this->items['brq_customer_name'] ?? null;
    }

    public function getDescription()
    {
        return $this->items['brq_description'] ?? null;
    }

    public function getInvoice(): ?string
    {
        return $this->items['brq_invoicenumber'] ?? null;
    }

    public function getOrderNumber(): ?string
    {
        return $this->items['brq_ordernumber'] ?? null;
    }

    public function getMutationType()
    {
        return $this->items['brq_mutationtype'] ?? null;
    }

    public function getSubCodeMessage(): ?string
    {
        return $this->items['brq_statusmessage'] ?? null;
    }

    public function getTransactionMethod()
    {
        return $this->items['brq_transaction_method'] ?? null;
    }

    public function getTransactionType()
    {
        return $this->items['brq_transaction_type'] ?? null;
    }

    public function getTransactionKey(): ?string
    {
        return $this->items['brq_transactions'] ?? null;
    }

    public function getDataRequest(): ?string
    {
        return $this->items['brq_datarequest'] ?? null;
    }

    public function getRelatedTransactionPartialPayment(): ?string
    {
        return $this->items['brq_relatedtransaction_partialpayment'] ?? null;
    }

    public function getAdditionalInformation($propertyName)
    {
        return $this->items['add_' . mb_strtolower($propertyName)] ?? null;
    }

    public function isRefund(): bool
    {
        return !empty($this->getRefundParentKey());
    }

    public function getRefundParentKey(): ?string
    {
        return $this->items['brq_relatedtransaction_refund'] ?? null;
    }

    public function isSuccess(): bool
    {
        return BuckarooTransactionStatus::fromTransactionStatus($this->getStatusCode()) == BuckarooTransactionStatus::STATUS_PAID;
    }

    public function getStatusCode(): ?int
    {
        return $this->items['brq_statuscode'] ?? null;
    }

    public function getService($name)
    {
        return $this->items['brq_service_' . strtolower($this->getPaymentMethod() ?? $this->getPrimaryService()) . '_' . $name] ?? null;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->items['brq_transaction_method'] ?? null;
    }

    public function getPrimaryService(): ?string
    {
        return $this->items['brq_primary_service'] ?? null;
    }

    public function isPendingProcessing(): bool
    {
        return BuckarooTransactionStatus::fromTransactionStatus($this->getStatusCode()) == BuckarooTransactionStatus::STATUS_PENDING ||
            in_array($this->getSubStatusCode(), ['P190', 'P191']);
    }

    public function getSubStatusCode(): ?string
    {
        return $this->items['brq_statuscode_detail'] ?? null;
    }

    public function getPayerHash(): ?string
    {
        return $this->items['brq_payer_hash'] ?? null;
    }

    public function getPaymentKey(): ?string
    {
        return $this->items['brq_payment'] ?? null;
    }

    public function isTest(): bool
    {
        return !empty($this->items['brq_test']);
    }

    public function isPendingApproval(): bool
    {
        return $this->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_PENDING_APPROVAL;
    }

    public function isCanceled(): bool
    {
        return $this->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_USER
            || $this->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_CANCELLED_BY_MERCHANT;
    }

    public function isAwaitingConsumer(): bool
    {
        return $this->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_WAITING_ON_CONSUMER;
    }
}