<?php

namespace Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\Sdk;

use BuckarooDeps\Buckaroo\PaymentMethods\In3\Models\Company as In3Company;

/**
 * B2B recipient model for the "Zakelijk op rekening" (In3 / AbnB2b) method.
 *
 * The bundled In3 SDK Company model only declares `customerNumber`, so the
 * `CompanyName` and `CocNumber` service parameters required for a B2B order
 * are silently dropped. We extend it here (instead of editing the vendored
 * SDK) and declare the missing properties. The In3 RecipientAdapter already
 * maps `companyName` -> CompanyName and `chamberOfCommerce` -> CocNumber.
 */
class Company extends In3Company
{
    /**
     * @var string|null
     */
    protected ?string $companyName;

    /**
     * @var string|null
     */
    protected ?string $chamberOfCommerce;
}
