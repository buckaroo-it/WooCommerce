<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class CreditCardGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = CreditCardProcessor::class;
    public const SHOW_IN_CHECKOUT_FIELD = 'show_in_checkout';
    public $creditCardProvider = array();

    protected $creditcardmethod;

    protected $creditcardpayauthorize;

    protected array $supportedCurrencies = [
        'ARS', 'AUD', 'BRL', 'CAD', 'CHF', 'CNY',
        'CZK', 'DKK', 'EUR', 'GBP', 'HRK', 'ISK',
        'JPY', 'LTL', 'LVL', 'MXN', 'NOK', 'NZD',
        'PLN', 'RUB', 'SEK', 'TRY', 'USD', 'ZAR',
    ];

    public function __construct()
    {
        $this->setParameters();
        $this->setCreditcardIcon();
        $this->has_fields = true;

        parent::__construct();

        $this->addRefundSupport();
    }

    /**
     * Set gateway parameters
     *
     * @return void
     */
    public function setParameters()
    {
        $this->id = 'buckaroo_creditcard';
        $this->title = 'Credit and debit card';
        $this->method_title = 'Buckaroo Credit and debit card';
    }

    /**
     * Set credicard icon
     *
     * @return void
     */
    public function setCreditcardIcon()
    {
        $this->setIcon('24x24/cc.gif', 'svg/creditcards.svg');
    }

    /**
     * Validate fields
     *
     * @return void;
     */
    public function validate_fields()
    {
        parent::validate_fields();

        $issuer = $this->request->input($this->id . '-creditcard-issuer');
        if ($issuer === null) {
            wc_add_notice(__('Select a credit or debit card.', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if (!in_array(
            $issuer,
            array(
                'amex',
                'cartebancaire',
                'cartebleuevisa',
                'dankort',
                'maestro',
                'mastercard',
                'nexi',
                'postepay',
                'visa',
                'visaelectron',
                'vpay',
            )
        )) {
            wc_add_notice(__('A valid credit card is required.', 'wc-buckaroo-bpe-gateway'), 'error');
        }
        if ($this->get_option('creditcardmethod') == 'encrypt' && $this->isSecure()) {
            $card_year = $this->request->input($this->id . '-cardyear');

            if ($card_year === null) {
                wc_add_notice(__('Enter expiration year field', 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            $fullYear = date('Y');
            $year = date('y');

            if ((int)$card_year < (int)$fullYear && strlen($card_year) === 4) {
                wc_add_notice(__('Enter valid expiration year', 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
            if ((int)$card_year < (int)$year && strlen($card_year) !== 4) {
                wc_add_notice(__('Enter valid expiration year', 'wc-buckaroo-bpe-gateway'), 'error');
                return;
            }
        }

        return;
    }

    /**
     * Returns true if secure (https), false if not (http)
     */
    public function isSecure()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || !empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443;
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable fn_buckaroo_process_response()
     */
    public function process_payment($order_id)
    {
        if ($this->creditcardpayauthorize == 'authorize') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
            $this->set_order_capture($order_id, "Creditcard", $this->request->input($this->id . "-creditcard-issuer"));
        }
        return parent::process_payment($order_id);
    }

    public function getCardsList()
    {
        $cards = array();
        $cardsDesc = array(
            'amex' => 'American Express',
            'cartebancaire' => 'Carte Bancaire',
            'cartebleuevisa' => 'Carte Bleue',
            'dankort' => 'Dankort',
            'maestro' => 'Maestro',
            'mastercard' => 'Mastercard',
            'nexi' => 'Nexi',
            'postepay' => 'PostePay',
            'visa' => 'Visa',
            'visaelectron' => 'Visa Electron',
            'vpay' => 'Vpay',
        );
        if (is_array($this->creditCardProvider)) {
            foreach ($this->creditCardProvider as $value) {
                $cards[] = array(
                    'servicename' => $value,
                    'displayname' => $cardsDesc[$value],
                );
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
            'title' => __('Credit and debit card method', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Redirect user to Buckaroo or enter credit or debit card information (directly) inline in the checkout. SSL is required to enable inline credit or debit card information.', 'wc-buckaroo-bpe-gateway'),
            'options' => array(
                'redirect' => 'Redirect',
                'encrypt' => 'Inline',
            ),
            'default' => 'redirect',
            'desc_tip' => __('Check with Buckaroo whether Client Side Encryption is enabled, otherwise transactions will fail. If in doubt, please contact us.', 'wc-buckaroo-bpe-gateway'),

        );
        $this->form_fields['creditcardpayauthorize'] = array(
            'title' => __('Credit and debit card flow', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway'),
            'options' => array(
                'pay' => 'Pay',
                'authorize' => 'Authorize',
            ),
            'default' => 'pay',
        );
        $this->form_fields['AllowedProvider'] = array(
            'title' => __('Allowed provider', 'wc-buckaroo-bpe-gateway'),
            'type' => 'multiselect',
            'options' => array(
                'amex' => 'American Express',
                'cartebancaire' => 'Carte Bancaire',
                'cartebleuevisa' => 'Carte Bleue',
                'dankort' => 'Dankort',
                'maestro' => 'Maestro',
                'mastercard' => 'Mastercard',
                'nexi' => 'Nexi',
                'postepay' => 'PostePay',
                'visa' => 'Visa',
                'visaelectron' => 'Visa Electron',
                'vpay' => 'Vpay',
            ),
            'description' => __('Select which credit or debit card providers will be visible to customer', 'wc-buckaroo-bpe-gateway'),
            'default' => array(
                'amex',
                'cartebancaire',
                'cartebleuevisa',
                'dankort',
                'mastercard',
                'maestro',
                'nexi',
                'postepay',
                'visa',
                'visaelectron',
                'vpay',
            ),
        );
        $this->form_fields[self::SHOW_IN_CHECKOUT_FIELD] = array(
            'title' => __('Show separate in checkout', 'wc-buckaroo-bpe-gateway'),
            'type' => 'multiselect',
            'options' => array(
                '' => __('None', 'wc-buckaroo-bpe-gateway'),
                'amex' => 'American Express',
                'cartebancaire' => 'Carte Bancaire',
                'cartebleuevisa' => 'Carte Bleue',
                'dankort' => 'Dankort',
                'maestro' => 'Maestro',
                'mastercard' => 'Mastercard',
                'nexi' => 'Nexi',
                'postepay' => 'PostePay',
                'visa' => 'Visa',
                'visaelectron' => 'Visa Electron',
                'vpay' => 'Vpay',
            ),
            'description' => __('Select which credit or debit card providers will be shown separately in the checkout', 'wc-buckaroo-bpe-gateway'),
            'default' => array(),
        );
    }

    /** @inheritDoc */
    public function process_admin_options()
    {
        parent::process_admin_options();
        $this->after_admin_options_update();
    }

    /**
     * Do code after admin options update
     *
     * @return void
     */
    public function after_admin_options_update()
    {
        set_transient('buckaroo_credicard_updated', true);
    }

    /**
     * Save only creditcards that are allowed
     *
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    public function validate_show_in_checkout_field($key, $value)
    {
        $allowed = $this->settings['AllowedProvider'];
        if (is_array($value)) {
            return array_filter(
                $value,
                function ($provider) use ($allowed) {
                    return in_array($provider, $allowed);
                }
            );
        }
        return $value;
    }

    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->creditCardProvider = $this->get_option('AllowedProvider', array());
        $this->creditcardmethod = $this->get_option('creditcardmethod', 'redirect');
        $this->creditcardpayauthorize = $this->get_option('creditcardpayauthorize', 'Pay');
    }

}