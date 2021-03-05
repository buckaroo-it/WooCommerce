<?php

require_once 'library/include.php';
require_once(dirname(__FILE__) . '/library/api/paymentmethods/klarna/klarna.php');
require_once(dirname(__FILE__) . '/gateway-buckaroo-klarna.php');

class WC_Gateway_Buckaroo_KlarnaPay extends WC_Gateway_Buckaroo_Klarna {
    var $currency;

    function __construct() {
        $woocommerce = getWooCommerceObject();

        $this->id = 'buckaroo_klarnapay';
        $this->title = 'Klarna: Pay later';
        $this->icon 		= apply_filters('woocommerce_buckaroo_klarnapay_icon', plugins_url('library/buckaroo_images/24x24/klarna.svg', __FILE__));
        $this->has_fields 	= true;
        $this->method_title = 'Buckaroo Klarna Pay later';
        $this->description = "Betaal met Klarna Pay";
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $this->currency = get_woocommerce_currency();
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');

        $this->secretkey = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode = BuckarooConfig::getMode();
        $this->thumbprint = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture = BuckarooConfig::get('CULTURE');
        $this->usenotification = BuckarooConfig::get('BUCKAROO_USE_NOTIFICATION');
        $this->notificationdelay = BuckarooConfig::get('BUCKAROO_NOTIFICATION_DELAY');

        $country = null;
        if (! empty($woocommerce->customer)) {
            $country = get_user_meta($woocommerce->customer->get_id(), 'shipping_country', true);
        }
        $this->klarnaPaymentFlowId = 'pay';
        $this->klarnaSelector = 'buckaroo_' . $this->id; //$this->klarnaPaymentFlowId

        $this->country = $country;

        parent::__construct();

        $this->supports           = array(
            'products',
            'refunds'
        );
        $this->type = 'klarna';
        //$this->showpayproc = ($this->settings['showpayproc'] == 'TRUE') ? true : false;
        $this->vattype = (isset($this->settings['vattype']) ? $this->settings['vattype'] : null);
        $this->notify_url = home_url('/');

        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '<' ) ) {

        } else {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_wc_gateway_buckaroo_klarnapay', array( $this, 'response_handler' ) );
            $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_KlarnaPay', $this->notify_url);
        }
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_certificate'));

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_hide_local'));

        //Start Dynamic Rendering of Hidden Fields
        $options = get_option("woocommerce_" . $this->id . "_settings", null);
        $ccontent_arr = array();
        $keybase = 'certificatecontents';
        $keycount = 1;
        if (!empty($options["$keybase$keycount"])) {
            while (!empty($options["$keybase$keycount"])) {
                $ccontent_arr[] = "$keybase$keycount";
                $keycount++;
            }
        }
        $while_key = 1;
        $selectcertificate_options = array('none' => 'None selected');
        while ($while_key != $keycount) {
            $this->form_fields["certificatecontents$while_key"] = array(
                'title' => '',
                'type' => 'hidden',
                'description' => '',
                'default' => ''
            );
            $this->form_fields["certificateuploadtime$while_key"] = array(
                'title' => '',
                'type' => 'hidden',
                'description' => '',
                'default' => '');
            $this->form_fields["certificatename$while_key"] = array(
                'title' => '',
                'type' => 'hidden',
                'description' => '',
                'default' => '');
            $selectcertificate_options["$while_key"] = $options["certificatename$while_key"];

            $while_key++;
        }
        $final_ccontent = $keycount;
        $this->form_fields["certificatecontents$final_ccontent"] = array(
            'title' => '',
            'type' => 'hidden',
            'description' => '',
            'default' => '');
        $this->form_fields["certificateuploadtime$final_ccontent"] = array(
            'title' => '',
            'type' => 'hidden',
            'description' => '',
            'default' => '');
        $this->form_fields["certificatename$final_ccontent"] = array(
            'title' => '',
            'type' => 'hidden',
            'description' => '',
            'default' => '');

        $this->form_fields['selectcertificate'] = array(
            'title' => __('Select Certificate', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Select your certificate by name.', 'wc-buckaroo-bpe-gateway'),
            'options' => $selectcertificate_options,
            'default' => 'none'
        );
        $this->form_fields['choosecertificate'] = array(
            'title' => __('', 'wc-buckaroo-bpe-gateway'),
            'type' => 'file',
            'description' => __(''),
            'default' => '');

        $this->form_fields['usenotification'] = array(
            'title' => __('Use Notification Service', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('The notification service can be used to have the payment engine sent additional notifications.', 'wc-buckaroo-bpe-gateway'),
            'options' => array('TRUE' => 'Yes', 'FALSE' => 'No'),
            'default' => 'FALSE');

        $this->form_fields['notificationdelay'] = array(
            'title' => __('Notification delay', 'wc-buckaroo-bpe-gateway'),
            'type' => 'text',
            'description' => __('The time at which the notification should be sent. If this is not specified, the notification is sent immediately.', 'wc-buckaroo-bpe-gateway'),
            'default' => '0');
    }
}
