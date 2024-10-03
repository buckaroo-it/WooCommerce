<?php

namespace Buckaroo\Woocommerce\Gateways\Przelewy24;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class Przelewy24Processor extends AbstractPaymentProcessor
{
    public function __construct()
    {
        $this->type = 'Przelewy24';
        $this->version = 1;
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay();
     */
    public function Pay($customVars = array())
    {
        $this->setCustomVar(
            array(
                'CustomerEmail' => array(
                    'value' => $customVars['Customeremail'],
                ),
                'CustomerFirstName' => array(
                    'value' => $customVars['CustomerFirstName'],
                ),
                'CustomerLastName' => array(
                    'value' => $customVars['CustomerLastName'],
                ),

            )
        );

        return parent::Pay();
    }
}