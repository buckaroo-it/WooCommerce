<?php

namespace Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\Helper;

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
     * In3 ABN invoices from GrossUnitPrice × qty (must equal AmountDebit) and
     * checks VatAmount == VatPercentage × line net, with net = line gross − VatAmount.
     *
     * {@inheritDoc}
     */
    protected function getArticles(): array
    {
        $articles = [];

        foreach (parent::getArticles() as $article) {
            $unit_price = isset($article['price']) ? (float) $article['price'] : 0.0;
            $quantity = isset($article['quantity']) ? (int) $article['quantity'] : 1;
            if ($quantity < 1) {
                $quantity = 1;
            }

            $line_gross = Helper::roundAmount($unit_price * $quantity);
            if (abs($line_gross) < 0.01) {
                continue;
            }

            $vat_percentage = isset($article['vatPercentage']) ? (float) $article['vatPercentage'] : 0.0;
            $article['vatAmount'] = $this->inclusiveLineVat($line_gross, $vat_percentage);
            $articles[] = $article;
        }

        return $articles;
    }

    /**
     * Inclusive VAT for a gross line: gross × rate / (100 + rate).
     */
    private function inclusiveLineVat(float $line_gross, float $vat_percentage): float
    {
        if ($vat_percentage <= 0) {
            return 0.0;
        }

        return Helper::roundAmount($line_gross * $vat_percentage / (100 + $vat_percentage));
    }

    /**
     * Get B2B billing data.
     *
     * @return array<mixed>
     */
    private function getBilling(): array
    {
        $phone = $this->getPhone();

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
     * Prefer the checkout method field when billing phone was empty.
     */
    private function getPhone(): string
    {
        $own = $this->request->input('buckaroo-zakelijkoprekening-phone');
        if (is_string($own) && trim($own) !== '') {
            return trim($own);
        }

        $phone = $this->getAddress('billing', 'phone');

        return is_string($phone) ? trim($phone) : '';
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
        if (! is_scalar($coc)) {
            return '';
        }

        // Send only digits (strip any spaces/dots/dashes the customer typed).
        return preg_replace('/\D+/', '', (string) $coc);
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
