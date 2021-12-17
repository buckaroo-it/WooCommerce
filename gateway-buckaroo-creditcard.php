<?php

require_once dirname(__FILE__) . '/library/api/paymentmethods/creditcard/creditcard.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Creditcard extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooCreditCard::class;
    public $creditCardProvider = [];
    public function __construct()
    {
        $this->id                     = 'buckaroo_creditcard';
        $this->title                  = 'Creditcards';
        $this->has_fields             = true;
        $this->method_title           = "Buckaroo Creditcards";
        $this->setIcon('24x24/cc.gif', 'new/CreditCards.png');

        parent::__construct();

        $this->addRefundSupport();
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->creditCardProvider     = $this->get_option('AllowedProvider', []);
        $this->creditcardmethod       = $this->get_option('creditcardmethod', "redirect");
        $this->creditcardpayauthorize = $this->get_option('creditcardpayauthorize', "Pay");
    }

    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {

        $action = ucfirst(isset($this->creditcardpayauthorize) ? $this->creditcardpayauthorize : 'pay');

        if ($action == 'Authorize') {
            $captures         = get_post_meta($order_id, 'buckaroo_capture', false);
            $previous_refunds = get_post_meta($order_id, 'buckaroo_refund', false);

            if ($captures == false || count($captures) < 1) {
                return new WP_Error('error_refund_trid', __("Order is not captured yet, you can only refund captured orders"));
            }

            // Merge captures with previous refunds
            foreach ($captures as &$captureJson) {
                $capture = json_decode($captureJson, true);
                foreach ($previous_refunds as &$refundJson) {
                    $refund = json_decode($refundJson, true);
                    if (isset($refund['OriginalCaptureTransactionKey']) && $capture['OriginalTransactionKey'] == $refund['OriginalCaptureTransactionKey']) {
                        if ($capture['amount'] >= $refund['amount']) {
                            $capture['amount'] -= $refund['amount'];
                            $refund['amount'] = 0;
                        } else {
                            $refund['amount'] -= $capture['amount'];
                            $capture['amount'] = 0;
                        }
                    }
                    $refundJson = json_encode($refund);
                }
                $captureJson = json_encode($capture);
            }

            $captures = json_decode(json_encode($captures), true);

            $refundQueue = array();

            // Find free `slots` in captures
            foreach ($captures as $captureJson) {
                $capture = json_decode($captureJson, true);

                if ($amount > 0) {
                    if ($amount > $capture['amount']) {
                        $refundQueue[$capture['OriginalTransactionKey']] = $capture['amount'];
                        $amount -= $capture['amount'];
                    } else {
                        $refundQueue[$capture['OriginalTransactionKey']] = $amount;
                        $amount                                          = 0;
                    }
                }
            }

            // Check if something cannot be refunded
            $NotRefundable = false;

            if ($amount > 0) {
                $NotRefundable = true;
            }

            if ($NotRefundable) {
                return new WP_Error('error_refund_trid', __("Refund amount cannot be bigger than the amount you have captured"));
            }

            $refund_result = array();
            foreach ($refundQueue as $OriginalTransactionKey => $amount) {
                if ($amount > 0) {
                    $refund_result[] = $this->process_partial_refunds($order_id, $amount, $reason, $OriginalTransactionKey);
                }
            }

            foreach ($refund_result as $result) {
                if ($result !== true) {
                    if (isset($result->errors['error_refund'][0])) {
                        return new WP_Error('error_refund_trid', __($result->errors['error_refund'][0]));
                    } else {
                        return new WP_Error('error_refund_trid', __("Unexpected error occured while processing refund, please check your transactions in the Buckaroo plaza."));
                    }
                }
            }

            return true;

        } else {
            return $this->process_partial_refunds($order_id, $amount, $reason);
        }
    }

    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_partial_refunds($order_id, $amount = null, $reason = '', $OriginalTransactionKey = null,
        $line_item_totals = null, $line_item_tax_totals = null, $line_item_qtys = null) {
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            return new WP_Error('error_refund_trid', __("Refund failed: Order not in ready state, Buckaroo transaction ID do not exists."));
        }
        update_post_meta($order_id, '_pushallowed', 'busy');
        
        /** @var BuckarooCreditCard */
        $creditcard = $this->createCreditRequest($order, $amount, $reason);

        $orderRefundData = [];

        if ($line_item_qtys === null) {
            $line_item_qtys = json_decode(stripslashes($_POST['line_item_qtys']), true);
        }

        if ($line_item_totals === null) {
            $line_item_totals = json_decode(stripslashes($_POST['line_item_totals']), true);
        }

        if ($line_item_tax_totals === null) {
            $line_item_tax_totals = json_decode(stripslashes($_POST['line_item_tax_totals']), true);
        }


        if ($OriginalTransactionKey !== null) {
            $creditcard->OriginalTransactionKey = $OriginalTransactionKey;
        }

        $creditcard->setType(
            get_post_meta(
                (int) str_replace('#', '', $order->get_order_number()),
                '_payment_method_transaction',
                true
            )
        );

        try {
            $creditcard->checkRefundData(
                $creditcard->getOrderRefundData()
            );
            $response = $creditcard->Refund();
        } catch (exception $e) {
            update_post_meta($order_id, '_pushallowed', 'ok');
            return new WP_Error('refund_error', __($e->getMessage()));
        }

        $final_response = fn_buckaroo_process_refund($response ?? null, $order, $amount, $this->currency);

        if ($final_response === true) {
            // Store the transaction_key together with refunded products, we need this for later refunding actions
            $refund_data = json_encode(['OriginalTransactionKey' => $response->transactions, 'OriginalCaptureTransactionKey' => $creditcard->OriginalTransactionKey, 'amount' => $amount]);
            add_post_meta($order_id, 'buckaroo_refund', $refund_data, false);
        }

        return $final_response;

    }

    /**
     * Validate fields
     * @return void;
     */
    public function validate_fields()
    {
        parent::validate_fields();
        
        if (empty($_POST['buckaroo-creditcard-issuer'])) {
            wc_add_notice(__("Select a credit card.", 'wc-buckaroo-bpe-gateway'), 'error');
        }
        if ($this->get_option('creditcardmethod') == 'encrypt') {
            if (empty($_POST['buckaroo-creditcard-cardyear'])) {
                wc_add_notice(__("Enter expiration year field", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            $fullYear = date('Y');
            $year     = date('y');
            if ((int) $_POST['buckaroo-creditcard-cardyear'] < (int) $fullYear && strlen($_POST['buckaroo-creditcard-cardyear']) === 4) {
                wc_add_notice(__("Enter valid expiration year", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            if ((int) $_POST['buckaroo-creditcard-cardyear'] < (int) $year && strlen($_POST['buckaroo-creditcard-cardyear']) !== 4) {
                wc_add_notice(__("Enter valid expiration year", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
        }

        return;
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        $this->setOrderCapture(
            $order_id,
            'Creditcard',
            $_POST["buckaroo-creditcard-issuer"]
        );
        $order = getWCOrder($order_id);
        /** @var BuckarooCreditCard */
        $creditcard = $this->createDebitRequest($order);

        $creditCardMethod       = isset($this->creditcardmethod) ? $this->creditcardmethod : 'redirect';
        $creditCardPayAuthorize = isset($this->creditcardpayauthorize) ? $this->creditcardpayauthorize : 'pay';

        $customVars = array();

        if (isset($_POST["buckaroo-encrypted-data"])) {
            $customVars['CreditCardDataEncrypted'] = $_POST["buckaroo-encrypted-data"];
        } else {
            $customVars['CreditCardDataEncrypted'] = null;
        }

        if (isset($_POST["buckaroo-creditcard-issuer"])) {
            $customVars['CreditCardIssuer'] = $_POST["buckaroo-creditcard-issuer"];
        } else {
            $customVars['CreditCardIssuer'] = null;
        }

        if ($creditCardMethod == 'encrypt' && $this->isSecure()) {
            // In this case we only send the encrypted card data.

            // If not then send an error.
            if (empty($_POST["buckaroo-encrypted-data"])) {
                wc_add_notice(__("The credit card data is incorrect, please check the values", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }

            if (empty($_POST["buckaroo-creditcard-issuer"])) {
                wc_add_notice(__("You havent selected your credit card issuer", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }

            $creditcard->CreditCardDataEncrypted = $_POST["buckaroo-encrypted-data"];

            if ($creditCardPayAuthorize == 'pay') {
                $response = $creditcard->PayEncrypt($customVars);
            } else if ($creditCardPayAuthorize == 'authorize') {
                $response = $creditcard->AuthorizeEncrypt($customVars, $order);
            } else {
                wc_add_notice(__("The type of credit card request is not defined. Contact Buckaroo.", 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }

            return fn_buckaroo_process_response($this, $response);
        }

    
        
        if ($creditCardPayAuthorize == 'pay') {
            $response = $creditcard->Pay($customVars);
        } else if ($creditCardPayAuthorize == 'authorize') {
            $response = $creditcard->AuthorizeCC($customVars, $order);
        } else {
            wc_add_notice(__("The type of credit card request is not defined. Contact Buckaroo.", 'wc-buckaroo-bpe-gateway'), 'error');
            return;
        }

        return fn_buckaroo_process_response($this, $response);
    }

    public function process_capture()
    {
        $order_id             = $_POST['order_id'];
        $woocommerce          = getWooCommerceObject();
        $GLOBALS['plugin_id'] = $this->plugin_id . $this->id . '_settings';
        $creditcard           = new BuckarooCreditCard();

        $order = getWCOrder($order_id);

        $customVars['CreditCardIssuer'] = get_post_meta($order->get_id(), '_wc_order_payment_issuer', true);

        $creditcard->amountDedit            = str_replace(',', '.', $_POST['capture_amount']);
        $payment_type                       = str_replace('buckaroo_', '', strtolower($this->id));
        $creditcard->OriginalTransactionKey = $order->get_transaction_id();
        $creditcard->channel                = BuckarooConfig::getChannel($payment_type, __FUNCTION__);
        $creditcard->currency               = $this->currency;
        $creditcard->description            = $this->transactiondescription;
        $creditcard->invoiceId              = (string) getUniqInvoiceId($order->get_order_number());
        $creditcard->orderId                = (string) $order_id;
        $creditcard->returnUrl              = $this->notify_url;

        $response = $creditcard->Capture($customVars);

        // Store the transaction_key together with captured amount, we need this for refunding
        $capture_data = json_encode(array('OriginalTransactionKey' => $response->transactions, 'amount' => $creditcard->amountDedit));
        add_post_meta($order_id, 'buckaroo_capture', $capture_data, false);

        return fn_buckaroo_process_capture($response, $order, $this->currency);

    }
    public function getCardsList()
    {
        $cards     = array();
        $cardsDesc = array("amex" => "American Express",
            "cartebancaire"           => "Carte Bancaire",
            "cartebleuevisa"          => "Carte Bleue",
            "dankort"                 => "Dankort",
            "mastercard"              => "Mastercard",
            "postepay"                => "PostePay",
            "visa"                    => "Visa",
            "visaelectron"            => "Visa Electron",
            "vpay"                    => "Vpay",
            "maestro"                 => "Maestro");
        if (is_array($this->creditCardProvider)) {
            foreach ($this->creditCardProvider as $value) {
                $cards[] = array("servicename" => $value, "displayname" => $cardsDesc[$value]);
            }
        }
        return $cards;
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {

        parent::init_form_fields();

        $this->form_fields['creditcardmethod'] = array(
            'title'       => __('Credit card method', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Redirect user to Buckaroo or enter creditcard information inline in the checkout. SSL is required to enable inline creditcard information', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('redirect' => 'Redirect', 'encrypt' => 'Inline'),
            'default'     => 'encrypt',
            'desc_tip'    =>__('Check with Buckaroo whether Client Side Encryption is enabled, otherwise transactions will fail. If in doubt, please contact us.', 'wc-buckaroo-bpe-gateway'),
        
        );
        $this->form_fields['creditcardpayauthorize'] = array(
            'title'       => __('Credit card Pay or Capture', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'select',
            'description' => __('Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway'),
            'options'     => array('pay' => 'Pay', 'authorize' => 'Authorize'),
            'default'     => 'pay');
        $this->form_fields['AllowedProvider'] = array(
            'title'       => __('Allowed provider', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'multiselect',
            'options'     => array(
                'amex'           => 'American Express',
                'cartebancaire'  => 'Carte Bancaire',
                'cartebleuevisa' => 'Carte Bleue',
                'dankort'        => 'Dankort',
                'mastercard'     => 'Mastercard',
                'postepay'       => 'PostePay',
                'visa'           => 'Visa',
                'visaelectron'   => 'Visa Electron',
                'vpay'           => 'Vpay',
                'maestro'        => "Maestro",
            ),
            'description' => __('select which Creditecard providers  will be appear to customer', 'wc-buckaroo-bpe-gateway'),
            'default'     => array('amex', 'cartebancaire', 'cartebleuevisa', 'dankort', 'mastercard', 'postepay', 'visa', 'visaelectron', 'vpay', 'maestro'),
        );

    }

    /**
     * Returns true if secure (https), false if not (http)
     */
    public function isSecure()
    {
        return
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $_SERVER['SERVER_PORT'] == 443;
    }

}
