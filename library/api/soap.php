<?php
    if (!class_exists('SoapClient')) {
        $logger = new BuckarooLogger(1);
        $logger->logForUser('SoapClient is not installed. Please ask your hosting provider to install SoapClient <a style="text-decoration: underline; color: #0000FF" href="http://php.net/manual/en/soap.installation.php">http://php.net/manual/en/soap.installation.php</a>');
    } else { 
        final class BuckarooSoap extends BuckarooAbstract {
        
            private $_vars;
            
            public function setVars($vars = array()) {
                $this->_vars = $vars;
            }
               
            public function __construct($data) {
                $this->setVars($data);
            }
            
            public function transactionRequest() {
                try {
                    //first attempt: use the cached WSDL
                    $client = new SoapClientWSSEC(
                        BuckarooConfig::WSDL_URL,
                        array(
                            'trace' => 1,
                            'cache_wsdl' => WSDL_CACHE_DISK,
                    ));
                } catch (Exception $e){ //(SoapFault $e) {
                    try {
                        //second attempt: use an uncached WSDL
                        ini_set('soap.wsdl_cache_ttl', 1);
                        $client = new SoapClientWSSEC(
                            BuckarooConfig::WSDL_URL,
                            array(
                                'trace' => 1,
                                'cache_wsdl' => WSDL_CACHE_NONE,
                        ));
                    } catch (Exception $e){ //(SoapFault $e) {
                        try {
                            //third and final attempt: use the supplied wsdl found in the lib folder
                            $client = new SoapClientWSSEC(
                            dirname(__FILE__).BuckarooConfig::WSDL_FILE,
                                array(
                                    'trace' => 1,
                                    'cache_wsdl' => WSDL_CACHE_NONE,
                            ));
                        } catch (Exception $e){ //(SoapFault $e) {
                            return $this->_error($e);
                        }
                    }
                 }
                
                $client->thumbprint = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
                $client->privateKey = BuckarooConfig::get('BUCKAROO_CERTIFICATE_PATH');
                
                $search = Array(",", " ");
                $replace = Array(".", "");
                $TransactionRequest = new Body();
                $TransactionRequest->Currency = $this->_vars['currency'];

                if (!empty($this->_vars['OriginalInvoiceNumber'])) {
                    $TransactionRequest->OriginalInvoiceNumber = $this->_vars['OriginalInvoiceNumber'];
                }

                if (!empty($this->_vars['AmountVat'])) {
                    $TransactionRequest->AmountVat = $this->_vars['AmountVat'];
                }

                $debit = round($this->_vars['amountDebit'], 2);
                $credit = round($this->_vars['amountCredit'], 2);
                $TransactionRequest->AmountDebit = str_replace($search, $replace, $debit);
                $TransactionRequest->AmountCredit = str_replace($search, $replace, $credit);
                $TransactionRequest->Invoice = $this->_vars['invoice'];
                $TransactionRequest->Order = $this->_vars['order'];
                $TransactionRequest->Description = $this->_vars['description'];
                $TransactionRequest->ReturnURL = $this->_vars['returnUrl'];
                if (!empty($this->_vars['OriginalTransactionKey'])) {
                    $TransactionRequest->OriginalTransactionKey = $this->_vars['OriginalTransactionKey'];
                }
                $TransactionRequest->StartRecurrent = FALSE;
                
                if (isset($this->_vars['customVars']['servicesSelectableByClient']) && isset($this->_vars['customVars']['continueOnIncomplete'])) {
                    $TransactionRequest->ServicesSelectableByClient = $this->_vars['customVars']['servicesSelectableByClient'];
                    $TransactionRequest->ContinueOnIncomplete       = $this->_vars['customVars']['continueOnIncomplete'];
                }
                /*
                if (array_key_exists('OriginalTransactionKey', $this->_vars)) {
                    $TransactionRequest->OriginalTransactionKey = $this->_vars['OriginalTransactionKey'];
                }
                */
                if (isset($this->_vars['customParameters'])) {
                    $TransactionRequest = $this->_addCustomParameters($TransactionRequest);
                }

                $TransactionRequest->Services = new Services();
                $this->_addServices($TransactionRequest);

                file_put_contents('/var/www/sites/buggish.txt', json_encode($TransactionRequest));

                /*
                $TransactionRequest->Services = new Services();
                 
                $TransactionRequest->Services->Service = new Service();
                $TransactionRequest->Services->Service->Name= $this->_vars['service']['type'];
                $TransactionRequest->Services->Service->Action = $this->_vars['service']['action'];;
                $TransactionRequest->Services->Service->Version = $this->_vars['service']['version'];;
                */
                $TransactionRequest->ClientIP = new IPAddress();
                $TransactionRequest->ClientIP->Type = 'IPv4';
                $TransactionRequest->ClientIP->_ = $_SERVER['REMOTE_ADDR'];

                $sellerProtectionEnabled = false;
                foreach ($TransactionRequest->Services->Service as $key => $service) {
                    if($service->Action == 'extraInfo') {
                        $sellerProtectionEnabled = true;
                    }
                }

                foreach ($TransactionRequest->Services->Service as $key => $service) {
                    if($service->Action == 'Pay' && $service->Name == 'paypal' && $sellerProtectionEnabled == true) {
                        continue;
                    }
                    $this->_addCustomFields($TransactionRequest, $key, $service->Name);
                }

                $Header = new Header();
                $Header->MessageControlBlock = new MessageControlBlock();
                $Header->MessageControlBlock->Id = '_control';
                $Header->MessageControlBlock->WebsiteKey = BuckarooConfig::get('BUCKAROO_MERCHANT_KEY');
                $Header->MessageControlBlock->Culture = BuckarooConfig::get('CULTURE');
                $Header->MessageControlBlock->TimeStamp = time();
                //Old Method 
                // $Header->MessageControlBlock->Channel = BuckarooConfig::CHANNEL;
                //New Method 
                $Header->MessageControlBlock->Channel = $this->_vars['channel'];

                $Header->MessageControlBlock->Software = BuckarooConfig::getSoftware();
                $Header->Security = new SecurityType();
                $Header->Security->Signature = new SignatureType();
                
                $Header->Security->Signature->SignedInfo = new SignedInfoType();
                $Header->Security->Signature->SignedInfo->CanonicalizationMethod = new CanonicalizationMethodType();
                $Header->Security->Signature->SignedInfo->CanonicalizationMethod->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
                $Header->Security->Signature->SignedInfo->SignatureMethod = new SignatureMethodType();
                $Header->Security->Signature->SignedInfo->SignatureMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
                
                $Reference = new ReferenceType();
                $Reference->URI = '#_body';
                $Transform = new TransformType();
                $Transform->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#'; 
                $Reference->Transforms=array($Transform);
                
                $Reference->DigestMethod = new DigestMethodType();
                $Reference->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1'; 
                $Reference->DigestValue = '';
                
                $Transform2 = new TransformType();
                $Transform2->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#'; 
                $ReferenceControl = new ReferenceType();
                $ReferenceControl->URI = '#_control';
                $ReferenceControl->DigestMethod = new DigestMethodType();
                $ReferenceControl->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1'; 
                $ReferenceControl->DigestValue = '';
                $ReferenceControl->Transforms=array($Transform2);
                
                $Header->Security->Signature->SignedInfo->Reference = array($Reference,$ReferenceControl);
                $Header->Security->Signature->SignatureValue = ''; 
                
                $soapHeaders = array();
                $soapHeaders[] = new SOAPHeader('https://checkout.buckaroo.nl/PaymentEngine/', 'MessageControlBlock', $Header->MessageControlBlock); 
                $soapHeaders[] = new SOAPHeader('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'Security', $Header->Security); 
                $client->__setSoapHeaders($soapHeaders);
                if ($this->_vars['mode'] == 'test') {
                    $location = BuckarooConfig::LOCATION_TEST;
                } else {
                    $location = BuckarooConfig::LOCATION;
                }
                
                $client->__SetLocation($location);

                try {
                    $response = $client->TransactionRequest($TransactionRequest);
                } catch (SoapFault $e) {
                    $logger = new BuckarooLogger(1);
                    $logger->logForUser($e->getMessage());
                    //$this->logException($e->getMessage());
                    return $this->_error($client);
                } catch (Exception $e) {
                    $logger = new BuckarooLogger(1);
                    $logger->logForUser($e->getMessage());
                    //$this->logException($e->getMessage());
                    return $this->_error($client);
                }
                
                if (is_null($response)) {
                    $response = false;
                }
                
                $responseXML = $client->__getLastResponse();
                $requestXML = $client->__getLastRequest();
                
                $responseDomDOC = new DOMDocument();
                $responseDomDOC->loadXML($responseXML);
                $responseDomDOC->preserveWhiteSpace = FALSE;
                $responseDomDOC->formatOutput = TRUE;
                
                $requestDomDOC = new DOMDocument();
                $requestDomDOC->loadXML($requestXML);
                $requestDomDOC->preserveWhiteSpace = FALSE;
                $requestDomDOC->formatOutput = TRUE;
                        
                return array($response, $responseDomDOC, $requestDomDOC);
            }
            
            protected function _addServices(&$TransactionRequest) {
                $services = array();
                foreach($this->_vars['services'] as $fieldName => $value) {
                    if (empty($value)) {
                        continue;
                    }

                    if(isset($value['action2']) && !empty($value['action2'])) {
                        $service          = new Service();
                        $service->Name    = $fieldName;
                        $service->Action  = $value['action2'];
                        $service->Version = $value['version2'];
                    
                        $services[] = $service;
                    }

                    $service          = new Service();
                    $service->Name    = $fieldName;
                    $service->Action  = $value['action'];
                    $service->Version = $value['version'];
                    
                    if (isset($this->_vars['OriginalInvoiceNumber']) && !empty($this->_vars['OriginalInvoiceNumber']) && isset($this->_vars['AmountVat']) && (!empty($this->_vars['AmountVat']) || $this->_vars['AmountVat'] == 0 ) ) {

                        $service->RequestParameter[0] = new RequestParameter();

                        $service->RequestParameter[0]->Name = "OriginalInvoiceNumber";
                        $service->RequestParameter[0]->Group = "";
                        $service->RequestParameter[0]->GroupID = "";
                        $service->RequestParameter[0]->_ = $this->_vars['OriginalInvoiceNumber'];


                        $service->RequestParameter[1] = new RequestParameter();

                        $service->RequestParameter[1]->Name = "AmountVat";
                        $service->RequestParameter[1]->Group = "";
                        $service->RequestParameter[1]->GroupID = "";
                        $service->RequestParameter[1]->_ = $this->_vars['AmountVat'];
                    }                    
                    
                    $services[] = $service;
                }

                $TransactionRequest->Services->Service = $services;
            }
            
            protected function _addCustomFields(&$TransactionRequest, $key, $name) {
                if (
                    !isset($this->_vars['customVars']) 
                    || !isset($this->_vars['customVars'][$name])
                    || empty($this->_vars['customVars'][$name])
                ) {
                    unset($TransactionRequest->Services->Service->RequestParameter);
                    return;
                }
                
                $requestParameters = array();
                foreach($this->_vars['customVars'][$name] as $fieldName => $value) {
                    if (
                        (is_null($value) || $value === '')
                        || (
                            is_array($value)
                            && (isset($value['value']) && (is_null($value['value']) || $value['value'] === ''))
                           )
                    ) {
                        continue;
                    }

                    if (is_array($value)) {
                        if (isset($value[0]) && is_array($value[0]))
                        {
                            foreach ($value as $k => $val)
                            {
                                $requestParameter = new RequestParameter();
                                $requestParameter->Name = $fieldName;
                                $requestParameter->Group = $val['group'];
                                $requestParameter->GroupID = $k+1;
                                $requestParameter->_ = $val['value'];
                                $requestParameters[] = $requestParameter;
                            }
                        } else {
                            $requestParameter = new RequestParameter();
                            $requestParameter->Name = $fieldName;
                            $requestParameter->Group = $value['group'];
                            $requestParameter->_ = $value['value'];
                            $requestParameters[] = $requestParameter;
                        };
                    } else {
                        $requestParameter = new RequestParameter();
                        $requestParameter->Name = $fieldName;
                        $requestParameter->_ = $value;
                        $requestParameters[] = $requestParameter;
                    }
                    
                }
                
                if (empty($requestParameters)) {
                    unset($TransactionRequest->Services->Service->RequestParameter);
                    return;
                } else {
                    $TransactionRequest->Services->Service[$key]->RequestParameter = $requestParameters;
                }
            }
            
            protected function _addCustomParameters(&$TransactionRequest) {
                $requestParameters = array();
                foreach($this->_vars['customParameters'] as $fieldName => $value) {
                    if (
                        (is_null($value) || $value === '')
                        || (
                            is_array($value)
                            && (is_null($value['value']) || $value['value'] === '')
                           )
                    ) {
                        continue;
                    }

                    $requestParameter = new RequestParameter();
                    $requestParameter->Name = $fieldName;
                    if (is_array($value)) {
                        $requestParameter->Group = $value['group'];
                        $requestParameter->_ = $value['value'];
                    } else {
                        $requestParameter->_ = $value;
                    }
                    
                    $requestParameters[] = $requestParameter;
                }
                
                if (empty($requestParameters)) {
                    unset($TransactionRequest->AdditionalParameters);
                    return;
                } else {
                    $TransactionRequest->AdditionalParameters = $requestParameters;
                }
                
                return $TransactionRequest;
            }
            
            protected function _error($client = false) {
                $response = false;

                $responseDomDOC = new DOMDocument();
                $requestDomDOC = new DOMDocument();
                if ($client) {
                    $responseXML = $client->__getLastResponse();
                    $requestXML = $client->__getLastRequest();
                
                    if (!empty($responseXML)) {
                        $responseDomDOC->loadXML($responseXML);
                        $responseDomDOC->preserveWhiteSpace = FALSE;
                        $responseDomDOC->formatOutput = TRUE;
                    }
                    
                    if (!empty($requestXML)) {
                        $requestDomDOC->loadXML($requestXML);
                        $requestDomDOC->preserveWhiteSpace = FALSE;
                        $requestDomDOC->formatOutput = TRUE;
                    }
                }
                
                return array($response, $responseDomDOC, $requestDomDOC);
            }
        }

        class SoapClientWSSEC extends SoapClient {
            /**
             * Contains the request XML
             * @var DOMDocument
             */
            private $document;
            
            /**
             * Path to the privateKey file
             * @var string 
             */
            public $privateKey = '';
            
            /**
             * Password for the privatekey
             * @var string 
             */
            public $privateKeyPassword = '';
            
            /**
             * Thumbprint from Payment Plaza
             * @var type 
             */ 
            public $thumbprint = '';
            
            public function __doRequest ($request , $location , $action , $version , $one_way = 0 ) {
                // Add code to inspect/dissect/debug/adjust the XML given in $request here
                $domDOC = new DOMDocument();
                $domDOC->preserveWhiteSpace = FALSE;
                $domDOC->formatOutput = TRUE;
                $domDOC->loadXML($request);
                
                //Sign the document                 
                $domDOC = $this->SignDomDocument($domDOC);

                //Record requests in debug mode
                writeToDebug($domDOC, "Request");
                
                // Uncomment the following line, if you actually want to do the request
                return parent::__doRequest($domDOC->saveXML($domDOC->documentElement), $location, $action, $version, $one_way);
            }
            
            //Get nodeset based on xpath and ID
            private function getReference($ID, $xPath) {   
                $query = '//*[@Id="'.$ID.'"]';
                $nodeset = $xPath->query($query);
                return $nodeset->item(0);
            }

            //Canonicalize nodeset
            private function getCanonical($Object) {
                return $Object->C14N(true, false);
            }

            //Calculate digest value (sha1 hash)
            private function calculateDigestValue($input) {
                return base64_encode(pack('H*',sha1($input)));
            }
            
            private function signDomDocument($domDocument) {
                //create xPath
                $xPath = new DOMXPath($domDocument);
                    
                //register namespaces to use in xpath query's
                $xPath->registerNamespace('wsse','http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
                $xPath->registerNamespace('sig','http://www.w3.org/2000/09/xmldsig#');
                $xPath->registerNamespace('soap','http://schemas.xmlsoap.org/soap/envelope/');
                
                //Set id on soap body to easily extract the body later.
                $bodyNodeList = $xPath->query('/soap:Envelope/soap:Body');
                $bodyNode = $bodyNodeList->item(0);
                $bodyNode->setAttribute('Id','_body');
                
                //Get the digest values
                $controlHash = $this->CalculateDigestValue($this->GetCanonical($this->GetReference('_control', $xPath)));
                $bodyHash = $this->CalculateDigestValue($this->GetCanonical($this->GetReference('_body', $xPath)));
                
                //Set the digest value for the control reference
                $Control = '#_control';
                $controlHashQuery = $query = '//*[@URI="'.$Control.'"]/sig:DigestValue';
                $controlHashQueryNodeset = $xPath->query($controlHashQuery);
                $controlHashNode = $controlHashQueryNodeset->item(0);
                $controlHashNode->nodeValue = $controlHash;
                
                //Set the digest value for the body reference
                $Body = '#_body';
                $bodyHashQuery = $query = '//*[@URI="'.$Body.'"]/sig:DigestValue';
                $bodyHashQueryNodeset = $xPath->query($bodyHashQuery);
                $bodyHashNode = $bodyHashQueryNodeset->item(0);
                $bodyHashNode->nodeValue = $bodyHash;
                
                //Get the SignedInfo nodeset
                $SignedInfoQuery = '//wsse:Security/sig:Signature/sig:SignedInfo';
                $SignedInfoQueryNodeSet = $xPath->query($SignedInfoQuery);
                $SignedInfoNodeSet = $SignedInfoQueryNodeSet->item(0);
                    
                //Canonicalize nodeset
                $signedINFO = $this->GetCanonical($SignedInfoNodeSet);

                //[9yards][JW][2017-04-20] Start edit access to certificate
                //****Old method of accessing certificate****\\
                // if (!file_exists($this->privateKey)) {
                //     $logger = new BuckarooLogger(1);
                //     $logger->logForUser($this->privateKey.' do not exists');
                // }
                // $fp = fopen($this->privateKey, "r");
                // $priv_key = fread($fp, 8192);
                // fclose($fp);

                //****New method of accessing certificate****\\
                if ($this->privateKey == ''){   
                    $logger = new BuckarooLogger(1);
                    $logger->logForUser('Certificate has not been uploaded');
                }
                $priv_key = substr($this->privateKey, 0, 8192);
                //[9yards][JW][2017-04-20] End edit access to certificate

                if ($priv_key === false) {
                    throw new Exception('Unable to read certificate.');
                }
                
                $pkeyid = openssl_get_privatekey($priv_key, '');    
                if ($pkeyid === false) {
                    throw new Exception('Unable to retrieve private key from certificate.');
                }
                
                //Sign signedinfo with privatekey
                $signature2 = null;
                $signatureCreate = openssl_sign($signedINFO, $signature2, $pkeyid);
                
                //Add signature value to xml document
                $sigValQuery = '//wsse:Security/sig:Signature/sig:SignatureValue';
                $sigValQueryNodeset = $xPath->query($sigValQuery);
                $sigValNodeSet = $sigValQueryNodeset->item(0);  
                $sigValNodeSet->nodeValue = base64_encode($signature2);
                
                //Get signature node
                $sigQuery = '//wsse:Security/sig:Signature';
                $sigQueryNodeset = $xPath->query($sigQuery);
                $sigNodeSet = $sigQueryNodeset->item(0);    
                
                //Create keyinfo element and Add public key to KeyIdentifier element
                $KeyTypeNode = $domDocument->createElementNS("http://www.w3.org/2000/09/xmldsig#","KeyInfo");   
                $SecurityTokenReference = $domDocument->createElementNS('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd','SecurityTokenReference');
                $KeyIdentifier = $domDocument->createElement("KeyIdentifier");
                $KeyIdentifier->nodeValue = $this->thumbprint;
                $KeyIdentifier->setAttribute('ValueType','http://docs.oasis-open.org/wss/oasis-wss-soap-message-security-1.1#ThumbPrintSHA1');
                $SecurityTokenReference->appendChild($KeyIdentifier);   
                $KeyTypeNode->appendChild($SecurityTokenReference);     
                $sigNodeSet->appendChild($KeyTypeNode);    
                return $domDocument;
            }
        }
    }
    class Header {
        public $MessageControlBlock;
    }

    class SecurityType {
        public $Signature;
    }

    class SignatureType {
        public $SignedInfo;
        public $SignatureValue;
        public $KeyInfo;
    }

    class SignedInfoType {
        public $CanonicalizationMethod;
        public $SignatureMethod;
        public $Reference;
    }

    class ReferenceType {
        public $Transforms;
        public $DigestMethod;
        public $DigestValue;
        public $URI;
        public $Id;
    }


    class TransformType {
        public $Algorithm;
    }

    class DigestMethodType {
        public $Algorithm;  
    }


    class SignatureMethodType {
        public $Algorithm;
    }

    class CanonicalizationMethodType {
        public $Algorithm;
    }

    class MessageControlBlock {
        public $Id;
        public $WebsiteKey;
        public $Culture;
        public $TimeStamp;
        public $Channel;
        public $Software;
    }

    class Body {
        public $Currency;
        public $AmountDebit;
        public $AmountCredit;
        public $Invoice;
        public $Order;
        public $Description;
        public $ClientIP;
        public $ReturnURL;
        public $ReturnURLCancel;
        public $ReturnURLError;
        public $ReturnURLReject;
        public $OriginalTransactionKey;
        public $StartRecurrent;
        public $Services;
        public $OriginalInvoiceNumber;
        public $AmountVat;
    }

    class Services {
        public $Global;
        public $Service;
    }

    class Service {
        public $RequestParameter;
        public $Name;
        public $Action;
        public $Version;
    }

    class RequestParameter {
        public $_;
        public $Name;
        public $Group;
        public $GroupID;
    }

    class IPAddress {
        public $_;
        public $Type;
    }
?>
