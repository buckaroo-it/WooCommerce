<?php

namespace Buckaroo\Woocommerce\Gateways\CreditCard\Cards;

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;

/**
 * Parent creditcard class
 * php version 7.2
 *
 * @category  Payment_Gateways
 *
 * @author    Buckaroo <support@buckaroo.nl>
 * @copyright 2021 Copyright (c) Buckaroo B.V.
 * @license   MIT https://tldrlegal.com/license/mit-license
 *
 * @version   GIT: 2.25.0
 *
 * @link      https://www.buckaroo.eu/
 */
class SingleCreditCardGateway extends CreditCardGateway
{
    public function __construct()
    {
        parent::__construct();
    }

    /** {@inheritDoc} */
    public function setCreditcardIcon()
    {
        $name = str_replace('buckaroo_creditcard_', '', $this->id);

        if ($name === 'cartebleuevisa') {
            $name = 'cartebleue';
        }

        $icon = "creditcards/{$name}.svg";
        $this->setIcon($icon);
    }

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
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        unset(
            $this->form_fields['AllowedProvider'],
            $this->form_fields[self::SHOW_IN_CHECKOUT_FIELD]
        );
    }

    /**@inheritDoc */
    public function update_option($key, $value = '')
    {
        if ($key === 'enabled') {
            $this->updateList($value === 'yes');
        }

        return parent::update_option($key, $value);
    }

    /**
     * Remove or add checkout creditcard payment to the list
     *
     * @param  bool  $show
     * @return void
     */
    public function updateList($show)
    {
        $credit_settings = get_option('woocommerce_buckaroo_creditcard_settings', null);

        if (
            $credit_settings === null ||
            ! isset($credit_settings['show_in_checkout']) ||
            ! is_array($credit_settings['show_in_checkout'])
        ) {
            return false;
        }

        $list = $credit_settings['show_in_checkout'];
        $creditcardMethod = str_replace('buckaroo_creditcard_', '', $this->id);
        if (in_array($creditcardMethod, $list)) {
            $list = array_diff($list, [$creditcardMethod]);
        }
        if ($show) {
            $list[] = $creditcardMethod;
        }

        $credit_settings['show_in_checkout'] = $list;

        return update_option('woocommerce_buckaroo_creditcard_settings', $credit_settings);
    }

    /**@inheritDoc */
    public function after_admin_options_update()
    {
        $this->updateList(
            $this->get_option('enabled', 'no') === 'yes'
        );
    }

    public function getServiceCode(?AbstractProcessor $processor = null)
    {
        if ($processor && $processor->getAction() == 'refund') {
            return 'creditcard';
        }

        return str_replace('buckaroo_creditcard_', '', $this->id);
    }
}
