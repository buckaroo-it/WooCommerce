<?php

namespace Buckaroo\Woocommerce\Gateways\PayPerEmail;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Services\Logger;
use WC_Order;

class PayPerEmailGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = PayPerEmailProcessor::class;

    public $paymentmethodppe;

    public $frontendVisible;

    public bool $usePayPerLink = false;

    /** Time window, in seconds, for the checkout invitation limits. */
    public const CHECKOUT_RATE_WINDOW = 3600;

    /** Checkout invitations allowed per client address within the window. */
    public const CHECKOUT_CLIENT_LIMIT = 5;

    /** Checkout invitations allowed per recipient address within the window. */
    public const CHECKOUT_RECIPIENT_LIMIT = 3;

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
        $this->method_description = __('Buckaroo service for sending customers a payment request by email with a secure pay link.', 'wc-buckaroo-bpe-gateway');
        $this->has_fields = true;
        $this->method_title = 'Buckaroo PayPerEmail';
        $this->setIcon('svg/payperemail.svg');

        parent::__construct();
    }

    public function redirectsToPaymentPage()
    {
        return false;
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
                'bancontactmrcash' => 'Bancontact',
                'transfer' => 'Bank Transfer',
                'giftcard' => 'Giftcards',
                'ideal' => 'iDEAL | Wero',
                'paypal' => 'PayPal',
                'sepadirectdebit' => 'SEPA Direct Debit',
                'belfius' => 'Belfius',
                'Przelewy24' => 'Przelewy24',
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

    /**
     * Whether a PayPerEmail invitation may be sent for the order from the admin.
     */
    public function canSendPayPerEmail(WC_Order $order): bool
    {
        return ! $order->is_paid() && $this->canShowPayPerEmail($order->get_status());
    }

    /**
     * Whether a PayLink may be created for the order from the admin.
     */
    public function canSendPayLink(WC_Order $order): bool
    {
        return ! $order->is_paid() && $this->canShowPaylink($order->get_status());
    }

    /**
     * Checkout PayPerEmail runs only when it is shown on the checkout page and
     * stays within the invitation limits.
     *
     * @param  int|string  $order_id
     */
    protected function rejectIneligibleOrder($order_id): ?array
    {
        if (! $this->merchantInitiated) {
            $rejected = $this->rejectCheckoutInvitation($order_id);
            if ($rejected !== null) {
                return $rejected;
            }
        }

        return parent::rejectIneligibleOrder($order_id);
    }

    /**
     * @param  int|string  $order_id
     */
    private function rejectCheckoutInvitation($order_id): ?array
    {
        $order = $order_id ? wc_get_order($order_id) : null;

        if (! $this->isEnabled() || ! $this->isVisibleOnFrontend() || ! $order instanceof WC_Order) {
            wc_add_notice(
                __('This payment method is not available for this order. Please choose another payment method.', 'wc-buckaroo-bpe-gateway'),
                'error'
            );

            return ['result' => 'failure'];
        }

        if (! $this->withinCheckoutRateLimit((string) $order->get_billing_email())) {
            Logger::log(__METHOD__ . '|limit reached|', [$order->get_id()]);
            wc_add_notice(
                __('Too many payment requests were sent. Please try again later or choose another payment method.', 'wc-buckaroo-bpe-gateway'),
                'error'
            );

            return ['result' => 'failure'];
        }

        return null;
    }

    /**
     * Count a checkout invitation for the client address and the recipient
     * address; false once either is over its limit.
     */
    private function withinCheckoutRateLimit(string $recipient): bool
    {
        $now = time();

        $clientCount = $this->countInvitation('bk_ppe_client_' . md5($this->clientAddress()), $now);
        $recipientCount = $this->countInvitation('bk_ppe_recipient_' . md5(strtolower(trim($recipient))), $now);

        return $clientCount <= self::CHECKOUT_CLIENT_LIMIT && $recipientCount <= self::CHECKOUT_RECIPIENT_LIMIT;
    }

    /**
     * Add one to the counter stored under the key and return the new count.
     */
    private function countInvitation(string $key, int $now): int
    {
        $entry = get_transient($key);

        if (
            ! is_array($entry) || ! is_int($entry['start'] ?? null) || ! is_int($entry['count'] ?? null)
            || $entry['start'] + self::CHECKOUT_RATE_WINDOW <= $now
        ) {
            $entry = ['start' => $now, 'count' => 0];
        }

        $entry['count']++;
        set_transient($key, $entry, max(1, $entry['start'] + self::CHECKOUT_RATE_WINDOW - $now));

        return $entry['count'];
    }

    /** Address of the connecting client as seen by the web server. */
    private function clientAddress(): string
    {
        $address = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        return $address !== '' ? $address : 'unknown';
    }

    /**
     * Run a PayPerEmail / PayLink admin action for the order, or leave an admin
     * notice when the order does not allow it.
     */
    private function runAdminOrderAction(?WC_Order $order, bool $payLink): void
    {
        $allowed = $order instanceof WC_Order
            && ($payLink ? $this->canSendPayLink($order) : $this->canSendPayPerEmail($order));

        if (! $allowed) {
            set_transient(
                get_current_user_id() . 'buckarooAdminNotice',
                [
                    'type' => 'error',
                    'message' => $payLink
                        ? __('A PayLink cannot be created for this order.', 'wc-buckaroo-bpe-gateway')
                        : __('A PayPerEmail cannot be sent for this order.', 'wc-buckaroo-bpe-gateway'),
                ]
            );

            return;
        }

        $this->usePayPerLink = $payLink;
        $this->merchantInitiated = true;
        $this->process_payment($order->get_id());
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
                $buttons = [
                    [
                        'url' => wp_nonce_url(
                            admin_url('admin-ajax.php?action=buckaroo_send_admin_payperemail&order_id=' . $orderId),
                            'buckaroo_send_payperemail_' . $orderId
                        ),
                        'icon' => 'email',
                        'tooltip' => __('Send a PayPerEmail', 'textdomain'),
                        'label' => __('P1', 'textdomain'),
                        'enabled' => $this->canSendPayPerEmail($order),
                    ],
                    [
                        'url' => wp_nonce_url(
                            admin_url('admin-ajax.php?action=buckaroo_create_paylink&order_id=' . $orderId),
                            'buckaroo_send_payperemail_' . $orderId
                        ),
                        'icon' => 'link',
                        'tooltip' => __('Create PayLink', 'textdomain'),
                        'label' => __('P1', 'textdomain'),
                        'enabled' => $this->canSendPayLink($order),
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
                if ($theorder instanceof WC_Order) {
                    if ($this->canSendPayPerEmail($theorder)) {
                        $actions['buckaroo_send_admin_payperemail'] = __('Send a PayPerEmail', 'woocommerce');
                    }
                    if ($this->canSendPayLink($theorder)) {
                        $actions['buckaroo_create_paylink'] = __('Create PayLink', 'woocommerce');
                    }
                }

                return $actions;
            }
        );

        add_action(
            'woocommerce_order_action_buckaroo_send_admin_payperemail',
            function ($order) {
                // WooCommerce's order-save request returns to the order screen on its own.
                $this->runAdminOrderAction($order instanceof WC_Order ? $order : null, false);
            }
        );

        add_action(
            'wp_ajax_buckaroo_send_admin_payperemail',
            function () {
                $orderId = absint($_GET['order_id'] ?? 0);
                $this->verifyAdminOrderActionRequest($orderId);
                $order = wc_get_order($orderId);
                $this->runAdminOrderAction($order instanceof WC_Order ? $order : null, false);
                wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
                exit;
            }
        );

        add_action(
            'woocommerce_order_action_buckaroo_create_paylink',
            function ($order) {
                // WooCommerce's order-save request returns to the order screen on its own.
                $this->runAdminOrderAction($order instanceof WC_Order ? $order : null, true);
            }
        );

        add_action(
            'wp_ajax_buckaroo_create_paylink',
            function () {
                $orderId = absint($_GET['order_id'] ?? 0);
                $this->verifyAdminOrderActionRequest($orderId);
                $order = wc_get_order($orderId);
                $this->runAdminOrderAction($order instanceof WC_Order ? $order : null, true);

                wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=shop_order'));
                exit;
            }
        );
    }

    /**
     * Verify the current user may run a PayPerEmail / PayLink action for the
     * given order before processing the payment request.
     */
    private function verifyAdminOrderActionRequest(int $orderId): void
    {
        if (! current_user_can('edit_shop_order', $orderId) && ! current_user_can('edit_shop_orders')) {
            wp_die(
                esc_html__('You are not allowed to perform this action.', 'wc-buckaroo-bpe-gateway'),
                '',
                ['response' => 403]
            );
        }

        check_admin_referer('buckaroo_send_payperemail_' . $orderId);
    }
}
