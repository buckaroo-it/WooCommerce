<?php

namespace Buckaroo\Woocommerce\Gateways\Payconiq;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class PayconiqGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = PayconiqProcessor::class;

    public function __construct()
    {
        $this->id = 'buckaroo_payconiq';
        $this->title = 'Payconiq';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Payconiq';
        $this->setIcon('24x24/payconiq.png', 'svg/payconiq.svg');

        parent::__construct();
        $this->addRefundSupport();
        add_action('template_redirect', [$this, 'payconiqQrcode']);
    }

    /**
     * Check response data
     *
     * @access public
     */
    function payconiqQrcode()
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            wp_safe_redirect(site_url());
            exit();
        }

        $page = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);

        if ($page === false) {
            wp_safe_redirect(site_url());
            exit();
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
        } elseif (strpos($page, 'payconiq') !== false) {
            wp_safe_redirect(site_url());
            exit();
        }
    }
}