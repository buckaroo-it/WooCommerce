<?php

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PaymentInitiation;

use WC_Buckaroo\Dependencies\Buckaroo\Exceptions\BuckarooException;
use WC_Buckaroo\Dependencies\Buckaroo\Models\Model;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PayablePaymentMethod;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PaymentInitiation\Models\Pay;
use WC_Buckaroo\Dependencies\Buckaroo\Services\TraitHelpers\HasIssuers;
use WC_Buckaroo\Dependencies\Buckaroo\Transaction\Response\TransactionResponse;

class PaymentInitiation extends PayablePaymentMethod
{
    use HasIssuers {
        issuers as traitIssuers;
    }
    protected string $paymentName = 'PayByBank';
    protected array $requiredConfigFields = ['currency', 'returnURL', 'returnURLCancel', 'pushURL'];

    /**
     * @param Model|null $model
     * @return TransactionResponse
     */
    public function pay(?Model $model = null): TransactionResponse
    {
        return parent::pay($model ?? new Pay($this->payload));
    }

    /**
     * @return array
     * @throws BuckarooException
     */
    public function issuers(): array
    {
        $this->serviceVersion = 1;

        return $this->traitIssuers();
    }
}
