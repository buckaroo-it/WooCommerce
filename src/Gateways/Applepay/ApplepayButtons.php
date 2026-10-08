<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\Express\ExpressPlacements;
use Buckaroo\Woocommerce\Gateways\ExpressPaymentManager;

class ApplepayButtons
{
    public function loadActions()
    {
        if (! $this->paymentMethodIsEnabled()) {
            return;
        }

        $expressManager = ExpressPaymentManager::getInstance();

        foreach (ExpressPlacements::forGateway('buckaroo_applepay') as $page) {
            $expressManager->registerExpressPayment('applepay', [$this, 'render_button'], $page);
        }
    }

    public function render_button()
    {
        $isDetailPage = get_post_type() == 'product';
        echo "<div class='applepay-button-container" . ($isDetailPage ? ' is-detail-page' : null) . "'><div></div></div>";
    }

    private function paymentMethodIsEnabled()
    {
        if ($settings = get_option('woocommerce_buckaroo_applepay_settings')) {
            if (isset($settings['enabled'])) {
                return $settings['enabled'] === 'yes' && ApplepayGateway::hasRequiredCredentials($settings);
            }
        }

        return false;
    }
}
