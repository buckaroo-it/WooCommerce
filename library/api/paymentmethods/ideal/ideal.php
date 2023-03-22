<?php

require_once(dirname(__FILE__) . '/../paymentmethod.php');

/**
 * @package Buckaroo
 */
class BuckarooIDeal extends BuckarooPaymentMethod {
    public $issuer;
    protected $data;

    /**
     * @access public
     */
    public function __construct() {
        $this->type = "ideal";
        $this->version = 2;
    }

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay();
     */
    public function Pay($customVars = array()) {
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
    public static function getIssuerList() {
        $issuerArray = array(
            'ABNANL2A' => array(
                'name' => 'ABN AMRO',
                'logo' => 'logo_abn_s.gif',
            ),
            'ASNBNL21' => array(
                'name' => 'ASN Bank',
                'logo' => 'logo_asn.gif',
            ),
            'BUNQNL2A' => array(
                'name' => 'bunq',
                'logo' => 'logo_bunq.png',
            ),
            'INGBNL2A' => array(
                'name' => 'ING',
                'logo' => 'logo_ing_s.gif',
            ),
            'KNABNL2H' => array(
                'name' => 'Knab',
                'logo' => 'logo_knab_s.gif',
            ),
            'RABONL2U' => array(
                'name' => 'Rabobank',
                'logo' => 'logo_rabo_s.gif',
            ),
            'RBRBNL21' => array(
                'name' => 'RegioBank',
                'logo' => 'regiobanklogo.png',
            ),
            'REVOLT21' => array(
                'name' => 'Revolut',
                'logo' => 'logo_revolutbanken.png',
            ),
            'SNSBNL2A' => array(
                'name' => 'SNS',
                'logo' => 'logo_sns_s.gif',
            ),
            'TRIONL2U' => array(
                'name' => 'Triodos Bank',
                'logo' => 'logo_triodos.gif',
            ),
            'FVLBNL22' => array(
                'name' => 'Van Lanschot',
                'logo' => 'logo_lanschot_s.gif',
            ),
            'BITSNL2A' => array(
                'name' => 'Yoursafe',
                'logo' => 'YourSafe.png',
            ),
        );
        return $issuerArray;
    }
}