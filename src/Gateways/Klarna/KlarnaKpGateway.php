<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CancelReservationAction;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureAction;
use Buckaroo\Woocommerce\SDK\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use WC_Order;

class KlarnaKpGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = KlarnaKpProcessor::class;
    public $type;

    public function __construct()
    {
        $this->id = 'buckaroo_klarnakp';
        $this->title = 'Klarna: Pay later';
        $this->method_title = 'Buckaroo Klarna Pay later (authorize/capture)';
        $this->has_fields = true;
        $this->type = 'klarnakp';
        $this->setIcon('24x24/klarna.svg', 'svg/klarna.svg');
        $this->setCountry();
        parent::__construct();
        $this->addRefundSupport();
    }

    public function cancel_reservation(WC_Order $order)
    {
        $processor = $this->newPaymentProcessorInstance($order);
        $payment = new BuckarooClient($this->getMode());

        $reservation_number = get_post_meta(
            $order->get_id(),
            '_buckaroo_klarnakp_reservation_number',
            true
        );

        if (!is_string($reservation_number) || strlen($reservation_number) === 0) {
            return $this->create_capture_error(__('Cannot perform capture, reservation_number not found'));
        }

        (new CancelReservationAction())->handle(
            $payment->method($this->getServiceCode())->cancelReserve([
                ...$processor->getBody(),
                'reservationNumber' => $reservation_number,
            ]),
            $order
        );

        // todo flash success/failed message
    }


    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        update_post_meta($order_id, '_wc_order_authorized', 'yes');
        $this->setOrderCapture($order_id, 'KlarnaKp');

        return parent::process_payment($order_id);
    }

    /**
     * Send capture request
     *
     */
    public function process_capture()
    {
        $order_id = $this->request->input('order_id');

        if ($order_id === null || !is_numeric($order_id)) {
            return $this->create_capture_error(__('A valid order number is required'));
        }

        $capture_amount = $this->request->input('capture_amount');
        if ($capture_amount === null || !is_scalar($capture_amount)) {
            return $this->create_capture_error(__('A valid capture amount is required'));
        }
        $reservation_number = get_post_meta(
            $order_id,
            '_buckaroo_klarnakp_reservation_number',
            true
        );

        if (!is_string($reservation_number) || strlen($reservation_number) === 0) {
            return $this->create_capture_error(__('Cannot perform capture, reservation_number not found'));
        }

        $order = Helper::findOrder($order_id);
        $processor = $this->newPaymentProcessorInstance($order);/** @var KlarnaKpProcessor $payment */;
        $payment = new BuckarooClient($this->getMode());
        $res = $payment->process($processor, additionalData: ['amountDebit' => $capture_amount]);

        return (new CaptureAction())->handle(
            $res,
            $order,
            $this->currency,
        );
    }

    /** @inheritDoc */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->add_financial_warning_field();
    }

    public function handleHooks()
    {
        new KlarnaCancelReservation();
        new KlarnaRefund();
        new KlarnaCapture();
    }
}