<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/payconiq/payconiq.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Payconiq extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooPayconiq::class;
    public function __construct()
    {
        $this->id                     = 'buckaroo_payconiq';
        $this->title                  = 'Payconiq';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Payconiq";
        $this->setIcon('24x24/payconiq.png', 'new/Payconic.png');

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

        $payconiq                         = new BuckarooPayconiq();
        $payconiq->amountDedit            = 0;
        $payconiq->amountCredit           = $amount;
        $payconiq->currency               = $this->currency;
        $payconiq->description            = $reason;
        $payconiq->invoiceId              = $order->get_order_number();
        $payconiq->orderId                = $order_id;
        $payconiq->OriginalTransactionKey = $order->get_transaction_id();
        $payconiq->returnUrl              = $this->notify_url;
        $clean_order_no                   = (int) str_replace('#', '', $order->get_order_number());
        $payconiq->setType(get_post_meta($clean_order_no, '_payment_method_transaction', true));
        $payment_type      = str_replace('buckaroo_', '', strtolower($this->id));
        $payconiq->channel = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $response          = null;

        $orderDataForChecking = $payconiq->getOrderRefundData();

        try {
            $payconiq->checkRefundData($orderDataForChecking);
            $response = $payconiq->Refund();
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
        /** @var BuckarooPayconiq */
        $payconiq = $this->createDebitRequest($order);
        $response = $payconiq->Pay();
        return fn_buckaroo_process_response($this, $response);
    }

    /**
     * Check response data
     *
     * @access public
     */
    public function response_handler()
    {
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $result               = fn_buckaroo_process_response($this);
        $order_id             = isset($_GET["order_id"]) ? $_GET["order_id"] : false;
        if (!is_null($result)) {
            wp_safe_redirect($result['redirect']);
        } elseif ($order_id) {
            // if we are here we are the redirect from the "cancel payment" link
            // So we have to cancel the payment.
            $order = new WC_Order($order_id);
            if (isset($order)) {
                $order->update_status('cancelled', __('890', 'wc-buckaroo-bpe-gateway'));
                wc_add_notice(
                    __(
                        'Payment cancelled. Please try again or choose another payment method.',
                        'wc-buckaroo-bpe-gateway'
                    ),
                    'error'
                );
                wp_safe_redirect($order->get_cancel_order_url());
            }
        }
        exit;
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_certificate'));

        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'enqueue_script_hide_local'));

        //Start Dynamic Rendering of Hidden Fields
        $options      = get_option("woocommerce_" . $this->id . "_settings", null);
        $ccontent_arr = array();
        $keybase      = 'certificatecontents';
        $keycount     = 1;
        if (!empty($options["$keybase$keycount"])) {
            while (!empty($options["$keybase$keycount"])) {
                $ccontent_arr[] = "$keybase$keycount";
                $keycount++;
            }
        }
        $while_key                 = 1;
        $selectcertificate_options = array('none' => 'None selected');
        while ($while_key != $keycount) {
            $this->form_fields["certificatecontents$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '',
            );
            $this->form_fields["certificateuploadtime$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '');
            $this->form_fields["certificatename$while_key"] = array(
                'title'       => '',
                'type'        => 'hidden',
                'description' => '',
                'default'     => '');
            $selectcertificate_options["$while_key"] = $options["certificatename$while_key"];

            $while_key++;
        }
        $final_ccontent                                          = $keycount;
        $this->form_fields["certificatecontents$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields["certificateuploadtime$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields["certificatename$final_ccontent"] = array(
            'title'       => '',
            'type'        => 'hidden',
            'description' => '',
            'default'     => '');
        $this->form_fields['selectcertificate'] = array(
            'title'       => __('Select Certificate', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Select your certificate by name.', 'wc-buckaroo-bpe-gateway'),
            'options'     => $selectcertificate_options,
            'default'     => 'none',
        );
        $this->form_fields['choosecertificate'] = array(
            'title'       => __('', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'file',
            'description' => __(''),
            'default'     => '');
    }

}

function payconiqQrcode()
{
    $page = esc_url($_SERVER['REQUEST_URI']);
    if (strpos($page, 'payconiqQrcode') !== false) {
        if (!isset($_GET["invoicenumber"]) && !isset($_GET["transactionKey"]) && !isset($_GET["currency"]) && !isset($_GET["amount"])) {
            // When no parameters, redirect to cart page.
            wc_add_notice(__('Checkout is not available whilst your cart is empty.', 'woocommerce'), 'notice');
            wp_safe_redirect(wc_get_page_permalink('cart'));
            exit;
        }

        ob_start();
        get_template_part('header');
        include 'templates/payconiq/qrcode.php';
        get_template_part('footer');

        $content = ob_get_clean();
        $content = preg_replace('#<title>(.*?)<\/title>#', '<title>Payconiq</title>', $content);
        echo $content;

        die();
    }elseif (strpos($page, 'payconiq') !== false) {
         wp_safe_redirect(site_url()); exit();
    }
}
add_action('template_redirect', 'payconiqQrcode');
