<?php

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\ApplePay;

use WC_Buckaroo\Dependencies\Buckaroo\Models\Model;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\ApplePay\Models\Pay;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\ApplePay\Models\PayPayload;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PayablePaymentMethod;
use WC_Buckaroo\Dependencies\Buckaroo\Transaction\Response\TransactionResponse;

class ApplePay extends PayablePaymentMethod
{
    /**
     * @var string
     */
    protected string $paymentName = 'applepay';

    /**
     * @param Model|null $model
     * @return TransactionResponse
     */
    public function pay(?Model $model = null): TransactionResponse
    {
        return parent::pay($model ?? new Pay($this->payload));
    }

    public function payRedirect(): TransactionResponse
    {
        $this->payModel = PayPayload::class;

        $pay = new PayPayload($this->payload);

        $this->setPayPayload();
        
        return $this->postRequest();
    }
}
