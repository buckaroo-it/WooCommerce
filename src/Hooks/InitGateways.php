<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayOldGateway;
use Buckaroo\Woocommerce\Gateways\Idin\IdinController;
use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;
use Buckaroo\Woocommerce\Gateways\PayByBank\PayByBankProcessor;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\Services\Helper;

class InitGateways
{
    public function __construct()
    {
        add_action('enqueue_block_assets', [$this, 'initGatewaysOnCheckout']);
        add_action('woocommerce_api_wc_push_buckaroo', [$this, 'pushClassInit']);

        $idinController = new IdinController();

        add_action('woocommerce_before_single_product', [$this, 'idinProduct']);
        add_action('woocommerce_before_cart', [$this, 'idinCart']);
        add_action('woocommerce_review_order_before_payment', [$this, 'idinCheckout']);

        add_action('woocommerce_api_wc_gateway_buckaroo_idin-identify', [$idinController, 'identify']);
        add_action('woocommerce_api_wc_gateway_buckaroo_idin-reset', [$idinController, 'reset']);
        add_action('woocommerce_api_wc_gateway_buckaroo_idin-return', [$idinController, 'returnHandler']);
    }

    public function pushClassInit()
    {
        (new PushProcessor())->handle();
        exit;
    }

    public function idinProduct(): void
    {
        global $post;

        if (IdinProcessor::isIdin([$post->ID])) {
            include 'templates/idin/cart.php';
        }
    }

    public function idinCart(): void
    {
        if (IdinProcessor::isIdin(IdinProcessor::getCartProductIds())) {
            include 'templates/idin/cart.php';
        }
    }

    public function idinCheckout(): void
    {
        if (! empty($_GET['bck_err']) && ($error = base64_decode($_GET['bck_err']))) {
            wc_add_notice(esc_html__(sanitize_text_field($error), 'wc-buckaroo-bpe-gateway'), 'error');
        }
        if (IdinProcessor::isIdin(IdinProcessor::getCartProductIds())) {
            include plugin_dir_path(BK_PLUGIN_FILE) . 'templates/idin/checkout.php';
        }
    }

    public function initGatewaysOnCheckout()
    {
        if (! class_exists('WC_Payment_Gateways')) {
            return [];
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        $payment_methods = [];

        foreach ($gateways as $gateway_id => $gateway) {
            if ($this->isBuckarooPayment($gateway_id) && $gateway->isVisibleInCheckout()) {
                $payment_method = [
                    'paymentMethodId' => $gateway_id,
                    'title' => $gateway->get_title(),
                    'description' => $gateway->description,
                    'image_path' => $gateway->getIcon(),
                    'buckarooImagesUrl' => plugin_dir_url(BK_PLUGIN_FILE) . 'library/buckaroo_images/',
                    'genders' => Helper::getAllGendersForPaymentMethods(),
                    'displayMode' => $gateway->get_option('displaymode'),
                ];

                if ($gateway_id === 'buckaroo_paybybank') {
                    $payment_method['payByBankIssuers'] = PayByBankProcessor::getIssuerList();
                    $payment_method['payByBankSelectedIssuer'] = PayByBankProcessor::getActiveIssuerCode();
                    $payment_method['lastPayByBankIssuer'] = PayByBankProcessor::getActiveIssuerCode();
                }
                if ($gateway_id === 'buckaroo_afterpaynew') {
                    $payment_method['customer_type'] = $gateway->customer_type;
                    $payment_method['financialWarning'] = $gateway->get_option('financial_warning');
                }
                if ($gateway_id === 'buckaroo_afterpay') {
                    $payment_method['b2b'] = $gateway->b2b;
                    $payment_method['type'] = (new AfterpayOldGateway())->type;
                    $payment_method['financialWarning'] = $gateway->get_option('financial_warning');
                }
                if (str_starts_with($gateway_id, 'buckaroo_creditcard')) {
                    $payment_method['creditCardIssuers'] = $gateway->getCardsList();
                    $payment_method['creditCardMethod'] = $gateway->get_option('creditcardmethod');
                    $payment_method['creditCardIsSecure'] = $this->getCredtCardIsSecure();
                }

                if ($gateway_id === 'buckaroo_applepay') {
                    $payment_method = array_merge(
                        $payment_method,
                        [
                            'showInCheckout' => $gateway->get_option('button_checkout') === 'TRUE',
                            'merchantIdentifier' => $gateway->get_option('merchant_guid'),
                        ]
                    );
                }

                if ($gateway_id === 'buckaroo_paypal') {
                    $expressPages = $gateway->get_option('express', []);
                    $payment_method = array_merge(
                        $payment_method,
                        [
                            'showInCheckout' => is_array($expressPages) && in_array(PaypalExpressController::LOCATION_CHECKOUT, $expressPages),
                        ]
                    );
                }
                if ($gateway_id === 'buckaroo_klarnakp' || $gateway_id === 'buckaroo_klarnapay' || $gateway_id === 'buckaroo_klarnapii') {
                    $payment_method['financialWarning'] = $gateway->get_option('financial_warning');
                }
                if ($gateway_id === 'buckaroo_in3') {
                    $payment_method['financialWarning'] = $gateway->get_option('financial_warning');
                }
                if ($gateway_id === 'buckaroo_billink') {
                    $payment_method['financialWarning'] = $gateway->get_option('financial_warning');
                }

                $payment_methods[] = $payment_method;
            }
        }
        wp_localize_script('buckaroo-blocks', 'buckarooGateways', $payment_methods);

        return $payment_methods;
    }

    /**
     * Check if payment gateway is ours
     */
    private function isBuckarooPayment(string $name): bool
    {
        return strncmp($name, 'buckaroo', strlen('buckaroo')) === 0;
    }

    private function getCredtCardIsSecure()
    {
        return (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ! empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443;
    }
}
