<?php

namespace Buckaroo\Woocommerce\Handlers\ResponseHandlers;


use Buckaroo\Resources\Constants\ResponseStatus;
use Buckaroo\Woocommerce\Constants\BuckarooTransactionStatus;

class JsonParser extends ResponseParser
{
    public function getAmountCredit(): ?float
    {
        return $this->formatAmount($this->items['AmountCredit'] ?? null);
    }

    public function getAmount(): ?float
    {
        return $this->formatAmount($this->items['Amount'] ?? null) ?? $this->getAmountDebit();
    }

    public function getAmountDebit(): ?float
    {
        return $this->formatAmount($this->items['AmountDebit'] ?? null);
    }

    public function getCurrency(): ?string
    {
        return $this->items['Currency'] ?? null;
    }

    public function getCustomerName(): ?string
    {
        return $this->items['CustomerName'] ?? null;
    }

    public function getDescription()
    {
        return $this->items['Description'] ?? null;
    }

    public function getInvoice(): ?string
    {
        return $this->items['Invoice'] ?? null;
    }

    public function getOrderNumber(): ?string
    {
        return $this->items['Order'] ?? null;
    }

    public function getMutationType()
    {
        return $this->items['MutationType'] ?? null;
    }

    public function getSubCodeMessage(): ?string
    {
        return $this->getDeep('Status.SubCode.Description');
    }

    public function hasRedirect(): bool
    {
        return !empty($this->getDeep('RequiredAction.RedirectURL')) && $this->getDeep('RequiredAction.Name') === 'Redirect';
    }

    public function getRedirectUrl(): string
    {
        return $this->getDeep('RequiredAction.RedirectURL') ?? '';
    }

    public function getTransactionMethod()
    {
        return $this->items['ServiceCode'] ?? null;
    }

    public function getTransactionType()
    {
        return $this->items['TransactionType'] ?? null;
    }

    public function getTransactionKey(): ?string
    {
        return $this->items['Key'] ?? null;
    }

    public function getDataRequest(): ?string
    {
        return $this->items['Key'] ?? null;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->getService('PaymentMethod') ?? $this->items['ServiceCode'] ?? null;
    }

    public function getService($name)
    {
        $services = $this->items['Services'] ?? [];
        foreach ($services as $service) {
            if ($service['Name'] === $name) {
                return $service;
            }
        }
        return null;
    }

    public function getRelatedTransactionPartialPayment(): ?string
    {
        return $this->getRelatedTransactions('partialpayment');
    }

    protected function getRelatedTransactions($type = 'refund')
    {
        $relatedTransactions = $this->getDeep('RelatedTransactions') ?? [];
        foreach ($relatedTransactions as $transaction) {
            if ($transaction['RelationType'] === $type) {
                return $transaction['RelatedTransactionKey'];
            }
        }
        return null;
    }

    public function isRefund(): bool
    {
        return $this->getRelatedTransactions() !== null;
    }

    public function isSuccess(): bool
    {
        return $this->getStatusCode() == ResponseStatus::BUCKAROO_STATUSCODE_SUCCESS;
    }

    public function getStatusCode(): ?int
    {
        return $this->getDeep('Status.Code.Code');
    }

    public function isPendingProcessing(): bool
    {
        return BuckarooTransactionStatus::fromTransactionStatus($this->getStatusCode()) == BuckarooTransactionStatus::STATUS_PENDING ||
            in_array($this->getSubStatusCode(), ['P190', 'P191']);
    }

    public function getSubStatusCode(): ?string
    {
        return $this->getDeep('Status.SubCode.Code');
    }

    public function getPayerHash(): ?string
    {
        return $this->items['PayerHash'] ?? null;
    }

    public function getPaymentKey(): ?string
    {
        return $this->items['PaymentKey'] ?? null;
    }

    public function getAdditionalInformation($propertyName)
    {
        $additionalParams = $this->getDeep('AdditionalParameters.List') ?? [];
        foreach ($additionalParams as $param) {
            if ($param['Name'] === $propertyName) {
                return $param['Value'];
            }
        }
        return null;
    }

    public function getRefundParentKey(): ?string
    {
        return $this->getRelatedTransactions();
    }

    public function isTest(): bool
    {
        return !empty($this->items['IsTest']);
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
