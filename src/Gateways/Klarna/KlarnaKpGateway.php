<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\PaymentProcessors\Actions\CancelReservationAction;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use Exception;
use WC_Order;
use WP_Error;

class KlarnaKpGateway extends KlarnaGateway
{
    public const PAYMENT_CLASS = KlarnaKpProcessor::class;

    public $type;

    public bool $capturable = true;

    public function __construct()
    {
        $this->id = 'buckaroo_klarnakp';
        $this->title = 'Klarna: Pay later';
        $this->method_title = 'Buckaroo Klarna Pay later (authorize/capture)';
        $this->has_fields = true;
        $this->type = 'klarnakp';
        $this->klarnaPaymentFlowId = 'pay';

        $this->setIcon('svg/klarna.svg');
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

        if (! is_string($reservation_number) || strlen($reservation_number) === 0) {
            return $this->create_capture_error(__('Cannot perform capture, reservation_number not found'));
        }

        (new CancelReservationAction())->handle(
            $payment->method($this->getServiceCode($processor))->cancelReserve(
                [
                    ...$processor->getBody(),
                    'reservationNumber' => $reservation_number,
                ]
            ),
            $order
        );

        // todo flash success/failed message
    }

    /**
     * Process payment
     *
     * @param  int  $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $processedPayment = parent::process_payment($order_id);

        if (isset($processedPayment['result']) && $processedPayment['result'] == 'success') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
            $this->setOrderCapture($order_id, 'KlarnaKp');
        }

        return $processedPayment;
    }

    /**
     * Set order capture
     *
     * @param  int  $order_id  Order id
     * @param  string  $paymentName  Payment name
     * @param  string|null  $paymentType  Payment type
     * @return void
     */
    protected function setOrderCapture($order_id, $paymentName, $paymentType = null)
    {
        update_post_meta($order_id, '_wc_order_selected_payment_method', $paymentName);
        $this->setOrderIssuer($order_id, $paymentType);
    }

    /**
     * Set order issuer
     *
     * @param  int  $order_id  Order id
     * @param  string|null  $paymentType  Payment type
     * @return void
     */
    protected function setOrderIssuer($order_id, $paymentType = null)
    {
        if (is_null($paymentType)) {
            $paymentType = $this->type;
        }
        update_post_meta($order_id, '_wc_order_payment_issuer', $paymentType);
    }

    /**
     * Process capture
     *
     * @param  int  $order_id
     * @return array|array[]|false|WP_Error
     *
     * @throws Exception
     */
    public function process_capture($order_id)
    {
        $reservation_number = get_post_meta(
            $order_id,
            '_buckaroo_klarnakp_reservation_number',
            true
        );

        if (! is_string($reservation_number) || strlen($reservation_number) === 0) {
            return $this->create_capture_error(__('Cannot perform capture, reservation_number not found'));
        }

        return parent::process_capture($order_id);
    }

    /** {@inheritDoc} */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->add_financial_warning_field();
    }

    public function handleHooks()
    {
        new KlarnaCancelReservation();
    }

    public function canShowCaptureForm($order): bool
    {
        $order = Helper::resolveOrder($order);

        if (! $order instanceof WC_Order) {
            return false;
        }

        return $order->get_meta('buckaroo_is_reserved') === 'yes';
    }
}
