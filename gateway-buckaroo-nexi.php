<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/nexi/nexi.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Nexi extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooNexi::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_nexi';
        $this->title                  = 'Nexi';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Nexi";
        $this->setIcon('24x24/nexi.png', 'new/Nexi.png');

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

        $nexi                         = new BuckarooNexi();
        $nexi->amountDedit            = 0;
        $nexi->amountCredit           = $amount;
        $nexi->currency               = $this->currency;
        $nexi->description            = $reason;
        $nexi->invoiceId              = $order->get_order_number();
        $nexi->orderId                = $order_id;
        $nexi->OriginalTransactionKey = $order->get_transaction_id();
        $nexi->returnUrl              = $this->notify_url;
        $clean_order_no               = (int) str_replace('#', '', $order->get_order_number());
        $nexi->setType(get_post_meta($clean_order_no, '_payment_method_transaction', true));
        $payment_type  = str_replace('buckaroo_', '', strtolower($this->id));
        $nexi->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response      = null;

        $orderDataForChecking = $nexi->getOrderRefundData();

        try {
            $nexi->checkRefundData($orderDataForChecking);
            $response = $nexi->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $order = getWCOrder($order_id);
        /** @var BuckarooNexi */
        $nexi = $this->createDebitRequest($order);
        $response = $nexi->Pay();
        return fn_buckaroo_process_response($this, $response);
    }
}
