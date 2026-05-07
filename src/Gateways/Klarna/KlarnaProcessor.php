<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;

class KlarnaProcessor extends AbstractPaymentProcessor
{
    public const DATA_REQUEST_META_KEY = '_buckaroo_klarna_data_request_key';

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        $body = array_merge_recursive(
            [
                'operatingCountry' => $this->getOperatingCountry(),
                'gender' => $this->getGender(),
            ],
            $this->getBilling(),
            $this->getShipping(),
            ['articles' => $this->getArticles()]
        );

        if ($this->isReserved()) {
            $dataRequestKey = get_post_meta($this->get_order()->get_id(), self::DATA_REQUEST_META_KEY, true);

            if (is_string($dataRequestKey) && strlen($dataRequestKey) > 0) {
                // Klarna's `klarna` service identifies a reservation by a service-level
                // `DataRequestKey` parameter (not the top-level `OriginalTransactionKey`).
                // Sending it as `originalTransactionKey` causes Buckaroo to reject the
                // capture with "originaltransaction is invalid for the action Pay".
                $body['dataRequestKey'] = $dataRequestKey;
            }
        }

        return $body;
    }

    /**
     * Klarna's `klarna` service rejects order lines with unit_price = 0 (e.g. free
     * shipping methods, $0 free-gift items). Such lines contribute nothing to the
     * order total, so dropping them keeps the article sum equal to the order total
     * while staying within Klarna's validation rules. Scoped to Klarna MoR only.
     */
    protected function getArticles(): array
    {
        return array_values(array_filter(
            parent::getArticles(),
            static function ($article) {
                if (! is_array($article)) {
                    return false;
                }

                $price = isset($article['price']) ? (float) $article['price'] : 0.0;
                $quantity = isset($article['quantity']) ? (int) $article['quantity'] : 0;

                return abs($price) >= 0.01 && $quantity > 0;
            }
        ));
    }

    private function getOperatingCountry(): string
    {
        $country = $this->getAddress('billing', 'country');

        if (! is_string($country) || strlen(trim($country)) === 0) {
            $country = $this->getAddress('shipping', 'country');
        }

        return is_string($country) ? strtoupper($country) : '';
    }

    private function getGender(): int
    {
        $value = $this->request->input($this->gateway->getKlarnaSelector() . '-gender');

        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        // Default to male (1) if no value submitted; Klarna requires a positive integer.
        return 1;
    }

    public function getAction(): string
    {
        return $this->isReserved() ? 'pay' : 'reserve';
    }

    public function beforeReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        $dataRequestKey = $responseParser->getDataRequest();

        if (is_string($dataRequestKey) && strlen($dataRequestKey) > 0) {
            update_post_meta($this->get_order()->get_id(), self::DATA_REQUEST_META_KEY, $dataRequestKey);

            if ($this->isResponseReserved($responseParser)) {
                update_post_meta($this->get_order()->get_id(), 'buckaroo_is_reserved', 'yes');
            }
        }
    }

    private function isReserved(): bool
    {
        return get_post_meta($this->get_order()->get_id(), 'buckaroo_is_reserved', true) === 'yes';
    }

    private function isResponseReserved(ResponseParser $responseParser): bool
    {
        if ($responseParser->isSuccess()) {
            return true;
        }

        return in_array($responseParser->get('coreStatus'), ['completed', 'processing', 'pending', 'on-hold'], true);
    }

    /**
     * @return array<mixed>
     */
    protected function getBilling(): array
    {
        $streetParts = $this->order_details->get_billing_address_components();

        return [
            'billing' => [
                'recipient' => [
                    'firstName' => $this->getAddress('billing', 'first_name'),
                    'lastName' => $this->getAddress('billing', 'last_name'),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->getAddress('billing', 'postcode'),
                    'city' => $this->getAddress('billing', 'city'),
                    'country' => $this->getAddress('billing', 'country'),
                ],
                'phone' => [
                    'mobile' => $this->getPhone($this->order_details->get_billing_phone()),
                ],
                'email' => $this->getAddress('billing', 'email'),
            ],
        ];
    }

    /**
     * Get shipping address data
     *
     * @return array<mixed>
     */
    protected function getShipping(): array
    {
        $streetParts = $this->order_details->get_shipping_address_components();

        return [
            'shipping' => [
                'recipient' => [
                    'firstName' => $this->getAddress('shipping', 'first_name'),
                    'lastName' => $this->getAddress('shipping', 'last_name'),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->getAddress('shipping', 'postcode'),
                    'city' => $this->getAddress('shipping', 'city'),
                    'country' => $this->getAddress('shipping', 'country'),
                ],
                'email' => $this->getAddress('shipping', 'email') ?: $this->getAddress('billing', 'email'),
            ],
        ];
    }

    private function getPhone(string $phone): string
    {
        $input_phone = $this->order_details->cleanup_phone(
            $this->request->input($this->gateway->getKlarnaSelector() . '-phone')
        );
        if (strlen(trim($input_phone)) > 0) {
            return $input_phone;
        }

        return $phone;
    }
}
