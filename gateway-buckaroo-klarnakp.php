<?php



class WC_Gateway_Buckaroo_KlarnaKp extends WC_Gateway_Buckaroo
{

    public function __construct()
    {
        $this->id           = 'buckaroo_klarnakp';
        $this->title        = 'Klarna: Pay later';
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
        $order_id = $this->request('order_id');
        
        if ($order_id === null || !is_numeric($order_id)) {
            return $this->create_capture_error(__('A valid order number is required'));
        }

        $capture_amount = $this->request('capture_amount');
        if($capture_amount === null || !is_scalar($capture_amount)) {
            return $this->create_capture_error(__('A valid capture amount is required'));
        }


        $order = getWCOrder($order_id);
        /** @var BuckarooKlarnaKp */
        // $klarna = $this->createDebitRequest($order);
        $klarna->amountDedit = str_replace(wc_get_price_decimal_separator(), '.', $capture_amount);
        $reservation_number = get_post_meta(
            $order_id, 
            '_buckaroo_klarnakp_reservation_number',
            true
        );

        if (!is_string($reservation_number) || strlen($reservation_number) === 0) {
            return $this->create_capture_error(__('Cannot perform capture, reservation_number not found'));
        }

        return fn_buckaroo_process_capture(
           $klarna->capture(
                new Buckaroo_Order_Capture(
                    new Buckaroo_Order_Details($order),
                    new Buckaroo_Http_Request()
                ),
                $reservation_number
            ),
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
}
