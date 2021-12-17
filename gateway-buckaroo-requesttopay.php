<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/requesttopay/requesttopay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_RequestToPay extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooRequestToPay::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_requesttopay';
        $this->title                  = 'Request To Pay';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Request To Pay";
        $this->setIcon('24x24/requesttopay.png', 'new/RequestToPay.png');

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

        $rtp                         = new BuckarooRequestToPay();
        $rtp->amountDedit            = 0;
        $rtp->amountCredit           = $amount;
        $rtp->currency               = $this->currency;
        $rtp->description            = $reason;
        $rtp->invoiceId              = $order->get_order_number();
        $rtp->orderId                = $order_id;
        $rtp->OriginalTransactionKey = $order->get_transaction_id();
        $rtp->returnUrl              = $this->notify_url;
        $payment_type                = str_replace('buckaroo_', '', strtolower($this->id));
        $rtp->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                    = null;

        $orderDataForChecking = $rtp->getOrderRefundData();

        try {
            $rtp->checkRefundData($orderDataForChecking);
            $response = $rtp->Refund();
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
        /** @var BuckarooRequestToPay */
        $rtp = $this->createDebitRequest($order);

        $customVars = [];
        $get_billing_first_name          = getWCOrderDetails($order_id, 'billing_first_name');
        $get_billing_last_name           = getWCOrderDetails($order_id, 'billing_last_name');
        $customVars['CustomerFirstName'] = !empty($get_billing_first_name) ? $get_billing_first_name : '';
        $customVars['CustomerLastName']  = !empty($get_billing_last_name) ? $get_billing_last_name : '';

        $response = $rtp->Pay($customVars);
        return fn_buckaroo_process_response($this, $response);
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {

    }
}
