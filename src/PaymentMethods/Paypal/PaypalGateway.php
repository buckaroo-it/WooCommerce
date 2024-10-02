<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\Paypal;

use Buckaroo_Paypal_Express;
use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentGatewayHandler;
use WC_Order;

class PaypalGateway extends PaymentGatewayHandler
{
    public $sellerprotection;

    protected $express_order_id = null;

    public function __construct()
    {
        $this->id = 'buckaroo_paypal';
        $this->title = 'PayPal';
        $this->has_fields = false;
        $this->method_title = "Buckaroo PayPal";
        $this->set_icon('24x24/paypal.gif', 'svg/paypal.svg');

        parent::__construct();
        $this->add_refund_support();
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        $this->set_order_contribution(new WC_Order($order_id));
        return parent::process_payment($order_id);
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

    private function set_order_contribution(WC_Order $order)
    {
        $prefix = (string)apply_filters(
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
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['sellerprotection'] = array(
            'title' => __('Seller Protection', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Sends customer address information to PayPal to enable PayPal seller protection.', 'wc-buckaroo-bpe-gateway'),
            'options' => array('TRUE' => __('Enabled', 'wc-buckaroo-bpe-gateway'), 'FALSE' => __('Disabled', 'wc-buckaroo-bpe-gateway')),
            'default' => 'TRUE'
        );
        $this->form_fields['express_merchant_id'] = array(
            'title' => __('PayPal express merchant id', 'wc-buckaroo-bpe-gateway'),
            'type' => 'text',
            'description' => __('PayPal merchant id required for paypal express', 'wc-buckaroo-bpe-gateway'),
        );
        $this->form_fields['express'] = array(
            'title' => __('PayPal express', 'wc-buckaroo-bpe-gateway'),
            'type' => 'multiselect',
            'description' => __('Enable PayPal express for the following pages.', 'wc-buckaroo-bpe-gateway'),
            'options' => array(
                Buckaroo_Paypal_Express::LOCATION_NONE => __('None', 'wc-buckaroo-bpe-gateway'),
                Buckaroo_Paypal_Express::LOCATION_PRODUCT => __('Product page', 'wc-buckaroo-bpe-gateway'),
                Buckaroo_Paypal_Express::LOCATION_CART => __('Cart page', 'wc-buckaroo-bpe-gateway'),
                Buckaroo_Paypal_Express::LOCATION_CHECKOUT => __('Checkout page', 'wc-buckaroo-bpe-gateway'),
            ),
            'default' => 'none'
        );
    }

    /**
     * Set paypal express id
     *
     * @param string $express_order_id
     *
     * @return void
     */
    public function set_express_order_id($express_order_id)
    {
        $this->express_order_id = $express_order_id;
    }


    public function get_express_order_id()
    {
        return $this->express_order_id;
    }
}