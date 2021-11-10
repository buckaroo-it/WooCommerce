<?php

require_once 'library/include.php';
require_once dirname(__FILE__) . '/library/api/paymentmethods/in3/in3.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_In3 extends WC_Gateway_Buckaroo
{
    public $type;
    public $vattype;
    public $country;
    public function __construct()
    {
        $woocommerce = getWooCommerceObject();

        $this->id                     = 'buckaroo_in3';
        $this->title                  = 'In3';
        $this->icon = apply_filters('woocommerce_buckaroo_in3_icon', BuckarooConfig::getIconPath('24x24/in3.png', 'new/In3.png'));
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo In3';
        $this->description            =  sprintf(__('Pay with %s', 'wc-buckaroo-bpe-gateway'), $this->title);
        $GLOBALS['plugin_id']         = $this->plugin_id . $this->id . '_settings';
        $this->currency               = get_woocommerce_currency();
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');
        $this->secretkey              = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode                   = BuckarooConfig::getMode();
        $this->thumbprint             = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture                = BuckarooConfig::get('CULTURE');
        $this->usenotification        = BuckarooConfig::get('BUCKAROO_USE_NOTIFICATION');
        $this->notificationdelay      = BuckarooConfig::get('BUCKAROO_NOTIFICATION_DELAY');

        $country = null;
        if (!empty($woocommerce->customer)) {
            $country = get_user_meta($woocommerce->customer->get_id(), 'shipping_country', true);
        }

        $this->country = $country;

        parent::__construct();

        $this->supports = array(
            'products',
            'refunds',
        );
        $this->type       = 'in3';
        $this->vattype    = (isset($this->settings['vattype']) ? $this->settings['vattype'] : null);
        $this->notify_url = home_url('/');

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '<')) {

        } else {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_buckaroo_in3', array($this, 'response_handler'));
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_In3', $this->notify_url);
        }
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
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '', $line_item_qtys = null, $line_item_totals = null, $line_item_tax_totals = null, $originalTransactionKey = null)
    {
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id']        = $this->plugin_id . $this->id . '_settings';
        $order                       = wc_get_order($order_id);
        $in3                         = new BuckarooIn3();
        $in3->amountDedit            = 0;
        $in3->amountCredit           = $amount;
        $in3->currency               = $this->currency;
        $in3->description            = $reason;
        $in3->invoiceId              = $order->get_order_number();
        $in3->orderId                = $order_id;
        $in3->OriginalTransactionKey = $order->get_transaction_id();
        $in3->returnUrl              = $this->notify_url;
        $payment_type                = str_replace('buckaroo_', '', strtolower($this->id));
        $in3->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                    = null;

        $orderDataForChecking = $in3->getOrderRefundData();

        try {
            $in3->checkRefundData($orderDataForChecking);
            $response = $in3->In3Refund();

        } catch (Exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }

        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @access public
     * @return void
     */
    public function validate_fields()
    {
        $country = isset($_POST['billing_country']) ? $_POST['billing_country'] : $this->country;

        if (strtolower($_POST['buckaroo-in3-orderas']) != 'debtor') {
            if (empty($_POST['buckaroo-in3-coc'])) {
                wc_add_notice(__("Please enter CoC number", 'wc-buckaroo-bpe-gateway'), 'error');
            }

            if (empty($_POST['buckaroo-in3-companyname'])) {
                wc_add_notice(__("Please enter company name", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        if (version_compare(WC()->version, '3.6', '<')) {
            resetOrder();
        }

        return;
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        // Save this meta that is used later for the Capture call
        update_post_meta($order_id, '_wc_order_selected_payment_method', 'In3');
        update_post_meta($order_id, '_wc_order_payment_issuer', $this->type);

        $woocommerce = getWooCommerceObject();

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = new WC_Order($order_id);
        $in3                  = new BuckarooIn3($this->type);

        if (method_exists($order, 'get_order_total')) {
            $in3->amountDedit = $order->get_order_total();
        } else {
            $in3->amountDedit = $order->get_total();
        }
        $payment_type      = str_replace('buckaroo_', '', strtolower($this->id));
        $in3->channel      = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $in3->currency     = $this->currency;
        $in3->description  = 'Order #' . $order_id;
        $in3->orderId      = $order_id;
        $in3->invoiceId    = getUniqInvoiceId((string) $order->get_order_number(), $this->mode);
        $in3->CustomerType = $_POST["buckaroo-in3-orderas"];

        if (strtolower($in3->CustomerType) != 'debtor') {
            $in3->cocNumber   = $_POST["buckaroo-in3-coc"];
            $in3->companyName = $_POST["buckaroo-in3-companyname"];
        }

        $in3->BillingGender = $_POST['buckaroo-in3-gender'];

        $get_billing_first_name = getWCOrderDetails($order_id, "billing_first_name");
        $get_billing_last_name  = getWCOrderDetails($order_id, "billing_last_name");
        $get_billing_email      = getWCOrderDetails($order_id, "billing_email");

        $in3->BillingInitials = $this->getInitials($get_billing_first_name);
        $in3->BillingLastName = $get_billing_last_name;
        $birthdate            = $_POST['buckaroo-in3-birthdate'];
        if ($this->validateDate($birthdate, 'd-m-Y')) {
            $birthdate = date('Y-m-d', strtotime($birthdate));
        } elseif (in_array(getWCOrderDetails($order_id, 'billing_country'), ['NL'])) {
            wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }

        $shippingCosts    = $order->get_total_shipping();
        $shippingCostsTax = $order->get_shipping_tax();
        if (floatval($shippingCosts) > 0) {
            $in3->ShippingCosts = number_format($shippingCosts, 2) + number_format($shippingCostsTax, 2);
        }
        if (floatval($shippingCostsTax) > 0) {
            $in3->ShippingCostsTax = number_format(($shippingCostsTax * 100) / $shippingCosts);
        }

        // Set birthday if it's NL or BE
        $in3->BillingBirthDate = date('Y-m-d', strtotime($birthdate));

        $get_billing_address_1         = getWCOrderDetails($order_id, 'billing_address_1');
        $get_billing_address_2         = getWCOrderDetails($order_id, 'billing_address_2');
        $address_components            = fn_buckaroo_get_address_components($get_billing_address_1 . " " . $get_billing_address_2);
        $in3->BillingStreet            = $address_components['street'];
        $in3->BillingHouseNumber       = $address_components['house_number'];
        $in3->BillingHouseNumberSuffix = $address_components['number_addition'];
        $in3->BillingPostalCode        = getWCOrderDetails($order_id, 'billing_postcode');
        $in3->BillingCity              = getWCOrderDetails($order_id, 'billing_city');
        $in3->BillingCountry           = getWCOrderDetails($order_id, 'billing_country');
        $get_billing_email             = getWCOrderDetails($order_id, 'billing_email');
        $in3->BillingEmail             = !empty($get_billing_email) ? $get_billing_email : '';
        $get_billing_phone             = getWCOrderDetails($order_id, 'billing_phone');
        $number                        = $this->cleanup_phone($get_billing_phone);
        $in3->BillingPhoneNumber       = $number['phone'];
        $in3->InvoiceDate              = date("d-m-Y");

        $in3->CustomerIPAddress = getClientIpBuckaroo();
        $in3->Accept            = 'TRUE';
        $products               = array();
        $items                  = $order->get_items();
        $itemsTotalAmount       = 0;

        foreach ($items as $item) {
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $item['product_id'];
            $tmp["ArticleQuantity"]    = $item["qty"];
            $tmp["ArticleUnitprice"]   = number_format(number_format($item["line_total"] + $item["line_tax"], 4) / $item["qty"], 2);
            $itemsTotalAmount += number_format($tmp["ArticleUnitprice"] * $item["qty"], 2);

            $products['product'][] = $tmp;
        }

        $fees = $order->get_fees();
        foreach ($fees as $key => $item) {
            $tmp["ArticleDescription"] = $item['name'];
            $tmp["ArticleId"]          = $key;
            $tmp["ArticleQuantity"]    = 1;
            $tmp["ArticleUnitprice"]   = number_format(($item["line_total"] + $item["line_tax"]), 2);
            $itemsTotalAmount += $tmp["ArticleUnitprice"];
            $products['fee'] = $tmp;
        }
        if (!empty($in3->ShippingCosts)) {
            $itemsTotalAmount += $in3->ShippingCosts;
        }

        if ($in3->amountDedit != $itemsTotalAmount) {
            if (number_format($in3->amountDedit - $itemsTotalAmount, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($in3->amountDedit - $itemsTotalAmount, 2);

                $products['product'][] = $tmp;
                $itemsTotalAmount += 0.01;
            } elseif (number_format($itemsTotalAmount - $in3->amountDedit, 2) >= 0.01) {
                $tmp["ArticleDescription"] = 'Remaining Price';
                $tmp["ArticleId"]          = 'remaining_price';
                $tmp["ArticleQuantity"]    = 1;
                $tmp["ArticleUnitprice"]   = number_format($in3->amountDedit - $itemsTotalAmount, 2);

                $products['product'][] = $tmp;
                $itemsTotalAmount -= 0.01;
            }
        }

        $in3->returnUrl = $this->notify_url;

        if ($this->usenotification == 'TRUE') {
            $in3->usenotification         = 1;
            $customVars['Customergender'] = $_POST['buckaroo-sepadirectdebit-gender'];

            $get_billing_first_name          = getWCOrderDetails($order_id, 'billing_first_name');
            $get_billing_last_name           = getWCOrderDetails($order_id, 'billing_last_name');
            $get_billing_email               = getWCOrderDetails($order_id, 'billing_email');
            $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
            $customVars['CustomerLastName']  = !empty($get_billing_last_name) ? $get_billing_last_name : '';
            $customVars['Customeremail']     = !empty($get_billing_email) ? $get_billing_email : '';
            $customVars['Notificationtype']  = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + ' . (int) $this->invoicedelay . ' day')) . ' + ' . (int) $this->notificationdelay . ' day'));
        }

        $in3->in3Version = $this->settings['in3version'];
        $action          = 'PayInInstallments';

        $response = $in3->PayIn3($products, $action);
        return fn_buckaroo_process_response($this, $response, $this->mode);
    }

    /**
     * Check response data
     *
     * @access public
     */
    public function response_handler()
    {
        $woocommerce = getWooCommerceObject();
        $result      = fn_buckaroo_process_response($this);
        if (!is_null($result)) {
            wp_safe_redirect($result['redirect']);
        } else {
            wp_safe_redirect($this->get_failed_url());
        }
        exit;
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

        $this->form_fields['usenotification'] = array(
            'title'       => __('Use Notification Service', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('The notification service can be used to have the payment engine sent additional notifications.', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => 'Yes', 'FALSE' => 'No'),
            'default'     => 'FALSE');

        $this->form_fields['notificationdelay'] = array(
            'title'       => __('Notification delay', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('The time at which the notification should be sent. If this is not specified, the notification is sent immediately.', 'wc-buckaroo-bpe-gateway'),
            'default'     => '0');

        $this->form_fields['in3version'] = array(
            'title'       => __('In3 version', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Choose In3 version', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('false' => 'In3 Flexible', 'true' => 'In3 Garant'),
            'default'     => 'pay');
    }
}
