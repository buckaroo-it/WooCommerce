<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/nexi/nexi.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Nexi extends WC_Gateway_Buckaroo
{
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
     * Validate payment fields on the frontend.
     *
     * @access public
     * @return void
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
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $woocommerce = getWooCommerceObject();

        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = getWCOrder($order_id);
        $nexi                 = new BuckarooNexi();

        if (method_exists($order, 'get_order_total')) {
            $nexi->amountDedit = $order->get_order_total();
        } else {
            $nexi->amountDedit = $order->get_total();
        }
        $payment_type      = str_replace('buckaroo_', '', strtolower($this->id));
        $nexi->channel     = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $nexi->currency    = $this->currency;
        $nexi->description = $this->transactiondescription;
        $nexi->invoiceId   = (string) getUniqInvoiceId($order->get_order_number());
        $nexi->orderId     = (string) $order_id;
        $nexi->returnUrl   = $this->notify_url;
        
        $response = $nexi->Pay();
        return fn_buckaroo_process_response($this, $response);
    }
}
