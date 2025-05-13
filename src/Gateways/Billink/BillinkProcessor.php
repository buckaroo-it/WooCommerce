<?php

namespace Buckaroo\Woocommerce\Gateways\Billink;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class BillinkProcessor extends AbstractPaymentProcessor
{
    /** {@inheritDoc} */
    public function getAction(): string
    {
        if ($this->isAuthorization()) {
            if (get_post_meta($this->get_order()->get_id(), '_wc_order_authorized', true) == 'yes') {
                return 'capture';
            }

            return 'authorize';
        }

        return parent::getAction();
    }

    private function isAuthorization(): bool
    {
        return $this->gateway->get_option('billinkpayauthorize', 'pay') === 'authorize';
    }

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        return array_merge_recursive(
            $this->getVatnumber(),
            $this->getCoc(),
            $this->getBillingData(),
            $this->getShippingData(),
            ['articles' => $this->getArticles()]
        );
    }

    /**
     * Get vat number
     *
     * @return array<mixed>
     */
    protected function getVatnumber(): array
    {
        $vatNumber = $this->request->input('buckaroo-billink-VatNumber');
        if (
            is_string($vatNumber) &&
            ! empty(trim($vatNumber))
        ) {
            return ['vATNumber' => $vatNumber];
        }

        return [];
    }

    /**
     * Get chamber of commerce number
     *
     * @return array<mixed>
     */
    protected function getCoc(): array
    {
        if (is_string($this->request->input('buckaroo-billink-company-coc-registration'))) {
            return [
                'billing' => [
                    'recipient' => [
                        'chamberOfCommerce' => $this->request->input('buckaroo-billink-company-coc-registration'),
                    ],
                ],
            ];
        }

        return [];
    }

    /**
     * @return array<mixed>
     */
    protected function getBillingData(): array
    {
        $streetParts = $this->order_details->get_billing_address_components();
        $country_code = $this->getAddress('billing', 'country');
        $first_name = $this->getAddress('billing', 'first_name');

        return [
            'billing' => [
                'recipient' => [
                    'category' => $this->getCategory('billing'),
                    'careOf' => $this->getCareOf('billing'),
                    'initials' => $this->getInitials($first_name),
                    'firstName' => $first_name,
                    'lastName' => $this->getAddress('billing', 'last_name'),
                    'birthDate' => $this->getBirthDate(),
                    'salutation' => $this->request->input('buckaroo-billink-gender'),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->getAddress('billing', 'postcode'),
                    'city' => $this->getAddress('billing', 'city'),
                    'country' => $country_code,
                ],
                'phone' => [
                    'mobile' => $this->order_details->get_billing_phone(),
                ],
                'email' => $this->getAddress('billing', 'email'),
            ],
        ];
    }

    /**
     * Get type of request b2b or b2c
     */
    private function getCategory(string $address_type = 'billing'): string
    {
        if (! $this->isCompanyEmpty($this->getAddress($address_type, 'company'))) {
            return 'B2B';
        }

        return 'B2C';
    }

    /**
     * Check if company is empty
     */
    public function isCompanyEmpty(?string $company = null): bool
    {
        return $company === null || strlen(trim($company)) === 0;
    }

    /**
     * Get  careOf
     */
    private function getCareOf(string $address_type = 'billing'): string
    {
        $company = $this->getAddress($address_type, 'company');
        if (! $this->isCompanyEmpty()) {
            return $company;
        }

        return $this->order_details->get_full_name($address_type);
    }

    private function getInitials(string $name): string
    {
        return strtoupper(substr($name, 0, 1));
    }

    /**
     * Get birth date
     *
     * @return null|string
     */
    private function getBirthDate()
    {
        $dateString = $this->request->input('buckaroo-billink-birthdate');
        if (! is_scalar($dateString)) {
            return null;
        }
        $date = strtotime((string) $dateString);
        if ($date === false) {
            return null;
        }

        return @date('d-m-Y', $date);
    }

    /**
     * @return array<mixed>
     */
    protected function getShippingData(): array
    {
        $streetParts = $this->order_details->get_shipping_address_components();
        $country_code = $this->getAddress('shipping', 'country');
        $first_name = $this->getAddress('shipping', 'first_name');

        return [
            'shipping' => [
                'recipient' => [
                    'category' => $this->getCategory('shipping'),
                    'careOf' => $this->getCareOf('shipping'),
                    'initials' => $this->getInitials($first_name),
                    'firstName' => $first_name,
                    'lastName' => $this->getAddress('shipping', 'last_name'),
                    'birthDate' => $this->getBirthDate(),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->getAddress('shipping', 'postcode'),
                    'city' => $this->getAddress('shipping', 'city'),
                    'country' => $country_code,
                ],
            ],
        ];
    }
}
