<?php

namespace Buckaroo\Woocommerce\Gateways\PayByBank;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;

class PayByBankProcessor extends AbstractPaymentProcessor
{
    private const SESSION_LAST_ISSUER_LABEL = 'buckaroo_last_payByBank_issuer';

    public static function getIssuerList()
    {
        $savedBankIssuer = self::getActiveIssuerCode();
        $issuerArray = [
            'ABNANL2A' => [
                'name' => 'ABN AMRO',
                'logo' => 'abnamro.svg',
            ],
            'ASNBNL21' => [
                'name' => 'ASN Bank',
                'logo' => 'asnbank.svg',
            ],
            'INGBNL2A' => [
                'name' => 'ING',
                'logo' => 'ing.svg',
            ],
            'RABONL2U' => [
                'name' => 'Rabobank',
                'logo' => 'rabobank.svg',
            ],
            'SNSBNL2A' => [
                'name' => 'SNS Bank',
                'logo' => 'sns.svg',
            ],
            'RBRBNL21' => [
                'name' => 'RegioBank',
                'logo' => 'regiobank.svg',
            ],
            'KNABNL2H' => [
                'name' => 'Knab',
                'logo' => 'knab.svg',
            ],
            'NTSBDEB1' => [
                'name' => 'N26',
                'logo' => 'n26.svg',
            ],
        ];

        $issuers = [];

        foreach ($issuerArray as $key => $issuer) {
            $issuer['selected'] = $key === $savedBankIssuer;

            $issuers[$key] = $issuer;
        }

        return $issuers;
    }

    public static function getActiveIssuerCode()
    {
        if (is_null(WC()->session)) {
            return null;
        }

        return WC()->session->get(self::SESSION_LAST_ISSUER_LABEL);
    }

    protected function getMethodBody(): array
    {
        return [
            'issuer' => $this->request->input('buckaroo-paybybank-issuer'),
        ];
    }

    public static function getIssuerLogoUrls()
    {
        $issuers = self::getIssuerList();
        $logos = [];

        foreach ($issuers as $code => $issuer) {
            $logos[$code] = esc_url(plugin_dir_url(BK_PLUGIN_FILE) . '/library/buckaroo_images/ideal/' . $issuer['logo']);
        }

        return $logos;
    }
}
