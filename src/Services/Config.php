<?php

namespace Buckaroo\Woocommerce\Services;

class Config
{

    const NAME = 'buckaroo3';
    const PLUGIN_NAME = 'Buckaroo BPE 3.0 official plugin';
    const VERSION = '3.13.2';
    const SHIPPING_SKU = 'WC8888';

    const GENDER_MALE = 1;
    const GENDER_FEMALE = 2;
    const GENDER_OTHER = 0;
    const GENDER_NOT_SPECIFIED = 9;

    const PAYMENT_PAYPEREMAIL = 'buckaroo-payperemail';
    const PAYMENT_BILLINK = 'buckaroo-billink';
    const PAYMENT_KLARNAKP = 'buckaroo-klarnakp';
    const PAYMENT_KLARNAPAY = 'buckaroo-klarnapay';
    const PAYMENT_KLARNAPII = 'buckaroo-klarnapii';

    private static $idinCategories;

    /**
     * Check if mode is test or live
     *
     * @access public
     * @param string $key
     * @return string $val
     */
    public static function get($key, $paymentId = null)
    {
        $val = null;

        if (is_null($paymentId)) {
            $paymentId = isset($GLOBALS['plugin_id']) ? $GLOBALS['plugin_id'] : '';
        } else {
            $paymentId = 'woocommerce_buckaroo_' . $paymentId . '_settings';
        }
        $options = array();
        if (!empty($paymentId)) {
            $options = get_option($paymentId, null);
        }

        $options['enabled'] = isset($options['enabled']) ? $options['enabled'] : false;
        $masterOptions = get_option('woocommerce_buckaroo_mastersettings_settings', null);
        if (is_array($masterOptions)) {
            unset($masterOptions['enabled']);
            $options = array_replace($options, $masterOptions);
        }

        switch ($key) {
            case 'CULTURE':
                $val = $options['culture'] ?? null;
                break;
            case 'BUCKAROO_TRANSDESC':
                $val = empty($options['transactiondescription']) ? 'Buckaroo' : $options['transactiondescription'];
                break;
            case 'BUCKAROO_MERCHANT_KEY':
                $val = $options['merchantkey'] ?? '';
                break;
            case 'BUCKAROO_SECRET_KEY':
                $val = $options['secretkey'] ?? '';
                break;
            case 'BUCKAROO_DEBUG':
                $options = get_option('woocommerce_buckaroo_mastersettings_settings', null);// Debug switch only in mastersettings
                $val = $options['debugmode'] ?? null;
                break;
            case 'BUCKAROO_IDIN_CATEGORIES':
                $val = (empty($options['idincategories']) ? array() : $options['idincategories']);
                break;
            case 'BUCKAROO_CREDITCARD_CARDS':
                $val = 'amex,cartebancaire,cartebleuevisa,dankort,mastercard,postepay,visa,visaelectron,vpay';
                break;
            case 'BUCKAROO_CREDITCARD_ALLOWED_CARDS':
                $val = 'amex,cartebancaire,cartebleuevisa,dankort,mastercard,postepay,visa,visaelectron,vpay';
                break;
            case 'BUCKAROO_GIFTCARD_ALLOWED_CARDS':
                $val = 'westlandbon,ideal,ippies,babygiftcard,bancontact,babyparkgiftcard,beautywellness,boekenbon,boekenvoordeel,designshopsgiftcard,fashioncheque,fashionucadeaukaart,fijncadeau,koffiecadeau,kokenzo,kookcadeau,nationaleentertainmentcard,naturesgift,podiumcadeaukaart,shoesaccessories,webshopgiftcard,wijncadeau,wonenzo,yourgift,vvvgiftcard,parfumcadeaukaart';
                break;
            default:
                if (isset($options[$key]) && !empty($options[$key])) {
                    $val = $options[$key];
                }
        }

        return $val;
    }


    public static function getAllGendersForPaymentMethods(): array
    {
        $defaultGenders = array(
            'male' => self::GENDER_MALE,
            'female' => self::GENDER_FEMALE,
            'they' => self::GENDER_OTHER,
            'unknown' => self::GENDER_NOT_SPECIFIED,
        );

        $billinkGenders = array(
            'male' => 'Male',
            'female' => 'Female',
            'they' => 'Unknown',
            'unknown' => 'Unknown',
        );

        $klarnaGenders = array(
            'male' => 'male',
            'female' => 'female',
        );

        return array(
            self::PAYMENT_PAYPEREMAIL => $defaultGenders,
            self::PAYMENT_BILLINK => $billinkGenders,
            self::PAYMENT_KLARNAKP => $klarnaGenders,
            self::PAYMENT_KLARNAPAY => $klarnaGenders,
            self::PAYMENT_KLARNAPII => $klarnaGenders,
        );
    }

    public static function translateGender($genderKey)
    {
        switch ($genderKey) {
            case 'male':
                return __('He/him', 'wc-buckaroo-bpe-gateway');
            case 'female':
                return __('She/her', 'wc-buckaroo-bpe-gateway');
            case 'they':
                return __('They/them', 'wc-buckaroo-bpe-gateway');
            case 'unknown':
                return __('I prefer not to say', 'wc-buckaroo-bpe-gateway');
            default:
                return $genderKey;
        }
    }
}