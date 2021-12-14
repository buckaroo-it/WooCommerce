<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/eps/eps.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_EPS extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooEPS::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_eps';
        $this->title                  = 'EPS';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo EPS";
        $this->setIcon('24x24/eps.png', 'new/EPS.png');

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * Can the order be refunded
     * @param object $order WC_Order
     * @return object & string
     */
    public function can_refund_order($order)
    {
        return $order && $order->get_transaction_id();
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

        $eps                         = new BuckarooEPS();
        $eps->amountDedit            = 0;
        $eps->amountCredit           = $amount;
        $eps->currency               = $this->currency;
        $eps->description            = $reason;
        $eps->invoiceId              = $order->get_order_number();
        $eps->orderId                = $order_id;
        $eps->OriginalTransactionKey = $order->get_transaction_id();
        $eps->returnUrl              = $this->notify_url;
        $payment_type                = str_replace('buckaroo_', '', strtolower($this->id));
        $eps->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                    = null;

        $orderDataForChecking = $eps->getOrderRefundData();
        try {
            $eps->checkRefundData($orderDataForChecking);
            $response = $eps->Refund();
        } catch (Exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }

    /**
     * Validate fields
     * @return void;
     */
    public function validate_fields()
    {
        if (version_compare(WC()->version, '3.6', '<')) {
            resetOrder();
        }
        return;
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
        /** @var BuckarooEPS */
        $eps = $this->createDebitRequest($order);
        $customVars     = array();

        

        $response = $eps->Pay($customVars);
        return fn_buckaroo_process_response($this, $response);
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields()
    {

    }
}
