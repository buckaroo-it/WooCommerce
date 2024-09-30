<?php

namespace WC_Buckaroo\WooCommerce\Finish;

use Buckaroo_Http_Request;

class Buckaroo_Return_Page
{
    public function __construct()
    {
        add_action('woocommerce_api_wc_buckaroo_return', [$this, 'process']);
    }

    public function process()
    {
        $result = (new Buckaroo_Finish_Processor())->process(
            new Buckaroo_Return_Payload(new Buckaroo_Http_Request())
        );

        wp_safe_redirect(
            $result['redirect'] ?? wc_get_checkout_url()
        );
    }


}
