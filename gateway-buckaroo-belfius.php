<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/belfius/belfius.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Belfius extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_belfius';
        $this->title                  = 'Belfius';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Belfius";
        $this->setIcon('24x24/belfius.png', 'new/Belfius.png');

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

        $belfius                         = new BuckarooBelfius();
        $belfius->amountDedit            = 0;
        $belfius->amountCredit           = $amount;
        $belfius->currency               = $this->currency;
        $belfius->description            = $reason;
        $belfius->invoiceId              = $order->get_order_number();
        $belfius->orderId                = $order_id;
        $belfius->OriginalTransactionKey = $order->get_transaction_id();
        $belfius->returnUrl              = $this->notify_url;
        $payment_type                    = str_replace('buckaroo_', '', strtolower($this->id));
        $belfius->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                        = null;

        $orderDataForChecking = $belfius->getOrderRefundData();

        try {
            $belfius->checkRefundData($orderDataForChecking);
            $response = $belfius->Refund();
        } catch (exception $e) {
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
        $woocommerce = getWooCommerceObject();

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = getWCOrder($order_id);
        $belfius              = new BuckarooBelfius();

        if (method_exists($order, 'get_order_total')) {
            $belfius->amountDedit = $order->get_order_total();
        } else {
            $belfius->amountDedit = $order->get_total();
        }
        $payment_type         = str_replace('buckaroo_', '', strtolower($this->id));
        $belfius->channel     = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $belfius->currency    = $this->currency;
        $belfius->description = $this->transactiondescription;
        $belfius->invoiceId   = (string) getUniqInvoiceId($order->get_order_number());
        $belfius->orderId     = (string) $order_id;
        $belfius->returnUrl   = $this->notify_url;


        $response = $belfius->Pay();

        return fn_buckaroo_process_response($this, $response);
    }
}
