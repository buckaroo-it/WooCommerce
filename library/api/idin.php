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
        $issuers = [
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

        if (BuckarooConfig::getIdinMode() == 'test') {
            $issuers[] = [
                'servicename' => 'BANKNL2Y',
                'displayname' => 'TEST BANK'
            ];
        }
        return $issuers;
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
        if (!BuckarooConfig::isIdin(BuckarooIdin::getCartProductIds())) return true;

        if ($currentIserId = get_current_user_id()) {
            return get_user_meta($currentIserId, 'buckaroo_idin', true);
        } else {
            return WC()->session->get('buckaroo_idin');
        }
        return false;
    }

    public static function setCurrentUserIsVerified($bin)
    {
        if ($currentIserId = get_current_user_id()) {
            add_user_meta($currentIserId, 'buckaroo_idin', 1, true);
            add_user_meta($currentIserId, 'buckaroo_idin_bin', $bin, true);
        } else {
            WC()->session->set('buckaroo_idin', 1);
            WC()->session->set('buckaroo_idin', $bin);
        }
    }

    public static function setCurrentUserIsNotVerified()
    {
        if ($currentIserId = get_current_user_id()) {
            delete_user_meta($currentIserId, 'buckaroo_idin');
            delete_user_meta($currentIserId, 'buckaroo_idin_bin');
        } else {
            WC()->session->set('buckaroo_idin', 0);
            WC()->session->set('buckaroo_idin_bin', 0);
        }
    }

    public static function getCartProductIds()
    {
        global $woocommerce;

        $productIds = [];

        if ($woocommerce->cart) {
            $items = $woocommerce->cart->get_cart();

            foreach ($items as $item) {
                $productIds[] = $item['data']->get_id();
            }
        }

        return $productIds;
    }
}