<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use BuckarooDeps\Buckaroo\Resources\Constants\Gender;

class KlarnaKpProcessor extends AbstractPaymentProcessor
{
    protected OrderDetails $order_details;

    public function getAction(): string
    {
        if (OrderMeta::get($this->get_order(), '_buckaroo_klarnakp_reservation_number')) {
            return 'pay';
        }

        return 'reserve';
    }

    protected function getMethodBody(): array
    {
        $reservation_number = OrderMeta::get(
            $this->get_order(),
            '_buckaroo_klarnakp_reservation_number'
        );

        return array_merge_recursive(
            $reservation_number ? ['reservationNumber' => $reservation_number] : [],
            // Gender selection was removed from checkout; Klarna requires the
            // gender/salutation parameter, so always send "Unknown".
            ['gender' => Gender::UNKNOWN],
            $this->getBillingData(),
            $this->getShippingData(),
            ['articles' => $this->getArticles()]
        );
    }

    protected function getBillingData(): array
    {
        $streetParts = $this->order_details->get_billing_address_components();
        $country_code = $this->getAddress('billing', 'country');

        return [
            'operatingCountry' => $country_code,
            'billing' => [
                'recipient' => [
                    'firstName' => $this->getAddress('billing', 'first_name'),
                    'lastName' => $this->getAddress('billing', 'last_name'),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'zipcode' => $this->getAddress('billing', 'postcode'),
                    'city' => $this->getAddress('billing', 'city'),
                    'country' => $country_code,
                ],
                // Klarna KP has no checkout fields of its own, and the classic
                // checkout posts the fields of every method, so only order data
                // is used (BTI-1880).
                'phone' => [
                    'mobile' => $this->order_details->get_billing_phone(),
                ],
                'email' => $this->getAddress('billing', 'email'),
            ],
        ];
    }

    protected function getShippingData(): array
    {
        $streetParts = $this->order_details->get_shipping_address_components();
        $country_code = $this->getAddress('shipping', 'country');

        return [
            'shipping' => [
                'recipient' => [
                    'firstName' => $this->getAddress('shipping', 'first_name'),
                    'lastName' => $this->getAddress('shipping', 'last_name'),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'zipcode' => $this->getAddress('shipping', 'postcode'),
                    'city' => $this->getAddress('shipping', 'city'),
                    'country' => $country_code,
                ],
                'email' => $this->getAddress('billing', 'email'),
            ],
        ];
    }

    public function beforeReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        OrderMeta::update(
            $this->get_order(),
            '_buckaroo_klarnakp_reservation_number',
            $responseParser->getService('reservationNumber')
        );
    }
}
