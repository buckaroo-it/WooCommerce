<?php

class Buckaroo_Afterpay extends Buckaroo_Default_Method
{

    public const CUSTOMER_TYPE_B2C = 'b2c';
    public const CUSTOMER_TYPE_B2B = 'b2b';
    public const CUSTOMER_TYPE_BOTH = 'both';

    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return array_merge_recursive(
            $this->get_billing_data(),
            $this->getShippingData(),
            ['articles' =>$this->get_articles()]
        );
    }

    /** @inheritDoc */
    public function get_action(): string
    {
        if ($this->is_authorization()) {
            return 'Authorize';
        }
        return parent::get_action();
    }

    private function is_authorization(): bool
    {
        return $this->gateway->get_option('afterpaynewpayauthorize', 'pay') === 'authorize';
    }

    /**
     * @return array<mixed>
     */
    protected function get_billing_data(): array
    {
        $streetParts  = $this->order_details->get_billing_address_components();
        $country_code = $this->get_address('billing', 'country');
        $data = [
            'billing' => [
                'recipient' => [
                    'category'              => $this->get_category('billing'),
                    'careOf'                => $this->get_care_of('billing'),
                    'firstName'             => $this->get_address('billing', 'first_name'),
                    'lastName'              => $this->get_address('billing', 'last_name')
                ],
                'address' => [
                    'street'                => $streetParts->get_street(),
                    'houseNumber'           => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode'               => $this->get_address('billing', 'postcode'),
                    'city'                  => $this->get_address('billing', 'city'),
                    'country'               => $country_code,
                ],
                'phone' => [
                    'mobile'        => $this->get_phone($this->order_details->get_billing_phone()),
                ],
                'email'         => $this->get_address('billing', 'email')
            ]
        ];
        return array_merge_recursive(
            $data,
            $this->get_company(),
            $this->get_birth_date($country_code)
        );
    }

    /**
     * @return array<mixed>
     */
    protected function getShippingData(): array
    {
        $streetParts  = $this->order_details->get_shipping_address_components();
        $country_code = $this->get_address('shipping', 'country');

        $data = [
            'shipping' => [
                'recipient' => [
                    'category'              => $this->get_category('shipping'),
                    'careOf'                => $this->get_care_of('shipping'),
                    'firstName'             => $this->get_address('shipping', 'first_name'),
                    'lastName'              => $this->get_address('shipping', 'last_name')
                ],
                'address' => [
                    'street'                => $streetParts->get_street(),
                    'houseNumber'           => $streetParts->get_house_number(),
                    'houseNumberAdditional' => $streetParts->get_number_additional(),
                    'zipcode'               => $this->get_address('shipping', 'postcode'),
                    'city'                  => $this->get_address('shipping', 'city'),
                    'country'               => $country_code,
                ],
            ],
        ];
        return array_merge_recursive(
            $data,
            $this->get_company('shipping'),
            $this->get_birth_date($country_code, 'shipping')
        );
    }

    /**
     * @param string $address_type
     *
     * @return array<mixed>
     */
    protected function get_company(
        string $address_type = 'billing'
    ): array {
        $company = $this->get_address($address_type, "company");
        if (
            $this->is_b2b() &&
            $this->get_address($address_type, "country") === 'NL' &&
            !$this->isCompanyEmpty($company)
        ) {
            return [
                $address_type => [
                    'recipient'        => [
                        'companyName'   => $company,
                        'chamberOfCommerce' => $this->request('buckaroo-afterpaynew-company-coc-registration'),
                    ]
                ]
            ];
        }
        return [];
    }

    /**
     * @param string $country_code
     * @param string $type
     *
     * @return array<mixed>
     */
    protected function get_birth_date(
        string $country_code,
        string $type = 'billing'
    ): array {
        if (in_array($country_code, ['NL', 'BE'])) {
            return [
                $type => [
                    'recipient' => [
                        'birthDate' => $this->getBirthDate(),
                    ]
                ]
            ];
        }
        return [];
    }

    private function get_phone(string $phone): string
    {
        return $this->request('buckaroo-afterpaynew-phone', $phone);
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
        if (
            $this->is_b2b() &&
            $this->get_address($address_type, "country") === 'NL' &&
            !$this->isCompanyEmpty($this->get_address($address_type, "company"))
        ) {
            return 'company';
        }
        return 'person';
    }

    /**
     * Get birth date
     *
     * @return null|string
     */
    private function getBirthDate()
    {

        $dateString = $this->request('buckaroo-afterpaynew-birthdate');
        if (!is_scalar($dateString)) {
            return null;
        }
        $date = strtotime((string)$dateString);
        if ($date === false) {
            return null;
        }

        return @date("d-m-Y", $date);
    }

    public function is_b2b(): bool
    {
        return $this->gateway->get_option('customer_type') !== self::CUSTOMER_TYPE_B2C;
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
}
