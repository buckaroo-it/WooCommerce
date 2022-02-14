<?php
/**
 * Vpay credicard payment
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
class WC_Gateway_Buckaroo_Vpay extends Buckaroo_Creditcard_Single
{

    public function setParameters() {
        $this->id                     = 'buckaroo_creditcard_vpay';
        $this->title                  = 'Vpay';
        $this->method_title           = "Buckaroo Vpay";
    }
}