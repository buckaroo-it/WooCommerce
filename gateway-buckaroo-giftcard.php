<?php

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_Giftcard extends WC_Gateway_Buckaroo
{
    public $giftcards;

    public function __construct()
    {
        $this->id                     = 'buckaroo_giftcard';
        $this->title                  = 'Giftcards';
        $this->has_fields             = false;
        $this->method_title           = "Buckaroo Giftcards";
        $this->setIcon('24x24/giftcard.gif', 'svg/giftcards.svg');

        parent::__construct();
        //disabled refunds by request see BP-1337
        // $this->addRefundSupport();
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->giftcards = $this->get_option('giftcards');

    }


    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['giftcards'] = array(
            'title'       => __('List of authorized giftcards', 'wc-buckaroo-bpe-gateway'),
            'type'        => 'text',
            'description' => __('Giftcards must be comma separated', 'wc-buckaroo-bpe-gateway'),
            'default'     => 'westlandbon,ideal,ippies,babygiftcard,babyparkgiftcard,beautywellness,boekenbon,boekenvoordeel,designshopsgiftcard,fashioncheque,fashionucadeaukaart,fijncadeau,koffiecadeau,kokenzo,kookcadeau,nationaleentertainmentcard,naturesgift,podiumcadeaukaart,shoesaccessories,webshopgiftcard,wijncadeau,wonenzo,yourgift,vvvgiftcard,parfumcadeaukaart');
    }

}
