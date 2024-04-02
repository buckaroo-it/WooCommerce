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

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\AfterpayDigiAccept;

use WC_Buckaroo\Dependencies\Buckaroo\Models\Model;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\AfterpayDigiAccept\Models\Pay;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\AfterpayDigiAccept\Models\Refund;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PayablePaymentMethod;
use WC_Buckaroo\Dependencies\Buckaroo\Transaction\Response\TransactionResponse;

class AfterpayDigiAccept extends PayablePaymentMethod
{
    /**
     * @var string
     */
    protected string $paymentName = 'afterpaydigiaccept';

    /**
     * @param Model|null $model
     * @return TransactionResponse
     */
    public function pay(?Model $model = null): TransactionResponse
    {
        return parent::pay($model ?? new Pay($this->payload));
    }

    public function refund(?Model $model = null)
    {
        return parent::refund($model ?? new Refund($this->payload));
    }

    public function authorize(?Model $model = null): TransactionResponse
    {
        $pay = new Pay($this->payload);

        $this->setPayPayload();

        $this->setServiceList('Authorize', $pay);

        return $this->postRequest();
    }

    public function capture(?Model $model = null): TransactionResponse
    {
        $pay = new Pay($this->payload);

        $this->setPayPayload();

        $this->setServiceList('Capture', $pay);

        return $this->postRequest();
    }

    public function cancelAuthorize(?Model $model = null): TransactionResponse
    {
        $this->setRefundPayload();

        $this->setServiceList('CancelAuthorize');

        return $this->postRequest();
    }
}
