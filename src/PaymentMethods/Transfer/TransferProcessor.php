<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\Transfer;

use DateTime;
use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentProcessorHandler;

class TransferProcessor extends PaymentProcessorHandler
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return [
            'email' => $this->get_address('billing', 'email'),
            'country' => $this->get_address('billing', 'country'),
            'customer' => [
                'firstName' => $this->get_address('billing', 'first_name'),
                'lastName' => $this->get_address('billing', 'last_name')
            ],
            'dateDue' => $this->get_due_date(),
            'sendMail' => $this->can_send_email(),
        ];
    }

    protected function get_due_date(): string
    {
        $now = new DateTime();
        $days = $this->gateway->get_option('datedue');

        if (is_scalar($days) && (int)$days <= 0) {
            $days = 14;
        }
        $now->modify('+' . $days . ' day');
        return $now->format('Y-m-d');
    }

    protected function can_send_email(): bool
    {
        return $this->gateway->get_option('sendmail') == 'TRUE';
    }
}