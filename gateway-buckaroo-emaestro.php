<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/emaestro/emaestro.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_EMaestro extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooEMaestro::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_emaestro';
        $this->title                  = 'eMaestro';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo eMaestro";
        $this->setIcon('24x24/emaestro.png', 'new/Maestro.png');

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

        $emaestro                         = new BuckarooEMaestro();
        $emaestro->amountDedit            = 0;
        $emaestro->amountCredit           = $amount;
        $emaestro->currency               = $this->currency;
        $emaestro->description            = $reason;
        $emaestro->invoiceId              = $order->get_order_number();
        $emaestro->orderId                = $order_id;
        $emaestro->OriginalTransactionKey = $order->get_transaction_id();
        $emaestro->returnUrl              = $this->notify_url;
        $clean_order_no                   = (int) str_replace('#', '', $order->get_order_number());
        $emaestro->setType(get_post_meta($clean_order_no, '_payment_method_transaction', true));
        $payment_type      = str_replace('buckaroo_', '', strtolower($this->id));
        $emaestro->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response          = null;

        $orderDataForChecking = $emaestro->getOrderRefundData();

        try {
            $emaestro->checkRefundData($orderDataForChecking);
            $response = $emaestro->Refund();
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
        $order = getWCOrder($order_id);
        /** @var BuckarooEMaestro */
        $emaestro = $this->createDebitRequest($order);
        $response = $emaestro->Pay();
        return fn_buckaroo_process_response($this, $response);
    }
}
