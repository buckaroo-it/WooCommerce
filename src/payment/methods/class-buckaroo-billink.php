<?php

namespace WC_Buckaroo\WooCommerce\Payment\Methods;
class Buckaroo_Billink extends Buckaroo_Default_Method
{
    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return array_merge_recursive(
            $this->get_vat_number(),
            $this->get_coc(),
            $this->get_billing_data(),
            $this->get_shipping_data(),
            ['articles' => $this->get_articles()]
        );
    }

    /** @inheritDoc */
    public function get_action(): string
    {
        if (!empty(trim($this->order_details->get_billing("company")))) {
            return 'authorize';
        }
        return 'pay';
    }

    /**
     * @return array<mixed>
     */
    protected function get_billing_data(): array
    {
        $streetParts = $this->order_details->get_billing_address_components();
        $country_code = $this->get_address('billing', 'country');
        $first_name = $this->get_address('billing', 'first_name');
        return [
            'billing' => [
                'recipient' => [
                    'category' => $this->get_category('billing'),
                    'careOf' => $this->get_care_of('billing'),
                    'initials' => $this->get_initials($first_name),
                    'firstName' => $first_name,
                    'lastName' => $this->get_address('billing', 'last_name'),
                    'birthDate' => $this->get_birth_date(),
                    'salutation' => $this->request('buckaroo-billink-gender')
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->get_address('billing', 'postcode'),
                    'city' => $this->get_address('billing', 'city'),
                    'country' => $country_code,
                ],
                'phone' => [
                    'mobile' => $this->order_details->get_billing_phone(),
                ],
                'email' => $this->get_address('billing', 'email')
            ]
        ];
    }

    /**
     *
     * @return array<mixed>
     */
    protected function get_shipping_data(): array
    {
        $streetParts = $this->order_details->get_shipping_address_components();
        $country_code = $this->get_address('shipping', 'country');
        $first_name = $this->get_address('shipping', 'first_name');

        return [
            'shipping' => [
                'recipient' => [
                    'category' => $this->get_category('shipping'),
                    'careOf' => $this->get_care_of('shipping'),
                    'initials' => $this->get_initials($first_name),
                    'firstName' => $first_name,
                    'lastName' => $this->get_address('shipping', 'last_name'),
                    'birthDate' => $this->get_birth_date(),
                ],
                'address' => [
                    'street' => $streetParts->get_street(),
                    'houseNumber' => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode' => $this->get_address('shipping', 'postcode'),
                    'city' => $this->get_address('shipping', 'city'),
                    'country' => $country_code,
                ],
            ],
        ];
    }

    /**
     * Get vat number
     *
     * @return array<mixed>
     */
    protected function get_vat_number(): array
    {
        $vatNumber = $this->request('buckaroo-billink-VatNumber');
        if (
            is_string($vatNumber) &&
            !empty(trim($vatNumber))
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
    protected function get_coc(): array
    {
        if (is_string($this->request('buckaroo-billink-company-coc-registration'))) {
            return [
                'billing' => [
                    'recipient' => [
                        'chamberOfCommerce' => $this->request('buckaroo-billink-company-coc-registration')
                    ]
                ]
            ];
        }
        return [];
    }

    /**
     * Get  careOf
     *
     * @param string $address_type
     *
     * @return string
     */
    private function get_care_of(string $address_type = 'billing'): string
    {
        $company = $this->get_address($address_type, "company");
        if (!$this->isCompanyEmpty()) {
            return $company;
        }

        return $this->order_details->get_full_name($address_type);
    }

    /**
     * Get type of request b2b or b2c
     *
     * @param string $address_type
     * @return string
     */
    private function get_category(string $address_type = 'billing'): string
    {
        if (!$this->isCompanyEmpty($this->get_address($address_type, "company"))) {
            return 'B2B';
        }
        return 'B2C';
    }

    /**
     * Check if company is empty
     *
     * @param string $company
     *
     * @return boolean
     */
    public function isCompanyEmpty(string $company = null): bool
    {
        return null === $company || strlen(trim($company)) === 0;
    }

    /**
     *
     * @param string $name
     *
     * @return string
     */
    private function get_initials(string $name): string
    {
        return strtoupper(substr($name, 0, 1));
    }

    /**
     * Get birth date
     *
     * @return null|string
     */
    private function get_birth_date()
    {

        $dateString = $this->request('buckaroo-billink-birthdate');
        if (!is_scalar($dateString)) {
            return null;
        }
        $date = strtotime((string)$dateString);
        if ($date === false) {
            return null;
        }

        return @date("d-m-Y", $date);
    }
}
