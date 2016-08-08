<?php

require_once dirname(__FILE__).'/api/config/configcore.php';
class BuckarooConfig extends BuckarooConfigCore
{
   const NAME = 'buckaroo3';
   const PLUGIN_NAME = 'Buckaroo BPE 3.0 official plugin';
   const VERSION = '2.2.12';
   
   public static function get($key)
   {
       $val = null;
       if (!empty($GLOBALS['plugin_id'])) {
           $options = get_option( $GLOBALS['plugin_id'], null );
           switch ($key) {
               case 'CULTURE':
                   $val = $options['culture'];
                   break;
               case 'BUCKAROO_CERTIFICATE_PATH':
                   $upload_dir = wp_upload_dir();
                   $val = $upload_dir["basedir"].'/woocommerce_uploads/'.BuckarooConfig::get('BUCKAROO_CERTIFICATE_FILE');
                  break;
               case 'BUCKAROO_CERTIFICATE_FILE':
                   $val = $options['certificate'];
                   break;
               case 'BUCKAROO_CERTIFICATE_THUMBPRINT':
                   $val = $options['thumbprint'];
                   break;
               case 'BUCKAROO_MERCHANT_KEY':
                   $val = $options['merchantkey'];
                   break;
               case 'BUCKAROO_SECRET_KEY':
                   $val = $options['secretkey'];
                   break;
               case 'BUCKAROO_THUMBPRINT':
                   $val = $options['thumbprint'];
                   break;
           }
       }
       if (is_null($val) || $val === false)
            return parent::get($key);
       else
            return $val;
   }
   
   public static function getMode($key = null)
   {
       $options = get_option( $GLOBALS['plugin_id'], null );
       return ($options['mode'] == "live" ? "live" : "test");;
   }
   
   public static function getSoftware()
    {
        global $woocommerce;
        $Software = new Software();
        $Software->PlatformName = 'WordPress WooCommerce';
        $Software->PlatformVersion = $woocommerce->version;
        $Software->ModuleSupplier = 'Buckaroo';
        $Software->ModuleName = BuckarooConfig::PLUGIN_NAME;
        $Software->ModuleVersion = BuckarooConfig::VERSION;
        return $Software;
    }
    
}

?>
