<?php

require_once __DIR__ . '/controllers/ApplePayController.php';
require_once dirname(__FILE__) . '/library/api/paymentmethods/applepay/applepay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Applepay extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooApplepay::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_applepay';
        $this->title                  = 'Applepay';
        $this->has_fields             = true;
        $this->method_title           = "Buckaroo Applepay";
        $this->CustomerCardName       = '';
        $this->setIcon('new/ApplePay.png', 'new/ApplePay.png');

        parent::__construct();
        $this->addRefundSupport();
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $this->registerControllers();
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
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return $this->processDefaultRefund($order_id, $amount, $reason, true);
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
        Buckaroo_Logger::log(__METHOD__ . "|1|", $_POST);

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
        $order = getWCOrder($order_id);
        /** @var BuckarooApplepay */
        $applepay = $this->createDebitRequest($order);

        $applepay->CustomerCardName = $this->CustomerCardName;

        $customVars                     = array();
        $customVars['PaymentData']      = base64_encode(json_encode($this->paymentData['token']));
        $customVars['CustomerCardName'] = $this->CustomerCardName;

    

        $response          = $applepay->Pay($customVars);
        $buckaroo_response = fn_buckaroo_process_response($this, $response);

        if ($response->status === BuckarooAbstract::STATUS_COMPLETED) {
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
        Buckaroo_Logger::log(__METHOD__ . "|1|");

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
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        unset($this->form_fields['title']);
        unset($this->form_fields['description']);
        
        $this->form_fields['button_product'] = array(
            'title'       => __('Button on product page', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Show the Apple pay button on the product page', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway')),
            'default'     => 'TRUE',
        );

        $this->form_fields['button_cart'] = array(
            'title'       => __('Button on cart page', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Show the Apple pay button on the cart page', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway')),
            'default'     => 'TRUE',
        );

        $this->form_fields['button_checkout'] = array(
            'title'       => __('Button on checkout page', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Show the Apple pay button on the checkout page', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway')),
            'default'     => 'TRUE',
        );

        $this->form_fields['merchant_guid'] = array(
            'title'       => __('Your merchant Guid', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('Your merchant Guid can be found in the Buckaroo plaza by click on "Your merchant name" and then opening the "General" page', 'wc-buckaroo-bpe-gateway'),
            'default'     => '0');
    }

    private static function createFakeCart($callback)
    {
        global $woocommerce;

        $cart         = $woocommerce->cart;

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
