<?php

require_once 'library/include.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_MasterSettings extends WC_Gateway_Buckaroo
{
    public $datedue;
    public $sendemail;
    public $showpayproc;
    public function __construct()
    {
        $woocommerce        = getWooCommerceObject();
        $this->id           = 'buckaroo_mastersettings';
        $this->title        = 'Master Settings';
        $this->has_fields   = false;
        $this->method_title = __('Buckaroo Master Settings', 'wc-buckaroo-bpe-gateway');

        parent::__construct();

        $this->supports = array(
            'products',
            'refunds',
        );
        $this->sendemail   = !empty($this->settings['sendmail']) ? $this->settings['sendmail'] : false;
        $this->showpayproc = false; //Never Show at checkout
        $this->notify_url  = home_url('/');

        if (!(version_compare(WOOCOMMERCE_VERSION, '2.0.0', '<'))) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_buckaroo_transfer', array($this, 'response_handler'));
            if ($this->showpayproc) {
                add_action('woocommerce_thankyou_buckaroo_transfer', array($this, 'thankyou_description'));
            }
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Transfer', $this->notify_url);
        }
    }

    public function enqueue_script_exodus($settings)
    {
        if (is_admin()) {
            wp_enqueue_script('buckaroo_exodus', plugin_dir_url(__FILE__) . 'library/js/9yards/exodus.js', array('jquery'), '1.0.0', true);
        }
        return $settings;
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        $this->id = (!isset($this->id) ? '' : $this->id);
        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_certificate'));

        $upload_dir = wp_upload_dir();

        //Hide migrate button, if migration flag is set
        if (!get_option('woocommerce_buckaroo_exodus')) {
            add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_exodus'));
            $this->form_fields['exodus'] = array(
                'title'       => __('Migrate Settings', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'button',
                'description' => __('Click to migrate settings, from existing payment methods to master settings.', 'wc-buckaroo-bpe-gateway'),
                'default'     => '');
        }

        //Start Certificate fields
        $this->form_fields['merchantkey'] = array(
            'title'             => __('Merchant key', 'wc-buckaroo-bpe-gateway'),
            'type'              => 'text',
            'description'       => __('This is your Buckaroo Payment Plaza website key (My Buckaroo -> Websites -> Choose website through Filter -> Key).', 'wc-buckaroo-bpe-gateway'),
            'default'           => '',
            'custom_attributes' => array(
                'required' => 'required',
            ),
        );
        $this->form_fields['secretkey'] = array(
            'title'             => __('Secret key', 'wc-buckaroo-bpe-gateway'),
            'type'              => 'text',
            'description'       => __('The secret password to verify transactions (Configuration -> Security -> Secret key).', 'wc-buckaroo-bpe-gateway'),
            'default'           => '',
            'custom_attributes' => array(
                'required' => 'required',
            ),
        );
        $this->form_fields['thumbprint'] = array(
            'title'       => __('Fingerprint', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('Certificate thumbprint (Configuration -> Security -> Certificates -> See "Fingerprint" after a certificate has been generated).', 'wc-buckaroo-bpe-gateway'),
            'default'     => '');
        $this->form_fields['upload'] = array(
            'title'       => __('Upload certificate', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'button',
            'description' => __('Click to select and upload your certificate. Note: Please save after uploading.', 'wc-buckaroo-bpe-gateway'),
            'default'     => '');

        $taxes                       = $this->getTaxClasses();
        $this->form_fields['feetax'] = [
            'title'       => __('Select tax class for fee', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'options'     => $taxes,
            'description' => __('Fee tax class', 'wc-buckaroo-bpe-gateway'),
            'default'     => '',
        ];

        //Start Dynamic Rendering of Hidden Fields
        $master_options = get_option('woocommerce_buckaroo_mastersettings_settings', null);
        $ccontent_arr   = array();
        $keybase        = 'certificatecontents';
        $keycount       = 1;
        if (!empty($master_options["$keybase$keycount"])) {
            while (!empty($master_options["$keybase$keycount"])) {
                $ccontent_arr[] = "$keybase$keycount";
                $keycount++;
            }
        }
        $while_key                 = 1;
        $selectcertificate_options = array('none' => 'None selected');
        while ($while_key != $keycount) {
            $this->form_fields["certificatecontents$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '',
            );
            $this->form_fields["certificateuploadtime$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '');
            $this->form_fields["certificatename$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '');
            $selectcertificate_options["$while_key"] = $master_options["certificatename$while_key"];

            $while_key++;
        }
        $final_ccontent                                          = $keycount;
        $this->form_fields["certificatecontents$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields["certificateuploadtime$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields["certificatename$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');

        $this->form_fields['selectcertificate'] = array(
            'title'       => __('Select certificate', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Select your certificate by name.', 'wc-buckaroo-bpe-gateway'),
            'options'     => $selectcertificate_options,
            'default'     => 'none',
        );
        $this->form_fields['choosecertificate'] = array(
            'title'       => __('', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'file',
            'description' => __(''),
            'default'     => '');

        //End Dynamic Rendering of Hidden Fields

        $this->form_fields['usenotification'] = array(
            'title'       => __('Use notification service', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('The notification service can be used to have the payment engine sent additional notifications.', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => 'Yes', 'FALSE' => 'No'),
            'default'     => 'FALSE');

        $this->form_fields['notificationdelay'] = array(
            'title'       => __('Notification delay', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('The time at which the notification should be sent. If this is not specified, the notification is sent immediately.', 'wc-buckaroo-bpe-gateway'),
            'default'     => '0');

        $this->form_fields['culture'] = array(
            'title'       => __('Language', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Buckaroo payment engine culture', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('en-US' => 'English', 'nl-NL' => 'Dutch', 'fr-FR' => 'French', 'de-DE' => 'German'),
            'default'     => 'nl-NL');
        $this->form_fields['debugmode'] = array(
            'title'       => __('Debug mode', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Toggle debug mode on/off', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('on' => 'On', 'off' => 'Off'),
            'default'     => 'off');

        $this->form_fields['transactiondescription'] = array(
            'title'             => __('Transaction description', 'wc-buckaroo-bpe-gateway'),
            'type'              => 'textarea',
            'description'       => __('Transaction description', 'wc-buckaroo-bpe-gateway'),
            'default'           => '',
            'custom_attributes' => array(
                'required' => 'required',
            ),
        );

        $this->form_fields['usenewicons'] = array(
            'title'       => __('Use new icons', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('By turning on this setting in checkout new payment method icons will be in use.', 'wc-buckaroo-bpe-gateway'),
            'options'     => array(0 => 'No', 1 => 'Yes'),
            'default'     => 0);
    }

    protected function getTaxClasses()
    {
        $allTaxRates = [];
        $taxClasses  = WC_Tax::get_tax_classes(); // Retrieve all tax classes.
        if (!in_array('', $taxClasses)) {
            // Make sure "Standard rate" (empty class name) is present.
            array_unshift($taxClasses, '');
        }
        foreach ($taxClasses as $taxClass) {
            // For each tax class, get all rates.
            $taxes = WC_Tax::get_rates_for_tax_class($taxClass);
            foreach ($taxes as $tax) {
                $allTaxRates[$tax->{'tax_rate_class'}] = $tax->{'tax_rate_name'};
                if (empty($allTaxRates[$tax->{'tax_rate_class'}])) {
                    $allTaxRates[$tax->{'tax_rate_class'}] = 'Standard Rate';
                }
            }
        }
        return $allTaxRates;
    }
}
