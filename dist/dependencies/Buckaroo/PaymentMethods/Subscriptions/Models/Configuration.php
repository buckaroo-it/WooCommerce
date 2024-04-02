<?php

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\Subscriptions\Models;

use WC_Buckaroo\Dependencies\Buckaroo\Models\ServiceParameter;

class Configuration extends ServiceParameter
{
    /**
     * @var string
     */
    protected string $name;
    /**
     * @var string
     */
    protected string $schemeKey;
    /**
     * @var string
     */
    protected string $invoiceNumberPrefix;
    /**
     * @var string
     */
    protected string $invoiceDescriptionFormat;
    /**
     * @var int
     */
    protected int $dueDateDays;
    /**
     * @var string
     */
    protected string $allowedServices;
    /**
     * @var bool
     */
    protected bool $generateInvoiceSpecification;
    /**
     * @var bool
     */
    protected bool $skipPayPerEmail;
}
