<?php

namespace Buckaroo\Woocommerce\Gateways\SepaDirectDebit;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class SepaDirectDebitProcessor extends AbstractPaymentProcessor
{
    public $customeraccountname;
    public $CustomerBIC;
    public $CustomerIBAN;

    /**
     * @access public
     */
    public function __construct()
    {
        $this->type = 'sepadirectdebit';
        $this->version = '1';
    }

    /**
     * @access public
     * @param array $customVars
     * @return parent::Pay()
     */
    public function PayDirectDebit()
    {

        $this->setCustomVar('customeraccountname', $this->customeraccountname);
        $this->setCustomVar('CustomerBIC', $this->CustomerBIC);
        $this->setCustomVar('CustomerIBAN', $this->CustomerIBAN);

        return parent::Pay();
    }

    /**
     * @access public
     * @param array $customVars
     * @return void
     */
    public function Pay($customVars = array())
    {
        return null;
    }
}