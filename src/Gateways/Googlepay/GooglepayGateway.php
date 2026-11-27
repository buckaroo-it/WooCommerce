<?php

namespace Buckaroo\Woocommerce\Gateways\Googlepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Gateways\Express\ExpressAvailability;
use Buckaroo\Woocommerce\Gateways\Express\ExpressCheckoutException;
use Buckaroo\Woocommerce\Gateways\Express\ExpressCheckoutRules;
use Buckaroo\Woocommerce\Gateways\Express\ExpressSettings;
use Buckaroo\Woocommerce\Gateways\ExpressProductCart;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use Throwable;
use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;

class GooglepayGateway extends AbstractPaymentGateway
{
    use ExpressAvailability;
    use ExpressSettings;

    public const CREATE_TRANSACTION_NONCE = 'buckaroo_googlepay_create_transaction';

    public const PAYMENT_CLASS = GooglepayProcessor::class;

    protected const REQUIRED_CREDENTIALS = ['merchant_guid' => 'always', 'google_merchant_id' => 'live'];

    protected $paymentData;

    protected $CustomerCardName;

    public function __construct()
    {
        $this->id = 'buckaroo_googlepay';
        $this->title = 'Google Pay';
        $this->method_description = __("Google's wallet, using the cards saved to a shopper's Google Account.", 'wc-buckaroo-bpe-gateway');
        $this->has_fields = true;
        $this->method_title = 'Buckaroo Google Pay';
        $this->CustomerCardName = '';
        $this->setIcon('svg/googlepay.svg');

        parent::__construct();
        $this->addRefundSupport();
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $this->registerControllers();
        }
    }

    private function registerControllers()
    {
        $namespace = 'woocommerce_api_wc_gateway_buckaroo_googlepay';

        add_action("{$namespace}-get-items-from-detail-page", [GooglepayController::class, 'getItemsFromDetailPage']);
        add_action("{$namespace}-get-items-from-cart", [GooglepayController::class, 'getItemsFromCart']);
        add_action("{$namespace}-get-shipping-methods", [GooglepayController::class, 'getShippingMethods']);
        add_action("{$namespace}-get-shop-information", [GooglepayController::class, 'getShopInformation']);
        add_action("{$namespace}-get-cart-total", [GooglepayController::class, 'getCartTotal']);
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
        $requestError = $this->expressRequestError();
        if ($requestError !== null) {
            $this->error_response($requestError);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- expressRequestError() verified the nonce.
        Logger::log(__METHOD__ . '|1|', ['request_fields' => array_keys($_POST)]);

        $this->paymentData = $this->request->input('paymentData');

        // No order is created for a payload without a wallet token (BTI-1874).
        if (! is_array($this->paymentData) || empty(GooglepayProcessor::walletToken($this->paymentData))) {
            $this->error_response('Google Pay data is invalid.');
        }

        if (
            ! isset($this->paymentData['billingContact']) ||
            ! isset($this->paymentData['shippingContact']) ||
            ! is_array($this->paymentData['billingContact']) ||
            ! is_array($this->paymentData['shippingContact'])
        ) {
            $this->error_response('Google Pay data is invalid.');
        }

        $items = $this->request->input('items');
        if ($items === null || ! is_array($items)) {
            $this->error_response('Google Pay data is invalid.');
        }

        $shipping_method = $this->request->input('selected_shipping_method');
        if ($shipping_method === null || ! is_scalar($shipping_method)) {
            $this->error_response('Invalid shipping method.');
        }
        $amount = $this->request->input('amount');
        if ($amount === null || ! is_scalar($amount)) {
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

        $walletLocation = self::walletLocation($this->paymentData['billingContact'], $this->paymentData['shippingContact']);
        if (! $this->isExpressCheckoutAllowed($items, $walletLocation)) {
            $this->error_response($this->expressUnavailableMessage());
        }

        $this->CustomerCardName = $this->resolveCustomerName(
            $this->paymentData['billingContact'],
            $this->paymentData['shippingContact']
        );

        $result = null;
        try {
            $orderResult = $this->createOrder(
                $this->paymentData['billingContact'],
                $this->paymentData['shippingContact'],
                $items,
                $shipping_method
            );

            if ($orderResult) {
                $result = $this->process_payment($orderResult['data']['id']);
            }
        } catch (ExpressCheckoutException $th) {
            $this->failExpressOrder($orderResult ?? null, $th->getMessage());
            $this->error_response($th->getMessage());
        } catch (Throwable $th) {
            // The exception text can hold internal details (BTI-1874).
            Logger::log(__METHOD__ . '|fail|', $th->getMessage());
            $this->failExpressOrder(
                $orderResult ?? null,
                __('The express payment could not be started.', 'wc-buckaroo-bpe-gateway')
            );
            $this->error_response($this->expressFailureMessage());
        }

        if ($result === null) {
            $this->error_response($this->expressFailureMessage());
        }

        if (! is_array($result) || ($result['result'] ?? '') !== 'success') {
            // Refused or never started: the order gives its stock and coupons back (BTI-1874).
            $this->failExpressOrder(
                $orderResult,
                __('The express payment could not be started.', 'wc-buckaroo-bpe-gateway')
            );
        }

        Logger::log(__METHOD__ . '|1|', $result);
        $this->success_response($result);
    }

    public function success_response($result)
    {
        wp_send_json($result);
    }

    public function error_response($errorMessage)
    {
        wp_send_json(
            [
                'status' => 'fail',
                'message' => ExpressCheckoutException::plainText((string) $errorMessage),
            ]
        );
    }

    private function resolveCustomerName(array $billingContact, array $shippingContact): string
    {
        foreach ([$billingContact, $shippingContact] as $contact) {
            $name = trim(
                sanitize_text_field($contact['givenName'] ?? '') . ' ' .
                sanitize_text_field($contact['familyName'] ?? '')
            );
            if ($name !== '') {
                return $name;
            }
        }

        return '';
    }

    public function createOrder($billing_addresses, $shipping_addresses, $items, $selected_method_id)
    {
        Logger::log(__METHOD__ . '|1|');

        $billingEmail = self::walletEmail($billing_addresses, $shipping_addresses);
        $billingPhone = self::walletPhone($billing_addresses, $shipping_addresses);
        $checkoutData = ExpressCheckoutRules::checkoutData(
            array_merge(self::walletAddress($billing_addresses), ['email' => $billingEmail, 'phone' => $billingPhone]),
            self::walletAddress($shipping_addresses),
            $this->id
        );
        $order = null;

        try {
            $shipping_rate = ExpressProductCart::calculateItems(
                $items,
                'buckaroo_googlepay',
                function ($cart) use (&$order, $selected_method_id, $billingEmail, $checkoutData) {
                    ExpressCheckoutRules::check($cart, $checkoutData);
                    $shipping_rate = ExpressProductCart::selectedShippingRate($cart, $selected_method_id);

                    // Created once the rules pass, so a rejected attempt fires no order hooks (BTI-1874).
                    $order = $this->newExpressOrder();
                    self::createOrderFromCart($order, $cart);
                    ExpressCheckoutRules::holdCoupons($order, $billingEmail);

                    return $shipping_rate;
                },
                self::walletLocation($billing_addresses, $shipping_addresses),
                true
            );

            $order->set_address(self::walletAddress($billing_addresses), 'billing');
            $order->set_address(self::walletAddress($shipping_addresses), 'shipping');

            if ($billingEmail) {
                $order->set_billing_email($billingEmail);
            }

            if ($billingPhone) {
                $order->set_billing_phone($billingPhone);
            }

            if ($shipping_rate) {
                ExpressProductCart::addShippingLine($order, $shipping_rate);
            }

            $order->set_payment_method($this);
            $this->setOrderContribution($order);

            $order->calculate_totals();

            if (! $this->isAllowedForOrder($order)) {
                throw new ExpressCheckoutException(esc_html($this->expressUnavailableMessage()));
            }

            $this->reserveExpressStock($order);

            $order->update_status('pending payment', 'Order created using Google Pay', true);
            wc_update_coupon_usage_counts($order->get_id());
        } catch (Throwable $e) {
            Logger::log(__METHOD__ . '|fail|', $e->getMessage());
            if ($order instanceof WC_Order) {
                $this->discardExpressOrder($order);
            }

            if ($e instanceof ExpressCheckoutException) {
                throw $e;
            }

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

    /**
     * Create order from cart using WooCommerce native methods
     */
    private static function createOrderFromCart($order, $cart)
    {
        $checkout = WC()->checkout();
        if (is_callable([$checkout, 'create_order_line_items'])) {
            $checkout->create_order_line_items($order, $cart);
        } else {
            self::createOrderLineItems($order, $cart);
        }

        $checkout->create_order_coupon_lines($order, $cart);

        foreach ($cart->get_fees() as $fee_key => $fee) {
            $item_fee = new WC_Order_Item_Fee();
            $item_fee->set_props([
                'name' => $fee->name,
                'tax_class' => $fee->tax_class,
                'tax_status' => $fee->taxable ? 'taxable' : 'none',
                'amount' => $fee->amount,
                'total' => $fee->total,
                'total_tax' => $fee->tax,
            ]);
            if ($fee->id === sanitize_title(__('Payment fee', 'wc-buckaroo-bpe-gateway'))) {
                $item_fee->add_meta_data(AbstractPaymentProcessor::FEE_GATEWAY_META, 'buckaroo_googlepay', true);
            }
            $order->add_item($item_fee);
        }
    }

    private static function createOrderLineItems($order, $cart)
    {
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];

            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_props([
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $cart_item['line_subtotal'],
                'total' => $cart_item['line_total'],
                'subtotal_tax' => $cart_item['line_subtotal_tax'],
                'total_tax' => $cart_item['line_tax'],
                'variation' => $cart_item['variation'],
            ]);
            do_action('woocommerce_checkout_create_order_line_item', $item, $cart_item_key, $cart_item, $order);
            $order->add_item($item);
        }
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

        $this->applyExpressSettings();
    }

    protected function expressSettingsSpec(): array
    {
        return [
            'credentials' => [
                'merchant_guid' => [
                    'title' => __('Gateway Merchant ID', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'text',
                    'description' => __('Your Buckaroo Gateway merchant ID, found in Buckaroo Plaza → Services → <a href="https://plaza.buckaroo.nl/Configuration/GooglePay" target="_blank" rel="noopener">Google Pay</a>.', 'wc-buckaroo-bpe-gateway'),
                    'default' => '0',
                ],
                'google_merchant_id' => [
                    'title' => __('Google Merchant ID', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'text',
                    'description' => __('Your Google Merchant ID, found in the <a href="https://pay.google.com/business/console" target="_blank" rel="noopener">Google Pay & Wallet Console</a> (e.g. BCR2DN4T...). Required for live payments.', 'wc-buckaroo-bpe-gateway'),
                    'default' => '',
                ],
            ],
            'placements' => ['product', 'cart', 'checkout'],
            'list_as_payment_method' => true,
            'graphical' => [
                'button_style' => [
                    'title' => __('Button style', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'select',
                    'description' => __('Colour of the express button as the customer sees it.', 'wc-buckaroo-bpe-gateway'),
                    'options' => [
                        'black' => __('Black', 'wc-buckaroo-bpe-gateway'),
                        'white' => __('White', 'wc-buckaroo-bpe-gateway'),
                    ],
                    'default' => 'black',
                ],
                'button_type' => [
                    'title' => __('Button type', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'select',
                    'description' => __('Wording Google shows on the button, next to the Google Pay mark.', 'wc-buckaroo-bpe-gateway'),
                    'options' => [
                        'plain' => __('Plain', 'wc-buckaroo-bpe-gateway'),
                        'pay' => __('Pay with', 'wc-buckaroo-bpe-gateway'),
                        'buy' => __('Buy with', 'wc-buckaroo-bpe-gateway'),
                        'checkout' => __('Checkout with', 'wc-buckaroo-bpe-gateway'),
                        'order' => __('Order with', 'wc-buckaroo-bpe-gateway'),
                        'book' => __('Book with', 'wc-buckaroo-bpe-gateway'),
                        'subscribe' => __('Subscribe with', 'wc-buckaroo-bpe-gateway'),
                    ],
                    // Not Plain: existing shops keep the button they already show.
                    'default' => 'pay',
                ],
                'button_preview' => [
                    'title' => __('Button preview', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'express_button_preview',
                    'description' => __('Updates as you change the settings above.', 'wc-buckaroo-bpe-gateway'),
                ],
            ],
        ];
    }

    protected function expressPreviewConfig(): array
    {
        return [
            'method' => 'googlepay',
            'fields' => ['color' => 'button_style', 'type' => 'button_type'],
        ];
    }

    /**
     * Whether Google Pay should be listed as a standard, selectable checkout
     * payment method (in addition to the Express Checkout button).
     */
    public function isCheckoutMethodEnabled(): bool
    {
        return $this->get_option('checkout_method', 'TRUE') === 'TRUE';
    }

    public function handleHooks()
    {
        $googlepayButtons = new GooglepayButtons();
        $googlepayButtons->loadActions();
    }
}
