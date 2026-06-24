<?php

namespace Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;

class ZakelijkOpRekeningProcessor extends AbstractPaymentProcessor
{
    /**
     * The Buckaroo routing value that selects the "Zakelijk op rekening"
     * (powered by ABN AMRO) variant of the In3 service.
     */
    public const ROUTE = 'AbnB2b';

    /**
     * Only the separate authorize / capture flow is supported.
     */
    public function getAction(): string
    {
        return 'authorize';
    }

    /** {@inheritDoc} */
    protected function getMethodBody(): array
    {
        return array_merge(
            $this->getBilling(),
            $this->getShipping(),
            [
                'route' => self::ROUTE,
                'articles' => $this->getArticles(),
            ]
        );
    }

    /**
     * Get B2B billing data.
     *
     * @return array<mixed>
     */
    private function getBilling(): array
    {
        $phone = $this->request->input(
            'buckaroo-zakelijkoprekening-phone',
            $this->getAddress('billing', 'phone')
        );

        $first_name = $this->getAddress('billing', 'first_name');

        return [
            'billing' => [
                'recipient' => [
                    'category' => 'B2B',
                    'careOf' => $this->getCareOf('billing'),
                    'companyName' => $this->getCompanyName('billing'),
                    'chamberOfCommerce' => $this->getCocNumber(),
                    'initials' => $this->order_details->get_initials(
                        $this->order_details->get_full_name()
                    ),
                    'firstName' => $first_name,
                    'lastName' => $this->getAddress('billing', 'last_name'),
                    'customerNumber' => (string) (get_current_user_id() ?? ''),
                    'phone' => $phone,
                    'country' => $this->getAddress('billing', 'country'),
                ],
                'email' => $this->getAddress('billing', 'email'),
                'phone' => [
                    'phone' => $phone,
                ],
                'address' => $this->getAddressPayload('billing'),
            ],
        ];
    }

    /**
     * Get B2B shipping data.
     *
     * @return array<mixed>
     */
    private function getShipping(): array
    {
        return [
            'shipping' => [
                'recipient' => [
                    'category' => 'B2B',
                    'careOf' => $this->getCareOf('shipping'),
                    'companyName' => $this->getCompanyName('shipping'),
                    'chamberOfCommerce' => $this->getCocNumber(),
                    'firstName' => $this->getAddress('shipping', 'first_name'),
                    'lastName' => $this->getAddress('shipping', 'last_name'),
                ],
                'address' => $this->getAddressPayload('shipping'),
            ],
        ];
    }

    /**
     * Get the address payload for the given address type.
     *
     * @return array<mixed>
     */
    private function getAddressPayload(string $address_type): array
    {
        $streetParts = $address_type === 'shipping'
            ? $this->order_details->get_shipping_address_components()
            : $this->order_details->get_billing_address_components();

        $data = [
            'street' => $streetParts->get_street(),
            'houseNumber' => $streetParts->get_house_number(),
            'zipcode' => $this->getAddress($address_type, 'postcode'),
            'city' => $this->getAddress($address_type, 'city'),
            'country' => $this->getAddress($address_type, 'country'),
        ];

        if (strlen($streetParts->get_number_additional()) > 0) {
            $data['houseNumberAdditional'] = $streetParts->get_number_additional();
        }

        return $data;
    }

    /**
     * Resolve the company name, falling back to the customer's full name.
     */
    private function getCompanyName(string $address_type): string
    {
        // Company name entered in the payment method itself takes precedence
        // (the WooCommerce billing "Company" field may be hidden).
        $own = $this->request->input('buckaroo-zakelijkoprekening-company');
        if (is_string($own) && strlen(trim($own)) > 0) {
            return trim($own);
        }

        $company = $this->getAddress($address_type, 'company');
        if (is_string($company) && strlen(trim($company)) > 0) {
            return $company;
        }

        return $this->order_details->get_full_name($address_type);
    }

    /**
     * careOf is the company name when present, otherwise the full name.
     */
    private function getCareOf(string $address_type): string
    {
        return $this->getCompanyName($address_type);
    }

    /**
     * Get the Chamber of Commerce (KvK) number entered at checkout.
     */
    private function getCocNumber(): string
    {
        $coc = $this->request->input('buckaroo-zakelijkoprekening-company-coc-registration');

        return is_scalar($coc) ? (string) $coc : '';
    }

    /** {@inheritDoc} */
    public function unsuccessfulReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        if ($responseParser->isPendingProcessing()) {
            return [
                'result' => 'failure',
                'redirect' => $redirectUrl,
            ];
        }

        return false;
    }
}
