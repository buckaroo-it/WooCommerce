<?php

/**
 * Core class for payment factory
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */
class Buckaroo_Refund_Processor
{
    protected WC_Order $order;

    public function __construct(WC_Order $order)
    {
        $this->order = $order;
    }
    public function process(
        Buckaroo_Sdk_Response $response
    ) {

        if ($response->is_success()) {
            $this->order->add_order_note(
                sprintf(
                    __('Refunded %1$s - Refund transaction ID: %2$s', 'wc-buckaroo-bpe-gateway'),
                    wc_price($response->get_refund_amount()),
                    $response->get_transaction_key()
                )
            );
            add_post_meta(
                $this->order->get_id(),
                '_refundbuckaroo' . $response->get_transaction_key(),
                'ok',
                true
            );
            return true;
        }


        $this->order->add_order_note(
            sprintf(
                __(
                    'Refund failed for transaction ID: %s ' . "\n" . $response->get_some_error(),
                    'wc-buckaroo-bpe-gateway'
                ),
                $this->order->get_transaction_id()
            )
        );

        return new WP_Error('error_refund', __("Refund failed: ") . $response->get_some_error());
    }
}
