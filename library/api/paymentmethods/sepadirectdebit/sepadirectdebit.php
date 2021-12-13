<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooSepaDirectDebit extends BuckarooPaymentMethod {
    public $customeraccountname;
    public $CustomerBIC;
    public $CustomerIBAN;

    /**
     * @access public
     */
    public function __construct() {
        $this->type = "sepadirectdebit";
        $this->version = '1';
    }

    /**
     * @access public
     * @param array $customVars
     * @return void
     */
    public function Pay($customVars = array()) {
        return null;
    }

    /**
     * @access public
     * @param array $customVars
     * @return parent::Pay()
     */
    public function PayDirectDebit($customVars) {

        $this->setCustomVar('customeraccountname', $this->customeraccountname);
        $this->setCustomVar('CustomerBIC', $this->CustomerBIC);
        $this->setCustomVar('CustomerIBAN', $this->CustomerIBAN);

        if ($this->usecreditmanagment) {

            $this->setServiceOfType('action', 'Invoice', 'creditmanagement');
            $this->setServiceOfType('version', '1', 'creditmanagement');
            
            $credit = [
                'MaxReminderLevel' => $customVars['MaxReminderLevel'],
                'DateDue' => $customVars['DateDue'],
                'InvoiceDate' => $customVars['InvoiceDate'],
                'CustomerFirstName' => $customVars['CustomerFirstName'],
                'CustomerLastName' => $customVars['CustomerLastName'],
                'CustomerInitials' => $customVars['CustomerInitials'],
                'Customergender' => $customVars['Customergender'],
                'Customeremail' => $customVars['Customeremail'],
                'CustomerType' => '0',
                'AmountVat' => $customVars['AmountVat'],
            ];
            

            if (isset($customVars['CustomerCode'])) {
                $credit['CustomerCode'] = $customVars['CustomerCode'];
            }
            if (!empty($customVars['CompanyName'])) {
                $credit['CompanyName'] = $customVars['CompanyName'];
            }
           
            if (!empty($customVars['PaymentMethodsAllowed'])) {
                $credit['PaymentMethodsAllowed'] = $customVars['PaymentMethodsAllowed'];
            }
            if (isset($customVars['MobilePhoneNumber'])) {
                $credit['MobilePhoneNumber'] = $customVars['MobilePhoneNumber'];
                $credit['PhoneNumber'] = $customVars['MobilePhoneNumber'];
            }
            if (isset($customVars['PhoneNumber'])) {
                $credit['PhoneNumber'] = $customVars['PhoneNumber'];
            }
            if (isset($customVars['CustomerBirthDate'])) {
                $credit['CustomerBirthDate'] = $customVars['CustomerBirthDate'];
            }
            $this->setCustomVarOfType(
                $credit, null, null, 'creditmanagement'
            );

            foreach ($customVars['ADDRESS'] as $key => $address) {
                $this->setCustomVarOfType(
                    $key, $address, 'address', 'creditmanagement'
                );
            }
            
        }
        return parent::Pay();
    }
}

