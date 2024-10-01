<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\CreditCards;

/**
 * Dankort credicard payment
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */
class Dankort extends DefaultCreditCard
{
    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard_dankort';
        $this->title = 'Dankort';
        $this->method_title = "Buckaroo Dankort";
    }
}