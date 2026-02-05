<?php

namespace Buckaroo\Woocommerce\Gateways;

class ExpressPaymentManager
{
    private static $instance = null;
    private $containerRendered = [];
    private $expressPayments = [];
    private $hooksRegistered = false;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->registerContainerHooks();
    }

    /**
     * Register WordPress hooks for container rendering
     */
    private function registerContainerHooks()
    {
        if ($this->hooksRegistered) {
            return;
        }

        add_action('woocommerce_after_add_to_cart_button', [$this, 'maybeRenderContainerProduct']);
        add_action('woocommerce_after_cart_totals', [$this, 'maybeRenderContainerCart']);
        $checkout_priority = apply_filters('buckaroo_express_checkout_priority', 21);
        add_action('woocommerce_before_checkout_form', [$this, 'maybeRenderContainerCheckout'], $checkout_priority);

        $this->hooksRegistered = true;
    }

    /**
     * Check and render container for product page
     */
    public function maybeRenderContainerProduct()
    {
        global $product;

        if (
            isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' &&
            $product && $product->get_stock_status() === 'instock'
        ) {
            $this->renderExpressPaymentsContainer('product');
        }
    }

    /**
     * Check and render container for cart page
     */
    public function maybeRenderContainerCart()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $this->renderExpressPaymentsContainer('cart');
        }
    }

    /**
     * Check and render container for checkout page
     */
    public function maybeRenderContainerCheckout()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $this->renderExpressPaymentsContainer('checkout');
        }
    }

    /**
     * Register an express payment method
     */
    public function registerExpressPayment($method_id, $renderer, $location)
    {
        if (!isset($this->expressPayments[$location])) {
            $this->expressPayments[$location] = [];
        }

        $this->expressPayments[$location][$method_id] = $renderer;
    }

    /**
     * Render the express payments container for a specific location
     */
    public function renderExpressPaymentsContainer($location)
    {
        if (isset($this->containerRendered[$location])) {
            return;
        }

        if (!isset($this->expressPayments[$location]) || empty($this->expressPayments[$location])) {
            return;
        }

        $this->containerRendered[$location] = true;

        echo '<div class="buckaroo-express-payments">';

        foreach ($this->expressPayments[$location] as $method_id => $renderer) {
            if (is_callable($renderer)) {
                call_user_func($renderer);
            }
        }

        echo '</div>';
    }

    /**
     * Check if any express payments are registered for a location
     */
    public function hasExpressPayments($location)
    {
        return isset($this->expressPayments[$location]) && !empty($this->expressPayments[$location]);
    }

    /**
     * Clear registered payments (useful for testing)
     */
    public function clearPayments()
    {
        $this->expressPayments = [];
        $this->containerRendered = [];
    }
}
