<?php

namespace Buckaroo\Woocommerce\Gateways;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Per-gateway WooCommerce Blocks payment method integration.
 *
 * WooCommerce determines whether a payment gateway supports the Cart & Checkout
 * blocks by matching gateway ids against the names of the payment method
 * integrations registered through the
 * `woocommerce_blocks_payment_method_type_registration` hook. The Site Editor
 * matches the enabled gateways against the active integrations (see
 * PaymentMethodRegistry::get_all_registered_script_data() and Checkout block
 * "globalPaymentMethods"); the Payments settings page matches every gateway
 * against all registered integrations.
 *
 * Registering a single umbrella integration is therefore not enough: each
 * Buckaroo gateway needs its own integration whose get_name() returns the exact
 * gateway id, otherwise the Site Editor reports the gateway as
 * "Incompatible with block-based checkout" and the Payments settings page
 * shows it as "Limited compatibility".
 *
 * This class provides one such integration. All Buckaroo gateways share the same
 * `buckaroo-blocks` frontend script (which performs the actual client-side
 * registerPaymentMethod() calls); this integration only exposes the server-side
 * compatibility declaration and the per-gateway data.
 */
class BuckarooBlocks extends AbstractPaymentMethodType
{
    /**
     * The gateway id this integration represents (e.g. "buckaroo_ideal").
     */
    protected $name;

    /**
     * Data made available to the client for this gateway.
     */
    protected array $paymentMethodData;

    public function __construct(string $name, array $paymentMethodData = [])
    {
        $this->name = $name;
        $this->paymentMethodData = $paymentMethodData;
    }

    public function initialize()
    {
        //
    }

    /**
     * Active while the gateway is enabled. Registering the integration is what
     * declares block support; WooCommerce only loads the script and data of
     * active integrations at checkout. Visibility at checkout is decided by the
     * gateway (is_available) and the client-side canMakePayment.
     */
    public function is_active()
    {
        $gateways = WC()->payment_gateways()->payment_gateways();

        return isset($gateways[$this->name]) && $gateways[$this->name]->enabled === 'yes';
    }

    /**
     * The aggregated gateway list consumed by the shared frontend script is
     * exposed once by the umbrella BuckarooExpressBlocks integration, so the
     * per-gateway integration only needs to expose its own (optional) data.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return $this->paymentMethodData;
    }

    /**
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        BuckarooBlocksScript::register();

        return [BuckarooBlocksScript::HANDLE];
    }
}
