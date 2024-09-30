<?php

namespace WC_Buckaroo\WooCommerce\Idin;

use WC_Buckaroo\WooCommerce\SDK\Buckaroo_Sdk_Response;

class Buckaroo_Idin_Processor
{
    public function process(
        Buckaroo_Sdk_Response $response
    )
    {
        if ($response->has_redirect()) {
            return [
                'result' => 'success',
                'redirect' => $response->get_redirect_url(),
            ];
        }
        return $this->failed($response->get_some_error());
    }

    /**
     * Redirect back to checkout with message
     *
     * @param string $message
     *
     * @return array
     */
    private function failed(string $message): array
    {
        wc_add_notice($message, 'error');
        return [
            'result' => 'error',
            'redirect' => wc_get_checkout_url(),
        ];
    }
}
