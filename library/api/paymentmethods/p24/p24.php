<?php
require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooP24 extends BuckarooPaymentMethod {

    public function __construct() {
        $this->type = "Przelewy24";
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
            [
                'CustomerEmail' => [
                    'value' => $customVars['Customeremail']
                ],
                'CustomerFirstName' => [
                    'value' => $customVars['CustomerFirstName']
                ],
                'CustomerLastName' => [
                    'value' => $customVars['CustomerLastName']
                ]

            ]
        );

        return parent::Pay();

    }

}
