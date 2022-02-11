<?php
/**
 * Parent creditcard class
 * php version 7.2
 *
 * @category  Payment_Gateways
 * @package   Buckaroo
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 * @version   GIT: 2.25.0
 * @link      https://www.buckaroo.eu/
 */
class Buckaroo_Creditcard_Single extends WC_Gateway_Buckaroo_Creditcard
{
    /**
     * Payment form on checkout page
     *
     * @return void
     */
    public function payment_fields()
    {
        $this->renderTemplate('buckaroo_creditcard');
    }
    /**
     * Add fields to the form_fields() array, specific to this page.
     *
     * @access public
     */
    public function init_form_fields()
    {

        parent::init_form_fields();
        unset(
            $this->form_fields['AllowedProvider'],
            $this->form_fields[self::SHOW_IN_CHECKOUT_FIELD]
        );
    }
}