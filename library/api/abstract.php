<?php
// require_once dirname(__FILE__).'/../config.php';
require_once dirname(__FILE__).'/../include.php';

/**
* @package Buckaroo
*/
abstract class BuckarooAbstract {
    
    const BUCKAROO_SUCCESS           = 'BUCKAROO_SUCCESS';
    const BUCKAROO_FAILED            = 'BUCKAROO_FAILED';
    const BUCKAROO_CANCELED          = 'BUCKAROO_CANCELED';
    const BUCKAROO_ERROR             = 'BUCKAROO_ERROR';
    const BUCKAROO_NEUTRAL           = 'BUCKAROO_NEUTRAL';
    const BUCKAROO_PENDING_PAYMENT   = 'BUCKAROO_PENDING_PAYMENT';
    const BUCKAROO_INCORRECT_PAYMENT = 'BUCKAROO_INCORRECT_PAYMENT';
    const REQUEST_ERROR              = 'REQUEST_ERROR';
    
	/**
	 *  List of possible response codes sent by buckaroo.
	 *  This is the list for the BPE 3.0 gateway.
	 */
    public $responseCodes = array(
        190 => array(
            'message' => 'Success',
            'status'  => self::BUCKAROO_SUCCESS,
        ),
        490 => array(
           'message' => 'Payment failure',
            'status'  => self::BUCKAROO_FAILED,
        ),
        491 => array(
            'message' => 'Validation error',
            'status'  => self::BUCKAROO_FAILED,
        ),
        492 => array(
            'message' => 'Technical error',
            'status'  => self::BUCKAROO_ERROR,
        ),
        690 => array(
            'message' => 'Payment rejected',
            'status'  => self::BUCKAROO_FAILED,
        ),
        790 => array(
            'message' => 'Waiting for user input',
            'status'  => self::BUCKAROO_PENDING_PAYMENT,
        ),
        791 => array(
            'message' => 'Waiting for processor',
            'status'  => self::BUCKAROO_PENDING_PAYMENT,
        ),
        792 => array(
            'message' => 'Waiting on consumer action',
            'status'  => self::BUCKAROO_PENDING_PAYMENT,
        ),
        793 => array(
            'message' => 'Payment on hold',
            'status'  => self::BUCKAROO_PENDING_PAYMENT,
        ),
        890 => array(
            'message' => 'Cancelled by consumer',
            'status'  => self::BUCKAROO_CANCELED,
        ),
        891 => array(
            'message' => 'Cancelled by merchant',
            'status'  => self::BUCKAROO_FAILED,
        ),
    );
    
    /**
     * Split the request response into three, get the values of those parts and echo them.
     * 
     * @access public
     * @param array $requestResponse
     */
    public function printResponse($requestResponse) {
        list($response, $responseXML, $requestXML) = $requestResponse;

        echo "The SOAP request has been sent. <br/>";
        if (is_object($requestXML) && is_object($responseXML)) {
            echo "Request: " . var_export($requestXML->saveXML(), true) . "<br/><br/>";
            echo "Response: " . var_export($response, true) . "<br/><br/>";
            echo "Response XML:" . var_export($responseXML->saveXML(), true) . "<br/><br/>";
        }

        echo "Response recieved. \n";
    }
    
    /**
     * Custom array sort function.
     * 
     * @param array $array
     * @param array $sortedArray
     */
    public function buckarooSort($array) {
        $arrayToSort = array();
        $origArray = array();
        foreach ($array as $key => $value) {
            $arrayToSort[strtolower($key)] = $value;
            $origArray[strtolower($key)] = $key;
        }
        
        ksort($arrayToSort);
        
        $sortedArray = array();
        foreach($arrayToSort as $key => $value) {
            $key = $origArray[$key];
            $sortedArray[$key] = $value;
        }
        
        return $sortedArray;
    }
}

/**
 * @package Buckaroo
 */
class Software {
    public $PlatformName;
    public $PlatformVersion;
    public $ModuleSupplier;
    public $ModuleName;
    public $ModuleVersion;
}