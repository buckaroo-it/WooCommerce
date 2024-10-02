<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\Klarna;

use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentGatewayHandler;
use WC_Order;


class KlarnaKpGateway extends PaymentGatewayHandler
{
    public function __construct()
    {
        $this->id = 'buckaroo_klarnakp';
        $this->title = 'Klarna: Pay later';
        $this->method_title = 'Buckaroo Klarna Pay later (authorize/capture)';
        $this->has_fields = true;
        $this->type = 'klarnakp';
        $this->set_icon('24x24/klarna.svg', 'svg/klarna.svg');
        parent::__construct();
        $this->add_refund_support();
    }

    public function cancel_reservation(WC_Order $order)
    {
        /** @var BuckarooKlarnaKp */
        // $klarna = $this->createDebitRequest($order);

        $reservation_number = get_post_meta(
            $order->get_id(),
            '_buckaroo_klarnakp_reservation_number',
            true
        );

        if (!is_string($reservation_number) || strlen($reservation_number) === 0) {
            return $this->create_capture_error(__('Cannot perform capture, reservation_number not found'));
        }

        return fn_buckaroo_process_reservation_cancel(
            $klarna->cancel_reservation(
                $reservation_number
            ),
            $order
        );

        //todo flash success/failed message
    }


    /**
     * Send capture request
     *
     * @return void
     */
    public function process_capture()
    {
        //todo
    }

    /** @inheritDoc */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->add_financial_warning_field();
    }
}