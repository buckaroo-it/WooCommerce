<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewGateway;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;

class KlarnaKpProcessor extends AbstractPaymentProcessor
{
    protected OrderDetails $order_details;

    public function getAction(): string
    {
        if (get_post_meta($this->get_order()->get_id(), '_buckaroo_klarnakp_reservation_number', true)) {
            return 'pay';
        }

        return 'reserve';
    }

    protected function getMethodBody(): array
    {
        $reservation_number = get_post_meta(
            $this->get_order()->get_id(),
            '_buckaroo_klarnakp_reservation_number',
            true
        );

        return array_merge_recursive(
            $reservation_number ? ['reservationNumber' => $reservation_number] : [],
            $this->getBillingData(),
            $this->getShippingData(),
            ['articles' => $this->getArticles()]
        );
    }

    protected function getBillingData(): array
    {
        $streetParts = $this->order_details->get_billing_address_components();
        $country_code = $this->getAddress('billing', 'country');
        $data = [
            'operatingCountry' => $country_code,
            'billing' => [
                'recipient' => [
                    'firstName' => $this->getAddress('billing', 'first_name'),
                    'lastName' => $this->getAddress('billing', 'last_name'),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'zipcode' => '2521VA',
                    'city' => $this->getAddress('billing', 'city'),
                    'country' => $country_code,
                ],
                'phone' => [
                    'mobile' => $this->getPhone($this->order_details->get_billing_phone()),
                ],
                'email' => $this->getAddress('billing', 'email'),
            ],
        ];

        return array_merge_recursive(
            $data,
            $this->getCompany(),
        );
    }

    private function getPhone(string $phone): string
    {
        return $this->request->input('buckaroo-afterpaynew-phone', $phone);
    }

    protected function getShippingData(): array
    {
        $streetParts = $this->order_details->get_shipping_address_components();
        $country_code = $this->getAddress('shipping', 'country');

        $data = [
            'shipping' => [
                'recipient' => [
                    'firstName' => $this->getAddress('shipping', 'first_name'),
                    'lastName' => $this->getAddress('shipping', 'last_name'),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'zipcode' => '2521VA',
                    'city' => $this->getAddress('shipping', 'city'),
                    'country' => $country_code,
                ],
                'email' => $this->getAddress('billing', 'email'),
            ],
        ];

        return array_merge_recursive(
            $data,
            $this->getCompany('shipping'),
        );
    }

    /**
     * @return array<mixed>
     */
    protected function getCompany(string $address_type = 'billing'): array
    {
        $company = $this->getAddress($address_type, 'company');
        if (
            $this->isB2b() &&
            $this->getAddress($address_type, 'country') === 'NL' &&
            ! $this->isCompanyEmpty($company)
        ) {
            return [
                $address_type => [
                    'recipient' => [
                        'companyName' => $company,
                        'chamberOfCommerce' => $this->request->input('buckaroo-afterpaynew-company-coc-registration'),
                    ],
                ],
            ];
        }

        return [];
    }

    public function isB2b(): bool
    {
        return $this->gateway->get_option('customer_type') !== AfterpayNewGateway::CUSTOMER_TYPE_B2C;
    }

    /**
     * Check if company is empty
     */
    public function isCompanyEmpty(?string $company = null): bool
    {
        return $company === null || strlen(trim($company)) === 0;
    }

    public function beforeReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        update_post_meta(
            $this->get_order()->get_id(),
            '_buckaroo_klarnakp_reservation_number',
            $responseParser->getService('reservationNumber')
        );
    }
}
