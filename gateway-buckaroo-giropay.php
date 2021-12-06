<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/giropay/giropay.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Giropay extends WC_Gateway_Buckaroo
{
    public function __construct()
    {
        $this->id                     = 'buckaroo_giropay';
        $this->title                  = 'Giropay';
        $this->has_fields             = true;
        $this->method_title           = "Buckaroo Giropay";
        $this->setIcon('24x24/giropay.gif', 'new/Giropay.png');

        parent::__construct();

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
        $GLOBALS['plugin_id']            = $this->plugin_id . $this->id . '_settings';
        $order                           = wc_get_order($order_id);
        $giropay                         = new BuckarooGiropay();
        $giropay->amountDedit            = 0;
        $giropay->amountCredit           = $amount;
        $giropay->currency               = $this->currency;
        $giropay->description            = $reason;
        $giropay->invoiceId              = $order->get_order_number();
        $giropay->orderId                = $order_id;
        $giropay->OriginalTransactionKey = $order->get_transaction_id();
        $giropay->returnUrl              = $this->notify_url;
        $payment_type                    = str_replace('buckaroo_', '', strtolower($this->id));
        $giropay->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                        = null;

        $orderDataForChecking = $giropay->getOrderRefundData();

        try {
            $giropay->checkRefundData($orderDataForChecking);
            $response = $giropay->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }
        return fn_buckaroo_process_refund($response, $order, $amount, $this->currency);
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if (empty($_POST['buckaroo-giropay-bancaccount'])) {
            wc_add_notice(__('Please provide correct BIC', 'wc-buckaroo-bpe-gateway'), 'error');
        }
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

        if (empty($_POST['buckaroo-giropay-bancaccount'])) {
            wc_add_notice(__('Please provide correct BIC', 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $order                = getWCOrder($order_id);
        $giropay              = new BuckarooGiropay();
        if (method_exists($order, 'get_order_total')) {
            $giropay->amountDedit = $order->get_order_total();
        } else {
            $giropay->amountDedit = $order->get_total();
        }
        $payment_type         = str_replace('buckaroo_', '', strtolower($this->id));
        $giropay->channel     = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $giropay->currency    = $this->currency;
        $giropay->description = $this->transactiondescription;
        $giropay->invoiceId   = (string) getUniqInvoiceId($order->get_order_number());
        $giropay->orderId     = (string) $order_id;
        $giropay->bic         = $_POST['buckaroo-giropay-bancaccount'];
        $giropay->returnUrl   = $this->notify_url;

        $response = $giropay->Pay();
        return fn_buckaroo_process_response($this, $response);
    }
}
