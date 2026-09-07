<?php

namespace Buckaroo\Woocommerce\Gateways;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Buckaroo Express payment methods integration for WooCommerce Blocks.
 *
 * This umbrella integration exposes the aggregated list of Buckaroo gateways to
 * the shared `buckaroo-blocks` frontend script, which performs the client-side
 * registration of both the regular and express payment methods. Per-gateway
 * block compatibility is declared separately by {@see BuckarooBlocks}.
 */
class BuckarooExpressBlocks extends AbstractPaymentMethodType
{
    protected $name = 'buckaroo_express_blocks';

    protected array $paymentMethods;

    public function __construct(array $paymentMethods = [])
    {
        $this->paymentMethods  = $paymentMethods;
    }

    public function initialize()
    {
        //
    }

    public function get_payment_method_data()
    {
        return [
            'buckarooGateways' => $this->paymentMethods,
        ];
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
