<?php

namespace Buckaroo\Woocommerce\Gateways\Klarna;

use Buckaroo\Woocommerce\Components\OrderDetails;
use Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor;
use Buckaroo\Woocommerce\Services\HttpRequest;

class KlarnaKpProcessor extends AbstractPaymentProcessor
{

    /**
     * @var OrderDetails
     */
    protected OrderDetails $order_details;
    /**
     * @var HttpRequest
     */
    protected $request;

}