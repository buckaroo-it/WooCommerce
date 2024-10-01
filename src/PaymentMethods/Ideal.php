<?php

namespace WC_Buckaroo\WooCommerce\PaymentMethods;

class Ideal extends PaymentGatewayHandler
{
    public function getIssuerList()
    {
        $issuerArray = array(
            'ABNANL2A' => array(
                'name' => 'ABN AMRO',
                'logo' => 'abnamro.svg',
            ),
            'ASNBNL21' => array(
                'name' => 'ASN Bank',
                'logo' => 'asnbank.svg',
            ),
            'INGBNL2A' => array(
                'name' => 'ING',
                'logo' => 'ing.svg',
            ),
            'RABONL2U' => array(
                'name' => 'Rabobank',
                'logo' => 'rabobank.svg',
            ),
            'SNSBNL2A' => array(
                'name' => 'SNS Bank',
                'logo' => 'sns.svg',
            ),
            'RBRBNL21' => array(
                'name' => 'RegioBank',
                'logo' => 'regiobank.svg',
            ),
            'TRIONL2U' => array(
                'name' => 'Triodos Bank',
                'logo' => 'triodos.svg',
            ),
            'FVLBNL22' => array(
                'name' => 'Van Lanschot Kempen',
                'logo' => 'vanlanschot.svg',
            ),
            'KNABNL2H' => array(
                'name' => 'Knab',
                'logo' => 'knab.svg',
            ),
            'BUNQNL2A' => array(
                'name' => 'bunq',
                'logo' => 'bunq.svg',
            ),
            'REVOLT21' => array(
                'name' => 'Revolut',
                'logo' => 'revolut.svg',
            ),
            'BITSNL2A' => array(
                'name' => 'Yoursafe',
                'logo' => 'yoursafe.svg',
            ),
            'NTSBDEB1' => array(
                'name' => 'N26',
                'logo' => 'n26.svg'
            ),
            'NNBANL2G' => array(
                'name' => 'Nationale Nederlanden',
                'logo' => 'nn.svg'
            )
        );
        return $issuerArray;
    }

    public function __construct()
    {
        $this->id = 'buckaroo_ideal';
        $this->title = 'iDEAL';
        $this->has_fields = true;
        $this->method_title = "Buckaroo iDEAL";
        $this->set_icon('24x24/ideal.png', 'svg/ideal.svg');

        parent::__construct();
        $this->add_refund_support();
        apply_filters('buckaroo_init_payment_class', $this);
    }

    /**
     * Validate frontend fields.
     *
     * Validate payment fields on the frontend.
     *
     * @return bool
     */
    public function validate_fields()
    {
        if ($this->canShowIssuers()) {
            $issuer = $this->request('buckaroo-ideal-issuer');

            if ($issuer === null) {
                wc_add_notice(__("<strong>iDEAL bank </strong> is a required field.", 'wc-buckaroo-bpe-gateway'), 'error');
            } else
                if (!in_array($issuer, array_keys($this->getIssuerList()))) {
                    wc_add_notice(__("A valid iDEAL bank is required.", 'wc-buckaroo-bpe-gateway'), 'error');
                }
        }
        parent::validate_fields();
    }

    public function canShowIssuers()
    {
        return $this->get_option('show_issuers') !== 'no';
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['show_issuers'] = array(
            'title' => __('Show Issuer Selection in the Checkout', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('When the "NO" option is selected, the issuer selection for iDEAL will not be displayed in the checkout. Instead, customers will be redirected to a separate page where they can choose their iDEAL issuer (i.e., their bank). On the other hand, selecting the "Yes" option will display the issuer selection directly in the checkout. It\'s important to note that enabling this option will incur additional costs from Buckaroo, estimated at around €0.002 for each transaction. For precise cost details, please reach out to <a href="mailto:wecare@buckaroo.nl">Buckaroo</a> directly.', 'wc-buckaroo-bpe-gateway'),
            'options' => array(
                'yes' => __('Yes'),
                'no' => __('No'),
            ),
            'default' => 'yes'
        );
    }
}