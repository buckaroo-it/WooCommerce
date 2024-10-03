<?php

namespace Buckaroo\Woocommerce\Gateways\Blik;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class BlikGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = BlikProcessor::class;

    public function __construct()
    {
        $this->id = 'buckaroo_blik';
        $this->title = 'Blik';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Blik';
        $this->setIcon('24x24/blik.png', 'svg/blik.svg');

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
        /** @var BlikProcessor */
        $blik = $this->createDebitRequest($order);

        $response = $blik->Pay();

        return fn_buckaroo_process_response($this, $response);
    }
}