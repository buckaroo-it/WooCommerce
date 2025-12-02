<?php

namespace Buckaroo\Woocommerce\Gateways\Transfer;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class TransferGateway extends AbstractPaymentGateway
{
    public const PAYMENT_CLASS = TransferProcessor::class;

    public $datedue;

    public $sendemail;

    public $showpayproc;

    protected array $supportedCurrencies = ['EUR', 'GBP', 'PLN'];

    public function __construct()
    {
        $this->id = 'buckaroo_transfer';
        $this->title = 'Bank Transfer';
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Bank Transfer';
        $this->setIcon('svg/sepa-credittransfer.svg');

        parent::__construct();
        $this->addRefundSupport();
    }

    /**
     * Print thank you description to the screen.
     *
     * Follows WooCommerce BACS behaviour by rendering clear bank transfer
     * instructions on the order received page.
     *
     * @param int|string|null $order_id
     */
    public function thankyou_description($order_id = null)
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        if (! session_id()) {
            @session_start();
        }

        $order = $order_id ? wc_get_order($order_id) : null;

        if (! $order instanceof \WC_Order) {
            return;
        }

        $amount            = wc_price($order->get_total(), ['currency' => $order->get_currency()]);
        $order_number      = $order->get_order_number();
        $payment_reference = get_post_meta($order->get_id(), 'buckaroo_paymentReference', true) ?: $order_number;
        $iban              = get_post_meta($order->get_id(), 'buckaroo_IBAN', true);
        $bic               = get_post_meta($order->get_id(), 'buckaroo_BIC', true);
        $account_holder    = get_post_meta($order->get_id(), 'buckaroo_accountHolderName', true) ?: 'Buckaroo Stichting Derdengelden';

        $intro = sprintf(
            __('Thank you for your order. You have chosen to pay by transfer. To complete your order, please transfer the outstanding amount, %1$s, using the details below.', 'wc-buckaroo-bpe-gateway'),
            $amount
        );

        $amount_label        = __('Amount', 'wc-buckaroo-bpe-gateway');
        $payment_ref_label   = __('Payment reference', 'wc-buckaroo-bpe-gateway');
        $accountholder_label = __('Accountholder', 'wc-buckaroo-bpe-gateway');
        $iban_label          = __('IBAN', 'wc-buckaroo-bpe-gateway');
        $bic_label           = __('BIC', 'wc-buckaroo-bpe-gateway');
        $note_prefix         = __('NB:', 'wc-buckaroo-bpe-gateway');

        $note = sprintf(
            __('To ensure that your payment can be processed smoothly, you must quote the payment reference %1$s in the description of your transfer. This will enable faster processing of the payment.', 'wc-buckaroo-bpe-gateway'),
            $payment_reference
        );

        echo '<section class="woocommerce-buckaroo-transfer-instructions">';
        echo wp_kses_post(wpautop($intro));

        echo '<ul class="wc-bacs-bank-details order_details buckaroo-transfer-details">';
        echo '<li class="amount">' . esc_html($amount_label) . ': <strong>' . wp_kses_post($amount) . '</strong></li>';
        echo '<li class="payment-reference">' . esc_html($payment_ref_label) . ': <strong>' . esc_html($payment_reference) . '</strong></li>';
        echo '<li class="accountholder">' . esc_html($accountholder_label) . ': <strong>' . esc_html($account_holder) . '</strong></li>';

        if (! empty($iban)) {
            echo '<li class="iban">' . esc_html($iban_label) . ': <strong>' . esc_html($iban) . '</strong></li>';
        }

        if (! empty($bic)) {
            echo '<li class="bic">' . esc_html($bic_label) . ': <strong>' . esc_html($bic) . '</strong></li>';
        }

        echo '</ul>';

        echo wp_kses_post(
            wpautop(
                sprintf(
                    '%s %s',
                    '<strong>' . esc_html($note_prefix) . '</strong>',
                    $note
                )
            )
        );

        echo '</section>';
    }

    /**
     * Legacy thank you description renderer based on the ConsumerMessage.HtmlText
     * from Buckaroo (if present). Kept for backwards compatibility and as a
     * fallback when no structured bank details are available.
     *
     * @return void
     */
    protected function render_legacy_thankyou_description(): void
    {
        if (empty($_SESSION['buckaroo_response'])) {
            return;
        }

        echo wp_kses(
            $_SESSION['buckaroo_response'],
            [
                'table' => ['class' => true],
                'td' => [
                    'class' => true,
                    'id' => true,
                ],
                'tr' => [],
                'br' => [],
                'b' => [],
            ]
        );
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['datedue'] = [
            'title' => __('Number of days till order expire', 'wc-buckaroo-bpe-gateway'),
            'type' => 'text',
            'description' => __('Number of days to the date that the order should be payed.', 'wc-buckaroo-bpe-gateway'),
            'default' => '14',
        ];
        $this->form_fields['sendmail'] = [
            'title' => __('Send email', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Buckaroo sends an email to the customer with the payment procedures.', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'TRUE' => __('Yes', 'wc-buckaroo-bpe-gateway'),
                'FALSE' => __('No', 'wc-buckaroo-bpe-gateway'),
            ],
            'default' => 'FALSE',
        ];
        $this->form_fields['showpayproc'] = [
            'title' => __('Show payment procedures', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Show payment procedures on the thank you page after payment confirmation.', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'TRUE' => __('Yes', 'wc-buckaroo-bpe-gateway'),
                'FALSE' => __('No', 'wc-buckaroo-bpe-gateway'),
            ],
            'default' => 'FALSE',
        ];
    }

    /**
     * {@inheritDoc}
     */
    protected function setProperties()
    {
        parent::setProperties();
        $this->datedue = $this->get_option('datedue');
        $this->sendemail = $this->get_option('sendmail');
        $this->showpayproc = $this->get_option('showpayproc') == 'TRUE';
    }
}
