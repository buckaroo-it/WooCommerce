<?php
/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) WC_Buckaroo\Dependencies\Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */

namespace WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PayPerEmail;

use WC_Buckaroo\Dependencies\Buckaroo\Models\Payload\PayPayload;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PaymentMethod;
use WC_Buckaroo\Dependencies\Buckaroo\PaymentMethods\PayPerEmail\Models\PaymentInvitation;

class PayPerEmail extends PaymentMethod
{
    /**
     * @var string
     */
    protected string $paymentName = 'payperemail';

    /**
     * @return PayPerEmail|mixed
     */
    public function paymentInvitation()
    {
        $paymentInvitation = new PaymentInvitation($this->payload);

        $payPayload = new PayPayload($this->payload);

        $this->request->setPayload($payPayload);

        $this->setServiceList('PaymentInvitation', $paymentInvitation);

        return $this->postRequest();
    }
}
