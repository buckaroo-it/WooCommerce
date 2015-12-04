<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

class BuckarooPayGarant extends BuckarooPaymentMethod
{
    public function __construct()
    {
        $this->type = "paymentguarantee";
        $this->version = '1';
        $this->mode = BuckarooConfig::getMode('PAYGARANT');
    }

    public function Pay($customVars = Array())
    {
        return null;
    }

    public function PaymentInvitation($customVars)
    {
        $this->data['services'][$this->type]['action'] = 'Paymentinvitation';
        $this->data['services'][$this->type]['version'] = $this->version;

        if ($this->usenotification && !empty($customVars['Customeremail'])) {
            $this->data['services']['notification']['action'] = 'ExtraInfo';
            $this->data['services']['notification']['version'] = '1';
            $this->data['customVars']['notification']['NotificationType'] = $customVars['Notificationtype'];
            $this->data['customVars']['notification']['CommunicationMethod'] = 'email';
            $this->data['customVars']['notification']['RecipientEmail'] = $customVars['Customeremail'];
            $this->data['customVars']['notification']['RecipientFirstName'] = $customVars['CustomerFirstName'];
            $this->data['customVars']['notification']['RecipientLastName'] = $customVars['CustomerLastName'];
            $this->data['customVars']['notification']['RecipientGender'] = $customVars['Customergender'];
            if (!empty($customVars['Notificationdelay'])) {
                $this->data['customVars']['notification']['SendDatetime'] = $customVars['Notificationdelay'];
            }
        }

        $this->data['currency'] = $this->currency;
        $this->data['amountDebit'] = $this->amountDedit;
        $this->data['amountCredit'] = $this->amountCredit;
        $this->data['invoice'] = $this->invoiceId;
        $this->data['order'] = $this->orderId;
        $this->data['description'] = $this->description;
        $this->data['returnUrl'] = $this->returnUrl;
        $this->data['mode'] = $this->mode;

        if (isset($customVars['CustomerCode'])) {
            $this->data['customVars'][$this->type]['CustomerCode'] = $customVars['CustomerCode'];
        }
        $this->data['customVars'][$this->type]['CustomerFirstName'] = $customVars['CustomerFirstName'];
        $this->data['customVars'][$this->type]['CustomerLastName'] = $customVars['CustomerLastName'];
        $this->data['customVars'][$this->type]['CustomerInitials'] = $customVars['CustomerInitials'];
        $this->data['customVars'][$this->type]['CustomerBirthDate'] = $customVars['CustomerBirthDate'];
        if (isset($customVars['CustomerGender'])) {
            $this->data['customVars'][$this->type]['CustomerGender'] = $customVars['CustomerGender'];
        }
        $this->data['customVars'][$this->type]['CustomerEmail'] = $customVars['CustomerEmail'];

        foreach ($customVars['ADDRESS'] as $key => $adress) {
            foreach ($adress as $key2 => $value) {
                $this->data['customVars'][$this->type][$key2][$key]['value'] = $value;
                $this->data['customVars'][$this->type][$key2][$key]['group'] = 'address';
            }
        }
        if (isset($customVars['PhoneNumber'])) {
            $this->data['customVars'][$this->type]['PhoneNumber'] = $customVars['PhoneNumber'];
        }

        if (isset($customVars['MobilePhoneNumber'])) {
            $this->data['customVars'][$this->type]['MobilePhoneNumber'] = $customVars['MobilePhoneNumber'];
        }

        $this->data['customVars'][$this->type]['DateDue'] = $customVars['DateDue'];
        $this->data['customVars'][$this->type]['InvoiceDate'] = $customVars['InvoiceDate'];
        $this->data['customVars'][$this->type]['AmountVat'] = $customVars['AmountVat'];

        if (!empty($customVars['PaymentMethodsAllowed'])) {
            $this->data['customVars'][$this->type]['PaymentMethodsAllowed'] = $customVars['PaymentMethodsAllowed'];
        }
        $this->data['customVars'][$this->type]['CustomerIBAN'] = $customVars['CustomerAccountNumber'];
        $this->data['customVars'][$this->type]['SendMail'] = $customVars['SendMail'];

        $soap = new BuckarooSoap($this->data);

        return BuckarooResponseFactory::getResponse($soap->transactionRequest());

    }

    public function CreditNote()
    {

    }
}

