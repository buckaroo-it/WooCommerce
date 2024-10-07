<?php

namespace Buckaroo\Woocommerce\Gateways\Idin;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use BuckarooConfig;

class IdinProcessor extends AbstractPaymentProcessor
{
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

    /**
     * @access public
     * @return array $issuerArray
     */
    public static function getIssuerList()
    {
        $issuers = array(
            array(
                'servicename' => 'ABNANL2A',
                'displayname' => 'ABN AMRO',
            ),
            array(
                'servicename' => 'ASNBNL21',
                'displayname' => 'ASN Bank',
            ),
            array(
                'servicename' => 'BUNQNL2A',
                'displayname' => 'bunq',
            ),
            array(
                'servicename' => 'INGBNL2A',
                'displayname' => 'ING',
            ),
            array(
                'servicename' => 'RABONL2U',
                'displayname' => 'Rabobank',
            ),
            array(
                'servicename' => 'RBRBNL21',
                'displayname' => 'RegioBank',
            ),
            array(
                'servicename' => 'SNSBNL2A',
                'displayname' => 'SNS Bank',
            ),
            array(
                'servicename' => 'TRIONL2U',
                'displayname' => 'Triodos Bank',
            ),
        );

        if (BuckarooConfig::getIdinMode() == 'test') {
            $issuers[] = array(
                'servicename' => 'BANKNL2Y',
                'displayname' => 'TEST BANK',
            );
        }
        return $issuers;
    }

    public static function checkCurrentUserIsVerified()
    {
        if (!BuckarooConfig::isIdin(self::getCartProductIds())) {
            return true;
        }

        if ($currentUserId = get_current_user_id()) {
            return get_user_meta($currentUserId, 'buckaroo_idin', true);
        } else {
            return WC()->session->get('buckaroo_idin');
        }
    }

    public static function getCartProductIds()
    {
        global $woocommerce;

        $productIds = array();

        if ($woocommerce->cart) {
            $items = $woocommerce->cart->get_cart();

            foreach ($items as $item) {
                $productIds[] = $item['data']->get_id();
            }
        }

        return $productIds;
    }

    public static function setCurrentUserIsVerified($bin)
    {
        $currentUserId = get_current_user_id();

        if ($currentUserId) {
            add_user_meta($currentUserId, 'buckaroo_idin', 1, true);
            add_user_meta($currentUserId, 'buckaroo_idin_bin', $bin, true);
        } else {
            WC()->session->set('buckaroo_idin', 1);
            WC()->session->set('buckaroo_idin_bin', $bin);
        }
    }

    public static function setCurrentUserIsNotVerified()
    {
        $currentUserId = get_current_user_id();

        if ($currentUserId) {
            delete_user_meta($currentUserId, 'buckaroo_idin');
            delete_user_meta($currentUserId, 'buckaroo_idin_bin');
        } else {
            WC()->session->set('buckaroo_idin', 0);
            WC()->session->set('buckaroo_idin_bin', 0);
        }
    }

    /** @inheritDoc */
    public function getAction(): string
    {
        return 'verify';
    }

    public function getBody(): array
    {
        return [
            'returnURL' => $this->get_return_url(),
            'cancelURL' => $this->get_return_url(),
            'pushURL' => $this->get_push_url(),
            'clientIP' => $this->get_ip(),
            'issuer' => $this->gateway->issuer,
//            'additionalParameters' => [
//                'current_user_id' => get_current_user_id(),
//            ],
        ];
    }

    public function get_return_url($order_id = null): string
    {
        $referer = sanitize_text_field($_SERVER['HTTP_REFERER'] ?? '');

        return add_query_arg(
            array(
                'wc-api' => 'WC_Gateway_Buckaroo_idin-return',
                'bk_redirect' => urlencode($referer) ?: wc_get_checkout_url(),
            ),
            home_url('/')
        );
    }

    private function get_push_url(): string
    {
        return add_query_arg('wc-api', 'wc_push_buckaroo', home_url('/'));
    }
}