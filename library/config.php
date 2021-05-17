<?php
require_once dirname(__FILE__).'/api/config/configcore.php';

/**
 * @package Buckaroo
 */
class BuckarooConfig extends BuckarooConfigCore {
    const NAME = 'buckaroo3';
    const PLUGIN_NAME = 'Buckaroo BPE 3.0 official plugin';
    const VERSION = '2.18.1';

    const SHIPPING_SKU = "WC8888";

    private static $idinCategories;

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
            $paymentId = isset($GLOBALS['plugin_id']) ? $GLOBALS['plugin_id'] : '';
        } else {
            $paymentId = 'woocommerce_buckaroo_' . $paymentId . '_settings';
        }
        $options = [];
        if (!empty($paymentId)) {
            $options = get_option($paymentId, null);
        }
        if ((empty($options) || empty($options['usemaster']) || $options['usemaster'] != 'no') && !get_option('woocommerce_buckaroo_mastersettings_settings') != TRUE) {
            $masterOptions = get_option('woocommerce_buckaroo_mastersettings_settings', null );

            $enabled = isset($options['enabled'])?$options['enabled']:false;
            if (is_array($options) && is_array($masterOptions)) {
                $options = array_replace($options, $masterOptions);
            }

            if(!is_array($options) && is_array($masterOptions)) {
                $options = $masterOptions;
            }

            if(is_array($options) && $enabled){
                $options['enabled'] = $enabled;
            }
        }
        switch ($key) {
            case 'CULTURE':
                $val = $options['culture'];
                break;
            case 'BUCKAROO_TRANSDESC':
                $val = empty($options['transactiondescription']) ? "Buckaroo": $options['transactiondescription'];
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
            case 'BUCKAROO_USE_NEW_ICONS':
                $val = (empty($options['usenewicons']) ?  FALSE : $options['usenewicons']);
                break;
            case 'BUCKAROO_USE_IDIN':
                $val = (empty($options['useidin']) ?  FALSE : $options['useidin']);
                break;
            case 'BUCKAROO_IDIN_CATEGORIES':
                $val = (empty($options['idincategories']) ?  [] : $options['idincategories']);
                break;
            default:
            if(isset($options[$key]) && !empty($options[$key])){
                $val = $options[$key];
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
                'paypal' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'sepadirectdebit' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => 'BackOffice'),
                'sofortbanking' => array('process_payment' => '', 'process_capture' => '',  'process_refund' => ''),
                'transfer' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'payconiq' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'nexi' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'przelewy24' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
		        'applepay' => array('process_payment' => '', 'process_refund' => ''),
                'kbc' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'requesttopay' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'in3' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'billink' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
                'payperemail' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
//                'klarnapay' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
//                'klarnapii' => array('process_payment' => '', 'process_capture' => '', 'process_refund' => ''),
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

    public static function getIconPath($oldIcon, $newIcon) {
        $icon = self::get('BUCKAROO_USE_NEW_ICONS') ? $newIcon : $oldIcon;
        return plugins_url('buckaroo_images/' . $icon, __FILE__);
    }

    public static function isIdin($ids = []) {
        $isIdin = false;
        if (self::get('BUCKAROO_USE_IDIN')) {
            if (!isset(self::$idinCategories)) {
                self::$idinCategories = BuckarooConfig::getIdinCategories();
            }
            if (self::$idinCategories) {
                if ($ids) {
                    foreach ($ids as $id) {
                        if ($productCategories = get_the_terms($id, 'product_cat')) {
                            foreach ($productCategories as $productCategory) {
                                if (in_array($productCategory->term_id, self::$idinCategories)) {
                                    $isIdin = true;
                                    return $isIdin;
                                }
                            }
                        }
                    }
                }
                return $isIdin;
            } else {
                $isIdin = true;
                return $isIdin;
            }
        } else {
            return $isIdin;
        }
    }

    public static function getIdinMode() {
        return (self::get('BUCKAROO_USE_IDIN') == "live") ? 'live' : 'test';
    }

    public static function getIdinCategories() {
        return self::get('BUCKAROO_IDIN_CATEGORIES');
    }
    
} ?>
