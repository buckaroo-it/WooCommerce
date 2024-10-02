<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods\PayByBank;

use BuckarooPayByBank;
use WC_Buckaroo\WooCommerce\PaymentMethods\PaymentGatewayHandler;

class PayByBankGateway extends PaymentGatewayHandler
{
    private const SESSION_LAST_ISSUER_LABEL = 'buckaroo_last_payByBank_issuer';

    public static function getActiveIssuerCode()
    {
        if (is_null(WC()->session)) {
            return null;
        }
        return WC()->session->get(self::SESSION_LAST_ISSUER_LABEL);
    }

    public static function getIssuerList()
    {
        $savedBankIssuer = self::getActiveIssuerCode();
        $issuerArray = array(
            'ABNANL2A' => array(
                'name' => 'ABN AMRO',
                'logo' => 'abnamro.svg',
            ),
            'ASNBNL21' => array(
                'name' => 'ASN Bank',
                'logo' => 'asnbank.svg',
            ),
            'INGBNL2A' => array(
                'name' => 'ING',
                'logo' => 'ing.svg',
            ),
            'RABONL2U' => array(
                'name' => 'Rabobank',
                'logo' => 'rabobank.svg',
            ),
            'SNSBNL2A' => array(
                'name' => 'SNS Bank',
                'logo' => 'sns.svg',
            ),
            'RBRBNL21' => array(
                'name' => 'RegioBank',
                'logo' => 'regiobank.svg',
            ),
            'KNABNL2H' => array(
                'name' => 'Knab',
                'logo' => 'knab.svg',
            ),
            'NTSBDEB1' => array(
                'name' => 'N26',
                'logo' => 'n26.svg',
            )
        );

        $issuers = [];

        foreach ($issuerArray as $key => $issuer) {
            $issuer['selected'] = $key === $savedBankIssuer;

            $issuers[$key] = $issuer;
        }

        return $issuers;
    }

    public static function getIssuerLogoUrls()
    {
        $issuers = self::getIssuerList();
        $logos = array();

        foreach ($issuers as $code => $issuer) {
            $logos[$code] = esc_url(plugin_dir_url(BK_PLUGIN_FILE) . "/library/buckaroo_images/ideal/" . $issuer['logo']);
        }

        return $logos;
    }

    public function __construct()
    {
        $this->id = 'buckaroo_paybybank';
        $this->title = 'PayByBank';
        $this->has_fields = true;
        $this->method_title = "Buckaroo PayByBank";
        $this->set_icon('24x24/paybybank.gif', 'svg/paybybank.gif');

        parent::__construct();
        $this->add_refund_support();
        apply_filters('buckaroo_init_payment_class', $this);
    }

    /**
     * Validate frontend fields.
     *
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        $issuer = $this->request('buckaroo-paybybank-issuer');

        if ($issuer === null) {
            wc_add_notice(__("<strong>PayByBank </strong> is a required field.", 'wc-buckaroo-bpe-gateway'), 'error');
        } else
            if (!in_array($issuer, array_keys(BuckarooPayByBank::getIssuerList()))) {
                wc_add_notice(__("A valid PayByBank is required.", 'wc-buckaroo-bpe-gateway'), 'error');
            }
        parent::validate_fields();
    }


    public function init_form_fields()
    {
        parent::init_form_fields();


        $this->form_fields['displaymode'] = [
            'title' => __('Bank selection display', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'options' => ['radio' => __('Radio button'), 'dropdown' => __('Dropdown')],
            'default' => 'radio',
        ];

        unset($this->form_fields['extrachargeamount']); // no fee for this payment method
    }
}