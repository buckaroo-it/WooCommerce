<?php

class WC_Gateway_Buckaroo extends WC_Payment_Gateway
{

    var $notify_url;
    var $transactiondescription;
    var $usenotification;
    var $invoicedelay;
    var $notificationdelay;
    var $extrachargeamount;
    var $extrachargetype;
    var $extrachargetaxtype;
    var $notificationtype;
    var $sellerprotection;

    function __construct()
    {
        global $woocommerce;

        // Load the form fields
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values
        //$this->enabled 		= $this->settings['enabled'];
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->secretkey = $this->settings['secretkey'];
        $this->mode = $this->settings['mode'];
        $this->certificate = $this->settings['certificate'];
        $this->thumbprint = $this->settings['thumbprint'];
        $this->transactiondescription = $this->settings['transactiondescription'];
        $this->culture = $this->settings['culture'];
        $this->currency = $this->settings['currency'];
        $this->extrachargeamount = 0;
        if (isset($this->settings['extrachargeamount'])) {
            $this->extrachargeamount = $this->settings['extrachargeamount'];
        }
        $this->extrachargetype = 'static';
        if (isset($this->settings['extrachargetype'])) {
            $this->extrachargetype = $this->settings['extrachargetype'];
        }
        $this->extrachargetaxtype = 'included';
        if (isset($this->settings['extrachargetaxtype'])) {
            $this->extrachargetaxtype = $this->settings['extrachargetaxtype'];
        }
        //$this->debug		= $this->settings['debug'];

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '<')) {

        } else {
            add_action('woocommerce_api_wc_gateway_buckaroo', array($this, 'response_handler'));
            add_action('woocommerce_cart_calculate_fees', array($this, 'calculate_order_fees'));
            add_action('wp_enqueue_scripts', array($this, 'refresh_frontend'));
        }

        if (!isset($this->settings['usenotification'])) {
            $this->usenotification = 'FALSE';
            $this->notificationdelay = '0';

        } else {
            $this->usenotification = $this->settings['usenotification'];
            $this->notificationdelay = $this->settings['notificationdelay'];
        }
        $this->notificationtype = 'PaymentComplete';

        if (!isset($this->settings['sellerprotection'])) {
            $this->sellerprotection = 'TRUE';
        } else {
            $this->sellerprotection = $this->settings['sellerprotection'];
        }

    }

    public function validate_number_field( $key ) {

        if ($key == 'extrachargeamount') {
            $field = $this->get_field_key( $key );

            if ( isset( $_POST[ $field ] ) ) {
                $text = wp_kses_post( trim( stripslashes( $_POST[ $field ] ) ) );
                if (!is_float($text) && !is_numeric($text)) {
                    $this->errors[] = __('Please provide valid payment fee');
                    return false;
                }
            }
        }
        return parent::validate_text_field($key, $text);
    }

    public function plugin_url()
    {
        return $this->plugin_url = untrailingslashit(plugins_url('/', __FILE__));
    }

    public function refresh_frontend()
    {
        $min = !defined('SCRIPT_DEBUG') || !SCRIPT_DEBUG ? '.min' : '';

        if (!is_checkout()) {
            return;
        }

        wp_enqueue_script(
            'wc-pf-checkout',
            $this->plugin_url() . '/assets/js/checkout' . $min . '.js',
            array('jquery'),
            BuckarooConfig::VERSION,
            true
        );
    }

    public function calculate_order_fees($cart)
    {
        if (!defined('WOOCOMMERCE_CHECKOUT')) {
            return;
        }
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $current_gateway    = WC()->session->chosen_payment_method;
        $subtotal           = $cart->cart_contents_total;

        if( !empty( $available_gateways ) ) {
            if ( isset( $current_gateway ) && isset( $available_gateways[ $current_gateway ] ) ) {
                $current_gateway = $available_gateways[ $current_gateway ];
            } elseif ( isset( $available_gateways[ get_option( 'woocommerce_default_gateway' ) ] ) ) {
                $current_gateway = $available_gateways[ get_option( 'woocommerce_default_gateway' ) ];
            } else {
                $current_gateway = current( $available_gateways );
            }
        }

        if (!empty($current_gateway->extrachargeamount) && !empty($current_gateway->extrachargeamount) && $current_gateway->extrachargeamount != 0) {
            $extra_charge_amount = $current_gateway->extrachargeamount;
            $extra_charge_type = $current_gateway->extrachargetype;
            $extra_charge_tax_type = $current_gateway->extrachargetaxtype;
            if ($extra_charge_type == 'percentage') {
                $extra_charge_amount = number_format($subtotal * $extra_charge_amount / 100, 2);
            }
            if($extra_charge_tax_type == 'excluded') {
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

            if ($do_apply) {

                $already_exists = false;
                $fees = $cart->get_fees();
                for ($i = 0; $i < count($fees); $i++) {
                    if ($fees[$i]->id == 'payment-method-fee') {
                        $already_exists = true;
                        $fee_id = $i;
                    }
                }

                if (!$already_exists) {
                    $cart->add_fee(__("Payment fee", 'wc-buckaroo-bpe-gateway'), $extra_charge_amount, $taxable);
                } else {
                    $fees[$fee_id]->amount = $extra_charge_amount;
                }
            }

            $this->current_extra_charge_amount = $extra_charge_amount;
            $this->current_extra_charge_type = $taxable;
        }
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    function init_form_fields()
    {
        $upload_dir = wp_upload_dir();
        $charset = strtolower(ini_get('default_charset'));
        $addDescription = '';
        if ($charset != 'utf-8') {
            $addDescription = '<fieldset style="border: 1px solid #ffac0e; padding: 10px;"><legend><b style="color: #ffac0e">Warning!</b></legend>default_charset is not set.<br>This might cause a problems on receiving push message.<br>Please set default_charset="UTF-8" in your php.ini and add AddDefaultCharset UTF-8 to .htaccess file.</fieldset>';
        }
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wc-buckaroo-bpe-gateway'),
                'label' => __(
                    'Enable ' . (isset($this->method_title) ? $this->method_title : '') . ' Payment Method',
                    'wc-buckaroo-bpe-gateway'
                ),
                'type' => 'checkbox',
                'description' => $addDescription,
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title'),
                'type' => 'text',
                'description' => __(
                    'This controls the title which the user sees during checkout.',
                    'wc-buckaroo-bpe-gateway'
                ),
                'default' => __($this->title, 'wc-buckaroo-bpe-gateway'),
                'css' => "width: 300px;"
            ),
            'description' => array(
                'title' => __('Description', 'wc-buckaroo-bpe-gateway'),
                'type' => 'textarea',
                'description' => __(
                    'This controls the description which the user sees during checkout.',
                    'wc-buckaroo-bpe-gateway'
                ),
                'default' => $this->description
            ),
            'merchantkey' => array(
                'title' => __('Merchant key', 'wc-buckaroo-bpe-gateway'),
                'type' => 'text',
                'description' => __('This is your Buckaroo payment plaza Website key.', 'wc-buckaroo-bpe-gateway'),
                'default' => ''
            ),
            'secretkey' => array(
                'title' => __('Secret key', 'wc-buckaroo-bpe-gateway'),
                'type' => 'text',
                'description' => __('The Secret password to verify transactions', 'wc-buckaroo-bpe-gateway'),
                'default' => ''
            ),
            'mode' => array(
                'title' => __('Transaction Mode', 'wc-buckaroo-bpe-gateway'),
                'type' => 'select',
                'description' => __('Transaction mode used for processing orders', 'wc-buckaroo-bpe-gateway'),
                'options' => array('live' => 'Live', 'test' => 'Test'),
                'default' => 'test'
            ),
            'certificate' => array(
                'title' => __('Certificate', 'wc-buckaroo-bpe-gateway'),
                'type' => 'text',
                'description' => __(
                    'Please enter certificate name. Certificate should be uploaded to "' . $upload_dir["basedir"] . '/woocommerce_uploads" directory.',
                    'wc-buckaroo-bpe-gateway'
                ),
                'default' => 'BuckarooPrivateKey.pem'
            ),
            'thumbprint' => array(
                'title' => __('Certificate thumbprint', 'wc-buckaroo-bpe-gateway'),
                'type' => 'text',
                'description' => __('Certificate thumbprint', 'wc-buckaroo-bpe-gateway'),
                'default' => ''
            ),
            'transactiondescription' => array(
                'title' => __('Transaction description', 'wc-buckaroo-bpe-gateway'),
                'type' => 'textarea',
                'description' => __('Transaction description', 'wc-buckaroo-bpe-gateway'),
                'default' => ''
            ),
            'culture' => array(
                'title' => __('Language', 'wc-buckaroo-bpe-gateway'),
                'type' => 'select',
                'description' => __('Buckaroo Payment Engine culture', 'wc-buckaroo-bpe-gateway'),
                'options' => array('en-US' => 'English', 'nl-NL' => 'Dutch', 'fr-FR' => 'French', 'de-DE' => 'German'),
                'default' => 'nl-NL'
            ),
            'currency' => array(
                'title' => __('Currency', 'wc-buckaroo-bpe-gateway'),
                'type' => 'select',
                'description' => __('Currency', 'wc-buckaroo-bpe-gateway'),
                'options' => array('EUR' => 'Euro', 'USD' => 'USD', 'GBP' => 'GBP'),
                'default' => 'EUR'
            ),
            'extrachargeamount' => array(
                'title' => __('Extra charge amount', 'wc-buckaroo-bpe-gateway'),
                'type' => 'number',
                'custom_attributes' => Array("step" => "0.01"),
                'description' => __('Specify static or percentage amount.'.' '.__('Decimals must be seperated by a dot (.)'), 'wc-buckaroo-bpe-gateway'),
                'default' => '0'
            ),
            'extrachargetype' => array(
                'title' => __('Extra charge type', 'wc-buckaroo-bpe-gateway'),
                'type' => 'select',
                'options' => array('static' => 'Static', 'percentage' => 'Percentage'),
                'description' => __('Percentage or static', 'wc-buckaroo-bpe-gateway'),
                'default' => 'static'
            ),
            'extrachargetaxtype' => array(
                'title' => __('Extra charge tax type', 'wc-buckaroo-bpe-gateway'),
                'type' => 'select',
                'options' => array('included' => 'Included', 'excluded' => 'Excluded'),
                'description' => __('Payment fee including or excluding tax', 'wc-buckaroo-bpe-gateway'),
                'default' => 'included'
            ),
            /*'debug' => array(
                        'title' => __( 'Debug', 'wc-buckaroo-bpe-gateway' ),
                        'type' => 'checkbox',
                        'label' => __( 'Enable logging (<code>woocommerce/logs/totalweb.txt</code>)', 'wc-buckaroo-bpe-gateway' ),
                        'default' => 'no'
                    )*/
        );
    }

    public function response_handler()
    {
        global $woocommerce;
        fn_buckaroo_process_response($this);
        exit;
    }

    /**
     * Payment form on checkout page
     */
    function payment_fields()
    {
        ?>
        <?php if ($this->mode == 'test') : ?><p><?php _e(
        'TEST MODE',
        'wc-buckaroo-bpe-gateway'
    ); ?></p><?php endif; ?>
        <?php if ($this->description) : ?><p><?php echo wpautop(wptexturize($this->description)); ?></p><?php endif; ?>
    <?php
    }

    function get_failed_url()
    {
        $thanks_page_id = woocommerce_get_page_id('checkout');
        if ($thanks_page_id) :
            $return_url = get_permalink($thanks_page_id);
        else :
            $return_url = home_url();
        endif;
        if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
            $return_url = str_replace('http:', 'https:', $return_url);
        }

        return apply_filters('woocommerce_get_return_url', $return_url);
    }

    function getInitials($str)
    {
        $ret = '';
        foreach (explode(' ', $str) as $word) {
            $ret .= strtoupper($word[0]) . '.';
        }
        return $ret;
    }

    public static function cleanup_phone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (substr($phone, 0, 3) == '316' || substr($phone, 0, 5) == '00316' || substr(
                $phone,
                0,
                6
            ) == '003106' || substr($phone, 0, 2) == '06'
        ) {
            if (substr($phone, 0, 6) == '003106') {
                $phone = substr_replace($phone, '00316', 0, 6);
            }
            $response = array('type' => 'mobile', 'phone' => $phone);
        } else {
            $response = array('type' => 'landline', 'phone' => $phone);
        }

        return $response;
    }

    function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
}
