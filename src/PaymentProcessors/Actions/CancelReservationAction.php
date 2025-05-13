<?php

namespace Buckaroo\Woocommerce\PaymentProcessors\Actions;

use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;

class CancelReservationAction
{
    public function handle(TransactionResponse $response, $order)
    {
        if ($response->isSuccess()) {
            $order->update_status(
                'cancelled',
                __('Klarna reservation was successfully canceled', 'wc-buckaroo-bpe-gateway')
            );

            set_transient(
                get_current_user_id() . 'buckarooAdminNotice',
                [
                    'type' => 'success',
                    'message' => sprintf(
                        __('Klarna reservation for order #%s was successfully canceled', 'wc-buckaroo-bpe-gateway'),
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
                        __('Cannot cancel klarna reservation for order #%s', 'wc-buckaroo-bpe-gateway'),
                        $order->get_order_number()
                    ),
                ]
            );
        }
    }
}
