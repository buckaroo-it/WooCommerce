<?php

namespace Buckaroo\Woocommerce\Gateways\Afterpay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Traits\HasDateValidation;
use WC_Order;

class AfterpayNewGateway extends AbstractPaymentGateway
{
    use HasDateValidation;

    public const PAYMENT_CLASS = AfterpayNewProcessor::class;

    public const REFUND_CLASS = AfterpayNewRefundProcessor::class;

    protected array $supportedCountries = ['NL', 'BE', 'DE'];

    public const CUSTOMER_TYPE_B2C = 'b2c';

    public const CUSTOMER_TYPE_B2B = 'b2b';

    public const CUSTOMER_TYPE_BOTH = 'both';

    public $type;

    public $b2b;

    public $vattype;

    public $country;

    public $afterpaynewpayauthorize;

    public $customer_type;

    public bool $capturable = true;

    public function __construct()
    {
        $this->id = 'buckaroo_afterpaynew';
        $this->title = 'Riverty';
        $this->method_description = __('European pay-after-delivery within 30 days.', 'wc-buckaroo-bpe-gateway');
        $this->has_fields = false;
        $this->method_title = 'Buckaroo Riverty';
        $this->setIcon('svg/afterpay.svg');
        $this->setCountry();

        parent::__construct();
        $this->addRefundSupport();
    }

    public function getServiceCode(?AbstractProcessor $processor = null)
    {
        return 'afterpay';
    }

    /**
     * Validate the payment fields against the billing details of the order.
     *
     * @return array<string>
     */
    public function getOrderValidationErrors(WC_Order $order): array
    {
        $errors = [];
        $country = $order->get_billing_country();
        $isB2B = $this->isB2BOrder($order);

        if ($this->customer_type === self::CUSTOMER_TYPE_B2B && ! $isB2B) {
            $errors[] = __('Riverty is only available for business customers in the Netherlands. Please fill in your company name in the billing details or choose another payment method.', 'wc-buckaroo-bpe-gateway');
        }

        if ($isB2B && ! $this->isAvailableB2B((float) $order->get_total('edit'))) {
            $errors[] = __('This payment method is not available for this order. Please choose another payment method.', 'wc-buckaroo-bpe-gateway');
        }

        if (in_array($country, ['NL', 'BE'], true) && ! $this->validateBirthdate($this->request->input('buckaroo-afterpaynew-birthdate'))) {
            $errors[] = __('You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.', 'wc-buckaroo-bpe-gateway');
        }

        if ($this->isBlank($this->request->input('buckaroo-afterpaynew-accept'))) {
            $errors[] = __('Please accept licence agreements', 'wc-buckaroo-bpe-gateway');
        }

        if ($isB2B && $this->isBlank($this->request->input('buckaroo-afterpaynew-company-coc-registration'))) {
            $errors[] = __('Company registration number is required', 'wc-buckaroo-bpe-gateway');
        }

        if ($this->getFieldOrFallback('buckaroo-afterpaynew-phone', $order->get_billing_phone()) === '') {
            $errors[] = __('Please enter phone number', 'wc-buckaroo-bpe-gateway');
        }

        if ($this->isHouseNumberMissing($order->get_billing_address_1(), $order->get_billing_address_2())) {
            $errors[] = __('Invalid billing address, cannot find house number', 'wc-buckaroo-bpe-gateway');
        }

        if (
            ! $this->isBlank($order->get_shipping_address_1()) &&
            $this->isHouseNumberMissing($order->get_shipping_address_1(), $order->get_shipping_address_2())
        ) {
            $errors[] = __('Invalid shipping address, cannot find house number', 'wc-buckaroo-bpe-gateway');
        }

        return $errors;
    }

    /**
     * The order is paid as a business (category "Company") when B2B is
     * enabled, the billing country is NL and a billing company is set; the
     * same rule the payment request uses.
     */
    public function isB2BOrder(WC_Order $order): bool
    {
        return $this->customer_type !== self::CUSTOMER_TYPE_B2C
            && $order->get_billing_country() === 'NL'
            && ! $this->isBlank($order->get_billing_company());
    }

    private function isHouseNumberMissing(string $address_1, string $address_2): bool
    {
        $components = OrderDetails::getAddressComponents($address_1 . ' ' . $address_2);

        return ! is_string($components['house_number']) || trim($components['house_number']) === '';
    }

    /**
     * Process payment
     *
     * @param  int  $order_id
     * @return callable|void fn_buckaroo_process_response() or void
     */
    public function process_payment($order_id)
    {
        $processedPayment = parent::process_payment($order_id);

        if (isset($processedPayment['result']) && $processedPayment['result'] == 'success' && $this->afterpaynewpayauthorize == 'authorize') {
            OrderMeta::update($order_id, '_wc_order_authorized', 'yes');
            $this->set_order_capture($order_id, 'AfterpayNew');
        }

        return $processedPayment;
    }

    /**
     * Add fields to the form_fields() array, specific to this page.
     */
    public function init_form_fields()
    {
        parent::init_form_fields();
        $this->form_fields['afterpaynewpayauthorize'] = [
            'title' => __('Riverty Pay or Capture', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Choose to execute Pay or Capture call', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                'pay' => 'Pay',
                'authorize' => 'Authorize',
            ],
            'default' => 'pay',
        ];

        $this->form_fields['customer_type'] = [
            'title' => __('Riverty customer type', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('This setting determines whether you accept Riverty payments for B2C, B2B or both customer types. When B2B is selected, this method is only shown when a company name is entered in the checkout process.', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                self::CUSTOMER_TYPE_BOTH => __('Both', 'wc-buckaroo-bpe-gateway'),
                self::CUSTOMER_TYPE_B2C => __('B2C (Business-to-consumer)', 'wc-buckaroo-bpe-gateway'),
                self::CUSTOMER_TYPE_B2B => __('B2B ((Business-to-Business)', 'wc-buckaroo-bpe-gateway'),
            ],
            'default' => self::CUSTOMER_TYPE_BOTH,
        ];
        $this->form_fields['b2b_min_value'] = [
            'title' => __('Min order amount  for B2B', 'wc-buckaroo-bpe-gateway'),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01'],
            'description' => __('The payment method shows only for orders with an order amount greater than the minimum amount.', 'wc-buckaroo-bpe-gateway'),
            'default' => '0',
        ];
        $this->form_fields['b2b_max_value'] = [
            'title' => __('Max order amount  for B2B', 'wc-buckaroo-bpe-gateway'),
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01'],
            'description' => __('The payment method shows only for orders with an order amount smaller than the maximum amount.', 'wc-buckaroo-bpe-gateway'),
            'default' => '0',
        ];
    }

    /**
     * Show payment if available. When only B2B is accepted every order is a
     * business order, so the B2B amount range applies; with "Both" it is
     * checked per order in getOrderValidationErrors().
     *
     * @return bool
     */
    public function isAvailable(float $cartTotal)
    {
        if ($this->customer_type === self::CUSTOMER_TYPE_B2B) {
            return $this->isAvailableB2B($cartTotal);
        }

        return true;
    }

    /**
     * Whether the amount lies within the configured B2B range, both limits
     * included. A limit of 0 (or empty) means no limit on that side.
     *
     * @return bool
     */
    public function isAvailableB2B(float $amount)
    {
        $b2bMin = (float) $this->get_option('b2b_min_value', 0);
        $b2bMax = (float) $this->get_option('b2b_max_value', 0);

        if ($b2bMin > 0 && $amount < $b2bMin) {
            return false;
        }

        return ! ($b2bMax > 0 && $amount > $b2bMax);
    }

    /**  {@inheritDoc} */
    protected function setProperties()
    {
        parent::setProperties();
        $this->afterpaynewpayauthorize = $this->get_option('afterpaynewpayauthorize');
        $this->vattype = $this->get_option('vattype');
        $this->type = 'afterpay';
        $this->customer_type = $this->get_option('customer_type', self::CUSTOMER_TYPE_BOTH);
    }

    public function canShowCaptureForm($order): bool
    {
        $order = Helper::resolveOrder($order);

        if (! $order instanceof WC_Order) {
            return false;
        }

        return $this->afterpaynewpayauthorize == 'authorize' && OrderMeta::get($order, '_wc_order_authorized') == 'yes';
    }
}
