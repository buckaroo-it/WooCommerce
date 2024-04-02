<?php
/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) WC_Buckaroo\Dependencies\Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Paypal;

use WC_Buckaroo\Dependencies\Buckaroo\Models\Model;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Interfaces\Combinable;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PayablePaymentMethod;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Paypal\Models\ExtraInfo;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Paypal\Models\Pay;
use WC_Buckaroo\Dependencies\Buckaroo\Transaction\Response\TransactionResponse;

class Paypal extends PayablePaymentMethod implements Combinable
{
    /**
     * @var string
     */
    protected string $paymentName = 'paypal';

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
    public function payRecurrent(): TransactionResponse
    {
        $pay = new Pay($this->payload);

        $this->setPayPayload();

        $this->setServiceList('PayRecurrent', $pay);

        return $this->postRequest();
    }

    /**
     * @return Paypal|mixed
     */
    public function extraInfo()
    {
        $extraInfo = new ExtraInfo($this->payload);

        $this->setPayPayload();

        $this->setServiceList('Pay,ExtraInfo', $extraInfo);

        return $this->postRequest();
    }
    
    /**
     * @param Model|null $model
     * @return TransactionResponse
     */
    public function payRemainder(?Model $model = null): TransactionResponse
    {
        return parent::payRemainder($model ?? new Pay($this->payload));
    }
}
