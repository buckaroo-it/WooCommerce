<?php
require_once 'library/include.php';
require_once dirname(__FILE__) . '/library/api/paymentmethods/sepadirectdebit/sepadirectdebit.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_SepaDirectDebit extends WC_Gateway_Buckaroo
{
    public $usecreditmanagment;
    public $datedue;
    public $maxreminderlevel;
    public $paymentmethodssdd;
    public $showpayproc;
    public $invoicedelay;

    public function __construct()
    {
        $woocommerce = getWooCommerceObject();

        $this->id                     = 'buckaroo_sepadirectdebit';
        $this->title                  = 'SEPA Direct Debit';
        $this->icon = apply_filters('woocommerce_buckaroo_sepadirectdebit_icon', BuckarooConfig::getIconPath('24x24/directdebit.png', 'new/SEPA-directdebit.png'));
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo SEPA Direct Debit';
        $this->description            =  sprintf(__('Pay with %s', 'wc-buckaroo-bpe-gateway'), $this->title);
        $GLOBALS['plugin_id']         = $this->plugin_id . $this->id . '_settings';
        $this->currency               = get_woocommerce_currency();
        $this->secretkey              = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode                   = BuckarooConfig::getMode();
        $this->thumbprint             = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture                = BuckarooConfig::get('CULTURE');
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');

        parent::__construct();

        $this->supports = array(
            'products',
            'refunds',
        );
        $this->usecreditmanagment = $this->settings['usecreditmanagment'] ?? null;
        $this->invoicedelay       = $this->settings['invoicedelay'] ?? null;
        $this->datedue           = $this->settings['datedue'] ?? null;
        $this->maxreminderlevel  = $this->settings['maxreminderlevel'] ?? null;
        $this->paymentmethodssdd = '';
        if (!empty($this->settings['paymentmethodssdd'])) {
            $this->paymentmethodssdd = $this->settings['paymentmethodssdd'];
        }
        $this->notify_url = home_url('/');

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_buckaroo_sepadirectdebit', array($this, 'response_handler'));
            if ($this->showpayproc) {
                add_action('woocommerce_thankyou_buckaroo_sepadirectdebit', array($this, 'thankyou_description'));
            }

            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_SepaDirectDebit', $this->notify_url);
        }
    }

    /**
     * Can the order be refunded
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
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = wc_get_order($order_id);

        $sepadirectdebit               = new BuckarooSepaDirectDebit();
        $sepadirectdebit->amountDedit  = 0;
        $sepadirectdebit->amountCredit = $amount;
        $sepadirectdebit->currency     = $this->currency;
        $sepadirectdebit->description  = $reason;
        $sepadirectdebit->invoiceId    = $order->get_order_number();

        $sepadirectdebit->orderId                = $order_id;
        $sepadirectdebit->OriginalTransactionKey = $order->get_transaction_id();
        $sepadirectdebit->returnUrl              = $this->notify_url;
        $payment_type                            = str_replace('buckaroo_', '', strtolower($this->id));
        $sepadirectdebit->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                                = null;

        $orderDataForChecking = $sepadirectdebit->getOrderRefundData();

        try {
            $sepadirectdebit->checkRefundData($orderDataForChecking);
            $response = $sepadirectdebit->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }

    /**
     * Validate frontend fields.
     *
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if (empty($_POST['buckaroo-sepadirectdebit-accountname'])
            || empty($_POST['buckaroo-sepadirectdebit-iban'])) {
            wc_add_notice(__("Please fill in all required fields", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $sepadirectdebit      = new BuckarooSepaDirectDebit();
        if (!$sepadirectdebit->isIBAN($_POST['buckaroo-sepadirectdebit-iban'])) {
            wc_add_notice(__("Wrong IBAN number", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        if ($this->usecreditmanagment == 'TRUE') {
            $birthdate = $_POST['buckaroo-sepadirectdebit-birthdate'];
            if (!$this->validateDate($birthdate, 'Y-m-d')) {
                wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
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
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        $woocommerce = getWooCommerceObject();

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';

        if (empty($_POST['buckaroo-sepadirectdebit-accountname'])
            || empty($_POST['buckaroo-sepadirectdebit-iban'])) {
            wc_add_notice(__("Please fill in all required fields", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        };

        $sepadirectdebit = new BuckarooSepaDirectDebit();
        if (!$sepadirectdebit->isIBAN($_POST['buckaroo-sepadirectdebit-iban'])) {
            wc_add_notice(__("Wrong IBAN number", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }

        $order = getWCOrder($order_id);

        if (method_exists($order, 'get_order_total')) {
            $sepadirectdebit->amountDedit = $order->get_order_total();
        } else {
            $sepadirectdebit->amountDedit = $order->get_total();
        }
        $payment_type                         = str_replace('buckaroo_', '', strtolower($this->id));
        $sepadirectdebit->channel             = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $sepadirectdebit->currency            = $this->currency;
        $sepadirectdebit->description         = $this->transactiondescription;
        $sepadirectdebit->customeraccountname = $_POST['buckaroo-sepadirectdebit-accountname'];
        $sepadirectdebit->CustomerBIC         = $_POST['buckaroo-sepadirectdebit-bic'];
        $sepadirectdebit->CustomerIBAN        = $_POST['buckaroo-sepadirectdebit-iban'];
        $sepadirectdebit->invoiceId           = getUniqInvoiceId((string) $order->get_order_number(), $this->mode);
        $sepadirectdebit->orderId             = (string) $order_id;

        $customVars = array();
        if ($this->usecreditmanagment == 'TRUE') {
            $birthdate = $_POST['buckaroo-sepadirectdebit-birthdate'];
            if (!$this->validateDate($birthdate, 'Y-m-d')) {
                wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            $sepadirectdebit->usecreditmanagment = 1;
            $customVars['MaxReminderLevel']      = $this->maxreminderlevel;

            $customVars['CustomerCode'] = $order->customer_user;

            $get_billing_company             = getWCOrderDetails($order_id, 'billing_company');
            $get_billing_first_name          = getWCOrderDetails($order_id, 'billing_first_name');
            $get_billing_last_name           = getWCOrderDetails($order_id, 'billing_last_name');
            $customVars['CompanyName']       = !empty($get_billing_company) ? $get_billing_company : '';
            $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
            $customVars['CustomerLastName']  = !empty($get_billing_last_name) ? $get_billing_last_name : '';
            $customVars['CustomerInitials']  = $this->getInitials($get_billing_first_name);
            $customVars['Customergender']    = $_POST['buckaroo-sepadirectdebit-gender'];
            $customVars['CustomerBirthDate'] = date('Y-m-d', strtotime($birthdate));

            $get_billing_email           = getWCOrderDetails($order_id, 'billing_email');
            $customVars['Customeremail'] = !empty($get_billing_email) ? $get_billing_email : '';
            $get_billing_phone           = getWCOrderDetails($order_id, 'billing_phone');
            $number                      = $this->cleanup_phone($get_billing_phone);

            if ($number['type'] == 'mobile') {
                $customVars['MobilePhoneNumber'] = $number['phone'];
            } else {
                $customVars['PhoneNumber'] = $number['phone'];
            }
            $customVars['InvoiceDate'] = date('Y-m-d', strtotime('now + ' . (int) $this->invoicedelay . ' day'));
            $customVars['DateDue']     = date('Y-m-d', strtotime($customVars['InvoiceDate'] . ' + ' . (int) $this->datedue . ' day'));

            $get_billing_address_1 = getWCOrderDetails($order_id, 'billing_address_1');
            $get_billing_address_2 = getWCOrderDetails($order_id, 'billing_address_2');
            $address_components    = fn_buckaroo_get_address_components($get_billing_address_1 . " " . $get_billing_address_2);

            $customVars['ADDRESS']['ZipCode'] = getWCOrderDetails($order_id, 'billing_postcode');
            $customVars['ADDRESS']['City']    = getWCOrderDetails($order_id, 'billing_city');
            if (!empty($address_components['street'])) {
                $customVars['ADDRESS']['Street'] = $address_components['street'];
            }

            if (!empty($address_components['house_number'])) {
                $customVars['ADDRESS']['HouseNumber'] = $address_components['house_number'];
            }

            if (!empty($address_components['number_addition'])) {
                $customVars['ADDRESS']['HouseNumberSuffix'] = $address_components['number_addition'];
            }

            $customVars['ADDRESS']['Country'] = getWCOrderDetails($order_id, 'billing_country');
            $customVars['AmountVat']          = (WooV3Plus()) ? $order->get_total_tax() : $order->order_tax;
            if (!empty($this->paymentmethodssdd)) {
                $customVars['PaymentMethodsAllowed'] = implode(",", $this->paymentmethodssdd);
            }

        }
       
    
        $sepadirectdebit->returnUrl = $this->notify_url;
        $response                   = $sepadirectdebit->PayDirectDebit($customVars);
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
        fn_buckaroo_process_response($this);
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

        $this->form_fields['usecreditmanagment'] = array(
            'title'       => __('Use credit managment', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Buckaroo sends payment reminders to the customer. (Contact Buckaroo before activating credit management. By default this is excluded in the contract.)', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => __('Yes', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('No', 'wc-buckaroo-bpe-gateway')),
            'default'     => 'FALSE');
        $this->form_fields['invoicedelay'] = array(
            'title'             => __('Invoice delay (in days)', 'wc-buckaroo-bpe-gateway'),
            'type'              => 'number',
            'custom_attributes' => ["step" => "1"],
            'description'       => __('Specify the amount of days before Buckaroo invoices the order and sends out the payment mail.', 'wc-buckaroo-bpe-gateway'),
            'default'           => '3');
        $this->form_fields['datedue'] = array(
            'title'       => __('Due date (in days)', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('Specify the number of days the customer has to complete their payment before the first reminder e-mail will be sent by Buckaroo.', 'wc-buckaroo-bpe-gateway'),
            'default'     => '14');
        $this->form_fields['maxreminderlevel'] = array(
            'title'       => __('Max reminder level', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Select the maximum reminder level buckaroo will use.', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('4' => '4', '3' => '3', '2' => '2', '1' => '1'),
            'default'     => '4');
        $this->form_fields['paymentmethodssdd'] = array(
            'title'       => __('Allowed payment methods', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'multiselect',
            'css'         => 'height: 650px;',
            'description' => __('Select allowed payment methods for SEPA Direct Debit. (Ctrl+Click select multiple)', 'wc-buckaroo-bpe-gateway'),
            'options'     => array(
                'ideal'                      => 'iDEAL',
                'transfer'                   => 'Overboeking (SEPA Credit Transfer)',
                'mastercard'                 => 'Mastercard',
                'visa'                       => 'Visa',
                'maestro'                    => 'eMaestro',
                'giropay'                    => 'Giropay',
                'paypal'                     => 'Paypal',
                'bancontactmrcash'           => 'Mr. Cash/Bancontact',
                'sepadirectdebit'            => 'Machtiging (SEPA Direct Debit)',
                'sofortueberweisung'         => 'Sofortbanking',
                'belfius'                    => 'Belfius',
                'empayment'                  => 'èM! Payment',
                'babygiftcard'               => 'Baby Giftcard',
                'babyparkgiftcard'           => 'Babypark Giftcard',
                'beautywellness'             => 'Beauty Wellness',
                'boekenbon'                  => 'Boekenbon',
                'boekenvoordeel'             => 'Boekenvoordeel',
                'designshopsgiftcard'        => 'Designshops Giftcard',
                'fijncadeau'                 => 'Fijn Cadeau',
                'koffiecadeau'               => 'Koffie Cadeau',
                'kokenzo'                    => 'Koken En Zo',
                'kookcadeau'                 => 'Kook Cadeau',
                'nationaleentertainmentcard' => 'Nationale Entertainment Card',
                'naturesgift'                => 'Natures Gift',
                'podiumcadeaukaart'          => 'Podium Cadeaukaart',
                'shoesaccessories'           => 'Shoes Accessories',
                'webshopgiftcard'            => 'Webshop Giftcard',
                'wijncadeau'                 => 'Wijn Cadeau',
                'wonenzo'                    => 'Wonen En Zo',
                'yourgift'                   => 'Your Gift',
                'fashioncheque'              => 'Fashioncheque'),
        );
      
    }
}
