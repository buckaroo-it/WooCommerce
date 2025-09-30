<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\ExpressPaymentManager;

class ApplepayButtons
{
    public function loadActions()
    {
        $expressManager = ExpressPaymentManager::getInstance();

        if ($this->paymentMethodIsEnabled()) {
            if ($this->buttonIsEnabled('product')) {
                $expressManager->registerExpressPayment('applepay', [$this, 'render_button'], 'product');
            }
            if ($this->buttonIsEnabled('cart')) {
                $expressManager->registerExpressPayment('applepay', [$this, 'render_button'], 'cart');
            }
            if ($this->buttonIsEnabled('checkout')) {
                $expressManager->registerExpressPayment('applepay', [$this, 'render_button'], 'checkout');
            }
        }
    }

    public function render_button()
    {
        $isDetailPage = get_post_type() == 'product';
        echo "<div class='applepay-button-container" . ($isDetailPage ? ' is-detail-page' : null) . "'><div></div></div>";
    }

    private function buttonIsEnabled($page)
    {
        if ($settings = get_option('woocommerce_buckaroo_applepay_settings')) {
            if (isset($settings["button_{$page}"])) {
                return $settings["button_{$page}"] === 'TRUE' ? true : false;
            }
        }

        return false;
    }

    private function paymentMethodIsEnabled()
    {
        if ($settings = get_option('woocommerce_buckaroo_applepay_settings')) {
            if (isset($settings['enabled'])) {
                return $settings['enabled'] === 'yes' ? true : false;
            }
        }

        return false;
    }
}
