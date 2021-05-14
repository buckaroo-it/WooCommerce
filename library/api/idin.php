<?php

/**
 * @package Buckaroo
 */
class BuckarooIdin
{
    /**
     * @access public
     * @return array $issuerArray
     */
    public static function getIssuerList()
    {
        return [
            [
                'servicename' => 'BANKNL2Y',
                'displayname' => 'TEST BANK'
            ],
            [
                'servicename' => 'ABNANL2A',
                'displayname' => 'ABN AMRO'
            ],
            [
                'servicename' => 'ASNBNL21',
                'displayname' => 'ASN Bank'
            ],
            [
                'servicename' => 'BUNQNL2A',
                'displayname' => 'bunq'
            ],
            [
                'servicename' => 'INGBNL2A',
                'displayname' => 'ING'
            ],
            [
                'servicename' => 'RABONL2U',
                'displayname' => 'Rabobank'
            ],
            [
                'servicename' => 'RBRBNL21',
                'displayname' => 'RegioBank'
            ],
            [
                'servicename' => 'SNSBNL2A',
                'displayname' => 'SNS Bank'
            ],
            [
                'servicename' => 'TRIONL2U',
                'displayname' => 'Triodos Bank'
            ]
        ];
    }

    public static function checkIfValidIssuer($code)
    {
        $issuerList = self::getIssuerList();
        foreach ($issuerList as $issuer) {
            if ($issuer['servicename'] == $code) {
                return true;
            }
        }
        return false;
    }

    public static function checkCurrentUserIsVerified()
    {
        if (!BuckarooConfig::isIdin()) return true;

        if ($currentIserId = get_current_user_id()) {
            return get_user_meta($currentIserId, 'buckaroo_idin', true);
        } else {
            return WC()->session->get('buckaroo_idin');
        }
        return false;
    }

    public static function setCurrentUserIsVerified()
    {
        if ($currentIserId = get_current_user_id()) {
            add_user_meta($currentIserId, 'buckaroo_idin', 1, true);
        } else {
            WC()->session->set('buckaroo_idin', 1);
        }
    }

    public static function setCurrentUserIsNotVerified()
    {
        if ($currentIserId = get_current_user_id()) {
            delete_user_meta($currentIserId, 'buckaroo_idin');
        } else {
            WC()->session->set('buckaroo_idin', 0);
        }
    }
}