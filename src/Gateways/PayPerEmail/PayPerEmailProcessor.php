<?php

namespace Buckaroo\Woocommerce\Gateways\PayPerEmail;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Services\Logger;

class PayPerEmailProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    public function getAction(): string
    {
        return 'paymentInvitation';
    }

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        $payload = [
            'email' => $this->request->input(
                'buckaroo-payperemail-email',
                $this->getAddress('billing', 'email'),
            ),
            'customer' => [
                'firstName' => $this->request->input(
                    'buckaroo-payperemail-firstname',
                    $this->getAddress('billing', 'first_name'),
                ),
                'lastName' => $this->request->input(
                    'buckaroo-payperemail-lastname',
                    $this->getAddress('billing', 'last_name'),
                ),
                'gender' => $this->request->input('buckaroo-payperemail-gender', 0),

            ],
            'expirationDate' => $this->getExpirationDate(),
            'paymentMethodsAllowed' => $this->getAllowedMethods(),
        ];

        if (isset($this->gateway->usePayPerLink) && $this->gateway->usePayPerLink === true) {
            $payload['merchantSendsEmail'] = true;
        }

        return $payload;
    }

    private function getExpirationDate(): string
    {
        $payperemailExpireDays = $this->gateway->get_option('expirationDate');

        if (! is_scalar($payperemailExpireDays)) {
            return '';
        }

        return date('Y-m-d', time() + (int) $payperemailExpireDays * 86400);
    }

    private function getAllowedMethods(): string
    {
        $methods = $this->gateway->get_option('paymentmethodppe');
        if (is_array($methods)) {
            return implode(',', $methods);
        }

        return '';
    }

    public function beforeReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        Logger::log(__METHOD__, 'Process paypermail');
        if (! is_admin()) {
            return false;
        }

        if ($responseParser->isSuccess()) {
            $payLinkParam = $this->extractPayLink($responseParser);
            $message = $responseParser->get('consumermessage')
                ? 'Email sent successfully.<br>'
                : 'Your paylink: <a target="_blank" href="' . $payLinkParam . '">' . $payLinkParam . '</a>';
            $this->get_order()->add_order_note($message);

            set_transient(
                get_current_user_id() . 'buckarooAdminNotice',
                [
                    'type' => 'success',
                    'message' => $message,
                ]
            );
        } else {
            $paramErrors = (array) $responseParser->get('RequestErrors.ParameterError');
            $parameterError = '';
            foreach ($paramErrors as $value) {
                $parameterError .= '<br/>' . ($value->_ ?? '');
            }
            set_transient(
                get_current_user_id() . 'buckarooAdminNotice',
                [
                    'type' => 'error',
                    'message' => $responseParser->getSubCodeMessage() . ' ' . $parameterError,
                ]
            );
            Logger::log(__METHOD__ . '|10|', $parameterError);
        }

        return [
            'result' => 'success',
            'redirect' => $redirectUrl,
        ];
    }

    public function unsuccessfulReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        Logger::log('Payperemail status check: ' . $responseParser->getStatusCode());
        if (Helper::handleUnsuccessfulPayment($responseParser->getStatusCode())) {
            return [
                'result' => 'error',
                'redirect' => $redirectUrl,
            ];
        }
    }

    protected function extractPayLink(ResponseParser $responseParser)
    {
        $services = $responseParser->get('Services');
        if (! is_array($services)) {
            return false;
        }
        foreach ($services as $service) {
            if (($service['name'] ?? '') === 'payperemail') {
                foreach ($service['parameters'] as $p) {
                    if (! empty($p['name']) && $p['name'] === 'PayLink') {
                        return $p['value'];
                    }
                }
            }
        }

        return false;
    }
}
