<?php

require_once(dirname(__FILE__) . '/../response.php');

class BuckarooTransferResponse extends BuckarooResponse
{
    public $BIC = '';
    public $IBAN = '';
    public $accountHolderName = '';
    public $accountHolderCountry = '';
    public $paymentReference = '';
    public $consumerMessage = array(
        'MustRead' => '',
        'CultureName' => '',
        'Title' => '',
        'PlainText' => '',
        'HtmlText' => ''
    );

    protected function _parseSoapResponseChild()
    {
        if (isset($this->_response->Services->Service->ResponseParameter) && isset($this->_response->Services->Service->Name)) {
            if ($this->_response->Services->Service->Name == 'transfer' && $this->_response->Services->Service->ResponseParameter[5]->Name == 'PaymentReference') {
                $this->BIC = $this->_response->Services->Service->ResponseParameter[0]->_;
                $this->IBAN = $this->_response->Services->Service->ResponseParameter[1]->_;
                $this->accountHolderName = $this->_response->Services->Service->ResponseParameter[2]->_;
                $this->accountHolderCity = $this->_response->Services->Service->ResponseParameter[3]->_;
                $this->accountHolderCountry = $this->_response->Services->Service->ResponseParameter[4]->_;
                $this->paymentReference = $this->_response->Services->Service->ResponseParameter[5]->_;
            }
        }
        if (isset($this->_response->ConsumerMessage)) {
            if (isset($this->_response->ConsumerMessage->MustRead)) {
                $this->consumerMessage['MustRead'] = $this->_response->ConsumerMessage->MustRead;
            }
            if (isset($this->_response->ConsumerMessage->CultureName)) {
                $this->consumerMessage['CultureName'] = $this->_response->ConsumerMessage->CultureName;
            }
            if (isset($this->_response->ConsumerMessage->Title)) {
                $this->consumerMessage['Title'] = $this->_response->ConsumerMessage->Title;
            }
            if (isset($this->_response->ConsumerMessage->PlainText)) {
                $this->consumerMessage['PlainText'] = $this->_response->ConsumerMessage->PlainText;
            }
            if (isset($this->_response->ConsumerMessage->HtmlText)) {
                $this->consumerMessage['HtmlText'] = $this->_response->ConsumerMessage->HtmlText;
            }
        }
    }

    protected function _parsePostResponseChild()
    {

    }

}
