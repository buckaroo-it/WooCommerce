<?php

namespace Buckaroo\Woocommerce\Core;

use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayButtons;
use Buckaroo\Woocommerce\Hooks\HookRegistry;
use Buckaroo\Woocommerce\Install\Install;

class Plugin
{
    protected PaymentGatewayRegistry $gatewayRegistry;

    public static function init(): void
    {
        new HookRegistry();
    }

    public function register()
    {
        // no code should be implemented before testing for active woocommerce
        if (!class_exists('WC_Order')) {
            set_transient(get_current_user_id() . 'buckaroo_require_woocommerce', true);

            return;
        }
        delete_transient(get_current_user_id() . 'buckaroo_require_woocommerce');


        load_plugin_textdomain('wc-buckaroo-bpe-gateway', false, dirname(plugin_basename(BK_PLUGIN_FILE)) . '/languages/');

        $this->registerGateways();
        $this->registerAfterpayButtons();

        if (!file_exists(__DIR__ . '/../../../../../.well-known/apple-developer-merchantid-domain-association')) {
            if (!file_exists(__DIR__ . '/../../../../../.well-known')) {
                mkdir(__DIR__ . '/../../../../../.well-known', 0775, true);
            }

            copy(__DIR__ . '/assets/apple-developer-merchantid-domain-association', __DIR__ . '/../../../../../.well-known/apple-developer-merchantid-domain-association');
        }


        // do a install if the plugin was installed prior to 2.24.1
        // make sure we have all our plugin files loaded
        Install::installUntrackedInstalation();

        return $this;
    }

    public function registerGateways(): void
    {
        $gatewayRegistry = (new PaymentGatewayRegistry)->load();

        add_filter('woocommerce_payment_gateways', [$gatewayRegistry, 'hook_gateways_to_woocommerce']);
    }

    public function registerAfterpayButtons(): void
    {
        (new ApplepayButtons())->loadActions();
    }

    public function getGatewayRegistry(): PaymentGatewayRegistry
    {
        return $this->gatewayRegistry;
    }
}