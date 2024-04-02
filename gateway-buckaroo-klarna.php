<?php


/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Klarna extends WC_Gateway_Buckaroo
{
    protected $type;
    protected $vattype;
    protected $klarnaPaymentFlowId = '';

    public function __construct()
    {
        $this->has_fields = true;
        $this->type       = 'klarna';
        $this->setIcon('24x24/klarna.svg', 'svg/klarna.svg');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->vattype = $this->get_option('vattype');
    }
    public function getKlarnaSelector()
    {
        return str_replace("_", "-", $this->id);
    }

    public function getKlarnaPaymentFlow()
    {
        return $this->klarnaPaymentFlowId;
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @access public
     * @return void
     */
    public function validate_fields()
    {
        $gender = $this->request($this->getKlarnaSelector() . '-gender');

        if(!in_array($gender, ["male","female"])) {
            wc_add_notice(__("Unknown gender", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if ($this->request('ship_to_different_address') !== null) {
            $countryCode =$this->request('shipping_country') == 'NL' ?$this->request('shipping_country') : '';
            $countryCode =$this->request('billing_country') == 'NL' ?$this->request('billing_country') : $countryCode;
            if (!empty($countryCode)
                && strtolower($this->klarnaPaymentFlowId) !== 'pay') {

                return wc_add_notice(__('Payment method is not supported for country ' . '(' . esc_html($countryCode) . ')', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        } else {
            if (
                ($this->request('billing_country') == 'NL')
                && strtolower($this->klarnaPaymentFlowId) !== 'pay'
                ) {

                return wc_add_notice(__('Payment method is not supported for country ' . '(' . esc_html($this->request('billing_country')) . ')', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }
    }


    /** @inheritDoc */
    public function init_form_fields()
    {
        parent::init_form_fields();

        if ($this->id !== 'buckaroo_klarnapii') {
            $this->add_financial_warning_field();
        }
    }
}
