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
                'logo' => 'abnamro.svg',
            ),
            'ASNBANK' => array(
                'name' => 'ASN Bank',
                'logo' => 'asnbank.svg',
            ),
            'INGBANK' => array(
                'name' => 'ING',
                'logo' => 'ing.svg',
            ),
            'RABOBANK' => array(
                'name' => 'Rabobank',
                'logo' => 'rabobank.svg',
            ),
            'SNSBANK' => array(
                'name' => 'SNS Bank',
                'logo' => 'sns.svg',
            ),
            'SNSREGIO' => array(
                'name' => 'RegioBank',
                'logo' => 'regiobank.svg',
            ),
            'TRIODOS' => array(
                'name' => 'Triodos Bank',
                'logo' => 'triodos.svg',
            ),
            'LANSCHOT' => array(
                'name' => 'Van Lanschot',
                'logo' => 'vanlanschot.svg',
            ),
            'KNAB' => array(
                'name' => 'Knab',
                'logo' => 'knab.svg',
            ),
            'BUNQ' => array(
                'name' => 'bunq',
                'logo' => 'bunq.svg',
            ),
            'REVOLUT' => array(
                'name' => 'Revolut',
                'logo' => 'revolut.svg',
            ),
            'YOURSAFE' => array(
                'name' => 'Yoursafe',
                'logo' => 'yoursafe.svg',
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