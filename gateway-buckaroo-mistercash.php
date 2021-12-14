<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/mistercash/mistercash.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Mistercash extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooMisterCash::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_bancontactmrcash';
        $this->title                  = 'Bancontact / MisterCash';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo Bancontact / MisterCash';
        $this->setIcon('24x24/mistercash.png', 'new/Bancontact.png');
        $this->migrateOldSettings('woocommerce_buckaroo_mistercash_settings');

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

        $mistercash                         = new BuckarooMisterCash();
        $mistercash->amountDedit            = 0;
        $mistercash->amountCredit           = $amount;
        $mistercash->currency               = $this->currency;
        $mistercash->description            = $reason;
        $mistercash->invoiceId              = $order->get_order_number();
        $mistercash->orderId                = $order_id;
        $mistercash->OriginalTransactionKey = $order->get_transaction_id();
        $mistercash->returnUrl              = $this->notify_url;
        $payment_type                       = str_replace('buckaroo_', '', strtolower($this->id));
        $mistercash->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response                           = null;

        $orderDataForChecking = $mistercash->getOrderRefundData();

        try {
            $mistercash->checkRefundData($orderDataForChecking);
            $response = $mistercash->Refund();
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
        $order = getWCOrder($order_id);
        /** @var BuckarooMisterCash */
        $mistercash = $this->createDebitRequest($order);
        $response = $mistercash->Pay();
        return fn_buckaroo_process_response($this, $response);
    }
}
