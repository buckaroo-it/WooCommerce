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

declare(strict_types=1);

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\BankTransfer;

use WC_Buckaroo\Dependencies\Buckaroo\Models\Model;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\BankTransfer\Models\Pay;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\BankTransfer\Service\ParameterKeys\PayAdapter;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PayablePaymentMethod;
use WC_Buckaroo\Dependencies\Buckaroo\Transaction\Response\TransactionResponse;

class BankTransfer extends PayablePaymentMethod
{
    protected string $paymentName = 'transfer';
    protected int $serviceVersion = 1;

    public function pay(?Model $model = null): TransactionResponse
    {
        return parent::pay($model ?? new PayAdapter(new Pay($this->payload)));
    }
}
