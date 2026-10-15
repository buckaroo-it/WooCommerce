<?php

namespace Buckaroo\Woocommerce\Gateways\Idin;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\Logger;

class IdinProcessor extends AbstractPaymentProcessor
{
    /**
     * Nonce action for the identify and reset endpoints. The `woocommerce`
     * prefix makes WooCommerce bind guest nonces to the customer session.
     */
    public const NONCE_ACTION = 'woocommerce-buckaroo-idin';

    /** How long a successful verification is accepted. */
    public const VERIFICATION_TTL = 30 * DAY_IN_SECONDS;

    /** How long a started verification can be completed. */
    public const PENDING_TTL = HOUR_IN_SECONDS;

    private static $idinCategories;

    /** @var string|null */
    private $verificationNonce;

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

        if (! self::getStoredValue('buckaroo_idin')) {
            return false;
        }

        $verifiedAt = (int) self::getStoredValue('buckaroo_idin_verified_at');

        return $verifiedAt > 0 && (time() - $verifiedAt) < self::VERIFICATION_TTL;
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
        self::setStoredValue('buckaroo_idin', 1);
        self::setStoredValue('buckaroo_idin_bin', $bin);
        self::setStoredValue('buckaroo_idin_verified_at', time());
    }

    public static function setCurrentUserIsNotVerified()
    {
        $currentUserId = get_current_user_id();

        if ($currentUserId) {
            delete_user_meta($currentUserId, 'buckaroo_idin');
            delete_user_meta($currentUserId, 'buckaroo_idin_bin');
            delete_user_meta($currentUserId, 'buckaroo_idin_verified_at');
            delete_user_meta($currentUserId, 'buckaroo_idin_pending');
        } else {
            WC()->session->set('buckaroo_idin', 0);
            WC()->session->set('buckaroo_idin_bin', 0);
            WC()->session->set('buckaroo_idin_verified_at', null);
            WC()->session->set('buckaroo_idin_pending', null);
        }
    }

    /**
     * Record that the current visitor started a verification and return the
     * nonce sent along with it. Starting again replaces the previous one.
     */
    public static function startVerification(): string
    {
        if (! get_current_user_id() && WC()->session && ! WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }

        $nonce = wp_generate_password(32, false);

        self::setStoredValue(
            'buckaroo_idin_pending',
            [
                'nonce' => $nonce,
                'created' => time(),
            ]
        );

        return $nonce;
    }

    /**
     * Check that a reply belongs to the verification the current visitor
     * started. A matching nonce is cleared, so a reply can be used only once.
     *
     * @param  mixed  $nonce  The nonce echoed back in the signed reply.
     */
    public static function consumeVerification($nonce): bool
    {
        $pending = self::getStoredValue('buckaroo_idin_pending');

        if (
            ! is_string($nonce) || $nonce === '' ||
            ! is_array($pending) || ! is_string($pending['nonce'] ?? null) ||
            ! hash_equals($pending['nonce'], $nonce)
        ) {
            return false;
        }

        self::setStoredValue('buckaroo_idin_pending', null);

        return (time() - (int) ($pending['created'] ?? 0)) < self::PENDING_TTL;
    }

    private static function getStoredValue(string $key)
    {
        if ($currentUserId = get_current_user_id()) {
            return get_user_meta($currentUserId, $key, true);
        }

        return WC()->session ? WC()->session->get($key) : null;
    }

    private static function setStoredValue(string $key, $value): void
    {
        if ($currentUserId = get_current_user_id()) {
            if ($value === null) {
                delete_user_meta($currentUserId, $key);
            } else {
                update_user_meta($currentUserId, $key, $value);
            }

            return;
        }

        if (WC()->session) {
            WC()->session->set($key, $value);
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
            'additionalParameters' => [
                'idin_nonce' => $this->getVerificationNonce(),
            ],
        ];
    }

    private function getVerificationNonce(): string
    {
        if ($this->verificationNonce === null) {
            $this->verificationNonce = self::startVerification();
        }

        return $this->verificationNonce;
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
