<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CancelReservationAction;
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
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
            update_post_meta($order_id, '_wc_order_selected_payment_method', 'Klarna (MoR)');
            update_post_meta($order_id, '_wc_order_payment_issuer', $this->type);
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
        $dataRequestKey = get_post_meta($order_id, KlarnaProcessor::DATA_REQUEST_META_KEY, true);

        if (! is_string($dataRequestKey) || strlen($dataRequestKey) === 0) {
            return $this->create_capture_error(__('Cannot perform capture, Klarna Data Request key not found', 'wc-buckaroo-bpe-gateway'));
        }

        return parent::process_capture($order_id);
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

        $dataRequestKey = get_post_meta($order->get_id(), KlarnaProcessor::DATA_REQUEST_META_KEY, true);

        if (! is_string($dataRequestKey) || strlen($dataRequestKey) === 0) {
            return $this->create_capture_error(__('Cannot cancel reservation, Klarna Data Request key not found', 'wc-buckaroo-bpe-gateway'));
        }

        (new CancelReservationAction())->handle(
            $payment->method($this->getServiceCode($processor))->cancelReserve(
                array_merge(
                    $processor->getBody(),
                    ['originalTransactionKey' => $dataRequestKey]
                )
            ),
            $order
        );
    }
}
