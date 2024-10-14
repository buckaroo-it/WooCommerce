<?php

namespace Buckaroo\Woocommerce\Gateways\Ideal;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;

class IdealGateway extends AbstractPaymentGateway
{
    const PAYMENT_CLASS = IdealProcessor::class;

    public function __construct()
    {
        $this->id = 'buckaroo_ideal';
        $this->title = 'iDEAL';
        $this->has_fields = true;
        $this->method_title = 'Buckaroo iDEAL';
        $this->setIcon('24x24/ideal.png', 'svg/ideal.svg');

        parent::__construct();
        $this->addRefundSupport();
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
            $issuer = $this->request->input('buckaroo-ideal-issuer');

            if ($issuer === null) {
                wc_add_notice(__('<strong>iDEAL bank </strong> is a required field.', 'wc-buckaroo-bpe-gateway'), 'error');
            } elseif (!in_array($issuer, array_keys(IdealProcessor::getIssuerList()))) {
                wc_add_notice(__('A valid iDEAL bank is required.', 'wc-buckaroo-bpe-gateway'), 'error');
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
            'default' => 'yes',
        );
    }
}