<?php

namespace Buckaroo\Woocommerce\Gateways\PayPerEmail;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class PayPerEmailGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = PayPerEmailProcessor::class;
    public $paymentmethodppe;
    public $frontendVisible;

    protected array $supportedCurrencies = [
        'ARS', 'AUD', 'BRL', 'CAD', 'CHF', 'CNY',
        'CZK', 'DKK', 'EUR', 'GBP', 'HRK', 'ISK',
        'JPY', 'LTL', 'LVL', 'MXN', 'NOK', 'NZD',
        'PLN', 'RUB', 'SEK', 'TRY', 'USD', 'ZAR',
    ];

    public function __construct()
    {
        $this->id = 'buckaroo_payperemail';
        $this->title = 'PayPerEmail';
        $this->has_fields = true;
        $this->method_title = 'Buckaroo PayPerEmail';
        $this->setIcon('payperemail.png', 'svg/payperemail.svg');

        parent::__construct();
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if ($this->isVisibleOnFrontend()) {
            if ($this->request->input('buckaroo-payperemail-gender') === null) {
                wc_add_notice(__('Please select gender', 'wc-buckaroo-bpe-gateway'), 'error');
            }

            $gender = $this->request->input('buckaroo-payperemail-gender');

            if (!in_array($gender, array('0', '1', '2', '9'))) {
                wc_add_notice(__('Unknown gender', 'wc-buckaroo-bpe-gateway'), 'error');
            }

            if ($this->request->input('buckaroo-payperemail-firstname') === null) {
                wc_add_notice(__('Please enter firstname', 'wc-buckaroo-bpe-gateway'), 'error');
            }

            if ($this->request->input('buckaroo-payperemail-lastname') === null) {
                wc_add_notice(__('Please enter lastname', 'wc-buckaroo-bpe-gateway'), 'error');
            }
            if ($this->request->input('buckaroo-payperemail-email') === null) {
                wc_add_notice(__('Please enter email', 'wc-buckaroo-bpe-gateway'), 'error');
            } elseif (!is_email($this->request->input('buckaroo-payperemail-email'))) {
                wc_add_notice(__('Please enter valid email', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        parent::validate_fields();
    }

    public function isVisibleOnFrontend()
    {
        if (!empty($this->frontendVisible) && strtolower($this->frontendVisible) === 'yes') {
            return true;
        }

        return false;
    }


    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {

        parent::init_form_fields();

        $this->form_fields['show_PayPerEmail_frontend'] = array(
            'title' => __('Show on Checkout page', 'wc-buckaroo-bpe-gateway'),
            'type' => 'checkbox',
            'description' => __('Show PayPerEmail on Checkout page', 'wc-buckaroo-bpe-gateway'),
            'default' => 'no',
        );

        $this->form_fields['show_PayLink'] = array(
            'title' => __('Show PayLink', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Show PayLink in admin order actions', 'wc-buckaroo-bpe-gateway'),
            'options' => array(
                'TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'),
                'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway'),
            ),
            'default' => 'TRUE',
        );

        $this->form_fields['show_PayPerEmail'] = array(
            'title' => __('Show PayPerEmail', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Show PayPerEmail in admin order actions', 'wc-buckaroo-bpe-gateway'),
            'options' => array(
                'TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'),
                'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway'),
            ),
            'default' => 'TRUE',
        );

        $this->form_fields['expirationDate'] = array(
            'title' => __('Due date (in days)', 'wc-buckaroo-bpe-gateway'),
            'type' => 'text',
            'description' => __('The expiration date for the paylink.', 'wc-buckaroo-bpe-gateway'),
            'default' => '',
        );

        $this->form_fields['paymentmethodppe'] = array(
            'title' => __('Allowed methods', 'wc-buckaroo-bpe-gateway'),
            'type' => 'multiselect',
            'options' => array(
                'amex' => 'American Express',
                'cartebancaire' => 'Carte Bancaire',
                'cartebleuevisa' => 'Carte Bleue',
                'dankort' => 'Dankort',
                'mastercard' => 'Mastercard',
                'postepay' => 'PostePay',
                'visa' => 'Visa',
                'visaelectron' => 'Visa Electron',
                'vpay' => 'Vpay',
                'maestro' => 'Maestro',
                'bancontactmrcash' => 'Bancontact / Mr Cash',
                'transfer' => 'Bank Transfer',
                'giftcard' => 'Giftcards',
                'ideal' => 'iDEAL',
                'paypal' => 'PayPal',
                'sepadirectdebit' => 'SEPA Direct Debit',
                'sofortueberweisung' => 'Sofort Banking',
                'belfius' => 'Belfius',
                'Przelewy24' => 'P24',
            ),
            'description' => __('Select which methods appear to the customer', 'wc-buckaroo-bpe-gateway'),
            'default' => array('amex', 'cartebancaire', 'cartebleuevisa', 'dankort', 'mastercard', 'postepay', 'visa', 'visaelectron', 'vpay', 'maestro', 'bancontactmrcash', 'transfer', 'giftcard', 'ideal', 'paypal', 'sepadirectdebit', 'sofortueberweisung', 'belfius', 'Przelewy24'),
        );
    }

    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->paymentmethodppe = $this->get_option('paymentmethodppe', '');
        $this->frontendVisible = $this->get_option('show_PayPerEmail_frontend', '');
    }


    public function handleHooks()
    {
        add_filter('woocommerce_order_actions', function ($actions) {
            global $theorder;

            if ($this->get_option('enabled') == 'yes') {
                if (in_array($theorder->get_status(), array('auto-draft', 'pending', 'on-hold'))) {
                    if ($this->get_option('show_PayPerEmail') == 'TRUE') {
                        $actions['buckaroo_send_admin_payperemail'] = esc_html__('Send a PayPerEmail', 'woocommerce');
                    }
                }
                if (in_array($theorder->get_status(), array('pending', 'pending', 'on-hold', 'failed'))) {
                    if ($this->get_option('show_PayLink') == 'TRUE') {
                        $actions['buckaroo_create_paylink'] = esc_html__('Create PayLink', 'woocommerce');
                    }
                }
            }
        }, 10, 1);

        add_action('woocommerce_order_action_buckaroo_send_admin_payperemail', function ($order) {
            $gateway = new PayPerEmailGateway();
            if (isset($gateway)) {
                $response = $gateway->process_payment($order->get_id());
                wp_redirect($response);
            }
        }, 10, 1);

        add_action('woocommerce_order_action_buckaroo_create_paylink', function ($order) {
            $gateway = new PayPerEmailGateway();
            if (isset($gateway)) {
                $response = $gateway->process_payment($order->get_id(), 1);
                wp_redirect($response);
            }
        }, 10, 1);
    }
}