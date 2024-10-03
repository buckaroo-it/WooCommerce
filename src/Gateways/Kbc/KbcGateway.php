<?php

namespace Buckaroo\Woocommerce\Gateways\Kbc;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use BuckarooKBC;

class KbcGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = KbcProcessor::class;

    public function __construct()
    {
        $this->id = 'buckaroo_kbc';
        $this->title = 'KBC';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo KBC';
        $this->setIcon('24x24/kbc.png', 'svg/kbc.svg');

        parent::__construct();
        $this->addRefundSupport();
        apply_filters('buckaroo_init_payment_class', $this);
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
        /** @var BuckarooKBC */
        $kbc = $this->createDebitRequest($order);

        $response = $this->apply_filters_or_error('buckaroo_before_payment_request', $order, $kbc);
        if ($response) {
            return $response;
        }

        $response = $kbc->Pay();
        return fn_buckaroo_process_response($this, $response);
    }

}