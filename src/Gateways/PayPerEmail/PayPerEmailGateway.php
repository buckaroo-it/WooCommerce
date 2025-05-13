<?php

namespace Buckaroo\Woocommerce\Gateways\PayPerEmail;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use WC_Order;

class PayPerEmailGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = PayPerEmailProcessor::class;

    public $paymentmethodppe;

    public $frontendVisible;

    public bool $usePayPerLink = false;

    protected array $supportedCurrencies = [
        'ARS',
        'AUD',
        'BRL',
        'CAD',
        'CHF',
        'CNY',
        'CZK',
        'DKK',
        'EUR',
        'GBP',
        'HRK',
        'ISK',
        'JPY',
        'LTL',
        'LVL',
        'MXN',
        'NOK',
        'NZD',
        'PLN',
        'RUB',
        'SEK',
        'TRY',
        'USD',
        'ZAR',
    ];

    public function __construct()
    {
        $this->id = 'buckaroo_payperemail';
        $this->title = 'PayPerEmail';
        $this->has_fields = true;
        $this->method_title = 'Buckaroo PayPerEmail';
        $this->setIcon('svg/payperemail.svg');

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

            if (! in_array($gender, ['0', '1', '2', '9'])) {
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
            } elseif (! is_email($this->request->input('buckaroo-payperemail-email'))) {
                wc_add_notice(__('Please enter valid email', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        parent::validate_fields();
    }

    public function isVisibleOnFrontend()
    {
        if (! empty($this->frontendVisible) && strtolower($this->frontendVisible) === 'yes') {
            return true;
        }

        return false;
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['show_PayPerEmail_frontend'] = [
            'title' => __('Show on Checkout page', 'wc-buckaroo-bpe-gateway'),
            'type' => 'checkbox',
            'description' => __('Show PayPerEmail on Checkout page', 'wc-buckaroo-bpe-gateway'),
            'default' => 'no',
        ];

        $this->form_fields['show_PayLink'] = [
            'title' => __('Show PayLink', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Show PayLink in admin order actions', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'),
                'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway'),
            ],
            'default' => 'TRUE',
        ];

        $this->form_fields['show_PayPerEmail'] = [
            'title' => __('Show PayPerEmail', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Show PayPerEmail in admin order actions', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'TRUE' => __('Show', 'wc-buckaroo-bpe-gateway'),
                'FALSE' => __('Hide', 'wc-buckaroo-bpe-gateway'),
            ],
            'default' => 'TRUE',
        ];

        $this->form_fields['expirationDate'] = [
            'title' => __('Due date (in days)', 'wc-buckaroo-bpe-gateway'),
            'type' => 'text',
            'description' => __('The expiration date for the paylink.', 'wc-buckaroo-bpe-gateway'),
            'default' => '',
        ];

        $this->form_fields['paymentmethodppe'] = [
            'title' => __('Allowed methods', 'wc-buckaroo-bpe-gateway'),
            'type' => 'multiselect',
            'options' => [
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
                'belfius' => 'Belfius',
                'Przelewy24' => 'P24',
            ],
            'description' => __('Select which methods appear to the customer', 'wc-buckaroo-bpe-gateway'),
            'default' => ['amex', 'cartebancaire', 'cartebleuevisa', 'dankort', 'mastercard', 'postepay', 'visa', 'visaelectron', 'vpay', 'maestro', 'bancontactmrcash', 'transfer', 'giftcard', 'ideal', 'paypal', 'sepadirectdebit', 'belfius', 'Przelewy24'],
        ];
    }

    /**  {@inheritDoc} */
    protected function setProperties()
    {
        parent::setProperties();
        $this->paymentmethodppe = $this->get_option('paymentmethodppe', '');
        $this->frontendVisible = $this->get_option('show_PayPerEmail_frontend', '');
    }

    protected function isEnabled()
    {
        return $this->get_option('enabled') === 'yes';
    }

    protected function canShowPayPerEmail($status)
    {
        return $this->isEnabled()
            && in_array($status, ['auto-draft', 'pending', 'on-hold'])
            && $this->get_option('show_PayPerEmail') === 'TRUE';
    }

    protected function canShowPaylink($status)
    {
        return $this->isEnabled()
            && in_array($status, ['pending', 'on-hold', 'failed'])
            && $this->get_option('show_PayLink') === 'TRUE';
    }

    public function handleHooks()
    {
        add_action(
            'woocommerce_admin_order_actions_end',
            function ($order) {
                if (! $order instanceof WC_Order) {
                    return;
                }

                $orderId = $order->get_id();
                $status = $order->get_status();
                $buttons = [
                    [
                        'url' => wp_nonce_url(
                            admin_url('admin-ajax.php?action=buckaroo_send_admin_payperemail&order_id=' . $orderId),
                            'buckaroo_send_payperemail_' . $orderId
                        ),
                        'icon' => 'email',
                        'tooltip' => __('Send a PayPerEmail', 'textdomain'),
                        'label' => __('P1', 'textdomain'),
                        'enabled' => $this->canShowPayPerEmail($status),
                    ],
                    [
                        'url' => wp_nonce_url(
                            admin_url('admin-ajax.php?action=buckaroo_create_paylink&order_id=' . $orderId),
                            'buckaroo_send_payperemail_' . $orderId
                        ),
                        'icon' => 'link',
                        'tooltip' => __('Create PayLink', 'textdomain'),
                        'label' => __('P1', 'textdomain'),
                        'enabled' => $this->canShowPaylink($status),
                    ],
                ];

                foreach (array_filter($buttons, fn ($b) => $b['enabled']) as $button) {
                    printf(
                        '<a class="wc-buckaroo-action-button button tips wc-action-button wc-action-button-%1$s %1$s" href="%2$s" data-tip="%3$s">%4$s</a>',
                        esc_attr($button['icon']),
                        esc_url($button['url']),
                        esc_attr($button['tooltip']),
                        esc_html($button['label'])
                    );
                }
            }
        );

        add_filter(
            'woocommerce_order_actions',
            function ($actions) {
                global $theorder;
                if ($this->isEnabled()) {
                    $status = $theorder->get_status();
                    if ($this->canShowPayPerEmail($status)) {
                        $actions['buckaroo_send_admin_payperemail'] = __('Send a PayPerEmail', 'woocommerce');
                    }
                    if ($this->canShowPaylink($status)) {
                        $actions['buckaroo_create_paylink'] = __('Create PayLink', 'woocommerce');
                    }
                }

                return $actions;
            }
        );

        add_action(
            'woocommerce_order_action_buckaroo_send_admin_payperemail',
            function ($order) {
                $response = $this->process_payment($order->get_id());
                wp_redirect($response);
            }
        );

        add_action(
            'wp_ajax_buckaroo_send_admin_payperemail',
            function () {
                $orderId = absint($_GET['order_id'] ?? 0);
                $this->process_payment($orderId);
                wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
                exit;
            }
        );

        add_action(
            'woocommerce_order_action_buckaroo_create_paylink',
            function ($order) {
                $this->usePayPerLink = true;
                $response = $this->process_payment($order->get_id());
                wp_redirect($response);
            }
        );

        add_action(
            'wp_ajax_buckaroo_create_paylink',
            function () {
                $orderId = absint($_GET['order_id'] ?? 0);
                $this->usePayPerLink = true;
                $this->process_payment($orderId);

                wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
                exit;
            }
        );
    }
}
