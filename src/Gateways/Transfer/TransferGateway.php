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

        $locale = (method_exists($order, 'get_locale') && $order->get_locale()) ? $order->get_locale() : get_locale();
        $lang   = strtolower(substr($locale, 0, 2));

        $templates = [
            'nl' => [
                'intro' => 'Bedankt voor uw bestelling. U heeft ervoor gekozen om via overboeking te betalen. Om uw bestelling te voltooien, maakt u het openstaande bedrag van [AMOUNT] over met behulp van de onderstaande gegevens.',
                'amount_label' => 'Bedrag',
                'payment_ref_label' => 'Betaalreferentie',
                'accountholder_label' => 'Rekeninghouder',
                'iban_label' => 'IBAN',
                'bic_label' => 'BIC',
                'note_prefix' => 'NB:',
                'note' => 'Om ervoor te zorgen dat uw betaling goed verwerkt kan worden, dient u de betaalreferentie [ORDER ID] te vermelden in de omschrijving van uw overboeking. Dit zorgt voor een snellere verwerking van de betaling.',
            ],
            'de' => [
                'intro' => 'Vielen Dank für Ihre Bestellung. Sie haben sich für eine Überweisung als Zahlungsmethode entschieden. Um Ihre Bestellung abzuschließen, überweisen Sie bitte den ausstehenden Betrag von [AMOUNT] unter Verwendung der untenstehenden Angaben.',
                'amount_label' => 'Betrag',
                'payment_ref_label' => 'Zahlungsreferenz',
                'accountholder_label' => 'Kontoinhaber',
                'iban_label' => 'IBAN',
                'bic_label' => 'BIC',
                'note_prefix' => 'Hinweis:',
                'note' => 'Damit Ihre Zahlung ordnungsgemäß verarbeitet werden kann, geben Sie bitte die Zahlungsreferenz [ORDER ID] im Verwendungszweck Ihrer Überweisung an. Dies ermöglicht eine schnellere Bearbeitung der Zahlung.',
            ],
            'fr' => [
                'intro' => 'Merci pour votre commande. Vous avez choisi de payer par virement bancaire. Pour finaliser votre commande, veuillez transférer le montant restant de [AMOUNT] en utilisant les informations ci-dessous.',
                'amount_label' => 'Montant',
                'payment_ref_label' => 'Référence de paiement',
                'accountholder_label' => 'Titulaire du compte',
                'iban_label' => 'IBAN',
                'bic_label' => 'BIC',
                'note_prefix' => 'NB :',
                'note' => 'Afin de garantir un traitement rapide de votre paiement, vous devez indiquer la référence de paiement [ORDER ID] dans la description de votre virement. Cela permettra un traitement plus rapide du paiement.',
            ],
            'es' => [
                'intro' => 'Gracias por su pedido. Ha elegido pagar mediante transferencia bancaria. Para completar su pedido, por favor transfiera el importe pendiente de [AMOUNT] utilizando los datos que se indican a continuación.',
                'amount_label' => 'Importe',
                'payment_ref_label' => 'Referencia de pago',
                'accountholder_label' => 'Titular de la cuenta',
                'iban_label' => 'IBAN',
                'bic_label' => 'BIC',
                'note_prefix' => 'Nota:',
                'note' => 'Para garantizar que su pago se procese correctamente, debe indicar la referencia de pago [ORDER ID] en el concepto de su transferencia. Esto permitirá una tramitación más rápida del pago.',
            ],
            'en' => [
                'intro' => 'Thank you for your order. You have chosen to pay by transfer. To complete your order, please transfer the outstanding amount, [AMOUNT], using the details below.',
                'amount_label' => 'Amount',
                'payment_ref_label' => 'Payment reference',
                'accountholder_label' => 'Accountholder',
                'iban_label' => 'IBAN',
                'bic_label' => 'BIC',
                'note_prefix' => 'NB:',
                'note' => 'To ensure that your payment can be processed smoothly, you must quote the payment reference [ORDER ID] in the description of your transfer. This will enable faster processing of the payment.',
            ],
        ];

        if (! isset($templates[$lang])) {
            $lang = 'en';
        }

        $template = $templates[$lang];

        $replacements = [
            '[AMOUNT]'   => $amount,
            '[ORDER NO]' => $payment_reference,
            '[ORDER ID]' => $payment_reference,
            '[IBAN NO]'  => $iban,
            '[BIC NO]'   => $bic,
        ];

        $intro = strtr($template['intro'], $replacements);
        $note  = strtr($template['note'], $replacements);

        $amount_label        = $template['amount_label'];
        $payment_ref_label   = $template['payment_ref_label'];
        $accountholder_label = $template['accountholder_label'];
        $iban_label          = $template['iban_label'];
        $bic_label           = $template['bic_label'];
        $note_prefix         = $template['note_prefix'];

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
