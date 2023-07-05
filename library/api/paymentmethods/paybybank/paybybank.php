<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooPayByBank extends BuckarooPaymentMethod
{
    private const SESSION_LAST_ISSUER_LABEL = 'buckaroo_last_payByBank_issuer';
    public $issuer;
    protected $data;

    /**
     * @access public
     */
    public function __construct()
    {
        $this->type = "PayByBank";
        $this->version = 2;
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay();
     */
    public function Pay($customVars = array())
    {
        WC()->session->set(self::SESSION_LAST_ISSUER_LABEL, $this->issuer);

        if ($this->issuer === 'INGBNL2A' && function_exists('wp_is_mobile') && wp_is_mobile()) {
            $this->type = 'ideal'; // send ideal request if issuer is ING and is on mobile
        }
        $this->setCustomVar(
            'issuer',
            $this->issuer
        );
        return parent::Pay();
    }

    /**
     * @access public
     * @return array $issuerArray
     */
    public static function getIssuerList()
    {
        $savedBankIssuer =  WC()->session->get(self::SESSION_LAST_ISSUER_LABEL);
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
            'N26' => array(
                'name' => 'N26',
                'logo' => 'n26.svg',
            ),
        );

        $issuers = [];

        foreach ($issuerArray as $key => $issuer) {
            $issuer['selected'] = $key === $savedBankIssuer;

            $issuers[$key] = $issuer;
        }

        $savedIssuer = array_filter($issuers, function($issuer) {
            return $issuer['selected'];
        });
        $issuers = array_filter($issuers, function($issuer) {
            return !$issuer['selected'];
        });

        return array_merge($savedIssuer, $issuers);
    }
}
