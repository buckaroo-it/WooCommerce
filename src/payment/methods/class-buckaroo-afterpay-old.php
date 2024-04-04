<?php

class Buckaroo_Afterpay_Old extends Buckaroo_Default_Method
{

    public const CUSTOMER_TYPE_B2C = 'b2c';
    public const CUSTOMER_TYPE_B2B = 'b2b';
    public const CUSTOMER_TYPE_BOTH = 'both';

    /** @inheritDoc */
    protected function get_method_body(): array
    {
        return array_merge_recursive(
            [
                'customerIPAddress' => $this->get_ip()
            ],
            $this->get_billing_data(),
            $this->getShippingData(),
            ['articles' => $this->get_articles()]
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
                    'firstName'             => $this->get_address('billing', 'first_name'),
                    'lastName'              => $this->get_address('billing', 'last_name'),
                    'initials'              => $this->order_details->get_initials(
                        $this->order_details->get_full_name('shipping')
                    ),
                    'culture'               => $country_code
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
                    'firstName'             => $this->get_address('shipping', 'first_name'),
                    'lastName'              => $this->get_address('shipping', 'last_name'),
                    'initials'              => $this->order_details->get_initials(
                        $this->order_details->get_full_name('shipping')
                    )
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
            $this->get_birth_date($country_code, 'shipping')
        );
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
                        'birthDate' => $this->get_formated_date(),
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
     * Get birth date
     *
     * @return null|string
     */
    private function get_formated_date()
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

    /**
     * Check if company is empty
     *
     * @param string $company
     *
     * @return boolean
     */
    public function is_company_empty(string $company = null): bool
    {
        return null === $company || strlen(trim($company)) === 0;
    }

    /**
     * Get order articles
     *
     * @return array
     */
    protected function get_articles(): array
    {
        return $this->order_articles->get_products_for_payment();
    }
}
