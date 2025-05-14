<?php

namespace Buckaroo\Woocommerce\Gateways\Afterpay;

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Services\Helper;
use Buckaroo\Woocommerce\Traits\HasDateValidation;
use WC_Order;

class AfterpayNewGateway extends AbstractPaymentGateway
{
    use HasDateValidation;

    public const PAYMENT_CLASS = AfterpayNewProcessor::class;

    public const CUSTOMER_TYPE_B2C = 'b2c';

    public const CUSTOMER_TYPE_B2B = 'b2b';

    public const CUSTOMER_TYPE_BOTH = 'both';

    public $type;

    public $b2b;

    public $vattype;

    public $country;

    public $sendimageinfo;

    public $afterpaynewpayauthorize;

    public $customer_type;

    public bool $capturable = true;

    public function __construct()
    {
        $this->id = 'buckaroo_afterpaynew';
        $this->title = 'Riverty';
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
     * Validate payment fields on the frontend.
     *
     * @return void
     */
    public function validate_fields()
    {
        $country = $this->request->input('billing_country');
        if ($country === null) {
            $country = $this->country;
        }

        $birthdate = $this->parseDate(
            $this->request->input('buckaroo-afterpaynew-birthdate')
        );

        if (! ($this->validateDate($birthdate, 'd-m-Y') && $this->validateBirthdate($birthdate)) && in_array($country, ['NL', 'BE'])) {
            wc_add_notice(__('You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if (! $this->request->input('buckaroo-afterpaynew-accept')) {
            wc_add_notice(__('Please accept licence agreements', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if (
            $this->customer_type !== self::CUSTOMER_TYPE_B2C &&
            $country === 'NL' &&
            $this->request->input('billing_company')
        ) {
            if ($this->request->input('buckaroo-afterpaynew-company-coc-registration') === null) {
                wc_add_notice(__('Company registration number is required', 'wc-buckaroo-bpe-gateway'), 'error');
            }
        }

        if (! $this->request->input('buckaroo-afterpaynew-phone') && ! $this->request->input('billing_phone')) {
            wc_add_notice(__('Please enter phone number', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if (
            $this->is_house_number_invalid('billing')
        ) {
            wc_add_notice(__('Invalid billing address, cannot find house number', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        if (
            $this->is_house_number_invalid('shipping') &&
            $this->request->input('ship_to_different_address') == 1
        ) {
            wc_add_notice(__('Invalid shipping address, cannot find house number', 'wc-buckaroo-bpe-gateway'), 'error');
        }

        parent::validate_fields();
    }

    private function is_house_number_invalid($type)
    {
        $components = OrderDetails::getAddressComponents(
            $this->request->input($type . '_address_1') . ' ' . $this->request->input($type . '_address_2')
        );

        return ! is_string($components['house_number']) || empty(trim($components['house_number']));
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

        if ($processedPayment['result'] == 'success' && $this->afterpaynewpayauthorize == 'authorize') {
            update_post_meta($order_id, '_wc_order_authorized', 'yes');
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
        $this->add_financial_warning_field();
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

        $this->form_fields['sendimageinfo'] = [
            'title' => __('Send image info', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('Image info will be sent to BPE gateway inside ImageUrl parameter', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                '0' => 'No',
                '1' => 'Yes',
            ],
            'default' => 'pay',
            'desc_tip' => 'Product images are only shown when they are available in JPG or PNG format',
        ];
        $this->form_fields['customer_type'] = [
            'title' => __('Riverty customer type', 'wc-buckaroo-bpe-gateway'),
            'type' => 'select',
            'description' => __('This setting determines whether you accept Riverty payments for B2C, B2B or both customer types. When B2B is selected, this method is only shown when a company name is entered in the checkout process.', 'wc-buckaroo-bpe-gateway'),
            'options' => [
                self::CUSTOMER_TYPE_BOTH => __('Both'),
                self::CUSTOMER_TYPE_B2C => __('B2C (Business-to-consumer)'),
                self::CUSTOMER_TYPE_B2B => __('B2B ((Business-to-Business)'),
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
     * Show payment if available
     *
     *
     * @return bool
     */
    public function isAvailable(float $cartTotal)
    {
        if ($this->customer_type !== self::CUSTOMER_TYPE_B2B) {
            return $this->isAvailableB2B($cartTotal);
        }

        return true;
    }

    /**
     * Check if payment is available for b2b
     *
     *
     * @return bool
     */
    public function isAvailableB2B(float $cartTotal)
    {
        $b2bMin = $this->get_option('b2b_min_value', 0);
        $b2bMax = $this->get_option('b2b_max_value', 0);

        if ($b2bMin == 0 && $b2bMax == 0) {
            return true;
        }

        return ($b2bMin > 0 && $cartTotal > $b2bMin) || ($b2bMax > 0 && $cartTotal < $b2bMax);
    }

    /**  {@inheritDoc} */
    protected function setProperties()
    {
        parent::setProperties();
        $this->afterpaynewpayauthorize = $this->get_option('afterpaynewpayauthorize');
        $this->sendimageinfo = $this->get_option('sendimageinfo');
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

        return $this->afterpaynewpayauthorize == 'authorize' && get_post_meta($order->get_id(), '_wc_order_authorized', true) == 'yes';
    }
}
