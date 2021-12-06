<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/postepay/postepay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_PostePay extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_postepay';
        $this->title                  = 'PostePay';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo PostePay";
        $this->setIcon('24x24/postepay.png', 'new/PostePay.png');

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

        $postepay                         = new BuckarooPostePay();
        $postepay->amountDedit            = 0;
        $postepay->amountCredit           = $amount;
        $postepay->currency               = $this->currency;
        $postepay->description            = $reason;
        $postepay->invoiceId              = $order->get_order_number();
        $postepay->orderId                = $order_id;
        $postepay->OriginalTransactionKey = $order->get_transaction_id();
        $postepay->returnUrl              = $this->notify_url;
        $clean_order_no               = (int) str_replace('#', '', $order->get_order_number());
        $postepay->setType(get_post_meta($clean_order_no, '_payment_method_transaction', true));
        $payment_type  = str_replace('buckaroo_', '', strtolower($this->id));
        $postepay->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response      = null;

        $orderDataForChecking = $postepay->getOrderRefundData();

        try {
            $postepay->checkRefundData($orderDataForChecking);
            $response = $postepay->Refund();
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
        $postepay                 = new BuckarooPostePay();

        if (method_exists($order, 'get_order_total')) {
            $postepay->amountDedit = $order->get_order_total();
        } else {
            $postepay->amountDedit = $order->get_total();
        }
        $payment_type      = str_replace('buckaroo_', '', strtolower($this->id));
        $postepay->channel     = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $postepay->currency    = $this->currency;
        $postepay->description = $this->transactiondescription;
        $postepay->invoiceId   = (string) getUniqInvoiceId($order->get_order_number());
        $postepay->orderId     = (string) $order_id;
        $postepay->returnUrl   = $this->notify_url;
        
        $response = $postepay->Pay();
        return fn_buckaroo_process_response($this, $response);
    }

}
