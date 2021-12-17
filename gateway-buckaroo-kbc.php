<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/kbc/kbc.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_KBC extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooKBC::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_kbc';
        $this->title                  = 'KBC/CBC';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo KBC/СBC";
        $this->setIcon('24x24/kbc.png', 'new/KBC.png');
        
        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = wc_get_order($order_id);

        $kbc                         = new BuckarooKBC();
        $kbc->amountDedit            = 0;
        $kbc->amountCredit           = $amount;
        $kbc->currency               = $this->currency;
        $kbc->description            = $reason;
        $kbc->invoiceId              = $order->get_order_number();
        $kbc->orderId                = $order_id;
        $kbc->OriginalTransactionKey = $order->get_transaction_id();
        $kbc->returnUrl              = $this->notify_url;
        $payment_type                = str_replace('buckaroo_', '', strtolower($this->id));
        $kbc->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                    = null;

        $orderDataForChecking = $kbc->getOrderRefundData();
        try {
            $kbc->checkRefundData($orderDataForChecking);
            $response = $kbc->Refund();
        } catch (Exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
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
        $response = $kbc->Pay();
        return fn_buckaroo_process_response($this, $response);
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {

    }
}
