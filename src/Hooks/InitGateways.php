<?php

namespace Buckaroo\Woocommerce\Hooks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayOldGateway;
use Buckaroo\Woocommerce\Gateways\Idin\IdinController;
use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;
use Buckaroo\Woocommerce\Gateways\PayByBank\PayByBankProcessor;
use Buckaroo\Woocommerce\Gateways\BuckarooBlocks;
use Buckaroo\Woocommerce\Gateways\BuckarooExpressBlocks;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\Services\Helper;

class InitGateways
{
    public function __construct()
    {
        add_action('enqueue_block_assets', [$this, 'initGatewaysOnCheckout']);
        add_action('woocommerce_api_wc_push_buckaroo', [$this, 'pushClassInit']);

        add_action('woocommerce_blocks_payment_method_type_registration', [$this, 'registerBuckarooExpressBlocks']);

        $idinController = new IdinController();

        add_action('woocommerce_before_single_product', [$this, 'idinProduct']);
        add_action('woocommerce_before_cart', [$this, 'idinCart']);
        add_action('woocommerce_review_order_before_payment', [$this, 'idinCheckout']);

        add_action('template_redirect', [$this, 'displayBuckarooErrors']);

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
        $this->displayBuckarooErrors();
        if (IdinProcessor::isIdin(IdinProcessor::getCartProductIds())) {
            include plugin_dir_path(BK_PLUGIN_FILE) . 'templates/idin/checkout.php';
        }
    }

    public function displayBuckarooErrors(): void
    {
        if (empty($_GET['bck_err'])) {
            return;
        }

        if (!is_checkout() && !is_wc_endpoint_url() && !is_page('checkout')) {
            return;
        }

        if ($this->isBlocksCheckout()) {
            return;
        }

        if ($error = base64_decode($_GET['bck_err'])) {
            wc_add_notice(esc_html__(sanitize_text_field($error), 'wc-buckaroo-bpe-gateway'), 'error');
        }
    }

    private function isBlocksCheckout(): bool
    {
        return function_exists('has_block') && has_block('woocommerce/checkout');
    }

    public function initGatewaysOnCheckout()
    {
        if (! class_exists('WC_Payment_Gateways')) {
            return [];
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        $payment_methods = [];

        foreach ($gateways as $gateway_id => $gateway) {
            // Register every enabled Buckaroo gateway so it is declared block-
            // compatible in the Site Editor. Runtime availability (e.g. store
            // currency vs the gateway's supported currencies) is carried by the
            // "available" flag and enforced client-side via canMakePayment in
            // blocks.js, so currency-restricted methods (Swish/Twint/Blik) are
            // recognised as compatible but never offered at an incompatible
            // checkout.
            if ($this->isBuckarooPayment($gateway_id) && $gateway->enabled === 'yes') {
                $payment_method = [
                    'paymentMethodId' => $gateway_id,
                    'title' => $gateway->get_title(),
                    'description' => $gateway->description,
                    'image_path' => $gateway->getIcon(),
                    'buckarooImagesUrl' => plugin_dir_url(BK_PLUGIN_FILE) . 'library/buckaroo_images/',
                    'genders' => Helper::getAllGendersForPaymentMethods(),
                    'displayMode' => $gateway->get_option('displaymode'),
                    'hasFee' => $this->gatewayHasFee($gateway),
                    'available' => $gateway->isVisibleInCheckout(),
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

                if ($gateway_id === 'buckaroo_googlepay') {
                    $payment_method = array_merge(
                        $payment_method,
                        [
                            'showInCheckout' => $gateway->get_option('button_checkout') === 'TRUE',
                            'merchantIdentifier' => $gateway->get_option('merchant_guid'),
                            'buttonStyle' => $gateway->get_option('button_style', 'black'),
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
                if ($gateway_id === 'buckaroo_klarnakp' || $gateway_id === 'buckaroo_klarnapay') {
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
     * Whether the gateway has a non-zero, valid payment fee configured.
     *
     * Used by the Blocks checkout to avoid firing the (full WordPress
     * bootstrap) fee-recalculation AJAX request when switching to a method
     * that carries no fee, which is the common case and keeps switching fast.
     */
    private function gatewayHasFee($gateway): bool
    {
        $rawAmount = $gateway->get_option('extrachargeamount', 0);

        if (! is_scalar($rawAmount)) {
            return false;
        }

        $rawAmount = trim((string) $rawAmount);

        if (! preg_match('/^\d+(?:\.\d+)?%?$/', $rawAmount)) {
            return false;
        }

        return (float) str_replace('%', '', $rawAmount) !== 0.0;
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

    /**
     * Register Buckaroo blocks support with WooCommerce Blocks.
     *
     * Two things are registered here:
     *
     *  1. One integration per enabled Buckaroo gateway, whose get_name() matches
     *     the gateway id. WooCommerce matches these names against the list of
     *     enabled gateways to decide block compatibility, so this is what removes
     *     the "Incompatible with block-based checkout" warning in the Site Editor.
     *
     *  2. The umbrella BuckarooExpressBlocks integration, which continues to
     *     expose the aggregated gateway list consumed by the shared frontend
     *     script for both regular and express payment method registration.
     *
     * @param object $payment_method_registry Payment method registry from WooCommerce Blocks
     */
    public function registerBuckarooExpressBlocks($payment_method_registry)
    {
        if (! class_exists(AbstractPaymentMethodType::class)) {
            return;
        }

        $paymentMethods = $this->initGatewaysOnCheckout();

        // Declare per-gateway block compatibility for every enabled Buckaroo gateway.
        foreach ($this->getEnabledBuckarooGatewayIds() as $gatewayId) {
            if ($payment_method_registry->is_registered($gatewayId)) {
                continue;
            }

            $payment_method_registry->register(
                new BuckarooBlocks($gatewayId)
            );
        }

        // Register universal blocks support for all Buckaroo express payment methods.
        if (! $payment_method_registry->is_registered('buckaroo_express_blocks')) {
            $payment_method_registry->register(new BuckarooExpressBlocks($paymentMethods));
        }
    }

    /**
     * Return the ids of every enabled Buckaroo payment gateway.
     *
     * Uses the full enabled-gateway list (not only the checkout-visible ones)
     * because the Site Editor evaluates block compatibility against every
     * enabled gateway.
     *
     * @return string[]
     */
    private function getEnabledBuckarooGatewayIds(): array
    {
        if (! class_exists('WC_Payment_Gateways')) {
            return [];
        }

        $ids = [];

        foreach (WC()->payment_gateways()->payment_gateways() as $gatewayId => $gateway) {
            if (! $this->isBuckarooPayment($gatewayId)) {
                continue;
            }

            if (filter_var($gateway->enabled, FILTER_VALIDATE_BOOLEAN)) {
                $ids[] = $gatewayId;
            }
        }

        return $ids;
    }
}
