<?php

namespace Buckaroo\Woocommerce\Gateways\Paypal;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\Express\ExpressSettings;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressOrder;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressShipping;
use WC_Admin_Settings;
use WC_Order;

class PaypalGateway extends AbstractPaymentGateway
{
    use ExpressSettings;

    public const PAYMENT_CLASS = PaypalProcessor::class;

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
                    'description' => __('Your PayPal merchant ID. Required before live payments are accepted.', 'wc-buckaroo-bpe-gateway'),
                ],
            ],
            'secondary_credentials' => [
                'sandbox_credentials_title' => [
                    'title' => __('Sandbox credentials', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'title',
                    'description' => __('Used only when Transaction mode is set to Test. The PayPal sandbox client ids are managed by the Buckaroo plugin.', 'wc-buckaroo-bpe-gateway'),
                ],
                'express_sandbox_merchant_id' => [
                    'title' => __('Sandbox merchant ID', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'text',
                    'description' => __('Used instead of the live merchant ID while in Test mode.', 'wc-buckaroo-bpe-gateway'),
                ],
            ],
            'placements' => ['product', 'cart', 'checkout'],
            'list_as_payment_method' => false,
            'behaviour' => [
                'sellerprotection' => [
                    'title' => __('Seller protection', 'wc-buckaroo-bpe-gateway'),
                    'type' => 'select',
                    'description' => __('Sends the customer address to PayPal, so eligible orders are covered by PayPal seller protection.', 'wc-buckaroo-bpe-gateway'),
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

    /**
     * Whether the sandbox warning has already been queued this request.
     *
     * WooCommerce runs a gateway's process_admin_options() twice when saving
     * its own section: once from save_settings_for_current_section() and again
     * from woocommerce_update_options_payment_gateways_{id}.
     */
    private static $sandboxWarningAdded = false;

    /**
     * Warn when Test mode is selected without a sandbox merchant id.
     *
     * Nothing calls display_errors() for payment gateways, so the notice goes
     * through WC_Admin_Settings instead of $this->add_error().
     */
    public function process_admin_options()
    {
        parent::process_admin_options();

        if (self::$sandboxWarningAdded) {
            return;
        }

        if ($this->get_option('enabled') !== 'yes' || $this->get_option('mode') !== 'test') {
            return;
        }

        if (trim((string) $this->get_option('express_sandbox_merchant_id')) === '') {
            self::$sandboxWarningAdded = true;
            WC_Admin_Settings::add_error(
                __(
                    'Sandbox merchant ID is required in Test mode. PayPal payments will fail until it is filled in.',
                    'wc-buckaroo-bpe-gateway'
                )
            );
        }
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
