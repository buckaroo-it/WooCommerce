<?php

namespace Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\Sdk;

use BuckarooDeps\Buckaroo\PaymentMethods\In3\Models\Person as In3Person;
use BuckarooDeps\Buckaroo\PaymentMethods\In3\Models\Recipient as In3Recipient;
use BuckarooDeps\Buckaroo\PaymentMethods\In3\Service\ParameterKeys\RecipientAdapter;

/**
 * Recipient for the "Zakelijk op rekening" method.
 *
 * The bundled In3 SDK Recipient resolves a B2B recipient to the SDK's own
 * Company model (which cannot hold the company name / CoC number). We override
 * recipient() so a B2B recipient is built from our extended Company model,
 * keeping the CompanyName and CocNumber parameters in the request. B2C falls
 * back to the standard In3 Person model.
 */
class Recipient extends In3Recipient
{
    /**
     * {@inheritDoc}
     */
    public function recipient($recipient = null)
    {
        if (is_array($recipient)) {
            if (isset($recipient['category']) && $recipient['category'] === 'B2B') {
                $this->recipient = new RecipientAdapter(new Company($recipient));
            } else {
                $this->recipient = new RecipientAdapter(new In3Person($recipient));
            }
        }

        return $this->recipient;
    }
}
