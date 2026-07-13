<?php

namespace Buckaroo\Woocommerce\Gateways\Applepay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Services\Logger;

class ApplepayProcessor extends AbstractPaymentProcessor
{
    protected $data;

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {

        $raw = $this->request->input('paymentData');
        if ($raw === null || $raw === '') {
            $raw = $this->request->input('paymentdata');
        }

        // Temporary diagnostics: trace exactly what the checkout submission
        // delivered, so an empty token can be attributed to the client or to
        // this normalisation step. Remove once the checkout flow is verified.
        Logger::log(__METHOD__ . '|paymentData raw|', [
            'type' => gettype($raw),
            'length' => is_string($raw) ? strlen($raw) : null,
            'preview' => is_string($raw) ? substr($raw, 0, 200) : (is_array($raw) ? array_keys($raw) : $raw),
        ]);

        $paymentData = $this->normalize_payment_data($raw);

        Logger::log(__METHOD__ . '|paymentData normalized|', [
            'keys' => array_keys($paymentData),
            'has_token' => isset($paymentData['token']) && ! empty($paymentData['token']),
        ]);

        $customerCardName = $this->get_customer_name($paymentData);
        if ($customerCardName === '') {
            $customerCardName = trim(
                $this->getAddress('billing', 'first_name') . ' ' . $this->getAddress('billing', 'last_name')
            );
        }

        return [
            'customerCardName' => $customerCardName,
            'paymentData' => $this->get_payment_data($paymentData),
        ];
    }

    /**
     * Normalise the Apple Pay payment payload to an array.
     *
     * @param  mixed  $data
     * @return array
     */
    private function normalize_payment_data($data): array
    {
        if (is_string($data)) {
            $decoded = json_decode(wp_unslash($data), true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * @param  mixed  $data
     */
    private function get_customer_name($data): string
    {
        if (
            isset($data['billingContact']) &&
            isset($data['billingContact']['givenName']) &&
            isset($data['billingContact']['familyName'])
        ) {
            return $data['billingContact']['givenName'] . ' ' . $data['billingContact']['familyName'];
        }

        return '';
    }

    /**
     * @param  mixed  $data
     */
    private function get_payment_data($data): string
    {
        if (isset($data['token']) && ! empty($data['token'])) {
            return base64_encode(json_encode($data['token']));
        }

        // Defensive fallback: some flows may deliver the ApplePayPaymentToken
        // itself (rather than the full ApplePayPayment wrapper). A token is
        // recognisable by its `paymentData` member.
        if (isset($data['paymentData']) && ! empty($data['paymentData'])) {
            Logger::log(__METHOD__ . '|fallback|', 'Payload is a bare token; using it directly');

            return base64_encode(json_encode($data));
        }

        Logger::log(__METHOD__ . '|empty|', 'No Apple Pay token found in payload — Buckaroo would receive empty paymentData');

        return '';
    }
}
