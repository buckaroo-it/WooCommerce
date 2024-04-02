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

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\iDeal;

use WC_Buckaroo\Dependencies\Buckaroo\Exceptions\BuckarooException;
use WC_Buckaroo\Dependencies\Buckaroo\Models\Model;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\iDeal\Models\Pay;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PayablePaymentMethod;
use WC_Buckaroo\Dependencies\Buckaroo\Services\TraitHelpers\HasIssuers;
use WC_Buckaroo\Dependencies\Buckaroo\Transaction\Response\TransactionResponse;

class iDeal extends PayablePaymentMethod
{
    use HasIssuers {
        issuers as traitIssuers;
    }

    /**
     * @var string
     */
    protected string $paymentName = 'ideal';
    /**
     * @var array|string[]
     */
    protected array $requiredConfigFields = ['currency', 'returnURL', 'returnURLCancel', 'pushURL'];

    /**
     * @param Model|null $model
     * @return TransactionResponse
     */
    public function pay(?Model $model = null)
    {
        return parent::pay($model ?? new Pay($this->payload));
    }

    /**
     * @param Model|null $model
     * @return TransactionResponse
     */
    public function payRemainder(?Model $model = null): TransactionResponse
    {
        return parent::payRemainder($model ?? new Pay($this->payload));
    }

    /**
     * @param Model|null $model
     * @return TransactionResponse
     */
    public function instantRefund(?Model $model = null):TransactionResponse
    {
        $this->setRefundPayload();

        $this->setServiceList('instantRefund', $model);

        return $this->postRequest();
    }

    /**
     * @return array
     * @throws BuckarooException
     */
    public function issuers(): array
    {
        $this->serviceVersion = 2;

        return $this->traitIssuers();
    }
}
