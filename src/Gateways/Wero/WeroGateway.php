<?php

namespace Buckaroo\Woocommerce\Gateways\Wero;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Services\Helper;
use WC_Order;

class WeroGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = WeroProcessor::class;

    public const REFUND_CLASS = WeroRefundProcessor::class;

    /**
     * Indicates that this gateway supports capture.
     */
    public bool $capturable = true;

    /**
     * Selected Wero flow: pay or authorize.
     *
     * @var string
     */
    protected string $weropayauthorize;

    /**
     * Wero supports EUR only (default already contains EUR).
     *
     * @var array<string>
     */
    protected array $supportedCurrencies = ['EUR'];

    public function __construct()
    {
        $this->id = 'buckaroo_wero';
        $this->title = 'Wero';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Wero';
        $this->setIcon('svg/wero.svg');

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * Process payment from checkout.
     *
     * @param  int  $order_id
     * @return array|void
     */
    public function process_payment($order_id)
    {
        $processedPayment = parent::process_payment($order_id);

        if (
            isset($processedPayment['result']) &&
            $processedPayment['result'] === 'success' &&
            $this->weropayauthorize === 'authorize'
        ) {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
            $this->set_order_capture($order_id, 'Wero');
        }

        return $processedPayment;
    }

    /** {@inheritDoc} */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['weropayauthorize'] = [
            'title' => __('Wero Pay or Authorize', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Choose to execute Pay or Authorize/Capture flow for Wero.', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'pay' => 'Pay',
                'authorize' => 'Authorize',
            ],
            'default' => 'pay',
        ];
    }

    /** {@inheritDoc} */
    protected function setProperties()
    {
        parent::setProperties();

        $this->weropayauthorize = $this->get_option('weropayauthorize', 'pay');
    }

    public function canShowCaptureForm($order): bool
    {
        $order = Helper::resolveOrder($order);

        if (! $order instanceof WC_Order) {
            return false;
        }

        return $this->weropayauthorize === 'authorize' &&
            get_post_meta($order->get_id(), '_wc_order_authorized', true) === 'yes';
    }
}
