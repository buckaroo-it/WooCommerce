<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods;

use BuckarooConfig;
use BuckarooIdin;
use DateTime;
use Throwable;
use WC_Buckaroo\WooCommerce\Payment\Buckaroo_Payment_Factory;
use WC_Buckaroo\WooCommerce\Refund\Buckaroo_Refund_Factory;
use WC_Buckaroo\WooCommerce\Refund\Buckaroo_Refund_Processor;
use WC_Buckaroo\WooCommerce\Return\Buckaroo_Return_Processor;
use WC_Buckaroo\WooCommerce\SDK\Buckaroo_Client_Processor;
use WC_Order;
use WC_Payment_Gateway;
use WC_Tax;

/**
 * @package Buckaroo
 */
class PaymentGatewayHandler extends WC_Payment_Gateway
{
    const BUCKAROO_TEMPLATE_LOCATION = '/templates/gateways/';

    public $notify_url;
    public $minvalue;
    public $maxvalue;

    public $showpayproc = false;
    public $productQtyLoop = false;

    public $currency;

    public $mode;

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
            add_filter('woocommerce_order_button_html', array($this, 'replace_order_button_html'));
        }

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );
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
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $this->set_title();
        $this->description = $this->get_payment_description();
        $this->currency = get_woocommerce_currency();
        $this->mode = $this->get_option('mode');
        $this->minvalue = $this->get_option('minvalue', 0);
        $this->maxvalue = $this->get_option('maxvalue', 0);
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        try {
            $payment = Buckaroo_Payment_Factory::get_payment($this, (int)$order_id);
            ray($payment);
            $payment = new Buckaroo_Client_Processor($payment);
            $return = new Buckaroo_Return_Processor($this, (int)$order_id);
            return $return->process($payment->process());
        } catch (Throwable $th) {
            throw $th;
        }
    }

    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        try {
            $refund = Buckaroo_Refund_Factory::get_refund($this, (int)$order_id, floatval($amount), (string)$reason);
            $refund = new Buckaroo_Client_Processor($refund);
            $return = new Buckaroo_Refund_Processor(new WC_Order($order_id));
            return $return->process($refund->process());
        } catch (Throwable $th) {
            throw $th;
        }
    }

    /**
     * Get payment code used for sdk
     *
     * @return string
     */
    public function get_sdk_code(): string
    {
        return str_replace("buckaroo_", "", $this->id);
    }


    /**
     * Get checkout payment description field
     *
     * @return string
     */
    public function get_payment_description()
    {
        $desc = $this->get_option('description', '');
        if (strlen($desc) === 0) {
            $desc = sprintf(__('Pay with %s', 'wc-buckaroo-bpe-gateway'), $this->title);
        }
        return $desc;
    }

    /**
     * Get Payment fee VAT
     */
    public function get_payment_fee_vat($amount)
    {
        //Allow this to run only on checkout page
        if (!is_checkout()) {
            return 0;
        }

        //Get selected tax rate
        $taxRate = $this->get_option('feetax', '');

        $vatIncluded = $this->get_option('paymentfeevat', 'on');

        $location = array(
            'country' => WC()->customer->get_shipping_country() ? WC()->customer->get_shipping_country() : WC()->customer->get_billing_country(),
            'state' => WC()->customer->get_shipping_state() ? WC()->customer->get_shipping_state() : WC()->customer->get_billing_state(),
            'city' => WC()->customer->get_shipping_city() ? WC()->customer->get_shipping_city() : WC()->customer->get_billing_city(),
            'postcode' => WC()->customer->get_shipping_postcode() ? WC()->customer->get_shipping_postcode() : WC()->customer->get_billing_postcode(),
        );

        // Loop through tax classes
        foreach (wc_get_product_tax_class_options() as $tax_class => $tax_class_label) {

            $tax_rates = WC_Tax::find_rates(array_merge($location, array('tax_class' => $tax_class)));

            if (!empty($tax_rates) && $tax_class == $taxRate && $vatIncluded == 'off') {
                return WC_Tax::get_tax_total(WC_Tax::calc_exclusive_tax($amount, $tax_rates));
            }
        }
        return 0;
    }

    /**
     * Set title with fee
     *
     * @return void
     */
    public function set_title()
    {
        $feeText = '';
        $fee = $this->get_option('extrachargeamount', 0);
        $is_percentage = strpos($fee, "%") !== false;
        $fee = floatval(str_replace("%", "", $fee));

        if ($fee != 0) {
            if ($is_percentage) {
                $fee = str_replace("&nbsp;", "", wc_price(
                        $fee,
                        [
                            "currency" => 'null',
                        ]
                    )) . '%';
            } else {
                $fee = wc_price($fee + $this->get_payment_fee_vat($fee));
            }

            $feeText = " (+ " . $fee . ")";
        }

        $this->title = strip_tags($this->get_option('title', $this->title ?? '') . $feeText);
    }

    /**
     * Set gateway icon
     *
     * @param string $oldPath Old image path
     * @param string $newPath New image path
     *
     * @return void
     */
    protected function set_icon($oldPath, $newPath)
    {
        $this->icon = apply_filters(
            'woocommerce_' . $this->id . '_icon',
            BuckarooConfig::getIconPath($oldPath, $newPath)
        );
    }

    /**
     * Get gateway icon
     *
     * @return string
     */

    public function get_icon_path()
    {

        return $this->icon;
    }

    /**
     * Add refund support
     *
     * @return void
     */
    protected function add_refund_support()
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
    protected function migrate_old_setting($oldKey)
    {
        if (
            !get_option('woocommerce_' . $this->id . '_settings') &&
            ($oldSettings = get_option($oldKey))
        ) {
            add_option('woocommerce_' . $this->id . '_settings', $oldSettings);
            delete_option($oldKey); //clean the table
        }
    }

    public function replace_order_button_html($button)
    {
        if (!BuckarooIdin::checkCurrentUserIsVerified()) {
            return '';
        }
        return $button;
    }


    public function init_settings()
    {
        parent::init_settings();

        // merge with master settings
        $options = get_option('woocommerce_buckaroo_mastersettings_settings', null);
        if (is_array($options)) {
            unset(
                $options['enabled'],
                $options['title'],
                $options['mode'],
                $options['description'],
            );
            $this->settings = array_replace($this->settings, $options);
        }
    }

    public function generate_buckaroo_notice_html($key, $data)
    {
        //Add Warning, if currency set in Buckaroo is unsupported
        if (isset($_GET['section']) && $this->id == sanitize_text_field($_GET['section']) && !checkCurrencySupported($this->id) && is_admin()) :
            ob_start();
            ?>
            <div class="error notice">
                <p><?php echo esc_html__('This payment method is not supported for the selected currency ', 'wc-buckaroo-bpe-gateway') . '(' . esc_html(get_woocommerce_currency()) . ')'; ?>
                </p>
            </div>
            <?php
            return ob_get_clean();
        endif;
    }

    /**
     * Initialize Gateway Settings Form Fields
     *
     * @access public
     */
    public function init_form_fields()
    {
        $charset = strtolower(ini_get('default_charset'));
        $addDescription = '';
        if ($charset != 'utf-8') {
            $addDescription = '<fieldset style="border: 1px solid #ffac0e; padding: 10px;"><legend><b style="color: #ffac0e">' . __('Warning', 'wc-buckaroo-bpe-gateway') . '!</b></legend>' . __('default_charset is not set.<br>This might cause a problems on receiving push message.<br>Please set default_charset="UTF-8" in your php.ini and add AddDefaultCharset UTF-8 to .htaccess file.', 'wc-buckaroo-bpe-gateway') . '</fieldset>';
        }


        $this->title = (!isset($this->title) ? '' : $this->title);
        $this->id = (!isset($this->id) ? '' : $this->id);
        $this->form_fields = [
            'buckaroo_notice' => [
                'type' => 'buckaroo_notice',
            ],
            'enabled' => [
                'title' => __('Enable/Disable', 'wc-buckaroo-bpe-gateway'),
                'label' => sprintf(__('Enable %s Payment Method', 'wc-buckaroo-bpe-gateway'), (isset($this->method_title) ? $this->method_title : '')),
                'type' => 'checkbox',
                'description' => $addDescription,
                'default' => 'no',
            ],
            'mode' => [
                'title' => __('Transaction mode', 'wc-buckaroo-bpe-gateway'),
                'type' => 'select',
                'description' => __('Transaction mode used for processing orders', 'wc-buckaroo-bpe-gateway'),
                'options' => ['live' => 'Live', 'test' => 'Test'],
                'default' => 'test',
            ],
            'title' => [
                'title' => __('Front-end label', 'wc-buckaroo-bpe-gateway'),
                'type' => 'text',
                'description' => __(
                    'Determines how the payment method is named in the checkout.',
                    'wc-buckaroo-bpe-gateway'
                ),
                'default' => __($this->title, 'wc-buckaroo-bpe-gateway'),
            ],
            'description' => [
                'title' => __('Description', 'wc-buckaroo-bpe-gateway'),
                'type' => 'textarea',
                'description' => __(
                    'This controls the description which the user sees during checkout.',
                    'wc-buckaroo-bpe-gateway'
                ),
                'default' => $this->get_payment_description(),
            ],
            'extrachargeamount' => [
                'title' => __('Payment fee', 'wc-buckaroo-bpe-gateway'),
                'type' => 'text',
                'description' => __('Specify static (e.g. 1.50) or percentage amount (e.g. 1%). Decimals must be separated by a dot (.)', 'wc-buckaroo-bpe-gateway'),
                'default' => '0',
            ],
            'minvalue' => [
                'title' => __('Minimum order amount allowed', 'wc-buckaroo-bpe-gateway'),
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01'],
                'description' => __('Specify minimum order amount allowed to show the current method. Zero or empty value means no rule will be applied.', 'wc-buckaroo-bpe-gateway'),
                'default' => '0',
            ],
            'maxvalue' => [
                'title' => __('Maximum order amount allowed', 'wc-buckaroo-bpe-gateway'),
                'type' => 'number',
                'custom_attributes' => ['step' => '0.01'],
                'description' => __('Specify maximum order amount allowed to show the current method. Zero or empty value means no rule will be applied.', 'wc-buckaroo-bpe-gateway'),
                'default' => '0',
            ]
        ];
    }

    /**
     * Payment form on checkout page
     *
     * @return void
     */
    public function payment_fields()
    {
        $this->render_template();
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
        if (in_array($key, ['minvalue', 'maxvalue'])) {
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
     * Get clean $_POST data
     *
     * @param string $key
     *
     * @return mixed
     */
    public function request($key)
    {
        if (!isset($_POST[$key])) {
            return;
        }
        $value = map_deep($_POST[$key], 'sanitize_text_field');
        if (is_string($value) && strlen(trim($value)) === 0) {
            return;
        }
        return $value;
    }

    /**
     * Get clean $_GET data
     *
     * @param string $key
     *
     * @return mixed
     */
    public function requestGet($key)
    {
        if (!isset($_GET[$key])) {
            return;
        }
        $value = map_deep($_GET[$key], 'sanitize_text_field');
        if (is_string($value) && strlen($value) === 0) {
            return;
        }
        return $value;
    }


    /**
     * Check that a date is valid.
     *
     * @param String $date A date expressed as a string
     * @param String $format The format of the date
     * @return Object Datetime
     * @return Boolean Format correct returns True, else returns false
     */
    public function validate_date($date, $format = 'Y-m-d H:i:s')
    {
        if ($date === null) {
            return false;
        }

        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    /**
     * Check that a user is 18 years or older.
     *
     * @param String $birthdate Birthdate expressed as a string
     *
     * @return Boolean Is user 18 years or older return true, else false
     */
    public function validate_birthdate($birthdate)
    {

        $currentDate = new DateTime();
        $userBirthdate = DateTime::createFromFormat('d-m-Y', $birthdate);

        $ageInterval = $currentDate->diff($userBirthdate)->y;

        return $ageInterval >= 18;
    }

    public function parse_date($date)
    {
        if ($this->validate_date($date, 'd-m-Y')) return $date;

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
    protected function get_template($name)
    {
        $location = dirname(BK_PLUGIN_FILE) . self::BUCKAROO_TEMPLATE_LOCATION;
        $file = $location . $name . ".php";

        if (file_exists($file)) {
            include $file;
        }
    }

    /**
     * Render the gateway template
     *
     * @return void
     */
    protected function render_template($id = null)
    {
        if (is_null($id)) {
            $id = $this->id;
        }

        $name = str_replace("buckaroo_", "", $id);

        do_action("buckaroo_before_render_gateway_template_" . $name, $this);

        $this->get_template('global');
        $this->get_template($name);

        do_action("buckaroo_after_render_gateway_template_" . $name, $this);
    }

    /**
     * Get checkout field values
     *
     * @param string $key Input name
     *
     * @return mixt
     */
    protected function request_scalar($key)
    {
        $value = '';
        $post_data = array();
        if (!empty($_POST["post_data"]) && is_string($_POST["post_data"])) {
            parse_str(
                $_POST["post_data"],
                $post_data
            );
        }

        if (isset($post_data[$key]) && is_scalar($post_data[$key])) {
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
    protected function set_order_capture($order_id, $paymentName, $paymentType = null)
    {
        update_post_meta($order_id, '_wc_order_selected_payment_method', $paymentName);
        update_post_meta($order_id, '_wc_order_payment_issuer', $paymentType);
    }


    /**
     * Return properly formated capture error
     *
     * @param string $message
     *
     * @return array
     */
    protected function create_capture_error($message)
    {
        return [
            "errors" => [
                "error_capture" => [
                    [$message]
                ]
            ]
        ];
    }

    /**
     * Return properly filter if exists or null
     *
     * @param $tag
     * @param $value
     * @param mixed ...$args
     * @return array | null
     */
    function apply_filters_or_error($tag, $value, ...$args)
    {
        if (!has_filter($tag)) {
            return null;
        }
        $response = apply_filters($tag, $value, ...$args);

        return (isset($response['result']) && $response['result'] === 'no_subscription') ? null : $response;
    }

    /**
     * Return properly filter if exists or null
     *
     * @param string $message
     *
     * @return array | null
     */
    function apply_filter_or_error($tag, $value)
    {
        if (has_filter($tag)) {
            return apply_filters($tag, $value);
        }
        return null;
    }

    /**
     * Add financial warning field to the setting page
     *
     * @return void
     */
    protected function add_financial_warning_field()
    {

        $this->form_fields['financial_warning'] = [
            'title' => __('Consumer Financial Warning'),
            'type' => 'select',
            'description' => __('Due to the regulations for BNPL methods in The Netherlands you’ll  have to warn customers about using a BNPL plan because it can be easy to get into debt. When enabled a warning will be showed in the checkout. Please note that this setting only applies for customers in The Netherlands.', 'wc-buckaroo-bpe-gateway'),
            'options' => ['enable' => 'Enable', 'disable' => 'Disable'],
            'default' => 'enable'
        ];
    }

    protected function can_show_financial_warining()
    {
        $country = $this->request_scalar('billing_country');
        return $this->get_option('financial_warning') !== 'disable' && $country === "NL";
    }
}
