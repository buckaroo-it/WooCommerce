<?php


require_once dirname(__FILE__) . '/library/api/paymentmethods/in3/in3.php';

/**
 * @package Buckaroo
 */
class WC_Gateway_Buckaroo_In3 extends WC_Gateway_Buckaroo
{
    const PAYMENT_CLASS = BuckarooIn3::class;
    public $type;
    public $vattype;
    public $country;
    public function __construct()
    {
        $this->id                     = 'buckaroo_in3';
        $this->title                  = 'in3';
        $this->has_fields             = false;
        $this->method_title           = 'Buckaroo in3';
        $this->setIcon('24x24/in3.png', 'svg/In3.svg');

        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }
    /**  @inheritDoc */
    protected function setProperties()
    {
        parent::setProperties();
        $this->type       = 'in3';
        $this->vattype    = $this->get_option('vattype');
    }
    /**
     * Can the order be refunded
     * @param integer $order_id
     * @param integer $amount defaults to null
     * @param string $reason
     * @return callable|string function or error
     */
    public function process_refund($order_id, $amount = null, $reason = '', $line_item_qtys = null, $line_item_totals = null, $line_item_tax_totals = null, $originalTransactionKey = null)
    {
        return $this->processDefaultRefund($order_id, $amount, $reason);
    }

    /**
     * Validate payment fields on the frontend.
     *
     * @access public
     * @return void
     */
    public function validate_fields()
    {
        $birthdate = $this->request('buckaroo-in3-birthdate');

        $country = $this->request('billing_country');
        if ($country === null) {
            $country = $this->country;
        }

        if ($country === 'NL' && !$this->validateDate($birthdate, 'd-m-Y')) {
            wc_add_notice(__("Please enter correct birthdate date", 'wc-buckaroo-bpe-gateway'), 'error');
        }

        parent::validate_fields();
    }

    /**
     * Process payment
     *
     * @param integer $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $this->setOrderCapture($order_id, 'In3');

        $order = getWCOrder($order_id);
        /** @var BuckarooIn3 */
        $in3 = $this->createDebitRequest($order);

        $order_details = new Buckaroo_Order_Details($order);
        
        $birthdate = date('Y-m-d', strtotime($this->request('buckaroo-in3-birthdate')));
        
        $in3 = $this->getBillingInfo($order_details, $in3, $birthdate);
        
        $in3->InvoiceDate       = date("d-m-Y");
        $in3->CustomerIPAddress = getClientIpBuckaroo();
        $in3->Accept            = 'TRUE';
        $in3->returnUrl         = $this->notify_url;

        $response = $in3->PayIn3(
            $this->get_products_for_payment($order_details),
            'PayInInstallments'
        );
        return fn_buckaroo_process_response($this, $response, $this->mode);
    }
    /**
     * Get billing info for pay request
     *
     * @param Buckaroo_Order_Details $order_details
     * @param BuckarooIn3 $method
     * @param string $birthdate
     *
     * @return BuckarooIn3  $method
     */
    protected function getBillingInfo($order_details, $method, $birthdate)
    {
        /** @var BuckarooIn3 */
        $method = $this->set_billing($method, $order_details);
        $method->BillingInitials  = $order_details->getInitials(
            $order_details->getBilling('first_name')
        );
        $method->BillingBirthDate = date('Y-m-d', strtotime($birthdate));

        return $method;
    }
}
