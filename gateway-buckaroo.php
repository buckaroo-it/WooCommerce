<?php
require_once(dirname(__FILE__) . '/library/api/idin.php');

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo extends WC_Payment_Gateway
{
    const PAYMENT_CLASS = null;
    const BUCKAROO_TEMPLATE_LOCATION = '/templates/gateways/';

    public $notify_url;
    public $transactiondescription;
    public $extrachargeamount;
    public $extrachargetype;
    public $extrachargetaxtype;
    public $sellerprotection;
    public $minvalue;
    public $maxvalue;

    public $showpayproc = false;

    public function __construct()
    {
        if ((!is_admin() && !checkCurrencySupported($this->id)) || (defined('DOING_AJAX') && !checkCurrencySupported($this->id))) {
            unset($this->id);
            unset($this->title);
        }
        // Load the form fields
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();
        
        $this->setProperties();
        
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_cart_calculate_fees', [$this, 'calculate_order_fees']);
            add_action('wp_enqueue_scripts', [$this, 'refresh_frontend']);

            add_action('wp_enqueue_scripts', function () {
                wp_enqueue_script('initiate_jquery_if_not_loaded', plugin_dir_url(__FILE__) . 'library/js/loadjquery.js', ['jquery'], '1.0.0', true);

                wp_enqueue_script('creditcard_encryption_sdk', plugin_dir_url(__FILE__) . 'library/js/9yards/creditcard-encryption-sdk.js', ['jquery'], '1.0.0', true);
                wp_enqueue_script('creditcard_call_encryption', plugin_dir_url(__FILE__) . 'library/js/9yards/creditcard-call-encryption.js', ['jquery'], '1.0.0', true);

            });
            add_filter('woocommerce_available_payment_gateways', array($this, 'payment_gateway_disable'));
            add_filter('woocommerce_order_button_html', array($this, 'replace_order_button_html'));
        }
        add_action('wp_enqueue_scripts', [$this, 'loadCss']);

        // [JM] Compatibility with WC3.6+
        add_action('woocommerce_checkout_process', array($this, 'action_woocommerce_checkout_process'));

        $this->addGatewayHooks(static::class);
    }
    /**
     * Init class fields from settings
     *
     * @return void
     */
    protected function setProperties()
    {
        $GLOBALS['plugin_id']         = $this->plugin_id . $this->id . '_settings';
        $this->title                  = $this->get_option('title', $this->title ?? '');
        $this->currency               = get_woocommerce_currency();
        $this->description            = $this->get_option(
            'description',  
            sprintf(__('Pay with %s', 'wc-buckaroo-bpe-gateway'), $this->title)
        );
        $this->mode                   = $this->get_option('mode');
        $this->minvalue               = $this->get_option('minvalue', 0);
        $this->maxvalue               = $this->get_option('maxvalue', 0);
        $this->sellerprotection       = $this->get_option('sellerprotection', 'TRUE');
        $this->extrachargetype        = $this->get_option('extrachargetype', 'static');
        $this->extrachargeamount      = $this->get_option('extrachargeamount', 0);
        $this->extrachargetaxtype     = $this->get_option('extrachargetaxtype', 'included');
        $this->transactiondescription = $this->get_option('transactiondescription');
    }
    /**
     * Set gateway icon
     *
     * @param string $oldPath  Old image path
     * @param string $newPath  New image path
     *
     * @return void
     */
    protected function setIcon($oldPath, $newPath)
    {
        $this->icon = apply_filters(
            'woocommerce_'.$this->id.'_icon',
            BuckarooConfig::getIconPath($oldPath, $newPath)
        );
    }
    /**
     * Set country field
     *
     * @return void
     */
    protected function setCountry()
    {
        $woocommerce = getWooCommerceObject();

        $country = null;
        if (!empty($woocommerce->customer)) {
            $country = get_user_meta($woocommerce->customer->get_id(), 'shipping_country', true);
        }
        $this->country = $country;
    }
    /**
     * Add the gateway hooks
     *
     * @param string $class Gateway Class name
     *
     * @return void
     */
    protected function addGatewayHooks($class)
    {
        $this->showpayproc = isset($this->settings['showpayproc']) && $this->settings['showpayproc'] == 'TRUE';
        
        $this->notify_url = home_url('/');
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {

            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                array($this, 'process_admin_options')
            );

            add_action(
                'woocommerce_api_'.strtolower(wc_clean($class)),
                array($this, 'response_handler')
            );

            if ($this->showpayproc) {
                add_action(
                    'woocommerce_thankyou_' . $this->id,
                    array($this, 'thankyou_description')
                );
            }

            $this->notify_url = add_query_arg('wc-api', $class, $this->notify_url);
        }
    }
    /**
     * Add refund support
     *
     * @return void
     */
    protected function addRefundSupport()
    {
        $this->supports = [
            'products',
            'refunds',
        ];
    }
    /**
     * Migrate old named setting to new name
     *
     * @param string $oldKey Old settings key
     *
     * @return void
     */
    protected function migrateOldSettings($oldKey)
    {
        if (
            !get_option('woocommerce_' . $this->id . '_settings') &&
            ($oldSettings = get_option($oldKey))
        ) {
            add_option('woocommerce_' . $this->id . '_settings', $oldSettings);
            delete_option($oldKey);//clean the table
        }
    }
    public function thankyou_description()
    {
        //not implemented 
    }
    public function payment_gateway_disable($available_gateways)
    {
        global $woocommerce;

        if (!BuckarooIdin::checkCurrentUserIsVerified()) {
            return [];
        }

        if ($available_gateways) {
            if (!empty(WC()->cart)) {
                $totalCartAmount = WC()->cart->get_total(null);
                foreach ($available_gateways as $key => $gateway) {
                    if (
                        (substr($key, 0, 8) === 'buckaroo')
                        && (
                            !empty($gateway->minvalue)
                            ||
                            !empty($gateway->maxvalue)
                        )
                    ) {
                        if (!empty($gateway->maxvalue) && $totalCartAmount > $gateway->maxvalue) {
                            unset($available_gateways[$key]);
                        }

                        if (!empty($gateway->minvalue) && $totalCartAmount < $gateway->minvalue) {
                            unset($available_gateways[$key]);
                        }
                    }
                }
            }
        }

        if (isset($available_gateways['buckaroo_applepay'])) {
            unset($available_gateways['buckaroo_applepay']);
        }
        if (isset($available_gateways['buckaroo_payperemail']) && $available_gateways['buckaroo_payperemail']->frontendVisible === "no") {
            unset($available_gateways['buckaroo_payperemail']);
        }
        return $available_gateways;
    }

    public function replace_order_button_html($button)
    {
        if (!BuckarooIdin::checkCurrentUserIsVerified()) {
            return '';
        }
        return $button;
    }

    public function action_woocommerce_checkout_process()
    {
        if (version_compare(WC()->version, '3.6', '>=')) {
            resetOrder();
        }
    }

    public function init_settings()
    {
        parent::init_settings();

        if (isset($this->settings['usemaster']) && $this->settings['usemaster'] == 'yes') {
            // merge with master settings
            $options            = get_option('woocommerce_buckaroo_mastersettings_settings', null);
            $options['enabled'] = $this->settings['enabled'];
            if (is_array($options)) {
                $this->settings = array_replace($this->settings, $options);
            }
        }
    }

    public function loadCss()
    {
        //Load Custom CSS
        wp_enqueue_style('buckaroo-custom-styles', substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT'])) . '/library/css/buckaroo-custom.css');
    }

    /**
     * Populates $this->plugin_url with a url, while removing the trailing slash.
     *
     * @access public
     * @return void
     *
     */
    public function plugin_url()
    {
        return $this->plugin_url = untrailingslashit(plugins_url('/', __FILE__));
    }

    /**
     * Add checkout javascript to the checkout page
     *
     * @access public
     * @return void
     *
     */
    public function refresh_frontend()
    {

        if (!is_checkout()) {
            return;
        }

        wp_enqueue_script(
            'wc-pf-checkout',
            $this->plugin_url() . '/assets/js/checkout.js',
            ['jquery'],
            BuckarooConfig::VERSION,
            true
        );
    }

    /**
     * Calculates fees on items in shopping cart. (e.g. Taxes)
     *
     * @access public
     * @param string $cart
     * @return void
     *
     */
    public function calculate_order_fees($cart)
    {
        if (!defined('WOOCOMMERCE_CHECKOUT')) {
            return;
        }
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $current_gateway    = WC()->session->chosen_payment_method;
        $subtotal           = $cart->cart_contents_total;

        if (!empty($available_gateways)) {
            if (isset($current_gateway) && isset($available_gateways[$current_gateway])) {
                $current_gateway = $available_gateways[$current_gateway];
            } elseif (isset($available_gateways[get_option('woocommerce_default_gateway')])) {
                $current_gateway = $available_gateways[get_option('woocommerce_default_gateway')];
            } else {
                $current_gateway = current($available_gateways);
            }
        }

        if (!empty($current_gateway->extrachargeamount) && !empty($current_gateway->extrachargeamount) && $current_gateway->extrachargeamount != 0) {
            $extra_charge_amount   = $current_gateway->extrachargeamount;
            $extra_charge_type     = $current_gateway->extrachargetype;
            $extra_charge_tax_type = $current_gateway->extrachargetaxtype;
            if ($extra_charge_type == 'percentage') {
                $extra_charge_amount = number_format($subtotal * $extra_charge_amount / 100, 2);
            }
            if ($extra_charge_tax_type == 'excluded') {
                $taxable = true;
            } else {
                $taxable = false;
            }

            $extra_charge_amount = apply_filters(
                'woocommerce_wc_pf_' . $current_gateway->id . '_amount',
                $extra_charge_amount,
                $subtotal,
                $current_gateway
            );
            $do_apply = $extra_charge_amount != 0;
            $do_apply = apply_filters(
                'woocommerce_wc_pf_apply',
                $do_apply,
                $extra_charge_amount,
                $subtotal,
                $current_gateway
            );
            $do_apply = apply_filters(
                'woocommerce_wc_pf_apply_for_' . $current_gateway->id,
                $do_apply,
                $extra_charge_amount,
                $subtotal,
                $current_gateway
            );
            $taxFeeClass = $this->settings['feetax'];
            if ($do_apply) {
                $already_exists = false;
                $fees           = $cart->get_fees();
                for ($i = 0; $i < count($fees); $i++) {
                    if ($fees[$i]->id == 'payment-method-fee') {
                        $already_exists = true;
                        $fee_id         = $i;
                    }
                }

                if (!$already_exists) {
                    $cart->add_fee(__("Payment fee", 'wc-buckaroo-bpe-gateway'), $extra_charge_amount, true, $taxFeeClass);
                } else {
                    $fees[$fee_id]->amount = $extra_charge_amount;
                }
            }

            $this->current_extra_charge_amount = $extra_charge_amount;
            $this->current_extra_charge_type   = $taxable;
            $this->current_extra_charge_tax    = $taxFeeClass;
        }
    }

    /**
     * Adds Scripts to the loading process of the page.
     *
     * @access public
     * @param array $settings
     * @return array $settings
     */
    public function enqueue_script_certificate($settings)
    {
        wp_enqueue_script('initiate_jquery_if_not_loaded', plugin_dir_url(__FILE__) . 'library/js/loadjquery.js', ['jquery'], '1.0.0', true);
        if (is_admin()) {
            wp_enqueue_script('buckaroo_certificate_management_js', plugin_dir_url(__FILE__) . 'library/js/9yards/upload_certificate.js', ['jquery'], '1.0.0', true);
        }
        return $settings;
    }

    /**
     * Adds Script to show or hide local settings, when master settings are not enabled.
     *
     * @access public
     * @param array $settings
     * @return array $settings
     */
    public function enqueue_script_hide_local($settings)
    {
        if (is_admin()) {
            wp_enqueue_script('buckaroo_display_local_settings', plugin_dir_url(__FILE__) . 'library/js/9yards/display_local.js', ['jquery'], '1.0.0', true);
        }
        return $settings;
    }

    /**
     * Initialize Gateway Settings Form Fields
     *
     * @access public
     */
    public function init_form_fields()
    {
        $upload_dir     = wp_upload_dir();
        $charset        = strtolower(ini_get('default_charset'));
        $addDescription = '';
        if ($charset != 'utf-8') {
            $addDescription = '<fieldset style="border: 1px solid #ffac0e; padding: 10px;"><legend><b style="color: #ffac0e">'.__('Warning', 'wc-buckaroo-bpe-gateway').'!</b></legend>'.__('default_charset is not set.<br>This might cause a problems on receiving push message.<br>Please set default_charset="UTF-8" in your php.ini and add AddDefaultCharset UTF-8 to .htaccess file.', 'wc-buckaroo-bpe-gateway').'</fieldset>';
        }
        //Add Warning, if currency set in Buckaroo is unsupported
        if (isset($_GET['section']) && $this->id == $_GET['section'] && !checkCurrencySupported($this->id) && is_admin()): ?>
<div class="error notice">
    <p><?php echo __('This payment method is not supported for the selected currency ', 'wc-buckaroo-bpe-gateway') . '(' . get_woocommerce_currency() . ')'; ?>
    </p>
</div>
<?php endif;

        $this->title       = (!isset($this->title) ? '' : $this->title);
        $this->id          = (!isset($this->id) ? '' : $this->id);
        $this->form_fields = [
            'enabled'                => [
                'title'       => __('Enable/Disable', 'wc-buckaroo-bpe-gateway'),
                'label'       => sprintf(__('Enable %s Payment Method', 'wc-buckaroo-bpe-gateway'), (isset($this->method_title) ? $this->method_title : '')),
                'type'        => 'checkbox',
                'description' => $addDescription,
                'default'     => 'no',
            ],
            'usemaster'              => [
                'title'       => __('Use master settings', 'wc-buckaroo-bpe-gateway'),
                'label'       => __(
                    "Tick to use master settings for this payment method (see 'Buckaroo Master Settings' page to setup your default certificate).",
                    'wc-buckaroo-bpe-gateway'
                ),
                'type'        => 'checkbox',
                'description' => $addDescription,
                'default'     => 'yes',
            ],
            'title'                  => [
                'title'       => __('Title'),
                'type'        => 'text',
                'description' => __(
                    'This controls the title which the user sees during checkout.',
                    'wc-buckaroo-bpe-gateway'
                ),
                'default'     => __($this->title, 'wc-buckaroo-bpe-gateway'),
                'css'         => "width: 300px;",
            ],
            'description'            => [
                'title'       => __('Description', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'textarea',
                'description' => __(
                    'This controls the description which the user sees during checkout.',
                    'wc-buckaroo-bpe-gateway'
                ),
                'default'     => $this->description,
            ],

            'upload'                 => [
                'title'       => __('Upload certificate', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'button',
                'description' => __('Click to select and upload your certificate. Note: Please save after uploading.', 'wc-buckaroo-bpe-gateway'),
                'default'     => '',
            ],
            'merchantkey'            => [
                'title'       => __('Merchant key', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'text',
                'description' => __('This is your Buckaroo Payment Plaza website key (My Buckaroo -> Websites -> Choose website through Filter -> Key).', 'wc-buckaroo-bpe-gateway'),
                'default'     => '',
            ],
            'secretkey'              => [
                'title'       => __('Secret key', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'text',
                'description' => __('The secret password to verify transactions (Configuration -> Security -> Secret key).', 'wc-buckaroo-bpe-gateway'),
                'default'     => '',
            ],
            'thumbprint'             => [
                'title'       => __('Fingerprint', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'text',
                'description' => __('Certificate thumbprint (Configuration -> Security -> Certificates -> See "Fingerprint" after a certificate has been generated).', 'wc-buckaroo-bpe-gateway'),
                'default'     => '',
            ],
            'mode'                   => [
                'title'       => __('Transaction mode', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'select',
                'description' => __('Transaction mode used for processing orders', 'wc-buckaroo-bpe-gateway'),
                'options'     => ['live' => 'Live', 'test' => 'Test'],
                'default'     => 'test',
            ],
            'transactiondescription' => [
                'title'       => __('Transaction description', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'textarea',
                'description' => __('Transaction description', 'wc-buckaroo-bpe-gateway'),
                'default'     => '',
            ],
            'culture'                => [
                'title'       => __('Language', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'select',
                'description' => __('Buckaroo payment engine culture', 'wc-buckaroo-bpe-gateway'),
                'options'     => ['en-US' => 'English', 'nl-NL' => 'Dutch', 'fr-FR' => 'French', 'de-DE' => 'German'],
                'default'     => 'nl-NL',
            ],
            'extrachargeamount'      => [
                'title'             => __('Extra charge amount', 'wc-buckaroo-bpe-gateway'),
                'type'              => 'number',
                'custom_attributes' => ["step" => "0.01"],
                'description'       => __('Specify static or percentage amount.', 'wc-buckaroo-bpe-gateway') . ' ' . __('Decimals must be seperated by a dot (.)', 'wc-buckaroo-bpe-gateway'),
                'default'           => '0',
            ],
            'extrachargetype'        => [
                'title'       => __('Extra charge type', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'select',
                'options'     => ['static' => 'Static', 'percentage' => 'Percentage'],
                'description' => __('Percentage or static', 'wc-buckaroo-bpe-gateway'),
                'default'     => 'static',
            ],
            'minvalue'      => [
                'title'             => __('Minimum order amount allowed', 'wc-buckaroo-bpe-gateway'),
                'type'              => 'number',
                'custom_attributes' => ['step' => '0.01'],
                'description'       => __('Specify minimum order amount allowed to show the current method. Zero or empty value means no rule will be applied.', 'wc-buckaroo-bpe-gateway'),
                'default'           => '0',
            ],
            'maxvalue'      => [
                'title'             => __('Maximum order amount allowed', 'wc-buckaroo-bpe-gateway'),
                'type'              => 'number',
                'custom_attributes' => ['step' => '0.01'],
                'description'       => __('Specify maximum order amount allowed to show the current method. Zero or empty value means no rule will be applied.', 'wc-buckaroo-bpe-gateway'),
                'default'           => '0',
            ],
        ];
    }

    /**
     * Check response data
     *
     * @access public
     */
    public function response_handler()
    {
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $result               = fn_buckaroo_process_response($this);
        if (!is_null($result)) {
            wp_safe_redirect($result['redirect']);
        } else {
            wp_safe_redirect($this->get_failed_url());
        }
        exit;
    }

    /**
     * Payment form on checkout page
     * 
     * @return void
     */
    public function payment_fields()
    {
        $this->renderTemplate();
    }

    public function get_failed_url()
    {
        $thanks_page_id = wc_get_page_id('checkout');
        if ($thanks_page_id):
            $return_url = get_permalink($thanks_page_id);else:
            $return_url = home_url();
        endif;
        if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
            $return_url = str_replace('http:', 'https:', $return_url);
        }

        return apply_filters('woocommerce_get_return_url', $return_url);
    }

    public function getInitials($str)
    {
        $ret = '';
        foreach (explode(' ', $str) as $word) {
            $ret .= strtoupper($word[0]) . '.';
        }
        return $ret;
    }

    /**
     * Cleanup a phonenumber handed to it as $phone.
     *
     * @access public
     * @param string $phone phonenumber
     * @return array
     */
    public static function cleanup_phone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Cleaning up dutch mobile numbers being entered incorrectly
        if (substr($phone, 0, 3) == '316' || substr($phone, 0, 5) == '00316' || substr($phone, 0, 6) == '003106' || substr($phone, 0, 2) == '06') {
            if (substr($phone, 0, 6) == '003106') {
                $phone = substr_replace($phone, '00316', 0, 6);
            }
            $response = ['type' => 'mobile', 'phone' => $phone];
        } else {
            $response = ['type' => 'landline', 'phone' => $phone];
        }

        return $response;
    }

    /**
     *
     *
     * @access public
     * @param string $key
     * @return boolean
     */
    public function validate_number_field($key)
    {
        if (in_array($key, ['extrachargeamount', 'minvalue', 'maxvalue'])) {
            //[9Yrds][2017-05-03][JW] WooCommerce 2.2 & 2.3 compatability
            $field = $this->plugin_id . $this->id . '_' . $key;
            // $field = $this->get_field_key($key);

            if (isset($_POST[$field])) {
                $text = wp_kses_post(trim(stripslashes($_POST[$field])));
                if (!is_float($text) && !is_numeric($text)) {
                    $this->errors[] = __('Please provide valid payment fee');
                    return false;
                }
            }
        }
        return parent::validate_text_field($key, $text);
    }

    /**
     * Check that a date is valid.
     *
     * @param String $date A date expressed as a string
     * @param String $format The format of the date
     * @return Object Datetime
     * @return Boolean Format correct returns True, else returns false
     */
    public function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    public function parseDate($date)
    {
        if ($this->validateDate($date, 'd-m-Y')) return $date;

        if (preg_match('/^\d{6}$/', $date)) {
            return DateTime::createFromFormat('dmy', $date)->format('d-m-Y');
        }
        if (preg_match('/^\d{8}$/', $date)) {
            return DateTime::createFromFormat('dmY', $date)->format('d-m-Y');
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return DateTime::createFromFormat('d/m/Y', $date)->format('d-m-Y');
        }
        if (preg_match('/^\d{1}\/\d{2}\/\d{4}$/', $date)) {
            return DateTime::createFromFormat('j/m/Y', $date)->format('d-m-Y');
        }
        if (preg_match('/^\d{1}\/\d{1}\/\d{4}$/', $date)) {
            return DateTime::createFromFormat('j/n/Y', $date)->format('d-m-Y');
        }
        if (preg_match('/^\d{2}\/\d{1}\/\d{4}$/', $date)) {
            return DateTime::createFromFormat('j/n/Y', $date)->format('d-m-Y');
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $date)) {
            return DateTime::createFromFormat('d/m/y', $date)->format('d-m-Y');
        }
        if (preg_match('/^\d{1}\/\d{2}\/\d{2}$/', $date)) {
            return DateTime::createFromFormat('j/m/y', $date)->format('d-m-Y');
        }
        if (preg_match('/^\d{1}\/\d{1}\/\d{2}$/', $date)) {
            return DateTime::createFromFormat('j/n/y', $date)->format('d-m-Y');
        }
        if (preg_match('/^\d{2}\/\d{1}\/\d{2}$/', $date)) {
            return DateTime::createFromFormat('j/n/y', $date)->format('d-m-Y');
        }
        return $date;
    }
    /**
     * Get the template for the payment gateway if exists 
     * 
     * @param string $name Template name / payment id.
     * 
     * @return void
     */
    protected function getPaymentTemplate($name) 
    {
        $location = dirname(BK_PLUGIN_FILE).self::BUCKAROO_TEMPLATE_LOCATION;
        $file = $location.$name.".php";

        if (file_exists($file)) {
            include $file;
        }
    }
    /**
     * Render the gateway template
     *
     * @return void
     */
    protected function renderTemplate($id = null)
    {
        if (is_null($id)) {
            $id = $this->id;
        }
        
        $name = str_replace("buckaroo_", "", $id);

        do_action("buckaroo_before_render_gateway_template_".$name, $this);

        $this->getPaymentTemplate('global');
        $this->getPaymentTemplate($name);

        do_action("buckaroo_after_render_gateway_template_".$name, $this);
    }
    /**
     * Get checkout field values
     *
     * @param string $key Input name
     *
     * @return mixt
     */
    protected function geCheckoutField($key)
    {
        $value = '';
        $post_data   = array();
        if (!empty($_POST["post_data"])) {
            parse_str($_POST["post_data"], $post_data);
        }

        if (isset($post_data[$key])) {
            $value = $post_data[$key];
        }
        return sanitize_text_field($value);
    }
    /**
     * Set order capture
     *
     * @param int $order_id Order id
     * @param string $paymentName Payment name
     * @param string|null $paymentType Payment type
     *
     * @return void
     */
    protected function setOrderCapture($order_id, $paymentName, $paymentType = null)
    {
        
        update_post_meta($order_id, '_wc_order_selected_payment_method', $paymentName);
        $this->setOrderIssuer($order_id, $paymentType);
    }
    /**
     * Set order issuer
     *
     * @param int $order_id Order id
     * @param string|null $paymentType Payment type
     *
     * @return void
     */
    protected function setOrderIssuer($order_id, $paymentType = null)
    {
        if (is_null($paymentType)) {
            $paymentType = $this->type;
        }
        update_post_meta($order_id, '_wc_order_payment_issuer', $paymentType);
    }
    /**
     * Create a request for debit
     *
     * @param WC_Order $order Woocommerce order
     *
     * @return BuckarooPaymentMethod
     */
    protected function createDebitRequest($order)
    {

        $payment = $this->createPaymentRequest($order);
        if (method_exists($order, 'get_order_total')) {
            $payment->amountDedit = $order->get_order_total();
        } else {
            $payment->amountDedit = $order->get_total();
        }
        return $payment;
    }
    /**
     * Create the payment method
     *
     * @param WC_Order $order Woocommerce order
     *
     * @return BuckarooPaymentMethod
     */
    protected function createPaymentRequest($order)
    {
        
        $paymentClass = static::PAYMENT_CLASS;
        $payment = new $paymentClass();
        $payment->currency = get_woocommerce_currency();
        $payment->amountDedit = 0;
        $payment->amountCredit = 0;
        $payment->invoiceId = (string)getUniqInvoiceId($order->get_order_number());
        $payment->orderId = (string)$order->get_id();
        $payment->description = $this->transactiondescription;
        $payment->returnUrl = $this->notify_url;
        $payment->mode = $this->mode;
        $payment->channel = BuckarooConfig::CHANNEL;
        return $payment;
    }
}
