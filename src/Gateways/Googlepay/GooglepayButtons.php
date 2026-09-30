<?php

namespace Buckaroo\Woocommerce\Gateways\Googlepay;

use Buckaroo\Woocommerce\Gateways\Express\ExpressPlacements;
use Buckaroo\Woocommerce\Gateways\ExpressPaymentManager;

class GooglepayButtons
{
    public function loadActions()
    {
        if (! $this->paymentMethodIsEnabled()) {
            return;
        }

        $expressManager = ExpressPaymentManager::getInstance();

        foreach (ExpressPlacements::forGateway('buckaroo_googlepay') as $page) {
            $expressManager->registerExpressPayment('googlepay', [$this, 'render_button'], $page);
        }
    }

    public function render_button()
    {
        $isDetailPage = get_post_type() == 'product';
        echo "<div class='googlepay-button-container" . ($isDetailPage ? ' is-detail-page' : null) . "'><div></div></div>";
    }

    private function paymentMethodIsEnabled()
    {
        if ($settings = get_option('woocommerce_buckaroo_googlepay_settings')) {
            if (isset($settings['enabled'])) {
                return $settings['enabled'] === 'yes' ? true : false;
            }
        }

        return false;
    }
}
