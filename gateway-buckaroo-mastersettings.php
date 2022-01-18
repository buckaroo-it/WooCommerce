<?php



/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_MasterSettings extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id           = 'buckaroo_mastersettings';
        $this->title        = 'Master Settings';
        $this->has_fields   = false;
        $this->method_title = __(
            'Buckaroo Master Settings', 'wc-buckaroo-bpe-gateway'
        );
        parent::__construct();
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
            'title'             => __('Website key', 'wc-buckaroo-bpe-gateway'),
            'type'              => 'password',
            'description'       => __('This is your Buckaroo Payment Plaza website key (My Buckaroo -> Websites -> Choose website through Filter -> Key).', 'wc-buckaroo-bpe-gateway'),
            'default'           => '',
            'custom_attributes' => array(
                'required' => 'required',
            ),
        );
        $this->form_fields['secretkey'] = array(
            'title'             => __('Secret key', 'wc-buckaroo-bpe-gateway'),
            'type'              => 'password',
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

        $this->initCerificateFields();
        
        $this->form_fields['test_credentials'] = array(
            'title'       => __('Test credentials', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'button',
            'description' => __('Click here to verify credentials', 'wc-buckaroo-bpe-gateway'),
            'custom_attributes' => [
                'gateway_id' => $this->plugin_id . $this->id,
                'title' => __('Test', 'wc-buckaroo-bpe-gateway'),
                'save_required' => __('Save credentials before testing', 'wc-buckaroo-bpe-gateway'),
            ],
            'default'     => '');

        $taxes                       = $this->getTaxClasses();
        $this->form_fields['feetax'] = [
            'title'       => __('Select tax class for fee', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'options'     => $taxes,
            'description' => __('Fee tax class', 'wc-buckaroo-bpe-gateway'),
            'default'     => '',
        ];
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
            'default'           => ''
        );

        $this->form_fields['usenewicons'] = array(
            'title'       => __('Use new icons', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('By turning on this setting in checkout new payment method icons will be in use', 'wc-buckaroo-bpe-gateway'),
            'options'     => array(0 => 'No', 1 => 'Yes'),
            'default'     => 0
        );

        $this->form_fields['useidin'] = array(
            'title'       => __('iDIN mode', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('By turning on this setting age verification with iDIN will be in use', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('0' => 'Off', 'live' => 'Live', 'test' => 'Test'),
            'default'     => '0'
        );

        $idinCategories = [];
        if ($categories = get_terms( ['taxonomy' => 'product_cat', 'hide_empty' => false])) {
            foreach ($categories as $category) {
                $idinCategories[$category->term_id] = $category->name;
            }
        }
        $this->form_fields['idincategories'] = array(
            'title'       => __('iDIN specific product categories', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'multiselect',
            'options'     => $idinCategories,
            'description' => __("Select for what product categories iDIN verification should be applied. Don't select anything if want to apply iDIN to any product", 'wc-buckaroo-bpe-gateway'),
            'default'     => [],
        );
        
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
