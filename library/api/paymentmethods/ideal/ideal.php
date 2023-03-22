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
            $this->_getIssuer($this->issuer)
        );
        return parent::Pay();
    }

    /**
     * @access public
     * @return array $issuerArray
     */
    public static function getIssuerList() {
        $issuerArray = array(
            'ABNAMRO' => array(
                'name' => 'ABN AMRO',
                'logo' => 'logo_abn_s.gif',
            ),
            'ASNBANK' => array(
                'name' => 'ASN Bank',
                'logo' => 'logo_asn.gif',
            ),
            'INGBANK' => array(
                'name' => 'ING',
                'logo' => 'logo_ing_s.gif',
            ),
            'RABOBANK' => array(
                'name' => 'Rabobank',
                'logo' => 'logo_rabo_s.gif',
            ),
            'SNSBANK' => array(
                'name' => 'SNS Bank',
                'logo' => 'logo_sns_s.gif',
            ),
            'SNSREGIO' => array(
                'name' => 'RegioBank',
                'logo' => 'regiobanklogo.png',
            ),
            'TRIODOS' => array(
                'name' => 'Triodos Bank',
                'logo' => 'logo_triodos.gif',
            ),
            'LANSCHOT' => array(
                'name' => 'Van Lanschot',
                'logo' => 'logo_lanschot_s.gif',
            ),
            'KNAB' => array(
                'name' => 'Knab',
                'logo' => 'logo_knab_s.gif',
            ),
            'BUNQ' => array(
                'name' => 'bunq',
                'logo' => 'logo_bunq.png',
            ),
            'REVOLUT' => array(
                'name' => 'Revolut',
                'logo' => 'logo_revolutbanken.png',
            ),
            'YOURSAFE' => array(
                'name' => 'Yoursafe',
                'logo' => 'YourSafe.png',
            ),
        );
        return $issuerArray;
    }

    /**
     * @access public
     * @param string $issuer
     * @return array $issuerCode
     */
    protected function _getIssuer($issuer) {
        $issuerCode = '';
        switch ($issuer) {
            case 'ABNAMRO':
                $issuerCode = 'ABNANL2A';
                break;
            case 'ASNBANK':
                $issuerCode = 'ASNBNL21';
                break;
            case 'INGBANK':
                $issuerCode = 'INGBNL2A';
                break;
            case 'RABOBANK':
                $issuerCode = 'RABONL2U';
                break;
            case 'SNSBANK':
                $issuerCode = 'SNSBNL2A';
                break;
            case 'SNSREGIO':
                $issuerCode = 'RBRBNL21';
                break;
            case 'TRIODOS':
                $issuerCode = 'TRIONL2U';
                break;
            case 'LANSCHOT':
                $issuerCode = 'FVLBNL22';
                break;
            case 'KNAB':
                $issuerCode = 'KNABNL2H';
                break;
            case 'BUNQ':
                $issuerCode = 'BUNQNL2A';
                break;
            case 'REVOLUT':
                $issuerCode = 'REVOLT21';
                break;
            case 'YOURSAFE':
                $issuerCode = 'BITSNL2A';
                break;
        }

        return $issuerCode;
    }
}