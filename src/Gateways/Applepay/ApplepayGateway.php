<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use Throwable;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;

class ApplepayGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = ApplepayProcessor::class;

    protected $paymentData;

    protected $CustomerCardName;

    protected $amount;

    public function __construct()
    {
        $this->id = 'buckaroo_applepay';
        $this->title = 'Apple Pay';
        $this->has_fields = true;
        $this->method_title = 'Buckaroo Apple Pay';
        $this->CustomerCardName = '';
        $this->setIcon('svg/applepay.svg');

        parent::__construct();
        $this->addRefundSupport();
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $this->registerControllers();
        }
    }

    private function registerControllers()
    {
        $namespace = 'woocommerce_api_wc_gateway_buckaroo_applepay';

        add_action("{$namespace}-get-items-from-detail-page", [ApplepayController::class, 'getItemsFromDetailPage']);
        add_action("{$namespace}-get-items-from-cart", [ApplepayController::class, 'getItemsFromCart']);
        add_action("{$namespace}-get-shipping-methods", [ApplepayController::class, 'getShippingMethods']);
        add_action("{$namespace}-get-shop-information", [ApplepayController::class, 'getShopInformation']);
        add_action("{$namespace}-create-transaction", [$this, 'createTransaction']);
    }

    /**
     * Validate fields
     *
     * @return void;
     */
    public function validate_fields()
    {
        Helper::resetOrder();
    }

    public function createTransaction()
    {
        Logger::log(__METHOD__ . '|1|', $_POST);

        $this->paymentData = $this->request->input('paymentData');

        if (! is_array($this->paymentData)) {
            $this->error_response('ApplePay data is invalid.');
        }

        if (
            ! isset($this->paymentData['billingContact']) ||
            ! isset($this->paymentData['shippingContact']) ||
            ! is_array($this->paymentData['billingContact']) ||
            ! is_array($this->paymentData['shippingContact'])
        ) {
            $this->error_response('ApplePay data is invalid.');
        }

        $items = $this->request->input('items');
        if ($items === null || ! is_array($items)) {
            $this->error_response('ApplePay data is invalid.');
        }

        $shipping_method = $this->request->input('selected_shipping_method');
        if ($shipping_method === null || ! is_scalar($shipping_method)) {
            $this->error_response('Invalid shipping method.');
        }
        $amount = $this->request->input('amount');
        if ($amount === null || ! is_scalar($amount) || (float) $amount <= 0) {
            $this->error_response('Invalid amount.');
        }
        if (
            count(
                array_diff(
                    ['givenName', 'familyName', 'emailAddress', 'addressLines', 'locality', 'postalCode', 'countryCode'],
                    array_keys($this->paymentData['shippingContact'])
                )
            )
        ) {
            $this->error_response('Invalid shipping address format.');
        }
        if (
            count(
                array_diff(
                    ['givenName', 'familyName', 'addressLines', 'locality', 'postalCode', 'countryCode'],
                    array_keys($this->paymentData['billingContact'])
                )
            )
        ) {
            $this->error_response('Invalid billing address format.');
        }

        $this->CustomerCardName = implode(
            ' ',
            [
                sanitize_text_field($this->paymentData['billingContact']['givenName']),
                sanitize_text_field($this->paymentData['billingContact']['familyName']),
            ]
        );

        $this->amount = $amount;

        try {
            $orderResult = $this->createOrder(
                $this->paymentData['billingContact'],
                $this->paymentData['shippingContact'],
                $items,
                $shipping_method
            );

            if ($orderResult) {
                $result = $this->process_payment($orderResult['data']['id']);
                Logger::log(__METHOD__ . '|1|', $result);

                echo json_encode($result);
                exit;
            } else {
                $this->error_response('Error while creation of WooCommerce order');
            }
        } catch (Throwable $th) {
            $this->error_response($th->getMessage());
        }
    }

    public function error_response($errorMessage)
    {
        wp_send_json(
            [
                'status' => 'fail',
                'message' => $errorMessage,
            ]
        );
    }

    public function createOrder($billing_addresses, $shipping_addresses, $items, $selected_method_id)
    {
        Logger::log(__METHOD__ . '|1|');

        $order = wc_create_order();

        $useExistingCart = self::cartCoversWalletItems(WC()->cart, $items);
        if ($useExistingCart) {
            $packages = WC()->cart->get_shipping_packages();
            $wc_methods = $packages
                ? WC()->shipping->calculate_shipping_for_package(current($packages))['rates']
                : [];
        } else {
            $wc_methods = self::createFakeCart(
                $items,
                function () {
                    $packages = WC()->cart->get_shipping_packages();

                    return WC()
                        ->shipping
                        ->calculate_shipping_for_package(current($packages))['rates'];
                }
            );
        }

        try {
            if ($useExistingCart) {
                self::createOrderFromExistingCart($order, WC()->cart);
            } else {
                self::createOrderFromCart($order, $items);
            }

            $order->set_address(self::orderAddresses($billing_addresses), 'billing');
            $order->set_address(self::orderAddresses($shipping_addresses), 'shipping');

            // set email
            $billingEmail = '';
            if (! empty($shipping_addresses['emailAddress'])) {
                $billingEmail = $shipping_addresses['emailAddress'];
            }
            if (! empty($billing_addresses['emailAddress'])) {
                $billingEmail = $billing_addresses['emailAddress'];
            }
            if ($billingEmail) {
                $order->set_billing_email($billingEmail);
            }

            // set phone
            $billingPhone = '';
            if (! empty($shipping_addresses['phoneNumber'])) {
                $billingPhone = $shipping_addresses['phoneNumber'];
            }
            if (! empty($billing_addresses['phoneNumber'])) {
                $billingPhone = $billing_addresses['phoneNumber'];
            }
            if ($billingPhone) {
                $order->set_billing_phone($billingPhone);
            }

            if (
                ! empty($selected_method_id)
                && ! preg_match('/free/', $selected_method_id)
            ) {
                if (
                    isset($wc_methods[$selected_method_id])
                    && $wc_methods[$selected_method_id] instanceof \WC_Shipping_Rate
                ) {
                    $rate = $wc_methods[$selected_method_id];
                    $shippingItem = new WC_Order_Item_Shipping();
                    $shippingItem->set_props([
                        'method_title' => $rate->get_label(),
                        'method_id' => $rate->get_method_id(),
                        'instance_id' => $rate->get_instance_id(),
                        'total' => wc_format_decimal($rate->get_cost()),
                        'taxes' => $rate->get_taxes(),
                    ]);
                    $order->add_item($shippingItem);
                } else {
                    Logger::log(__METHOD__ . '|missing shipping rate|', $selected_method_id);
                }
            }

            $order->set_payment_method($this->id);
            $order->set_payment_method_title($this->title);
            $this->setOrderContribution($order);

            $order->calculate_totals();

            $authorizedAmount = (float) $this->amount;
            if ($authorizedAmount > 0) {
                $computed = (float) $order->get_total('edit');
                if (abs($computed - $authorizedAmount) > 0.01) {
                    Logger::log(__METHOD__ . '|total drift|', [
                        'computed' => $computed,
                        'authorized' => $authorizedAmount,
                    ]);
                }
                $order->set_total($authorizedAmount);
            }

            $order->update_status('pending payment', 'Order created using Apple pay', true);
        } catch (Throwable $e) {
            Logger::log(__METHOD__ . '|fail|', $e->getMessage());

            return false;
        }

        return [
            'success' => true,
            'data' => [
                'id' => $order->get_id(),
                'key' => $order->get_order_key(),
                'items' => $items,
            ],
        ];
    }

    private static function createOrderFromCart($order, $items)
    {
        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'product' || ! isset($item['id'], $item['quantity'])) {
                continue;
            }
            $product = wc_get_product($item['id']);
            if (! $product) {
                continue;
            }
            $price = (float) ($item['price'] ?? 0);
            $orderItem = new WC_Order_Item_Product();
            $orderItem->set_props([
                'product' => $product,
                'quantity' => (int) $item['quantity'],
                'subtotal' => $price,
                'total' => $price,
            ]);
            $orderItem->set_product($product);
            $order->add_item($orderItem);
        }
    }

    private static function createOrderFromExistingCart($order, $cart)
    {
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'] ?? null;
            if (! $product) {
                continue;
            }

            $orderItem = new WC_Order_Item_Product();
            $orderItem->set_props([
                'product' => $product,
                'quantity' => $cart_item['quantity'],
                'subtotal' => $cart_item['line_subtotal'],
                'total' => $cart_item['line_total'],
                'subtotal_tax' => $cart_item['line_subtotal_tax'],
                'total_tax' => $cart_item['line_tax'],
            ]);
            $order->add_item($orderItem);
        }

        foreach ($cart->get_applied_coupons() as $coupon_code) {
            $order->apply_coupon($coupon_code);
        }

        foreach ($cart->get_fees() as $fee) {
            $feeItem = new WC_Order_Item_Fee();
            $feeItem->set_props([
                'name' => $fee->name,
                'tax_class' => $fee->tax_class,
                'tax_status' => $fee->taxable ? 'taxable' : 'none',
                'amount' => $fee->amount,
                'total' => $fee->total,
                'total_tax' => $fee->tax,
            ]);
            $order->add_item($feeItem);
        }
    }

    private static function cartCoversWalletItems($cart, array $items): bool
    {
        if ($cart->is_empty()) {
            return false;
        }

        $cartFingerprint = [];
        foreach ($cart->get_cart() as $cart_item) {
            $id = (int) ($cart_item['variation_id'] ?: $cart_item['product_id']);
            $cartFingerprint[] = $id . ':' . (int) $cart_item['quantity'];
        }

        $walletFingerprint = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'product' && isset($item['id'], $item['quantity'])) {
                $walletFingerprint[] = (int) $item['id'] . ':' . (int) $item['quantity'];
            }
        }

        if (empty($walletFingerprint) || empty($cartFingerprint)) {
            return false;
        }

        sort($cartFingerprint);
        sort($walletFingerprint);

        return $cartFingerprint === $walletFingerprint;
    }

    private static function createFakeCart($items, $callback)
    {
        global $woocommerce;
        $cart = $woocommerce->cart;

        $original_cart_products = array_map(
            function ($product) {
                return [
                    'product_id' => $product['product_id'],
                    'variation_id' => $product['variation_id'],
                    'quantity' => $product['quantity'],
                ];
            },
            $cart->get_cart_contents()
        );

        $original_applied_coupons = array_map(
            function ($coupon) {
                return [
                    'coupon_id' => $coupon->get_id(),
                    'code' => $coupon->get_code(),
                ];
            },
            $cart->get_coupons()
        );

        $cart->empty_cart();

        foreach ($items as $item) {
            if (
                count(
                    array_diff(
                        ['id', 'quantity'],
                        array_keys($item)
                    )
                ) === 0
            ) {
                $cart->add_to_cart(
                    $item['id'],
                    $item['quantity'],
                );
            }
        }

        foreach ($original_applied_coupons as $original_applied_coupon) {
            $cart->apply_coupon($original_applied_coupon['code']);
        }

        do_action('woocommerce_before_calculate_totals', $cart);
        $cart->calculate_totals();
        do_action('woocommerce_after_calculate_totals', $cart);

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

    private static function orderAddresses($address)
    {
        return [
            'first_name' => $address['givenName'],
            'last_name' => $address['familyName'],
            'email' => $address['emailAddress'],
            'address_1' => $address['addressLines'][0],
            'city' => $address['locality'],
            'postcode' => $address['postalCode'],
            'country' => $address['countryCode'],
        ];
    }

    private function setOrderContribution(WC_Order $order)
    {
        $prefix = (string) apply_filters(
            'wc_order_attribution_tracking_field_prefix',
            'wc_order_attribution_'
        );

        // Remove leading and trailing underscores.
        $prefix = trim($prefix, '_');

        // Ensure the prefix ends with _, and set the prefix.
        $prefix = "_{$prefix}_";

        $order->add_meta_data($prefix . 'source_type', 'typein');
        $order->add_meta_data($prefix . 'utm_source', '(direct)');
        $order->save();
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        unset($this->form_fields['title']);
        unset($this->form_fields['description']);

        $this->form_fields['button_product'] = [
            'title' => __('Button on product page', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Show the Apple pay button on the product page', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'),
                'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway'),
            ],
            'default' => 'TRUE',
        ];

        $this->form_fields['button_cart'] = [
            'title' => __('Button on cart page', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Show the Apple pay button on the cart page', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'),
                'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway'),
            ],
            'default' => 'TRUE',
        ];

        $this->form_fields['button_checkout'] = [
            'title' => __('Button on checkout page', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Show the Apple pay button on the checkout page', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'),
                'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway'),
            ],
            'default' => 'TRUE',
        ];
        $this->set_guid_after_usemaster();
    }

    /**
     * Set merchand_guid after master settings checkbox
     *
     * @return void
     */
    protected function set_guid_after_usemaster()
    {
        $new_form_fields = [];
        foreach ($this->form_fields as $k => $value) {
            $new_form_fields[$k] = $value;
            if ($k === 'mode') {
                $new_form_fields['merchant_guid'] = [
                    'title' => __('GUID', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'text',
                    'description' => __('The Buckaroo GUID which can be found in the Buckaroo Plaza > My Buckaroo > General.', 'wc-buckaroo-bpe-gateway'),
                    'default' => '0',
                ];
            }
        }
        $this->form_fields = $new_form_fields;
    }

    public function handleHooks()
    {
        $afterpayButtons = new ApplepayButtons();
        $afterpayButtons->loadActions();

        $destinationDir = ABSPATH . '.well-known';
        $destinationFile = $destinationDir . '/apple-developer-merchantid-domain-association';
        $sourceFile = plugin_dir_path(BK_PLUGIN_FILE) . 'assets/apple-developer-merchantid-domain-association';

        /**
         * Ensure the Apple Developer Domain Association file exists.
         * Creates the necessary directories and copies the association file if it doesn't exist.
         */
        if (! file_exists($destinationFile)) {
            if (! is_dir($destinationDir)) {
                if (! mkdir($destinationDir, 0775, true) && ! is_dir($destinationDir)) {
                    // Handle the error appropriately, e.g., log it or throw an exception
                    error_log("Failed to create directory: {$destinationDir}");

                    return;
                }
            }

            if (! copy($sourceFile, $destinationFile)) {
                // Handle the error appropriately, e.g., log it or throw an exception
                error_log("Failed to copy {$sourceFile} to {$destinationFile}");
            }
        }
    }
}
