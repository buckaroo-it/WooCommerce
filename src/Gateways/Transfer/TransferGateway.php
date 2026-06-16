<?php

namespace Buckaroo\Woocommerce\Gateways\Transfer;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use WC_Order;

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
     * @param  int|string|null  $order_id
     */
    public function thankyou_description($order_id = null)
    {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;

        $order = $order_id ? wc_get_order($order_id) : null;

        if (! $order instanceof WC_Order) {
            return;
        }

        $this->render_transfer_instructions($order);
    }

    protected function render_transfer_instructions(WC_Order $order): void
    {
        $amount = wc_price($order->get_total(), ['currency' => $order->get_currency()]);
        $payment_reference = $order->get_meta('buckaroo_paymentReference') ?: $order->get_order_number();
        $iban = $order->get_meta('buckaroo_IBAN');
        $bic = $order->get_meta('buckaroo_BIC');
        $account_holder = $order->get_meta('buckaroo_accountHolderName') ?: 'Buckaroo Stichting Derdengelden';
        $GLOBALS['test'] = true;
        $intro = sprintf(
            __('Thank you for your order. You have chosen to pay by transfer. To complete your order, please transfer the outstanding amount, %1$s, using the details below.', 'wc-buckaroo-bpe-gateway'),
            $amount
        );

        $amount_label = __('Amount', 'wc-buckaroo-bpe-gateway');
        $payment_ref_label = __('Payment reference', 'wc-buckaroo-bpe-gateway');
        $accountholder_label = __('Accountholder', 'wc-buckaroo-bpe-gateway');
        $iban_label = __('IBAN', 'wc-buckaroo-bpe-gateway');
        $bic_label = __('BIC', 'wc-buckaroo-bpe-gateway');
        $note_prefix = __('NB:', 'wc-buckaroo-bpe-gateway');

        $note = sprintf(
            __('To ensure that your payment can be processed smoothly, you must quote the payment reference %1$s in the description of your transfer. This will enable faster processing of the payment.', 'wc-buckaroo-bpe-gateway'),
            $payment_reference
        );

        echo '<section class="woocommerce-buckaroo-transfer-instructions">';
        echo wp_kses_post(wpautop($intro));

        echo '<table class="bankdetails">';
        echo '<tr><td class="label" id="amountlabel">' . esc_html($amount_label) . ':</td><td class="labelvalue" id="amount">' . wp_kses_post($amount) . '</td></tr>';
        echo '<tr><td class="label" id="referencelabel">' . esc_html($payment_ref_label) . ':</td><td class="labelvalue" id="reference">' . esc_html($payment_reference) . '</td></tr>';
        echo '<tr><td class="label" id="accountholdernamelabel">' . esc_html($accountholder_label) . ':</td><td class="labelvalue" id="accountholdername">' . esc_html($account_holder) . '</td></tr>';

        if (! empty($iban)) {
            echo '<tr><td class="label" id="ibanlabel">' . esc_html($iban_label) . ':</td><td class="labelvalue" id="ibancode">' . esc_html($iban) . '</td></tr>';
        }

        if (! empty($bic)) {
            echo '<tr><td class="label" id="biclabel">' . esc_html($bic_label) . ':</td><td class="labelvalue" id="biccode">' . esc_html($bic) . '</td></tr>';
        }

        echo '</table>';

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
