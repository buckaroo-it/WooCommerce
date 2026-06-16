<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;

class ExtendReservationAction
{
    public function handle(TransactionResponse $response, $order)
    {
        if ($response->isSuccess()) {
            $order->add_order_note(
                __('Klarna reservation was successfully extended', 'wc-buckaroo-bpe-gateway')
            );

            set_transient(
                get_current_user_id() . 'buckarooAdminNotice',
                [
                    'type' => 'success',
                    'message' => sprintf(
                        __('Klarna reservation for order #%s was successfully extended', 'wc-buckaroo-bpe-gateway'),
                        $order->get_order_number()
                    ),
                ]
            );
        } else {
            set_transient(
                get_current_user_id() . 'buckarooAdminNotice',
                [
                    'type' => 'warning',
                    'message' => sprintf(
                        __('Cannot extend klarna reservation for order #%s', 'wc-buckaroo-bpe-gateway'),
                        $order->get_order_number()
                    ),
                ]
            );
        }
    }
}
