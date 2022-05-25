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
        $this->setIcon('24x24/payconiq.png', 'svg/Payconiq.svg');

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
        return $this->processDefaultRefund($order_id, $amount, $reason, true);
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
        $order_id             = isset($_GET["order_id"]) && is_scalar($_GET["order_id"]) ? intval($_GET["order_id"]) : false;
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

}

function payconiqQrcode()
{
    $page = filter_var( $_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);

    if ($page === false) {
        wp_safe_redirect(site_url()); exit();
    }
    
    if (strpos($page, 'payconiqQrcode') !== false) {
        if (
            !isset($_GET["invoicenumber"]) ||
            !isset($_GET["transactionKey"]) ||
            !isset($_GET["currency"]) ||
            !isset($_GET["amount"]) ||
            !isset($_GET["returnUrl"])
        ) {
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
