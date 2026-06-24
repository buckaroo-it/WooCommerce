<?php

namespace Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\Sdk;

use BuckarooDeps\Buckaroo\Models\Model;
use BuckarooDeps\Buckaroo\PaymentMethods\In3\In3;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;

/**
 * "Zakelijk op rekening" payment method (powered by ABN AMRO).
 *
 * Reuses the In3 service (paymentName "In3") but exposes the separate
 * Authorize and Capture actions that the bundled In3 SDK does not provide,
 * mirroring how the SDK's Afterpay method implements them. Defined here in the
 * plugin namespace so the vendored SDK stays untouched.
 */
class ZakelijkOpRekeningPaymentMethod extends In3
{
    /**
     * Reserve the funds (separate Authorize flow). Returns a RedirectURL,
     * exactly like the regular In3 Pay action.
     */
    public function authorize(?Model $model = null): TransactionResponse
    {
        $pay = $model ?? new Pay($this->payload);

        $this->setPayPayload();

        $this->setServiceList('Authorize', $pay);

        return $this->postRequest();
    }

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
