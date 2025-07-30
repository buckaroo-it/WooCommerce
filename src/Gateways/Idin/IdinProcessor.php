<?php

namespace Buckaroo\Woocommerce\Gateways\Idin;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\Logger;

class IdinProcessor extends AbstractPaymentProcessor
{
    private static $idinCategories;

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
     * @return array $issuerArray
     */
    public static function getIssuerList()
    {
        $issuers = [
            [
                'servicename' => 'ABNANL2A',
                'displayname' => 'ABN AMRO',
            ],
            [
                'servicename' => 'ASNBNL21',
                'displayname' => 'ASN Bank',
            ],
            [
                'servicename' => 'BUNQNL2A',
                'displayname' => 'bunq',
            ],
            [
                'servicename' => 'INGBNL2A',
                'displayname' => 'ING',
            ],
            [
                'servicename' => 'RABONL2U',
                'displayname' => 'Rabobank',
            ],
            [
                'servicename' => 'RBRBNL21',
                'displayname' => 'RegioBank',
            ],
            [
                'servicename' => 'SNSBNL2A',
                'displayname' => 'SNS Bank',
            ],
            [
                'servicename' => 'TRIONL2U',
                'displayname' => 'Triodos Bank',
            ],
        ];

        if ((new IdinGateway())->getMode() == 'test') {
            $issuers[] = [
                'servicename' => 'BANKNL2Y',
                'displayname' => 'TEST BANK',
            ];
        }

        return $issuers;
    }

    public static function checkCurrentUserIsVerified()
    {
        if (! self::isIdin(self::getCartProductIds())) {
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

        $productIds = [];

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

    /** {@inheritDoc} */
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
            'pushURLFailure' => $this->get_push_url(),
            'clientIP' => $this->getIp(),
            'issuer' => $this->gateway->issuer,
            // 'additionalParameters' => [
            // 'current_user_id' => get_current_user_id(),
            // ],
        ];
    }

    public function get_return_url($order_id = null): string
    {
        $referer = sanitize_text_field($_SERVER['HTTP_REFERER'] ?? '');

        return add_query_arg(
            [
                'wc-api' => 'WC_Gateway_Buckaroo_idin-return',
                'bk_redirect' => urlencode($referer) ?: wc_get_checkout_url(),
            ],
            home_url('/')
        );
    }

    private function get_push_url(): string
    {
        return add_query_arg('wc-api', 'wc_push_buckaroo', home_url('/'));
    }

    public static function isIdin($ids = [])
    {
        $isIdin = false;
        $options = get_option('woocommerce_buckaroo_mastersettings_settings');

        if ($options['useidin'] ?? false) {
            if (! isset(self::$idinCategories)) {
                self::$idinCategories = $options['idincategories'] ?? [];
            }
            if (self::$idinCategories) {
                if ($ids) {
                    foreach ($ids as $id) {
                        if ($productCategories = get_the_terms($id, 'product_cat')) {
                            foreach ($productCategories as $productCategory) {
                                if (in_array($productCategory->term_id, self::$idinCategories)) {
                                    $isIdin = true;

                                    return $isIdin;
                                }
                            }
                        }
                    }
                }

                return $isIdin;
            } else {
                $isIdin = true;

                return $isIdin;
            }
        } else {
            return $isIdin;
        }
    }

    public function beforeReturnHandler(ResponseParser $responseParser, string $redirectUrl)
    {
        if (! $this->get_order()->get_id() && ! $responseParser->isSuccess()) {
            Logger::log(__METHOD__ . '|25|');

            return [
                'result' => 'error',
                'message' => $responseParser->getSubCodeMessage() ?: '',
            ];
        }

        return false;
    }
}
