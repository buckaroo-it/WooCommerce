<?php

namespace Buckaroo\Woocommerce\Gateways\GiftCard;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use BuckarooConfig;

class GiftCardProcessor extends AbstractPaymentProcessor
{
    public $cardtype = '';

    /**
     * @access public
     * @param array $customVars
     * @return callable parent::Pay()
     */
    public function Pay($customVars = array())
    {

        $servicesSelectableByClient = BuckarooConfig::get('BUCKAROO_GIFTCARD_ALLOWED_CARDS');

        if (!empty($customVars['servicesSelectableByClient'])) {
            $servicesSelectableByClient = $customVars['servicesSelectableByClient'];
        }
        $this->setCustomVarWithoutType(
            array(
                'servicesSelectableByClient' => $servicesSelectableByClient,
                'continueOnIncomplete' => 'RedirectToHTML',
            )
        );

        $this->data['services'] = array();
        return parent::PayGlobal();
    }
}