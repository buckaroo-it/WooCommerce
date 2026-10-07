<?php

namespace Buckaroo\Woocommerce\Gateways\Paypal;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\Express\ExpressSettings;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressOrder;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressShipping;
use WC_Order;

class PaypalGateway extends AbstractPaymentGateway
{
    use ExpressSettings;

    /**
     * Funding sources the storefront suppresses, mirrored so the preview
     * draws the same buttons the customer gets.
     *
     * These duplicate the list BuckarooSdk.PayPal.initiate() builds into its
     * own script URL, which the plugin cannot read. Test_PaypalFunding
     * (@group external-http) compares the two against the live SDK.
     */
    public const PREVIEW_DISABLE_FUNDING = 'credit,card,bancontact,blik,eps,giropay,ideal,mercadopago,mybank,p24,sepa,sofort,venmo';

    public const PREVIEW_ENABLE_FUNDING = 'paylater';

    public const PAYMENT_CLASS = PaypalProcessor::class;

    protected const REQUIRED_CREDENTIALS = ['express_merchant_id' => 'live'];

    public $sellerprotection;

    protected $express_order_id = null;

    protected array $supportedCurrencies = [
        'AUD',
        'BRL',
        'CAD',
        'CHF',
        'DKK',
        'EUR',
        'GBP',
        'HKD',
        'HUF',
        'ILS',
        'JPY',
        'MYR',
        'NOK',
        'NZD',
        'PHP',
        'PLN',
        'SEK',
        'SGD',
        'THB',
        'TRL',
        'TWD',
        'USD',
    ];

    public function __construct()
    {
        $this->id = 'buckaroo_paypal';
        $this->title = 'PayPal';
        $this->method_description = __('Global digital wallet with card and balance payments, plus Buyer Protection.', 'wc-buckaroo-bpe-gateway');
        $this->has_fields = false;
        $this->method_title = 'Buckaroo PayPal';
        $this->setIcon('svg/paypal.svg');

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * Process payment
     *
     * @param  int  $order_id
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        $this->setOrderContribution(new WC_Order($order_id));

        return parent::process_payment($order_id);
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

        $this->applyExpressSettings();
    }

    /**
     * Fields this method supports. Anything absent is not rendered.
     *
     * PayPal is offered as a regular payment method too, so it keeps Title and
     * Description and has no separate "list as payment method" switch: that is
     * governed by Enable/Disable.
     */
    protected function expressSettingsSpec(): array
    {
        return [
            'credentials' => [
                'express_merchant_id' => [
                    'title' => __('Merchant ID', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'text',
                    'description' => __('Found in PayPal → <a href="https://www.paypal.com/businessmanage/account/aboutBusiness" target="_blank" rel="noopener">Business information</a>. Required for live payments.', 'wc-buckaroo-bpe-gateway'),
                ],
            ],
            'secondary_credentials' => [
                'sandbox_credentials_title' => [
                    'title' => __('Sandbox credentials', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'title',
                    'description' => __('Used only when Transaction mode is set to Test. The PayPal sandbox client ids are managed by the Buckaroo plugin. The sandbox merchant ID is optional.', 'wc-buckaroo-bpe-gateway'),
                ],
                'express_sandbox_merchant_id' => [
                    'title' => __('Sandbox merchant ID', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'text',
                    'description' => __('Used in Test mode. Found in PayPal sandbox → <a href="https://www.sandbox.paypal.com/businessmanage/account/aboutBusiness" target="_blank" rel="noopener">Business information</a>.', 'wc-buckaroo-bpe-gateway'),
                ],
            ],
            'placements' => ['product', 'cart', 'checkout'],
            'list_as_payment_method' => false,
            'graphical' => [
                'button_style' => [
                    'title' => __('Button style', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'select',
                    'description' => __('Colour of the express button as the customer sees it.', 'wc-buckaroo-bpe-gateway'),
                    'options' => [
                        'gold' => __('Gold (standard)', 'wc-buckaroo-bpe-gateway'),
                        'blue' => __('Blue', 'wc-buckaroo-bpe-gateway'),
                        'silver' => __('Silver', 'wc-buckaroo-bpe-gateway'),
                        'white' => __('White', 'wc-buckaroo-bpe-gateway'),
                        'black' => __('Black', 'wc-buckaroo-bpe-gateway'),
                    ],
                    // Gold is PayPal's own default, which is what renders today.
                    'default' => 'gold',
                ],
                'button_type' => [
                    'title' => __('Button type', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'select',
                    'description' => __('Wording PayPal shows on the button, next to the PayPal mark.', 'wc-buckaroo-bpe-gateway'),
                    'options' => [
                        'paypal' => __('Plain', 'wc-buckaroo-bpe-gateway'),
                        'checkout' => __('Checkout', 'wc-buckaroo-bpe-gateway'),
                        'buynow' => __('Buy now', 'wc-buckaroo-bpe-gateway'),
                        'pay' => __('Pay with', 'wc-buckaroo-bpe-gateway'),
                    ],
                    // The mark on its own is PayPal's own default, which is what
                    // renders today. PayPal localises the wording itself.
                    'default' => 'paypal',
                ],
                'button_rounded' => [
                    'title' => __('Rounded button shape', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'select',
                    'description' => __('Show the express button with fully rounded corners.', 'wc-buckaroo-bpe-gateway'),
                    'options' => [
                        'TRUE' => __('Yes', 'wc-buckaroo-bpe-gateway'),
                        'FALSE' => __('No', 'wc-buckaroo-bpe-gateway'),
                    ],
                    // rect is PayPal's own default.
                    'default' => 'FALSE',
                ],
                'button_preview' => [
                    'title' => __('Button preview', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'express_button_preview',
                    'description' => __('Updates as you change the settings above.', 'wc-buckaroo-bpe-gateway'),
                ],
            ],
            'behaviour' => [
                'sellerprotection' => [
                    'title' => __('Seller protection', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'select',
                    'description' => __('Sends the customer address to PayPal, needed for seller protection.', 'wc-buckaroo-bpe-gateway'),
                    'options' => [
                        'TRUE' => __('Yes', 'wc-buckaroo-bpe-gateway'),
                        'FALSE' => __('No', 'wc-buckaroo-bpe-gateway'),
                    ],
                    'default' => 'TRUE',
                ],
            ],
        ];
    }

    /**
     * PayPal keeps its placements in the "express" array rather than the
     * button_{location} keys the other express methods use.
     *
     * @param  array  $stored
     * @return array<string>
     */
    protected function readExpressPlacements(array $stored): array
    {
        $express = $stored['express'] ?? [];

        if (! is_array($express)) {
            return [];
        }

        return array_values(
            array_intersect(static::$expressLocations, $express)
        );
    }

    /**
     * An empty selection is stored as the LOCATION_NONE sentinel, because
     * PaypalExpressController::is_active() treats exactly that value as "off"
     * and would otherwise read an empty array as active.
     *
     * @param  array  $settings
     * @param  array<string>  $selected
     * @return array
     */
    protected function writeExpressPlacements(array $settings, array $selected): array
    {
        $selected = array_values(
            array_intersect(static::$expressLocations, $selected)
        );

        $settings['express'] = $selected === []
            ? [PaypalExpressController::LOCATION_NONE]
            : $selected;

        return $settings;
    }

    protected function expressPreviewConfig(): array
    {
        return [
            'method' => 'paypal',
            'fields' => ['color' => 'button_style', 'shape' => 'button_rounded', 'type' => 'button_type'],
            // Buckaroo's standard PayPal client id, as used by the Buckaroo SDK on the
            // storefront. The SDK will not load without one; it only draws the preview.
            'clientId' => 'ATv1oKfBmc76Zzl8rAMai_OwpXIp9CsDTMzEceayY7X2Sy8t6bQT2rm7DIC7LYbfkch9m9S3R3amkeyU',
            'sdkParams' => [
                'currency' => get_woocommerce_currency(),
                'disable-funding' => self::PREVIEW_DISABLE_FUNDING,
                'enable-funding' => self::PREVIEW_ENABLE_FUNDING,
            ],
        ];
    }

    public function get_express_order_id()
    {
        return $this->express_order_id;
    }

    /**
     * Set paypal express id
     *
     * @param  string  $express_order_id
     * @return void
     */
    public function set_express_order_id($express_order_id)
    {
        $this->express_order_id = $express_order_id;
    }

    /**
     * Init class fields from settings
     *
     * @return void
     */
    protected function setProperties()
    {
        parent::setProperties();
        $this->sellerprotection = $this->get_option('sellerprotection', 'TRUE');
    }

    public function handleHooks()
    {
        new PaypalExpressController(
            new PaypalExpressShipping(),
            new PaypalExpressOrder()
        );
    }
}
