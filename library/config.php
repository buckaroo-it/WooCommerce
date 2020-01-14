<?php
require_once dirname(__FILE__).'/api/config/configcore.php';

/**
 * @package Buckaroo
 */
class BuckarooConfig extends BuckarooConfigCore {
    const NAME = 'buckaroo3';
    const PLUGIN_NAME = 'Buckaroo BPE 3.0 official plugin';
    const VERSION = '2.13.2';

    const SHIPPING_SKU = "WC8888";
   
    /**
     * Check if mode is test or live
     * 
     * @access public
     * @param string $key 
     * @return string $val
     */
    public static function get($key, $paymentId = null) {
        $val = null;

        if (is_null($paymentId)){
            $paymentId = $GLOBALS['plugin_id'];
        } else {
            $paymentId = 'woocommerce_buckaroo_' . $paymentId . '_settings';
        }

        if (!empty($paymentId)) {
            $options = get_option( $paymentId, null );
            if ((empty($options['usemaster']) || $options['usemaster'] != 'no') && !get_option('woocommerce_buckaroo_mastersettings_settings') != TRUE) {
                $masterOptions = get_option('woocommerce_buckaroo_mastersettings_settings', null );

                if (is_array($options) && is_array($masterOptions)) {
                    $options = array_replace($options, $masterOptions);
                }
          
                if(!is_array($options) && is_array($masterOptions)) {
                    $options = $masterOptions;
                }
            }
            switch ($key) {
                case 'CULTURE':
                    $val = $options['culture'];
                    break;
                case 'BUCKAROO_TRANSDESC':
                    $val = $options['transactiondescription'];
                    break;
                case 'BUCKAROO_USE_NOTIFICATION':
                    $val = (empty($options['usenotification']) ?  FALSE : $options['usenotification']);
                    break;
                case 'BUCKAROO_NOTIFICATION_DELAY':
                    if ($options['usenotification'] != FALSE) {
                        $val = $options['notificationdelay'];
                    } else {
                        $val = '0';
                    }
                    break;
                case 'BUCKAROO_CERTIFICATE_PATH':
                    $val = "";
                    if (!empty($options['selectcertificate']) && $options['selectcertificate'] != 'none') {
                        $selectedCert = $options['selectcertificate'];
                        $val = $options["certificatecontents$selectedCert"];
                    }
                    //Start - Support old version of certificate storage
                    if ($val == "" && (empty($options["certificatecontents1"]) || $options["certificatecontents1"] == "")) {
                        $tmp_options = get_option($paymentId, null);
                        $certificate_name = !empty($tmp_options['certificate']) ?  $tmp_options['certificate'] : 'BuckarooPrivateKey.pem';
                        $upload_dir = wp_upload_dir();
                        $val = file_get_contents($upload_dir["basedir"]."/woocommerce_uploads/".$certificate_name);
                    }
                    //End - Support old version of certificate storage

                    break;
                case 'BUCKAROO_MERCHANT_KEY':
                    $val = $options['merchantkey'];
                    break;
                case 'BUCKAROO_SECRET_KEY':
                    $val = $options['secretkey'];
                    break;
                case 'BUCKAROO_CERTIFICATE_THUMBPRINT':
                    $val = $options['thumbprint'];
                    break;
                case 'BUCKAROO_DEBUG':
                    $options = get_option('woocommerce_buckaroo_mastersettings_settings', null );//Debug switch only in mastersettings
                    $val = $options['debugmode'];
                    break;
            }
        }
        if (is_null($val) || $val === false) {
            return parent::get($key);
        } else {
            return $val;
        }
    }
    
    /**
     * Check if mode is test or live
     * 
     * @access public
     * @param string $key defaults to Null 
     * @return string $mode
     */
    public static function getMode($key = null) {
        $options = get_option( $GLOBALS['plugin_id'], null );
        // if (!empty($options['usemaster']) && $options['usemaster'] != 'no') {
        //     $options = get_option('woocommerce_buckaroo_mastersettings_settings', null );
        // }
        $mode = (!empty($options['mode']) && $options['mode'] == "live") ? 'live' : 'test';
        return $mode;
    }
    
    /**
     * Override the old BuckarooConfig::CHANNEL; method and allow custom payment method channels
     * 
     * @access public
     * @param string $payment_type defaults to Null 
     * @param string $method defaults to Null 
     * @return string $channel
     */
    public static function getChannel($payment_type = null, $method = null) {
        $channel = BuckarooConfig::CHANNEL;
        if ($payment_type != null && $method != null) {
            $overrides = array(
                'afterpay' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => 'BackOffice'), 
                'afterpaynew' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'creditcard' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => '', 'process_capture' => 'BackOffice'),
                'emaestro' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''), 
                'giftcard' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''), 
                'giropay' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''), 
                'ideal' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'mistercash' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''), 
                'paygarant' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''), 
                'paypal' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''), 
                'paysafecard' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''), 
                'sepadirectdebit' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => 'BackOffice'), 
                'sofortbanking' => array('process_payment' => '', 'process_capture' => '',  'process_refund' => ''),
                'transfer' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'payconiq' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'nexi' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'p24' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
		'applepay' => array('process_payment' => '', 'process_refund' => ''),
            );
            //'' defaults to Web, set by BuckarooConfig::CHANNEL (see library/api/config/coreconfig.php);
            $channel = ($overrides[$payment_type][$method] != '') ? $overrides[$payment_type][$method] : $channel;
        }
        return $channel;

    }
   
    /**
     * Create and populate the $Software object
     * 
     * @access public
     * @return Object
     */
    public static function getSoftware() {
        global $woocommerce;
        $Software = new Software();
        $Software->PlatformName = 'WordPress WooCommerce';
        $Software->PlatformVersion = $woocommerce->version;
        $Software->ModuleSupplier = 'Buckaroo';
        $Software->ModuleName = BuckarooConfig::PLUGIN_NAME;
        $Software->ModuleVersion = BuckarooConfig::VERSION;
        return $Software;
    }
    
} ?>
