<?php

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PaymentInitiation\Models;

use WC_Buckaroo\Dependencies\Buckaroo\Models\ServiceParameter;

class Pay extends ServiceParameter
{
    protected string $issuer;

    protected string $countryCode;
}
