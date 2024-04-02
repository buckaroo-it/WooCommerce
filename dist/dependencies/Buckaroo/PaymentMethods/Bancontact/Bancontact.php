<?php

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Bancontact;

use WC_Buckaroo\Dependencies\Buckaroo\Models\Model;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Bancontact\Models\Authenticate;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Bancontact\Models\Pay;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Bancontact\Models\PayEncrypted;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Interfaces\Combinable;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PayablePaymentMethod;
use WC_Buckaroo\Dependencies\Buckaroo\Transaction\Response\TransactionResponse;

/**
 *
 */
class Bancontact extends PayablePaymentMethod implements Combinable
{
    /**
     * @var string
     */
    protected string $paymentName = 'bancontactmrcash';
    /**
     * @var int
     */
    protected int $serviceVersion = 0;

    /**
     * @param Model|null $model
     * @return TransactionResponse
     */
    public function pay(?Model $model = null): TransactionResponse
    {
        return parent::pay($model ?? new Pay($this->payload));
    }

    /**
     * @return TransactionResponse
     */
    public function payEncrypted(): TransactionResponse
    {
        $payEncrypted = new PayEncrypted($this->payload);

        $this->setPayPayload();

        $this->setServiceList('PayEncrypted', $payEncrypted);

        return $this->postRequest();
    }

    /**
     * @return TransactionResponse
     */
    public function payRecurring(): TransactionResponse
    {
        $this->setPayPayload();

        $this->setServiceList('PayRecurring');

        return $this->postRequest();
    }

    /**
     * @return TransactionResponse
     */
    public function payOneClick(): TransactionResponse
    {
        $this->setPayPayload();

        $this->setServiceList('PayOneClick');

        return $this->postRequest();
    }

    /**
     * @return TransactionResponse
     * @deprecated deprecated since version 1.7.0, please use authorize method
     */
    public function authenticate(): TransactionResponse
    {
        return $this->authorize();
    }

    /**
     * @return Bancontact|mixed
     */
    public function authorize()
    {
        $authenticate = new Authenticate($this->payload);

        $this->setPayPayload();

        $this->setServiceList('Authorize', $authenticate);

        return $this->postRequest();
    }

    /**
     * @return Bancontact|mixed
     */
    public function capture()
    {
        $authenticate = new Authenticate($this->payload);

        $this->setPayPayload();

        $this->setServiceList('Capture', $authenticate);

        return $this->postRequest();
    }

    /**
     * @return Bancontact|mixed
     */
    public function cancelAuthorize()
    {
        $this->setPayPayload();

        $this->setServiceList('CancelAuthorize');

        return $this->postRequest();
    }
}
