<?php

include_once(dirname(__FILE__) . '/functions.php');


/**
 * @package Buckaroo
 */
class BuckarooPaymentRequestFactory {

    const REQUEST_TYPE_PAYPAL = 'buckaroopaypal';
    const REQUEST_TYPE_EMPAYMENT = 'empayment';
    const REQUEST_TYPE_IDEAL = 'ideal';
    const REQUEST_TYPE_PAYCONIQ = 'payconiq';
    const REQUEST_TYPE_GIROPAY = 'giropay';
    const REQUEST_TYPE_DIRECTDEBIT = 'directdebit';
    const REQUEST_TYPE_SEPADIRECTDEBIT = 'sepadirectdebit';
    const REQUEST_TYPE_MISTERCASH = 'bancontactmrcash';
    const REQUEST_TYPE_EMAESTRO = 'maestro';
    const REQUEST_TYPE_SOFORTBANKING = 'sofortueberweisung';
    const REQUEST_TYPE_GIFTCARD = 'giftcard';
    const REQUEST_TYPE_CREDITCARD = 'creditcard';
    const REQUEST_TYPE_TRANSFER = 'transfer';

    static public $valid_request_types = array(
        BuckarooPaymentRequestFactory::REQUEST_TYPE_PAYPAL => 'BuckarooPayPal',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_EMPAYMENT => 'Empayment',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_IDEAL => 'IDeal',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_DIRECTDEBIT => 'DirectDebit',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_SEPADIRECTDEBIT => 'SepaDirectDebit',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_PAYCONIQ => 'Payconiq',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_GIROPAY => 'Giropay',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_MISTERCASH => 'MisterCash',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_EMAESTRO => 'EMaestro',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_SOFORTBANKING => 'Sofortbanking',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_GIFTCARD => 'GiftCard',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_CREDITCARD => 'CreditCard',
        BuckarooPaymentRequestFactory::REQUEST_TYPE_TRANSFER => 'Transfer',
    );

    final public static function Create($request_type_id, $data = array()) {

        $class_name = self::$valid_request_types[$request_type_id];
        buckaroo_autoload($class_name);
        if (!class_exists($class_name)) {
            throw new Exception('Payment method not found', '1'); //TODO: ExceptionPayment
        }
        return new $class_name($data);
    }

}