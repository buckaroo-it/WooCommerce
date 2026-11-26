<?php

namespace Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\Sdk;

use BuckarooDeps\Buckaroo\Models\Model;
use BuckarooDeps\Buckaroo\PaymentMethods\In3\In3;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;

/**
 * "Zakelijk op rekening" payment method (powered by ABN AMRO).
 *
 * Reuses the In3 service (paymentName "In3"); Authorize comes from the SDK.
 * Capture is overridden so a full capture can be sent without articles.
 */
class ZakelijkOpRekeningPaymentMethod extends In3
{
    /**
     * Capture (part of) a previously authorized transaction.
     *
     * When the capture amount equals the authorized amount, Buckaroo reuses the
     * articles from the Authorize call, so no InvoiceLine parameters are sent
     * (model is null). The amount and OriginalTransactionKey are carried on the
     * top-level payload.
     */
    public function capture(?Model $model = null): TransactionResponse
    {
        $this->setPayPayload();

        $this->setServiceList('Capture', $model);

        return $this->postRequest();
    }
}
