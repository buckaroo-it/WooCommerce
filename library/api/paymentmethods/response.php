<?php

require_once(dirname(__FILE__) . '/../../logger.php');
require_once(dirname(__FILE__) . '/../abstract.php');

/**
 * @package Buckaroo
 */
abstract class BuckarooResponse extends BuckarooAbstract {

    //false if not received response       
    private $_received = false;
    //true if validated and securety checked
    private $_validated = false;
    //request is test?
    private $_test = true;
    private $_signature;
    private $_isPost;
    //payment key
    public $payment;
    //paypal, paymentguarantee, ideal...
    public $payment_method;
    public $statuscode;
    public $statuscode_detail;
    public $status;
    public $statusmessage;
    public $message;
    public $invoice;
    public $invoicenumber;
    public $amount_credit;
    public $amount;
    public $currency;
    public $timestamp;
    public $ChannelError;
    public $brq_transaction_type;
    public $brq_relatedtransaction_partialpayment;
    public $brq_relatedtransaction_refund;
    //transaction key
    public $transactions;
    //if is errors, othervise = null
    /* [ParameterError] => stdClass Object
      (
      [_] => Parameter "CustomerAccountNumber" has wrong value
      [Service] => paymentguarantee
      [Action] => PaymentInvitation
      [Name] => CustomerAccountNumber
      [Error] => ParameterInvalid
      )
     * 
     */
    public $parameterError = null;
    /*     * **************************************************** */
    protected $_responseXML = '';
    protected $_response = '';

    public function __construct($data = null) {
        
        $logger = new BuckarooLogger(BuckarooLogger::INFO, 'response');
        $logger->logInfo("\n\n\n\n***************** Response ***********************");
        if ($this->isHttpRequest()) {
            $logger->logInfo("Type: HTTP");
            $logger->logInfo("POST", print_r($_POST, true));
        } else {
            $logger->logInfo("Type: SOAP");
            if (!is_null($data)) {
                
                if ($data[0] != false)
                    $logger->logInfo("Data[0]: ", print_r($data[0], true));
                if ($data[1] != false)
                    $logger->logInfo("Data[1]: ", $data[1]->saveHTML());
                if ($data[2] != false)
                    $logger->logInfo("Data[2]: ", $data[2]->saveHTML()); 
            }
        }

        $this->_isPost = $this->isHttpRequest();
        $this->_received = false;
        
        if ($this->_isPost) { //HTTP
            $this->_parsePostResponse();
            $this->_parsePostResponseChild();
            $this->_received = true;
        } else if (!is_null($data) && $data[0] != false) { //if valid SOAP response

            $this->setResponse($data[0]);
            $this->setResponseXML($data[1]);
            $this->_parseSoapResponse();
            $this->_parseSoapResponseChild();
            $this->_received = true;
        } else {
            $this->status = self::REQUEST_ERROR;
        }
    }
    
    /**
     * Determine if response is HTTP or SOAP
     * 
     * @access private
     * @return boolean
     */
    private function isHttpRequest() {
        if (isset($_POST['brq_statuscode']) ) //HTTP request
        {
            return true;
        }
        return false;
    }
    

    public function isTest() {
        return $this->_test;
    }

    public function isValid() {
        if (!$this->_validated) {
            if ($this->_isPost) {
                $this->_validated = $this->_canProcessPush();
            } else {
                $this->_validated = $this->_verifyResponse();
            }
        }
        return $this->_validated;
    }

    public function isReceived() {
        return $this->_received;
    }

    public function hasSucceeded() {
        //if isValid false return false
        if ($this->isValid() && $this->isReceived()) {
            // if ($this->status === self::BUCKAROO_SUCCESS)
            if ($this->status === self::BUCKAROO_PENDING_PAYMENT || $this->status === self::BUCKAROO_SUCCESS)
                return true;
        }
        return false;
    }

    public function isRedirectRequired() {
        if (isset($this->_response->RequiredAction->Name) && isset($this->_response->RequiredAction->Type)) {
            if ($this->_response->RequiredAction->Name == 'Redirect' && $this->_response->RequiredAction->Type == 'Redirect') {
                return true;
            }
        }
        return false;
    }

    public function getRedirectUrl() {
        //TODO: if not https throw an error 
        if (isset($this->_response->RequiredAction->RedirectURL))
            return $this->_response->RequiredAction->RedirectURL;
        else
            return false;
    }

    private function setResponseXML($xml) {
        $this->_responseXML = $xml;
        //Record requests in debug mode
        writeToDebug($xml, 'Response');
    }

    private function getResponseXML() {
        return $this->_responseXML;
    }

    private function setResponse($response) {
        $this->_response = $response;
    }

    public function getResponse() {
        return $this->_response;
    }


    private function _parseSoapResponse() {
        $this->payment = '';
        if (isset($this->_response->ServiceCode))
            $this->payment_method = $this->_response->ServiceCode;
        $this->transactions = $this->_response->Key;
        $this->statuscode = $this->_response->Status->Code->Code;
        $this->statusmessage = $this->_response->Status->Code->_;
        $this->statuscode_detail = '';
        if (isset($this->_response->Invoice))
            $this->invoice = $this->_response->Invoice;
        $this->order = $this->_response->Order;
        $this->invoicenumber = $this->invoice;
        $this->amount = 0;
        if (isset($this->_response->AmountDebit))
            $this->amount = $this->_response->AmountDebit;
        $this->amount_credit = 0;
        if (isset($this->_response->AmountCredit)) {
            $this->amount = $this->_response->AmountCredit;
            $this->amount_credit = $this->_response->AmountCredit;

        }
        if (isset($this->_response->Key)) {
            $this->transactionId = $this->_response->Key;
        }
        $this->currency = $this->_response->Currency;
        $this->_test = ($this->_response->IsTest == 1) ? true : false;
        $this->timestamp = $this->_response->Status->DateTime;
        if (isset($this->_response->RequestErrors->ChannelError->_))
            $this->ChannelError = $this->_response->RequestErrors->ChannelError->_;
        if (isset($this->_response->Status->Code->_) && empty($this->ChannelError)) {
            $this->ChannelError = $this->_response->Status->Code->_;
            if (isset($this->_response->Status->SubCode->_)) {
                $this->ChannelError = $this->ChannelError.': '.$this->_response->Status->SubCode->_;
            }
        }

        $responseArray = $this->responseCodes[(int) $this->statuscode];
        $this->status = $responseArray['status'];
        $this->message = $responseArray['message'];

        if (isset($this->_response->RequestErrors->ParameterError))
            $this->ParameterError = $this->_response->RequestErrors->ParameterError;
    }

    abstract protected function _parseSoapResponseChild();

    private function _setPostVariable($key) {
        if (isset($_POST[$key]))
            return $_POST[$key];
        else
            return null;
    }

    private function _parsePostResponse() {
        $this->payment = $this->_setPostVariable('brq_payment');
        if (isset($_POST['brq_payment_method']))
            $this->payment_method = $_POST['brq_payment_method'];
        elseif (isset($_POST['brq_transaction_method']))
            $this->payment_method = $_POST['brq_transaction_method'];


        $this->statuscode = $this->_setPostVariable('brq_statuscode');
        $this->statusmessage = $this->_setPostVariable('brq_statusmessage');
        $this->statuscode_detail = $this->_setPostVariable('brq_statuscode_detail');
        $this->brq_relatedtransaction_partialpayment = $this->_setPostVariable('brq_relatedtransaction_partialpayment');
        $this->brq_transaction_type = $this->_setPostVariable('brq_transaction_type');
        $this->brq_relatedtransaction_refund = $this->_setPostVariable('brq_relatedtransaction_refund');

        $this->invoice = $this->_setPostVariable('brq_invoicenumber');
        $this->invoicenumber = $this->_setPostVariable('brq_invoicenumber');
        $this->amount = $this->_setPostVariable('brq_amount');
        if (isset($_POST['brq_amount_credit']))
            $this->amount_credit = $_POST['brq_amount_credit'];
        $this->currency = $this->_setPostVariable('brq_currency');
        $this->_test = $this->_setPostVariable('brq_test');
        $this->timestamp = $this->_setPostVariable('brq_timestamp');
        $this->transactions = $this->_setPostVariable('brq_transactions');
        $this->_signature = $this->_setPostVariable('brq_signature');

        if (isset($this->statuscode)) {
            $responseArray = $this->responseCodes[(int) $this->statuscode];
            $this->status = $responseArray['status'];
            $this->message = $responseArray['message'];
        }
    }

    function checkForSequentialNumbersPlugin() {
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        return is_plugin_active('woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers-pro.php');
    }

    abstract protected function _parsePostResponseChild();

    protected function _verifyResponse() {
        $verified = false;
        if ($this->isReceived()) {
            $verifiedSignature = $this->_verifySignature();
            $verifiedDigest = $this->_verifyDigest();

            if ($verifiedSignature === true && $verifiedDigest === true) {
                $verified = true;
            }
        };
        return $verified;
    }

    protected function _verifySignature() {
        $verified = false;

        //save response XML to string
        $responseDomDoc = $this->_responseXML;

        $responseString = $responseDomDoc->saveXML();

        //retrieve the signature value
        $sigatureRegex = "#<SignatureValue>(.*)</SignatureValue>#ims";
        $signatureArray = array();
        preg_match_all($sigatureRegex, $responseString, $signatureArray);

        //decode the signature
        $signature = $signatureArray[1][0];
        $sigDecoded = base64_decode($signature);

        $xPath = new DOMXPath($responseDomDoc);

        //register namespaces to use in xpath query's
        $xPath->registerNamespace('wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
        $xPath->registerNamespace('sig', 'http://www.w3.org/2000/09/xmldsig#');
        $xPath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        //Get the SignedInfo nodeset
        $SignedInfoQuery = '//wsse:Security/sig:Signature/sig:SignedInfo';
        $SignedInfoQueryNodeSet = $xPath->query($SignedInfoQuery);

        //Record requests in debug mode

        $SignedInfoNodeSet = $SignedInfoQueryNodeSet->item(0);

        //Canonicalize nodeset
        $signedInfo = $SignedInfoNodeSet->C14N(true, false);

        //get the public key
        $certificate_path = dirname(__FILE__) . '/../../' . BuckarooConfig::CERTIFICATE_PATH . 'Checkout.pem';
        if (!file_exists($certificate_path)) {
            $logger = new BuckarooLogger(1);
            $logger->logForUser($certificate_path.' do not exists');
        }
        $pubKey = openssl_get_publickey(openssl_x509_read(file_get_contents($certificate_path)));

        //verify the signature
        $sigVerify = openssl_verify($signedInfo, $sigDecoded, $pubKey);

        if ($sigVerify === 1) {
            $verified = true;
        }

        // workaround
        if (!$verified) {
            $keyDetails = openssl_pkey_get_details($pubKey);
            if (!empty($keyDetails["key"])) {
                $sigVerify = openssl_verify($signedInfo, $sigDecoded, $keyDetails["key"]);
                if ($sigVerify === 1) {
                    $verified = true;
                }
            }
        }

        return $verified;
    }

    protected function _verifyDigest() {
        $verified = false;

        //save response XML to string
        $responseDomDoc = $this->_responseXML;
        $responseString = $responseDomDoc->saveXML();

        //retrieve the signature value
        $digestRegex = "#<DigestValue>(.*?)</DigestValue>#ims";
        $digestArray = array();
        preg_match_all($digestRegex, $responseString, $digestArray);

        $digestValues = array();
        foreach ($digestArray[1] as $digest) {
            $digestValues[] = $digest;
        }

        $xPath = new DOMXPath($responseDomDoc);

        //register namespaces to use in xpath query's
        $xPath->registerNamespace('wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
        $xPath->registerNamespace('sig', 'http://www.w3.org/2000/09/xmldsig#');
        $xPath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

        $controlHashReference = $xPath->query('//*[@Id="_control"]')->item(0);
        $controlHashCanonical = $controlHashReference->C14N(true, false);
        $controlHash = base64_encode(pack('H*', sha1($controlHashCanonical)));

        $bodyHashReference = $xPath->query('//*[@Id="_body"]')->item(0);
        $bodyHashCanonical = $bodyHashReference->C14N(true, false);
        $bodyHash = base64_encode(pack('H*', sha1($bodyHashCanonical)));

        if (in_array($controlHash, $digestValues) === true && in_array($bodyHash, $digestValues) === true) {
            $verified = true;
        }

        return $verified;
    }

    /**
     * Checks if the post recieved is valid by checking its signature field.
     * This field is unique for every payment and every store.
     * Also calls method that checks if an order is able to be updated further.
     * Canceled, completed, holded etc. orders are not able to be updated
     */
    protected function _canProcessPush() {
        $correctSignature = false;
        //   $canUpdate = false;
        $signature = $this->_calculateSignature();
        if ($signature === $_POST['brq_signature']) {
            $correctSignature = true;
        }
        /*
          //check if the order can recieve further status updates
          if ($correctSignature === true) {
          $canUpdate = $this->_canUpdate();
          }

          $return = array(
          (bool) $correctSignature,
          (bool) $canUpdate,
          );
         * 
         */
        return $correctSignature; //$return;
    }

    /**
     * Checks if the order can be updated by checking if its state and status is not
     * complete, closed, cancelled or holded and the order can be invoiced
     * 
     * @return boolean $return
     */
    protected function _canUpdate() {
        $return = false;

        // Get successful state and status
        $completedStateAndStatus = array('complete', 'complete');
        $cancelledStateAndStatus = array('canceled', 'canceled');
        $holdedStateAndStatus = array('holded', 'holded');
        $closedStateAndStatus = array('closed', 'closed');

        $currentStateAndStatus = array($this->_order->getState(), $this->_order->getStatus());

        //prevent completed orders from recieving further updates
        if ($completedStateAndStatus != $currentStateAndStatus && $cancelledStateAndStatus != $currentStateAndStatus && $holdedStateAndStatus != $currentStateAndStatus && $closedStateAndStatus != $currentStateAndStatus
        ) {
            $return = true;
        } else {
            $logger = new BuckarooLogger(BuckarooLogger::INFO, 'response');
            $logger->logWarn("\nOrder already has succes, complete, closed, or holded state \n\n");
        }

        return $return;
    }

    /**
     * Determines the signature using array sorting and the SHA1 hash algorithm
     * 
     * @return string $signature
     */
    protected function _calculateSignature() {
        $origArray = $_POST;

        $url_decode = true;
        if (isset($origArray['brq_transaction_method']) && $origArray['brq_transaction_method'] == 'Payconiq') {
            $url_decode = false;
        }
        
        if (isset($origArray['brq_payment_method']) && $origArray['brq_payment_method'] == 'Payconiq') {
            $url_decode = false;
        }        

        unset($origArray['brq_signature']);
        foreach($origArray as $key => $val) {
            $origArray[$key] = stripslashes($val);
        }
        //sort the array
        $sortableArray = $this->buckarooSort($origArray);

        //turn into string and add the secret key to the end
        $signatureString = '';
        foreach ($sortableArray as $key => $value) {
            if ($url_decode) {
                $value = urldecode($value);
            }
            $signatureString .= $key . '=' . $value;
        }
        $transaction_method = isset($origArray['brq_transaction_method']) ? $origArray['brq_transaction_method'] : NULL;
        $signatureString .= BuckarooConfig::get('BUCKAROO_SECRET_KEY', $transaction_method);
//        $signatureString .= BuckarooConfig::get('BUCKAROO_SECRET_KEY', $origArray['brq_transaction_method']);
        //return the SHA1 encoded string for comparison
        $signature = SHA1($signatureString);

        return $signature;
    }

    public function getCartId() {
        return (int) substr($this->invoicenumber, 1);
    }
}
