<?php
require_once(dirname(__FILE__) . '/library/api/idin.php');
require_once(dirname(__FILE__) . '/library/class-wc-session-handler-buckaroo.php');
/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo extends WC_Payment_Gateway
{
    const PAYMENT_CLASS = null;
    const BUCKAROO_TEMPLATE_LOCATION = '/templates/gateways/';

    public $notify_url;
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

        if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
            add_filter('woocommerce_session_handler', array($this, 'woocommerce_session_handler'));
        }

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_cart_calculate_fees', [$this, 'calculate_order_fees']);
            add_filter('woocommerce_available_payment_gateways', array($this, 'payment_gateway_disable'));
            add_filter('woocommerce_order_button_html', array($this, 'replace_order_button_html'));
        }

        // [JM] Compatibility with WC3.6+
        add_action('woocommerce_checkout_process', array($this, 'action_woocommerce_checkout_process'));

        $this->addGatewayHooks(static::class);
    }

    public function woocommerce_session_handler()
    {
        return 'WC_Session_Handler_Buckaroo';
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
            unset(
                $options['enabled'],
                $options['title'],
                $options['mode'],
                $options['description'],
            );
            if (is_array($options)) {
                $this->settings = array_replace($this->settings, $options);
            }
        }
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
     * Initialize Gateway Settings Form Fields
     *
     * @access public
     */
    public function init_form_fields()
    {
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
                    "Tick to use master settings for this payment method.",
                    'wc-buckaroo-bpe-gateway'
                ),
                'type'        => 'checkbox',
                'description' => sprintf(
                    __(
                        'See <a href="%s">Buckaroo Settings</a> tab to setup your default certificate and keys',
                    ),
                    esc_url(
                        admin_url('admin.php?page=wc-settings&tab=buckaroo_settings')
                    )
                ),   
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
            'merchantkey'            => [
                'title'       => __('Website key', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'password',
                'description' => __('This is your Buckaroo Payment Plaza website key (My Buckaroo -> Websites -> Choose website through Filter -> Key).', 'wc-buckaroo-bpe-gateway'),
                'default'     => '',
            ],
            'secretkey'              => [
                'title'       => __('Secret key', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'password',
                'description' => __('The secret password to verify transactions (Configuration -> Security -> Secret key).', 'wc-buckaroo-bpe-gateway'),
                'default'     => '',
            ],
            'thumbprint'             => [
                'title'       => __('Fingerprint', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'text',
                'description' => __('Certificate thumbprint (Configuration -> Security -> Certificates -> See "Fingerprint" after a certificate has been generated).', 'wc-buckaroo-bpe-gateway'),
                'default'     => '',
            ],
            'upload'                 => [
                'title'       => __('Upload certificate', 'wc-buckaroo-bpe-gateway'),
                'type'        => 'button',
                'description' => __('Click to select and upload your certificate. Note: Please save after uploading.', 'wc-buckaroo-bpe-gateway'),
                'default'     => '',
            ],
        ];


        $this->initCerificateFields();

        $this->form_fields['test_credentials'] = array(
            'title'       => __('Test credentials', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'button',
            'description' => __('Click here to verify website key & secret key.', 'wc-buckaroo-bpe-gateway'),
            'custom_attributes' => [
                'title' => __('Test', 'wc-buckaroo-bpe-gateway'),
            ],
            'default'     => ''
        );

        
        $this->form_fields = array_merge($this->form_fields, [

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
        ]);
    }
    /**
     * Add certificate fields to the gateway settings page
     *
     * @return void
     */
    public function initCerificateFields()
    {
        //Start Dynamic Rendering of Hidden Fields
        $options      = get_option("woocommerce_" . $this->id . "_settings", null);
        $ccontent_arr = array();
        $keybase      = 'certificatecontents';
        $keycount     = 1;
        if (!empty($options["$keybase$keycount"])) {
            while (!empty($options["$keybase$keycount"])) {
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
            $selectcertificate_options["$while_key"] = $options["certificatename$while_key"];

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
            'title'       => __('Select Certificate', 'wc-buckaroo-bpe-gateway'),
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
    public function validate_number_field($key, $text)
    {
        if (in_array($key, ['extrachargeamount', 'minvalue', 'maxvalue'])) {
            //[9Yrds][2017-05-03][JW] WooCommerce 2.2 & 2.3 compatability
            $field = $this->plugin_id . $this->id . '_' . $key;

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
     * Can the order be refunded
     * @access public
     * @param object $order WC_Order
     * @return object & string
     */
    public function can_refund_order($order)
    {
        return $order && $order->get_transaction_id();
    }
    /**
     * Validate fields
     * @return void;
     */
    public function validate_fields()
    {
        if (version_compare(WC()->version, '3.6', '<')) {
            resetOrder();
        }
        return;
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
     * Process default refund
     *
     * @param int      $order_id Order id
     * @param float    $amount Refund amount
     * @param string   $reason Refund reason
     * @param boolean  $setType Set request type from meta
     * @param callable $callback Set additional params to the $request object
     *
     * @return WP_Error|String|Boolean
     */
    protected function processDefaultRefund($order_id, $amount, $reason, $setType = false, $callback = null)
    {
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');

        $request = $this->createCreditRequest($order, $amount, $reason);

        if ($setType) {
            $request->setType(
                get_post_meta(
                    (int)str_replace('#', '', $order->get_order_number()),
                    '_payment_method_transaction',
                    true
                )
            );
        }

        if (is_callable($callback)) {
            $callback($request);
        }

        try {
            $response = $request->Refund();
        } catch (exception $e) {
            Buckaroo_Logger::log(__METHOD__, $e->getMessage());
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response ?? null, $order, $amount, $this->currency);
    }
    /**
     * Create a request for credit
     *
     * @param WC_Order $order Woocommerce order
     *
     * @return BuckarooPaymentMethod
     */
    protected function createCreditRequest($order, $amount, $reason)
    {

        $payment = $this->createPaymentRequest($order);
        $payment->amountCredit = $amount;
        $payment->description = $reason;
        $payment->invoiceId = $order->get_order_number();
        $payment->OriginalTransactionKey = $order->get_transaction_id();
        return $payment;
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
        $payment->description = $this->get_option('transactiondescription', 'Order #' . (string)$order->get_id());
        $payment->returnUrl = $this->notify_url;
        $payment->mode = $this->mode;
        $payment->channel = BuckarooConfig::CHANNEL;
        return $payment;
    }

    protected function handleThirdPartyShippings($afterpay, $order, $country)
    {
        if (!empty($_POST['shipping_method'][0]) && ($_POST['shipping_method'][0] == 'dhlpwc-parcelshop')) {
            $dhlConnectorData                    = $order->get_meta('_dhlpwc_order_connectors_data');
            $dhlCountry                          = !empty($country) ? $country : $_POST['billing_country'];
            $requestPart                         = $dhlCountry . '/' . $dhlConnectorData['id'];
            $dhlParcelShopAddressData            = $this->getDHLParcelShopLocation($requestPart);
            $afterpay->AddressesDiffer           = 'TRUE';
            $afterpay->ShippingStreet            = $dhlParcelShopAddressData->street;
            $afterpay->ShippingHouseNumber       = $dhlParcelShopAddressData->number;
            $afterpay->ShippingPostalCode        = $dhlParcelShopAddressData->postalCode;
            $afterpay->ShippingHouseNumberSuffix = '';
            $afterpay->ShippingCity              = $dhlParcelShopAddressData->city;
            $afterpay->ShippingCountryCode       = $dhlParcelShopAddressData->countryCode;
        }

        if (!empty($_POST['post-deliver-or-pickup']) && $_POST['post-deliver-or-pickup'] == 'post-pickup') {
            $postNL                              = $order->get_meta('_postnl_delivery_options');
            $afterpay->AddressesDiffer           = 'TRUE';
            $afterpay->ShippingStreet            = $postNL['street'];
            $afterpay->ShippingHouseNumber       = $postNL['number'];
            $afterpay->ShippingPostalCode        = $postNL['postal_code'];
            $afterpay->ShippingHouseNumberSuffix = trim(str_replace('-', ' ', $postNL['number_suffix']));
            $afterpay->ShippingCity              = $postNL['city'];
            $afterpay->ShippingCountryCode       = $postNL['cc'];
        }

        if (!empty($_POST['sendcloudshipping_service_point_selected'])) {
            $afterpay->AddressesDiffer = 'TRUE';
            $sendcloudPointAddress     = $order->get_meta('sendcloudshipping_service_point_meta');
            $addressData               = $this->parseSendCloudPointAddress($sendcloudPointAddress['extra']);

            $afterpay->ShippingStreet            = $addressData['street']['name'];
            $afterpay->ShippingHouseNumber       = $addressData['street']['house_number'];
            $afterpay->ShippingPostalCode        = $addressData['postal_code'];
            $afterpay->ShippingHouseNumberSuffix = $addressData['street']['number_addition'];
            $afterpay->ShippingCity              = $addressData['city'];
            $afterpay->ShippingCountryCode       = $afterpay->BillingCountry;
        }

        if (isset($_POST['_myparcel_delivery_options'])) {
            $myparselDeliveryOptions = $order->get_meta('_myparcel_delivery_options');
            if (!empty($myparselDeliveryOptions)) {
                if ($myparselDeliveryOptions = unserialize($myparselDeliveryOptions)) {
                    if ($myparselDeliveryOptions->isPickup()) {
                        $afterpay->AddressesDiffer = 'TRUE';
                        $pickupOptions = $myparselDeliveryOptions->getPickupLocation();
                        $afterpay->ShippingStreet = $pickupOptions->getStreet();
                        $afterpay->ShippingHouseNumber = $pickupOptions->getNumber();
                        $afterpay->ShippingPostalCode = $pickupOptions->getPostalCode();
                        $afterpay->ShippingCity = $pickupOptions->getCity();
                        $afterpay->ShippingCountryCode = $pickupOptions->getCountry();
                    }
                }
            }
        }
    }

    private function parseSendCloudPointAddress($addressData)
    {
        $formattedAddress = [];
        $addressData      = explode('|', $addressData);

        $streetData = $addressData[1];
        $cityData   = $addressData[2];

        $formattedCityData = $this->parseSendcloudCityData($cityData);
        $formattedStreet   = $this->formatStreet($streetData);

        $formattedAddress['street']      = $formattedStreet;
        $formattedAddress['postal_code'] = $formattedCityData[0];
        $formattedAddress['city']        = $formattedCityData[1];

        return $formattedAddress;
    }

    private function parseSendcloudCityData($cityData)
    {
        $cityData = preg_split('/\s/', $cityData, 2);

        return $cityData;
    }

    private function getDHLParcelShopLocation($parcelShopUrl)
    {
        $url  = "https://api-gw.dhlparcel.nl/parcel-shop-locations/" . $parcelShopUrl;
        $data = wp_remote_request($url);

        if ($data['response']['code'] !== 200) {
            throw new Exception(__('Parcel Shop not found'));
        }

        $data = json_decode($data['body']);

        if (empty($data->address)) {
            throw new Exception(__('Parcel Shop address is incorrect'));
        }

        return $data->address;
    }

    protected function process_refund_common($action, $order_id, $amount = null, $reason = '')
    {
        if ($action == 'Authorize') {
            // check if order is captured
            $captures         = get_post_meta($order_id, 'buckaroo_capture', false);
            $previous_refunds = get_post_meta($order_id, 'buckaroo_refund', false);

            if ($captures == false || count($captures) < 1) {
                return new WP_Error('error_refund_trid', __("Order is not captured yet, you can only refund captured orders"));
            }

            // Merge previous refunds with captures
            foreach ($captures as &$captureJson) {
                $capture = json_decode($captureJson, true);
                foreach ($previous_refunds as &$refundJson) {
                    $refund = json_decode($refundJson, true);

                    if (isset($refund['OriginalCaptureTransactionKey']) && $capture['OriginalTransactionKey'] == $refund['OriginalCaptureTransactionKey']) {

                        foreach ($capture['products'] as &$capture_product) {
                            foreach ($refund['products'] as &$refund_product) {
                                if ($capture_product['ArticleId'] != BuckarooConfig::SHIPPING_SKU && $capture_product['ArticleId'] == $refund_product['ArticleId'] && $refund_product['ArticleQuantity'] > 0) {
                                    if ($capture_product['ArticleQuantity'] >= $refund_product['ArticleQuantity']) {
                                        $capture_product['ArticleQuantity'] -= $refund_product['ArticleQuantity'];
                                        $refund_product['ArticleQuantity'] = 0;
                                    } else {
                                        $refund_product['ArticleQuantity'] -= $capture_product['ArticleQuantity'];
                                        $capture_product['ArticleQuantity'] = 0;
                                    }
                                } elseif ($capture_product['ArticleId'] == BuckarooConfig::SHIPPING_SKU && $capture_product['ArticleId'] == $refund_product['ArticleId'] && $refund_product['ArticleUnitprice'] > 0) {
                                    if ($capture_product['ArticleUnitprice'] >= $refund_product['ArticleUnitprice']) {
                                        $capture_product['ArticleUnitprice'] -= $refund_product['ArticleUnitprice'];
                                        $refund_product['ArticleUnitprice'] = 0;
                                    } else {
                                        $refund_product['ArticleUnitprice'] -= $capture_product['ArticleUnitprice'];
                                        $capture_product['ArticleUnitprice'] = 0;
                                    }
                                }
                            }
                        }
                    }
                    $refundJson = json_encode($refund);
                }
                $captureJson = json_encode($capture);
            }

            $captures = json_decode(json_encode($captures), true);

            $line_item_qtys       = json_decode(stripslashes($_POST['line_item_qtys']), true);
            $line_item_totals     = json_decode(stripslashes($_POST['line_item_totals']), true);
            $line_item_tax_totals = json_decode(stripslashes($_POST['line_item_tax_totals']), true);

            $line_item_qtys_new                 = array();
            $line_item_totals_new               = array();
            $line_item_tax_totals_new           = array();

            $order = wc_get_order($order_id);
            $items = $order->get_items();

            // Items to products
            $item_ids = array();

            foreach ($items as $item) {
                $item_ids[$item->get_id()] = $item->get_product_id();
            }

            $totalQtyToRefund = 0;

            // Loop through products
            if (is_array($line_item_qtys)) {
                foreach ($line_item_qtys as $id_to_refund => $qty_to_refund) {
                    // Find free `slots` in captures
                    foreach ($captures as $captureJson) {
                        $capture = json_decode($captureJson, true);
                        foreach ($capture['products'] as $product) {
                            if ($product['ArticleId'] == $item_ids[$id_to_refund]) {
                                // Found the product in the capture.
                                // See if qty is sufficent.
                                if ($qty_to_refund > 0) {
                                    if ($qty_to_refund <= $product['ArticleQuantity']) {
                                        $line_item_qtys_new[$id_to_refund] = $qty_to_refund;
                                        $qty_to_refund                               = 0;
                                    } else {
                                        $line_item_qtys_new[$id_to_refund] = $product['ArticleQuantity'];
                                        $qty_to_refund -= $product['ArticleQuantity'];
                                    }
                                }
                            }
                        }
                    }
                    $totalQtyToRefund += $qty_to_refund;
                }
            }

            // loop for fees
            $fee_items = $order->get_items('fee');

            $feeCostsToRefund = 0;
            foreach ($fee_items as $fee_item) {
                if (isset($line_item_totals[$fee_item->get_id()]) && $line_item_totals[$item->get_id()] > 0) {
                    $feeCostsToRefund = $line_item_totals[$fee_item->get_id()];
                    $feeIdToRefund    = $fee_item->get_id();
                }
            }

            // loop for shipping costs
            $shipping_item = $order->get_items('shipping');

            $shippingCostsToRefund = 0;
            foreach ($shipping_item as $item) {
                if (isset($line_item_totals[$item->get_id()]) && $line_item_totals[$item->get_id()] > 0) {
                    $shippingCostsToRefund = $line_item_totals[$item->get_id()];
                    $shippingIdToRefund    = $item->get_id();
                }
            }

            // Find free `slots` in captures
            foreach ($captures as $captureJson) {
                $capture = json_decode($captureJson, true);
                foreach ($capture['products'] as $product) {
                    if ($product['ArticleId'] == BuckarooConfig::SHIPPING_SKU) {
                        // Found the shipping in the capture.
                        // See if amount is sufficent.
                        if ($shippingCostsToRefund > 0) {
                            if ($shippingCostsToRefund <= $product['ArticleUnitprice']) {
                                $line_item_totals_new[$shippingIdToRefund]     = $shippingCostsToRefund;
                                $line_item_tax_totals_new[$shippingIdToRefund] = array(1 => 0);
                                $shippingCostsToRefund                                   = 0;
                            } else {
                                $line_item_totals_new[$shippingIdToRefund]     = $product['ArticleUnitprice'];
                                $line_item_tax_totals_new[$shippingIdToRefund] = array(1 => 0);
                                $shippingCostsToRefund -= $product['ArticleUnitprice'];
                            }
                        }
                    } elseif ($product['ArticleId'] == $feeIdToRefund) {
                        // Found the payment fee in the capture.
                        // See if amount is sufficent.
                        if ($feeCostsToRefund > 0) {
                            if ($feeCostsToRefund <= $product['ArticleUnitprice']) {
                                $line_item_totals_new[$feeIdToRefund]     = $feeCostsToRefund;
                                $line_item_tax_totals_new[$feeIdToRefund] = array(1 => 0);
                                $feeCostsToRefund                         = 0;
                            } else {
                                $line_item_totals_new[$feeIdToRefund]     = $product['ArticleUnitprice'];
                                $line_item_tax_totals_new[$feeIdToRefund] = array(1 => 0);
                                $feeCostsToRefund -= $product['ArticleUnitprice'];
                            }
                        }
                    }
                }
            }

            // Check if something cannot be refunded
            $NotRefundable = false;

            if ($shippingCostsToRefund > 0 || $totalQtyToRefund > 0) {
                $NotRefundable = true;
            }

            if ($NotRefundable) {
                return new WP_Error('error_refund_trid', __("Selected items or amount is not fully captured, you can only refund captured items"));
            }

            if ($amount > 0) {
                $refund_result = $this->process_partial_refunds(
                    $order_id,
                    $amount,
                    $reason,
                    $line_item_qtys_new,
                    $line_item_totals_new,
                    $line_item_tax_totals_new,
                    $capture['OriginalTransactionKey']
                );
            }

            if ($refund_result !== true) {
                if (isset($refund_result->errors['error_refund'][0])) {
                    return new WP_Error('error_refund_trid', __($result->errors['error_refund'][0]));
                } else {
                    return new WP_Error('error_refund_trid', __("Unexpected error occured while processing refund, please check your transactions in the Buckaroo plaza."));
                }
            }

            return true;
        } else {
            return $this->process_partial_refunds($order_id, $amount, $reason);
        }
    }

    public function getFeeTax($fee)
    {
        $feeInfo    = WC_Tax::get_rates($fee->get_tax_class());
        $feeInfo    = array_shift($feeInfo);
        $feeTaxRate = $feeInfo['rate'] ?? 0;

        return $feeTaxRate;
    }

    public function getAfterPayShippingInfo($afterpay_version, $method, $order, $line_item_totals, $line_item_tax_totals){

        $shipping_item = $order->get_items('shipping');
        $shippingCosts = 0;

        if ($afterpay_version == 'afterpay-new' && $method == 'partial_refunds'){
            $shippingTaxClassKey = 0;

            foreach ($shipping_item as $item) {
                if (isset($line_item_totals[$item->get_id()]) && $line_item_totals[$item->get_id()] > 0) {
                    $shippingCosts   = $line_item_totals[$item->get_id()];
                    $shippingTaxInfo = $item->get_taxes();
                    if (isset($line_item_tax_totals[$item->get_id()])) {
                        foreach ($shippingTaxInfo['total'] as $shippingTaxClass => $shippingTaxClassValue) {
                            $shippingTaxClassKey = $shippingTaxClass;
                            $shippingCosts += $shippingTaxClassValue;
                        }
                    }
                }
            }
        } else {
            foreach ($shipping_item as $item) {
                if (isset($line_item_totals[$item->get_id()]) && $line_item_totals[$item->get_id()] > 0) {
                    $shippingCosts = $line_item_totals[$item->get_id()] + (isset($line_item_tax_totals[$item->get_id()]) ? current($line_item_tax_totals[$item->get_id()]) : 0);
                }
            }
        }
    
        if ($shippingCosts > 0) {
            // Add virtual shipping cost product
            $tmp["ArticleDescription"] = "Shipping";
            $tmp["ArticleId"]          = BuckarooConfig::SHIPPING_SKU;
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = $shippingCosts;
            
            if ($afterpay_version == 'afterpay') {
                $tmp["ArticleVatcategory"] = 1;
            } elseif ($afterpay_version == 'afterpay-new' && $method == 'partial_refunds'){
                $tmp["ArticleVatcategory"] = WC_Tax::_get_tax_rate($shippingTaxClassKey)['tax_rate'] ?? 0;
            }

            return ['costs' => $shippingCosts, 'shipping_virtual_product' => $tmp];
        }
        return ['costs' => 0];
    }

    public function getProductTaxRate($product) {
        $tax      = new WC_Tax();
        $taxes    = $tax->get_rates($product->get_tax_class());
        $rates    = array_shift($taxes);
        $itemRate = number_format(array_shift($rates), 2);
        if ($product->get_tax_status() != 'taxable') {
            $itemRate = 0;
        } 
        return ['rate' => $itemRate];
    }

    public function getProductsInfo($order, $amountDedit, $shippingCosts){
        $products                    = array();
        $items                       = $order->get_items();
        $itemsTotalAmount            = 0;

        //Loop trough products
        foreach ($items as $item) {

            $product   = new WC_Product($item['product_id']);
            //Product details
            $tmp['ArticleDescription'] = $item['name'];
            $tmp['ArticleId'] = $item['product_id'];
            $tmp['ArticleQuantity'] = $item['qty'];

            //Get payment method product specific
            $productArr = $this->getProductSpecific($product, $item, $tmp);
            $tmp = $productArr['product_tmp'];            
            $itemsTotalAmount += $productArr['product_itemsTotalAmount'];

            //VAT
            $productTaxRate = $this->getProductTaxRate($product, $feeItemRate);
            $tmp['ArticleVatcategory'] = $productTaxRate['rate'];
            if (!empty($productTaxRate['product_qty_loop'])) {                
                for ($i = 0; $item['qty'] > $i; $i++) {
                    $products[] = $tmp;
                }
            } else {
                $feeItemRate = $feeItemRate > $productTaxRate['rate'] ? $feeItemRate : $productTaxRate['rate'];
                $products[] = $tmp;
            }

        }
        
        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {

            $tmp['ArticleDescription'] = $item['name'];
            $tmp['ArticleId']          = $key;
            $tmp['ArticleQuantity']    = 1;
            $tmp['ArticleUnitprice']   = number_format(($item['line_total'] + $item['line_tax']), 2);
            $tmp['ArticleVatcategory'] = 4;

            //Payment method fee specific
            if (method_exists($this, 'getFeeSpecific')) {
                $feeArr = $this->getFeeSpecific($item, $tmp, $fees[$key]);
                $tmp = $feeArr['product_tmp'];
            }

            if ($feeArr['product_itemsTotalAmount']) {
                $itemsTotalAmount += $feeArr['product_itemsTotalAmount'];
            } else {
                $itemsTotalAmount += $tmp['ArticleUnitprice'];
            }         

            $products[] = $tmp;
        }

        if (!empty($shippingCosts)) {
            $itemsTotalAmount += $shippingCosts;
        }

        if ($amountDedit != $itemsTotalAmount) {

            Buckaroo_Logger::log(__METHOD__ . "|2|".$this->id, ['amounDedit'=> $amountDedit, 'itemstotal'=>$itemsTotalAmount]);
            $tmp['ArticleDescription'] = 'Remaining Price';
            $tmp['ArticleId']          = 'remaining_price';
            $tmp['ArticleQuantity']    = 1;
            $tmp['ArticleUnitprice']   = number_format($amountDedit - $itemsTotalAmount, 2);
            $tmp['ArticleVatcategory'] = 0;

            if (number_format($amountDedit - $itemsTotalAmount, 2) >= 0.01) {
                $diffMode = 1;
            } elseif (number_format($itemsTotalAmount - $amountDedit, 2) >= 0.01) {
                $diffMode = 2;
            }

            if ($diffMode) {
                //Payment method remaining price specific
                if (method_exists($this, 'getRemainingPriceSpecific')) {
                    $feeArr = $this->getRemainingPriceSpecific($diffMode, $amountDedit, $itemsTotalAmount, $tmp);
                    $tmp = $feeArr['product_tmp'];
                }
                $products[] = $tmp;
            }
            
        }
        return $products;
    }

    public function formatStreet($street)
    {
        $format = [
            'house_number'    => '',
            'number_addition' => '',
            'name'            => $street,
        ];

        if (preg_match('#^(.*?)([0-9\-]+)(.*)#s', $street, $matches)) {
            // Check if the number is at the beginning of streetname
            if ('' == $matches[1]) {
                $format['house_number'] = trim($matches[2]);
                $format['name']         = trim($matches[3]);
            } else {
                if (preg_match('#^(.*?)([0-9]+)(.*)#s', $street, $matches)) {
                    $format['name']            = trim($matches[1]);
                    $format['house_number']    = trim($matches[2]);
                    $format['number_addition'] = trim($matches[3]);
                }
            }
        }

        return $format;
    }
}
