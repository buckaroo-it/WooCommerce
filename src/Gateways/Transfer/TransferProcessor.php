<?php

namespace Buckaroo\Woocommerce\Gateways\Transfer;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;
use DateTime;

class TransferProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        return [
            'email' => $this->getAddress('billing', 'email'),
            'country' => $this->getAddress('billing', 'country'),
            'customer' => [
                'firstName' => $this->getAddress('billing', 'first_name'),
                'lastName' => $this->getAddress('billing', 'last_name'),
            ],
            'dateDue' => $this->getDueDate(),
            'sendMail' => $this->canSendEmail(),
        ];
    }

    protected function getDueDate(): string
    {
        $now = new DateTime();
        $days = $this->gateway->get_option('datedue');

        if (is_scalar($days) && (int) $days <= 0) {
            $days = 14;
        }
        $now->modify('+' . $days . ' day');

        return $now->format('Y-m-d');
    }

    protected function canSendEmail(): bool
    {
        return $this->gateway->get_option('sendmail') == 'TRUE';
    }

    public function unsuccessfulReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        Logger::log('Transfer status check: ' . $responseParser->getStatusCode());
        if (Helper::handleUnsuccessfulPayment($responseParser->getStatusCode())) {
            return [
                'result' => 'error',
                'redirect' => $redirectUrl,
            ];
        }
    }
}
