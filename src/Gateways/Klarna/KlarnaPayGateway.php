<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureResult;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use WC_Order;

class KlarnaPayGateway extends KlarnaGateway
{
    public bool $capturable = true;

    public function __construct()
    {
        $this->id = 'buckaroo_klarnapay';
        $this->title = 'Klarna';
        $this->method_title = 'Klarna (MoR)';

        parent::__construct();
    }

    public function getServiceCode(?AbstractProcessor $processor = null)
    {
        return 'klarna';
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['automatic_capture'] = [
            'title' => __('Automatic capture', 'wc-buckaroo-bpe-gateway'),
            'label' => __('Automatic capture when order is completed', 'wc-buckaroo-bpe-gateway'),
            'type' => 'checkbox',
            'default' => 'no',
        ];
    }

    /**
     * Payment form on checkout page
     *
     * @return void
     */
    public function payment_fields()
    {
        $this->renderTemplate();
    }

    public function handleHooks()
    {
        new KlarnaFulfillmentActions();
    }

    public function canShowCaptureForm($order): bool
    {
        $order = Helper::resolveOrder($order);

        if (! $order instanceof WC_Order) {
            return false;
        }

        return $order->get_meta('buckaroo_is_reserved') === 'yes';
    }

    /**
     * Process payment
     *
     * @param  int  $order_id
     * @return array|void
     */
    public function process_payment($order_id)
    {
        $processedPayment = parent::process_payment($order_id);

        if (isset($processedPayment['result']) && $processedPayment['result'] === 'success') {
            OrderMeta::update($order_id, '_wc_order_authorized', 'yes');
            // Must match the registry key ('klarnapay') so the capture AJAX
            // handler can resolve the gateway via PaymentGatewayRegistry::newGatewayInstance().
            $this->set_order_capture($order_id, 'KlarnaPay', $this->type);
        }

        return $processedPayment;
    }

    /**
     * Process capture (Pay action for fulfillment)
     *
     * @param  int  $order_id
     * @return array|false
     */
    public function process_capture($order_id)
    {
        $dataRequestKey = OrderMeta::get($order_id, KlarnaProcessor::DATA_REQUEST_META_KEY);

        if (! is_string($dataRequestKey) || strlen($dataRequestKey) === 0) {
            return $this->create_capture_error(__('Cannot perform capture, Klarna Data Request key not found', 'wc-buckaroo-bpe-gateway'));
        }

        return parent::process_capture($order_id);
    }

    public function capture(
        WC_Order $order,
        $amount,
        CaptureAllocation $allocation,
        ?BuckarooClient $buckarooClient = null,
        ?int $attemptNumber = null
    ): CaptureResult {
        return $this->executeCapture($order, $amount, $allocation, $buckarooClient, $attemptNumber);
    }

    protected function executeCapture(
        WC_Order $order,
        $amount,
        CaptureAllocation $allocation,
        ?BuckarooClient $buckarooClient = null,
        ?int $attemptNumber = null
    ): CaptureResult {
        return (new KlarnaCaptureAction(
            $this->newPaymentProcessorInstance($order),
            $order,
            $amount,
            $allocation,
            $buckarooClient,
            $attemptNumber
        ))->process();
    }

    /**
     * Cancel the Klarna reservation for an order
     *
     * @param  WC_Order  $order
     * @return void
     */
    public function cancel_reservation(WC_Order $order)
    {
        $processor = $this->newPaymentProcessorInstance($order);
        $payment = new BuckarooClient($this->getMode());

        $dataRequestKey = OrderMeta::get($order, KlarnaProcessor::DATA_REQUEST_META_KEY);

        if (! is_string($dataRequestKey) || strlen($dataRequestKey) === 0) {
            return $this->create_capture_error(__('Cannot cancel reservation, Klarna Data Request key not found', 'wc-buckaroo-bpe-gateway'));
        }

        (new CancelReservationAction())->handle(
            $payment->method($this->getServiceCode($processor))->cancelReserve(
                array_merge(
                    $processor->getBody(),
                    // Klarna's `klarna` service references the original reserve via
                    // a service-level `DataRequestKey` parameter (the SDK's `Pay`
                    // model exposes it as `dataRequestKey`).
                    ['dataRequestKey' => $dataRequestKey]
                )
            ),
            $order
        );
    }

    /**
     * Extend the Klarna reservation for an order
     *
     * @param  WC_Order  $order
     * @return void
     */
    public function extend_reservation(WC_Order $order)
    {
        $processor = $this->newPaymentProcessorInstance($order);
        $payment = new BuckarooClient($this->getMode());

        $dataRequestKey = OrderMeta::get($order, KlarnaProcessor::DATA_REQUEST_META_KEY);

        if (! is_string($dataRequestKey) || strlen($dataRequestKey) === 0) {
            return $this->create_capture_error(__('Cannot extend reservation, Klarna Data Request key not found', 'wc-buckaroo-bpe-gateway'));
        }

        (new ExtendReservationAction())->handle(
            $payment->method($this->getServiceCode($processor))->extendReservation(
                array_merge(
                    $processor->getBody(),
                    // Klarna's `klarna` service references the original reserve via
                    // a service-level `DataRequestKey` parameter (the SDK's `Pay`
                    // model exposes it as `dataRequestKey`).
                    ['dataRequestKey' => $dataRequestKey]
                )
            ),
            $order
        );
    }
}
