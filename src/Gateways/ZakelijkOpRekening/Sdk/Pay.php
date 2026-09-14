<?php

namespace Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\Sdk;

use BuckarooDeps\Buckaroo\PaymentMethods\In3\Models\Pay as In3Pay;

/**
 * Pay/Authorize service model for the "Zakelijk op rekening" method.
 *
 * Identical to the In3 SDK Pay model, except the billing and shipping
 * recipients are built from our Recipient subclass so the B2B company details
 * (CompanyName / CocNumber) survive serialization. The `route` property
 * (inherited from the In3 Pay model) is serialized as the "Route" parameter
 * and is set to "AbnB2b" by the processor.
 */
class Pay extends In3Pay
{
    /**
     * {@inheritDoc}
     */
    public function billing($billing = null)
    {
        if (is_array($billing)) {
            $this->billingRecipient = new Recipient('Billing', $billing);
            $this->shippingRecipient = new Recipient('Shipping', $billing);
        }

        return $this->billingRecipient;
    }

    /**
     * {@inheritDoc}
     */
    public function shipping($shipping = null)
    {
        if (is_array($shipping)) {
            $this->shippingRecipient = new Recipient('Shipping', $shipping);
        }

        return $this->shippingRecipient;
    }
}
