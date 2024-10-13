<?php

namespace Buckaroo\Woocommerce\ResponseParser;

class JsonParser extends ResponseParser
{
    public function getAmountCredit(): ?float
    {
        return $this->formatAmount($this->get('AmountCredit'));
    }

    public function getAmount(): ?float
    {
        return $this->formatAmount($this->get('Amount')) ?? $this->getAmountDebit();
    }

    public function getAmountDebit(): ?float
    {
        return $this->formatAmount($this->get('AmountDebit'));
    }

    public function getCurrency(): ?string
    {
        return $this->get('Currency');
    }

    public function getCustomerName(): ?string
    {
        return $this->get('CustomerName');
    }

    public function getDescription()
    {
        return $this->get('Description');
    }

    public function getInvoice(): ?string
    {
        return $this->get('Invoice');
    }

    public function getOrderNumber(): ?string
    {
        return $this->get('Order');
    }

    public function getMutationType()
    {
        return $this->get('MutationType');
    }

    public function getSubCodeMessage(): ?string
    {
        return $this->get('Status.SubCode.Description');
    }

    public function hasRedirect(): bool
    {
        return $this->get('RequiredAction.RedirectURL')
            && $this->get('RequiredAction.Name') == 'Redirect';
    }

    public function getRedirectUrl(): string
    {
        return $this->get('RequiredAction.RedirectURL');
    }

    public function getTransactionMethod()
    {
        return $this->get('ServiceCode');
    }

    public function getTransactionType()
    {
        return $this->get('TransactionType');
    }

    public function getTransactionKey(): ?string
    {
        return $this->get('Key');
    }

    public function getDataRequest(): ?string
    {
        return $this->get('Key');
    }

    public function getPaymentMethod(): ?string
    {
        return $this->getService('PaymentMethod') ?? $this->get('ServiceCode');
    }

    public function getService($name)
    {
        return $this->firstWhere($this->get('Services'), 'Name', $name);
    }

    public function getRelatedTransactionPartialPayment(): ?string
    {
        return $this->getRelatedTransactions('partialpayment');
    }

    protected function getRelatedTransactions($type = 'refund')
    {
        return $this->firstWhere($this->get('RelatedTransactions'), 'RelationType', $type)['RelatedTransactionKey'] ?? null;
    }

    public function isRefund(): bool
    {
        return $this->getRelatedTransactions() !== null;
    }

    public function getStatusCode(): ?int
    {
        return $this->get('Status.Code.Code');
    }

    public function getSubStatusCode(): ?string
    {
        return $this->get('Status.SubCode.Code');
    }

    public function getPayerHash(): ?string
    {
        return $this->get('PayerHash');
    }

    public function getPaymentKey(): ?string
    {
        return $this->get('PaymentKey');
    }

    public function getRefundParentKey(): ?string
    {
        return $this->getRelatedTransactions();
    }

    public function getServiceParameter($name, $parameter)
    {
        return $this->firstWhere($this->getServiceParameters($name), 'Name', $parameter)['Value'] ?? null;
    }

    public function getServiceParameters($name)
    {
        $service = $this->getService($name);
        return isset($service['Parameters']) ? $service['Parameters'] : null;
    }

    public function isTest(): bool
    {
        return $this->get('IsTest');
    }

    public function getRealOrderId()
    {
        return $this->getAdditionalInformation('real_order_id');
    }

    public function getAdditionalInformation($propertyName)
    {
        return $this->firstWhere($this->get('AdditionalParameters.List'), 'Name', $propertyName)['Value'] ?? null;
    }
}