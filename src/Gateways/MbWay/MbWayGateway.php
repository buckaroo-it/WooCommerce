<?php

namespace Buckaroo\Woocommerce\Gateways\MbWay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class MbWayGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = MbWayProcessor::class;

    public function __construct()
    {
        $this->id = 'buckaroo_mbway';
        $this->title = 'MBWay';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo MBWay';
        $this->setIcon('svg/mbway.svg', 'svg/mbway.svg');

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * Can the order be refunded
     *
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return $this->processDefaultRefund($order_id, $amount, $reason);
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        $order = getWCOrder($order_id);
        /** @var MbWayProcessor */
        $request = $this->createDebitRequest($order);
        return fn_buckaroo_process_response($this, $request->Pay());
    }
}