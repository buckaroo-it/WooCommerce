<?php
require_once 'library/include.php';
require_once __DIR__ . '/controllers/ApplePayController.php';
require_once dirname(__FILE__) . '/library/api/paymentmethods/applepay/applepay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Applepay extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $woocommerce                  = getWooCommerceObject();
        $this->id                     = 'buckaroo_applepay';
        $this->title                  = 'Applepay';
        $this->icon                   = null;
        $this->has_fields             = true;
        $this->method_title           = "Buckaroo Applepay";
        $this->description            =  sprintf(__('Pay with %s', 'wc-buckaroo-bpe-gateway'), $this->title);
        $GLOBALS['plugin_id']         = $this->plugin_id . $this->id . '_settings';
        $this->currency               = get_woocommerce_currency();
        $this->secretkey              = BuckarooConfig::get('BUCKAROO_SECRET_KEY');
        $this->mode                   = BuckarooConfig::getMode();
        $this->thumbprint             = BuckarooConfig::get('BUCKAROO_CERTIFICATE_THUMBPRINT');
        $this->culture                = BuckarooConfig::get('CULTURE');
        $this->transactiondescription = BuckarooConfig::get('BUCKAROO_TRANSDESC');
        $this->usenotification        = BuckarooConfig::get('BUCKAROO_USE_NOTIFICATION');
        $this->notificationdelay      = BuckarooConfig::get('BUCKAROO_NOTIFICATION_DELAY');
        $this->CustomerCardName       = '';

        parent::__construct();

        if (!isset($this->settings['usenotification'])) {
            $this->usenotification   = 'FALSE';
            $this->notificationdelay = '0';

        } else {
            $this->usenotification   = $this->settings['usenotification'];
            $this->notificationdelay = $this->settings['notificationdelay'];
        }

        $this->supports = array(
            'products',
            'refunds',
        );

        $this->notify_url = home_url('/');

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '<')) {

        } else {
            $this->registerControllers();
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_api_wc_gateway_buckaroo_applepay', array($this, 'response_handler'));
            $this->notify_url = add_query_arg('wc-api', 'WC_Gateway_Buckaroo_Applepay', $this->notify_url);
        }
    }

    private function registerControllers()
    {
        $namespace = "woocommerce_api_wc_gateway_buckaroo_applepay";

        add_action("{$namespace}-get-items-from-detail-page", [ApplePayController::class, 'getItemsFromDetailPage']);
        add_action("{$namespace}-get-items-from-cart", [ApplePayController::class, 'getItemsFromCart']);
        add_action("{$namespace}-get-shipping-methods", [ApplePayController::class, 'getShippingMethods']);
        add_action("{$namespace}-get-shop-information", [ApplePayController::class, 'getShopInformation']);
        add_action("{$namespace}-create-transaction", [$this, 'createTransaction']);
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
        $GLOBALS['plugin_id']             = $this->plugin_id . $this->id . '_settings';
        $order                            = wc_get_order($order_id);
        $applepay                         = new BuckarooApplepay();
        $applepay->amountDedit            = 0;
        $applepay->amountCredit           = $amount;
        $applepay->currency               = $this->currency;
        $applepay->description            = $reason;
        $applepay->invoiceId              = $order->get_order_number();
        $applepay->orderId                = $order_id;
        $applepay->OriginalTransactionKey = $order->get_transaction_id();
        $applepay->returnUrl              = $this->notify_url;
        $clean_order_no                   = (int) str_replace('#', '', $order->get_order_number());
        $applepay->setType(get_post_meta($clean_order_no, '_payment_method_transaction', true));
        $payment_type      = str_replace('buckaroo_', '', strtolower($this->id));
        $applepay->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response          = null;
        try {
            $response = $applepay->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }

    /**
     * Validate fields
     * @return void;
     */
    public function validate_fields()
    {
        resetOrder();
        return;
    }

    public function createTransaction()
    {
        require_once dirname(__FILE__) . '/library/logger.php';
        $logger = new BuckarooLogger(BuckarooLogger::INFO, 'applepay');
        $logger->logInfo(__METHOD__ . "|1|", $_POST);

        $this->paymentData      = $_POST['paymentData'];
        $this->CustomerCardName = $this->paymentData['billingContact']['givenName'] . ' ' . $this->paymentData['billingContact']['familyName'];

        $this->amount       = $_POST['amount'];
        $items              = $_POST['items'];
        $selected_method_id = $_POST['selected_shipping_method'];

        if (empty($this->amount) || !$this->paymentData || empty($items)) {
            throw new \Exception('ApplePay data is invalid.');
        }

        $billing_address  = $this->paymentData['billingContact'];
        $shipping_address = $this->paymentData['shippingContact'];

        $orderResult = $this->createOrder($billing_address, $shipping_address, $items, $selected_method_id);

        if ($orderResult) {
            $this->process_payment($orderResult['data']['id']);
        } else {
            throw new \Exception('Error while creation of WooCommerce order');
        }
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
        $order                = getWCOrder($order_id);
        $applepay             = new BuckarooApplepay();

        if (method_exists($order, 'get_order_total')) {
            $applepay->amountDedit = $order->get_order_total();
        } else {
            $applepay->amountDedit = $order->get_total();
        }

        $payment_type = str_replace('buckaroo_', '', strtolower($this->id));

        $applepay->channel          = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $applepay->currency         = $this->currency;
        $applepay->description      = $this->transactiondescription;
        $applepay->invoiceId        = $order->get_order_number();
        $applepay->orderId          = $order_id;
        $applepay->returnUrl        = $this->notify_url;
        $applepay->CustomerCardName = $this->CustomerCardName;

        $customVars                     = array();
        $customVars['PaymentData']      = base64_encode(json_encode($this->paymentData['token']));
        $customVars['CustomerCardName'] = $this->CustomerCardName;

        if ($this->usenotification == 'TRUE') {
            $applepay->usenotification    = 1;
            $customVars['Customergender'] = 0;

            $get_billing_first_name          = getWCOrderDetails($order_id, 'billing_first_name');
            $get_billing_last_name           = getWCOrderDetails($order_id, 'billing_last_name');
            $get_billing_email               = getWCOrderDetails($order_id, 'billing_email');
            $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
            $customVars['CustomerLastName']  = !empty($get_billing_last_name) ? $get_billing_last_name : '';
            $customVars['Customeremail']     = !empty($get_billing_email) ? $get_billing_email : '';

            $customVars['Notificationtype']  = 'PaymentComplete';
            $customVars['Notificationdelay'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime('now + ' . (int) $this->notificationdelay . ' day'))));
        }

        $response          = $applepay->Pay($customVars);
        $buckaroo_response = fn_buckaroo_process_response($this, $response);

        if ($response->status === "BUCKAROO_SUCCESS") {
            $order->update_status('processing', 'Order paid with Apple pay');
        } else {
            $buckaroo_response = [
                'status'  => 'fail',
                'message' => $response->message,
            ];
        }

        echo json_encode($buckaroo_response);
        exit;
    }

    public function createOrder($billing_addresses, $shipping_addresses, $items, $selected_method_id)
    {
        require_once dirname(__FILE__) . '/library/logger.php';
        $logger = new BuckarooLogger(BuckarooLogger::INFO, 'applepay');
        $logger->logInfo(__METHOD__ . "|1|");

        $order = wc_create_order();

        $wc_methods = self::createFakeCart(function () {
            $packages = WC()->cart->get_shipping_packages();

            return WC()
                ->shipping
                ->calculate_shipping_for_package(current($packages))['rates']
            ;
        });

        try {
            $products = array_filter($items, function ($item) {
                return $item['type'] === 'product';
            });

            $coupons = array_filter($items, function ($item) {
                return $item['type'] === 'coupon';
            });

            $extra_charge = array_filter($items, function ($item) {
                return $item['type'] === 'extra_charge';
            });

            foreach ($products as $product) {
                $order->add_product(wc_get_product($product['id']), $product['quantity']);
            }

            foreach ($coupons as $coupon) {
                preg_match('/coupon\:\s(.*)/i', $coupon['name'], $matches);
                $order->apply_coupon($matches[1]);
            }

            foreach ($extra_charge as $charge) {
                $taxable = $charge['attributes']['taxable']
                ? 'taxable'
                : 'none';

                $item_fee = new WC_Order_Item_Fee();
                $item_fee->set_name($charge['name']);
                $item_fee->set_amount((string) $charge['price']);
                $item_fee->set_tax_status('taxable');
                $item_fee->set_tax_class('');
                $item_fee->set_tax_status($taxable);
                $item_fee->set_total((string) $charge['price']);
                $order->add_item($item_fee);
            }

            $order->set_address(self::orderAddresses($billing_addresses), 'billing');
            $order->set_address(self::orderAddresses($shipping_addresses), 'shipping');

            ////set email
            $billingEmail = '';
            if (!empty($shipping_addresses['emailAddress'])) {
                $billingEmail = $shipping_addresses['emailAddress'];
            }
            if (!empty($billing_addresses['emailAddress'])) {
                $billingEmail = $billing_addresses['emailAddress'];
            }
            if ($billingEmail) {
                $order->set_billing_email($billingEmail);
            }

            ////set phone
            $billingPhone = '';
            if (!empty($shipping_addresses['phoneNumber'])) {
                $billingPhone = $shipping_addresses['phoneNumber'];
            }
            if (!empty($billing_addresses['phoneNumber'])) {
                $billingPhone = $billing_addresses['phoneNumber'];
            }
            if ($billingPhone) {
                $order->set_billing_phone($billingPhone);
            }

            if (!empty($selected_method_id) && !preg_match('/free/', $selected_method_id)) {
                $order->add_shipping($wc_methods[$selected_method_id]);
            }

            update_post_meta($order->get_id(), '_payment_method', $this->id);
            update_post_meta($order->get_id(), '_payment_method_title', $this->title);

            $order->calculate_totals();
            $order->update_status('pending payment', 'Order created using Apple pay', true);
        } catch (\Exception $e) {
            return false;
        }

        return [
            'success' => true,
            'data'    => [
                'id'    => $order->get_id(),
                'key'   => $order->get_order_key(),
                'items' => $items,
            ],
        ];
    }

    private static function orderAddresses($address)
    {
        return [
            'first_name' => $address['givenName'],
            'last_name'  => $address['familyName'],
            'email'      => $address['emailAddress'],
            'address_1'  => $address['addressLines'][0],
            'city'       => $address['locality'],
            'postcode'   => $address['postalCode'],
            'country'    => $address['countryCode'],
        ];
    }

    /**
     * Check response data
     *
     * @access public
     */
    public function response_handler()
    {
        $woocommerce          = getWooCommerceObject();
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
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        unset($this->form_fields['title']);
        unset($this->form_fields['description']);

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_certificate'));
        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_hide_local'));

        //Start Dynamic Rendering of Hidden Fields
        $options = get_option("woocommerce_" . $this->id . "_settings", null);

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

        $this->form_fields['button_product'] = array(
            'title'       => __('Button on product page', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Show the Apple pay button on the product page', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => 'Show', 'FALSE' => 'Hide'),
            'default'     => 'TRUE',
        );

        $this->form_fields['button_cart'] = array(
            'title'       => __('Button on cart page', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Show the Apple pay button on the cart page', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => 'Show', 'FALSE' => 'Hide'),
            'default'     => 'TRUE',
        );

        $this->form_fields['button_checkout'] = array(
            'title'       => __('Button on checkout page', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Show the Apple pay button on the checkout page', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => 'Show', 'FALSE' => 'Hide'),
            'default'     => 'TRUE',
        );

        $this->form_fields['merchant_guid'] = array(
            'title'       => __('Your merchant ID', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('Your merchant ID as supplied by Buckaroo, can be found in the Buckaroo plaza.', 'wc-buckaroo-bpe-gateway'),
            'default'     => '0');
    }

    private static function createFakeCart($callback)
    {
        global $woocommerce;

        $cart         = $woocommerce->cart;
        $country_code = strtoupper($_GET['country_code']);

        $current_shown_product = [
            'product_id'   => $_GET['product_id'],
            'variation_id' => $_GET['variation_id'],
            'quantity'     => (int) $_GET['quantity'],
        ];

        $original_cart_products = array_map(function ($product) {
            return [
                'product_id'   => $product['product_id'],
                'variation_id' => $product['variation_id'],
                'quantity'     => $product['quantity'],
            ];
        }, $cart->get_cart_contents());

        $original_applied_coupons = array_map(function ($coupon) {
            return [
                'coupon_id' => $coupon->get_id(),
                'code'      => $coupon->get_code(),
            ];
        }, $cart->get_coupons());

        $cart->empty_cart();
        $cart->add_to_cart(
            $current_shown_product['product_id'],
            $current_shown_product['quantity'],
            $current_shown_product['variation_id']
        );

        foreach ($original_applied_coupons as $original_applied_coupon) {
            $cart->apply_coupon($original_applied_coupon['code']);
        }

        $fake_cart_result = call_user_func($callback);

        $cart->empty_cart();

        foreach ($original_cart_products as $original_product) {
            $cart->add_to_cart(
                $original_product['product_id'],
                $original_product['quantity'],
                $original_product['variation_id']
            );
        }

        foreach ($original_applied_coupons as $original_applied_coupon) {
            $cart->apply_coupon($original_applied_coupon['code']);
        }

        wc_clear_notices();

        return $fake_cart_result;
    }
}
