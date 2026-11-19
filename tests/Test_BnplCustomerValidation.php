<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewGateway;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayOldGateway;
use Buckaroo\Woocommerce\Gateways\Billink\BillinkGateway;
use Buckaroo\Woocommerce\Gateways\In3\In3Gateway;
use Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\ZakelijkOpRekeningGateway;
use PHPUnit\Framework\TestCase;

/**
 * Customer validation of Riverty, In3, Billink and Zakelijk op rekening runs
 * against the billing details of the order being paid, so classic checkout,
 * Blocks checkout and order-pay are checked the same way.
 */
class Test_BnplCustomerValidation extends TestCase
{
    private const AGE_ERROR = 'You must be at least 18 years old to use this payment method. Please enter your correct date of birth. Or choose another payment method to complete your order.';

    private const NOT_AVAILABLE = 'This payment method is not available for this order. Please choose another payment method.';

    private const B2B_ONLY = 'Riverty is only available for business customers in the Netherlands. Please fill in your company name in the billing details or choose another payment method.';

    /** @var array<WC_Order> */
    private array $orders = [];

    private array $post = [];

    private $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post = $_POST;
        $this->customer = WC()->customer;
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        WC()->customer = $this->customer;
        foreach ($this->orders as $order) {
            $order->delete(true);
        }
        wc_clear_notices();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    private function order(array $billing = [], float $total = 100.0, array $shipping = []): WC_Order
    {
        $billing = array_merge([
            'country' => 'NL',
            'company' => '',
            'phone' => '0612345678',
            'address_1' => 'Hoofdstraat 1',
            'address_2' => '',
        ], $billing);

        $order = wc_create_order();
        foreach ($billing as $key => $value) {
            $order->{'set_billing_' . $key}($value);
        }
        foreach ($shipping as $key => $value) {
            $order->{'set_shipping_' . $key}($value);
        }
        $order->set_currency('EUR');
        $order->set_total($total);
        $order->save();

        return $this->orders[] = $order;
    }

    private function adultBirthdate(): string
    {
        return (new DateTimeImmutable('today', wp_timezone()))->modify('-30 years')->format('d-m-Y');
    }

    private function rivertyNew(string $customerType = AfterpayNewGateway::CUSTOMER_TYPE_BOTH, $min = 0, $max = 0): AfterpayNewGateway
    {
        $gateway = new AfterpayNewGateway();
        $gateway->customer_type = $customerType;
        $gateway->settings['b2b_min_value'] = $min;
        $gateway->settings['b2b_max_value'] = $max;
        $gateway->minvalue = 0;
        $gateway->maxvalue = 0;

        return $gateway;
    }

    private function rivertyOld(string $b2b = 'disable'): AfterpayOldGateway
    {
        $gateway = new AfterpayOldGateway();
        $gateway->b2b = $b2b;
        $gateway->type = 'afterpaydigiaccept';

        return $gateway;
    }

    private function validRivertyNewPost(array $extra = []): array
    {
        return array_merge([
            'buckaroo-afterpaynew-birthdate' => $this->adultBirthdate(),
            'buckaroo-afterpaynew-accept' => 'ON',
        ], $extra);
    }

    private function errors(AbstractPaymentGateway $gateway, WC_Order $order, array $post): array
    {
        $_POST = $post;

        return $gateway->getOrderValidationErrors($order);
    }

    private function callProtected($object, string $method, ...$args)
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($object, ...$args);
    }

    // ------------------------------------------------------ order data source

    public function test_posted_billing_country_does_not_override_order_country()
    {
        $order = $this->order(['country' => 'NL']);
        $post = ['billing_country' => 'DE', 'buckaroo-afterpaynew-accept' => 'ON', 'buckaroo-in3-phone' => '0612345678'];

        $this->assertContains(self::AGE_ERROR, $this->errors($this->rivertyNew(), $order, $post));
        $this->assertContains(self::AGE_ERROR, $this->errors(new In3Gateway(), $order, $post));
    }

    public function test_posted_billing_company_does_not_override_order_company()
    {
        $withCompany = $this->order(['company' => 'Acme BV']);
        $this->assertContains(
            'Company registration number is required',
            $this->errors($this->rivertyNew(), $withCompany, $this->validRivertyNewPost(['billing_company' => '']))
        );
        $this->assertContains(
            'Please enter correct COC (KvK) number',
            $this->errors(new BillinkGateway(), $withCompany, ['billing_company' => ''])
        );

        $withoutCompany = $this->order(['company' => '']);
        $this->assertContains(
            self::B2B_ONLY,
            $this->errors($this->rivertyNew(AfterpayNewGateway::CUSTOMER_TYPE_B2B), $withoutCompany, $this->validRivertyNewPost(['billing_company' => 'Acme BV']))
        );
        $this->assertSame([], $this->errors(new BillinkGateway(), $withoutCompany, ['billing_company' => 'Acme BV']));
    }

    public function test_session_customer_country_does_not_override_order_country()
    {
        WC()->customer = new WC_Customer();
        WC()->customer->set_billing_country('DE');
        WC()->customer->set_shipping_country('DE');

        $order = $this->order(['country' => 'NL']);

        $this->assertContains(self::AGE_ERROR, $this->errors(new In3Gateway(), $order, []));
        $this->assertContains(self::AGE_ERROR, $this->errors($this->rivertyNew(), $order, ['buckaroo-afterpaynew-accept' => 'ON']));
    }

    public function test_order_pay_uses_target_order_billing_details()
    {
        WC()->customer = new WC_Customer();
        WC()->customer->set_billing_company('');

        // Order-pay posts only the payment method fields.
        $order = $this->order(['company' => 'Acme BV', 'country' => 'NL']);

        $errors = $this->errors($this->rivertyNew(), $order, $this->validRivertyNewPost());

        $this->assertContains('Company registration number is required', $errors);
    }

    public function test_zakelijk_rejects_non_nl_order_country()
    {
        $post = [
            'billing_country' => 'NL',
            'buckaroo-zakelijkoprekening-company-coc-registration' => '12345678',
        ];
        $message = 'Zakelijk op rekening is only available for companies located in The Netherlands.';

        $this->assertContains($message, $this->errors(new ZakelijkOpRekeningGateway(), $this->order(['country' => 'BE', 'company' => 'Acme BV']), $post));
        $this->assertContains($message, $this->errors(new ZakelijkOpRekeningGateway(), $this->order(['country' => '', 'company' => 'Acme BV']), $post));
    }

    public function test_zakelijk_accepts_valid_nl_order()
    {
        $order = $this->order(['country' => 'NL', 'company' => 'Acme BV'], 500.0);

        $this->assertSame([], $this->errors(new ZakelijkOpRekeningGateway(), $order, [
            'buckaroo-zakelijkoprekening-company-coc-registration' => '12345678',
        ]));
    }

    public function test_zakelijk_requires_company_on_order_or_method_field()
    {
        $order = $this->order(['company' => '']);
        $post = ['billing_company' => 'Acme BV', 'buckaroo-zakelijkoprekening-company-coc-registration' => '12345678'];
        $message = 'A company name is required to pay with Zakelijk op rekening. Please fill in the company name in your billing details.';

        $this->assertContains($message, $this->errors(new ZakelijkOpRekeningGateway(), $order, $post));

        $post['buckaroo-zakelijkoprekening-company'] = 'Acme BV';
        $this->assertNotContains($message, $this->errors(new ZakelijkOpRekeningGateway(), $order, $post));
    }

    public function test_process_payment_stops_before_payment_request()
    {
        $order = $this->order(['country' => 'NL']);
        $_POST = ['buckaroo-afterpaynew-accept' => 'ON'];

        $result = $this->rivertyNew()->process_payment($order->get_id());

        $this->assertSame(['result' => 'failure'], $result);
        $this->assertContains(self::AGE_ERROR, wp_list_pluck(wc_get_notices('error'), 'notice'));
    }

    // ------------------------------------------------------- date of birth

    /**
     * @return array<string, array{0: string|null, 1: bool}>
     */
    public static function birthdates(): array
    {
        $today = new DateTimeImmutable('today', wp_timezone());

        return [
            'future' => [$today->modify('+1 day')->format('d-m-Y'), false],
            'two-digit year' => ['01-01-50', false],
            'year 2050' => ['01-01-2050', false],
            'aged 17' => [$today->modify('-17 years')->format('d-m-Y'), false],
            'turns 18 tomorrow' => [$today->modify('-18 years +1 day')->format('d-m-Y'), false],
            'exactly 18 today' => [$today->modify('-18 years')->format('d-m-Y'), true],
            'older than 18' => [$today->modify('-40 years')->format('d/m/Y'), true],
            'missing' => [null, false],
            'empty' => ['', false],
            'invalid calendar date' => ['31-02-1990', false],
            'unsupported format' => ['1990-01-15', false],
        ];
    }

    /**
     * @dataProvider birthdates
     */
    public function test_in3_birthdate($birthdate, bool $valid)
    {
        $post = $birthdate === null ? [] : ['buckaroo-in3-birthdate' => $birthdate];

        $errors = $this->errors(new In3Gateway(), $this->order(), $post);

        $this->assertSame($valid, ! in_array(self::AGE_ERROR, $errors, true));
    }

    /**
     * @dataProvider birthdates
     */
    public function test_riverty_birthdate($birthdate, bool $valid)
    {
        $post = ['buckaroo-afterpaynew-accept' => 'ON'];
        if ($birthdate !== null) {
            $post['buckaroo-afterpaynew-birthdate'] = $birthdate;
        }

        $errors = $this->errors($this->rivertyNew(), $this->order(), $post);

        $this->assertSame($valid, ! in_array(self::AGE_ERROR, $errors, true));
    }

    /**
     * @dataProvider birthdates
     */
    public function test_riverty_old_birthdate($birthdate, bool $valid)
    {
        $post = ['buckaroo-afterpay-accept' => 'ON'];
        if ($birthdate !== null) {
            $post['buckaroo-afterpay-birthdate'] = $birthdate;
        }

        $errors = $this->errors($this->rivertyOld(), $this->order(), $post);

        $this->assertSame($valid, ! in_array(self::AGE_ERROR, $errors, true));
    }

    public function test_validated_birthdate_is_sent_in_payment_request()
    {
        $order = $this->order(['country' => 'NL', 'first_name' => 'Jan', 'last_name' => 'Jansen']);

        $_POST = [
            'buckaroo-afterpaynew-birthdate' => '05/09/1990',
            'buckaroo-afterpay-birthdate' => '05/09/1990',
            'buckaroo-in3-birthdate' => '05/09/1990',
        ];

        $new = $this->rivertyNew()->newPaymentProcessorInstance($order);
        $this->assertSame('1990-09-05', $this->callProtected($new, 'getBirthDate', 'NL')['billing']['recipient']['birthDate']);

        $old = $this->rivertyOld()->newPaymentProcessorInstance($order);
        $this->assertSame('05-09-1990', $this->callProtected($old, 'getBirthDate', 'NL')['billing']['recipient']['birthDate']);

        $in3 = (new In3Gateway())->newPaymentProcessorInstance($order);
        $this->assertSame('1990-09-05', $this->callProtected($in3, 'getBilling')['billing']['recipient']['birthDate']);
    }

    // ------------------------------------------------------------ Riverty B2B

    public function test_riverty_old_b2b_option_ignored_when_disabled_by_merchant()
    {
        $post = [
            'buckaroo-afterpay-accept' => 'ON',
            'buckaroo-afterpay-b2b' => 'ON',
            'buckaroo-afterpay-company-coc-registration' => '12345678',
            'buckaroo-afterpay-company-name' => 'Acme BV',
        ];

        $this->assertContains(self::AGE_ERROR, $this->errors($this->rivertyOld('disable'), $this->order(), $post));

        $acceptgiro = $this->rivertyOld('enable');
        $acceptgiro->type = 'afterpayacceptgiro';
        $this->assertContains(self::AGE_ERROR, $this->errors($acceptgiro, $this->order(), $post));
    }

    public function test_riverty_old_b2b_checkout_when_enabled()
    {
        $post = [
            'buckaroo-afterpay-accept' => 'ON',
            'buckaroo-afterpay-b2b' => 'ON',
            'buckaroo-afterpay-company-coc-registration' => '12345678',
            'buckaroo-afterpay-company-name' => 'Acme BV',
        ];

        $this->assertSame([], $this->errors($this->rivertyOld('enable'), $this->order(), $post));

        $post['buckaroo-afterpay-company-name'] = '  ';
        $this->assertContains('Company name is required', $this->errors($this->rivertyOld('enable'), $this->order(), $post));
    }

    public function test_riverty_b2b_only_rejects_consumer_order()
    {
        $gateway = $this->rivertyNew(AfterpayNewGateway::CUSTOMER_TYPE_B2B);

        $this->assertContains(self::B2B_ONLY, $this->errors($gateway, $this->order(['company' => '']), $this->validRivertyNewPost()));
        $this->assertContains(self::B2B_ONLY, $this->errors($gateway, $this->order(['company' => 'Acme BV', 'country' => 'BE']), $this->validRivertyNewPost()));
        $this->assertSame([], $this->errors(
            $gateway,
            $this->order(['company' => 'Acme BV']),
            $this->validRivertyNewPost(['buckaroo-afterpaynew-company-coc-registration' => '12345678'])
        ));
    }

    /**
     * @return array<string, array{0: float, 1: bool}>
     */
    public static function b2bAmounts(): array
    {
        return [
            'below minimum' => [99.99, false],
            'at minimum' => [100.0, true],
            'within range' => [250.0, true],
            'at maximum' => [500.0, true],
            'above maximum' => [500.01, false],
        ];
    }

    /**
     * @dataProvider b2bAmounts
     */
    public function test_riverty_b2b_amount_range(float $total, bool $allowed)
    {
        $gateway = $this->rivertyNew(AfterpayNewGateway::CUSTOMER_TYPE_BOTH, '100', '500');
        $order = $this->order(['company' => 'Acme BV'], $total);

        $errors = $this->errors($gateway, $order, $this->validRivertyNewPost(['buckaroo-afterpaynew-company-coc-registration' => '12345678']));

        $this->assertSame($allowed, ! in_array(self::NOT_AVAILABLE, $errors, true));

        $b2bOnly = $this->rivertyNew(AfterpayNewGateway::CUSTOMER_TYPE_B2B, '100', '500');
        $this->assertSame($allowed, $b2bOnly->isAvailable($total));
    }

    public function test_riverty_b2b_amount_range_does_not_apply_to_consumers()
    {
        $both = $this->rivertyNew(AfterpayNewGateway::CUSTOMER_TYPE_BOTH, '100', '500');
        $this->assertSame([], $this->errors($both, $this->order(['company' => ''], 1000.0), $this->validRivertyNewPost()));
        $this->assertTrue($both->isAvailable(1000.0));

        $b2c = $this->rivertyNew(AfterpayNewGateway::CUSTOMER_TYPE_B2C, '100', '500');
        $this->assertSame([], $this->errors($b2c, $this->order(['company' => 'Acme BV'], 1000.0), $this->validRivertyNewPost()));
        $this->assertTrue($b2c->isAvailable(1000.0));
    }

    public function test_riverty_b2b_limits_of_zero_are_unbounded()
    {
        $gateway = $this->rivertyNew(AfterpayNewGateway::CUSTOMER_TYPE_B2B, '0', '500');
        $this->assertTrue($gateway->isAvailable(0.01));
        $this->assertFalse($gateway->isAvailable(501.0));

        $gateway = $this->rivertyNew(AfterpayNewGateway::CUSTOMER_TYPE_B2B, '100', '');
        $this->assertTrue($gateway->isAvailable(100000.0));
        $this->assertFalse($gateway->isAvailable(99.0));
    }

    public function test_riverty_b2c_setting_never_treats_order_as_business()
    {
        $gateway = $this->rivertyNew(AfterpayNewGateway::CUSTOMER_TYPE_B2C);

        $this->assertFalse($gateway->isB2BOrder($this->order(['company' => 'Acme BV'])));
        $this->assertTrue($this->rivertyNew()->isB2BOrder($this->order(['company' => 'Acme BV'])));
        $this->assertFalse($this->rivertyNew()->isB2BOrder($this->order(['company' => 'Acme BV', 'country' => 'BE'])));
    }

    // --------------------------------------------------------- required fields

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function requiredValues(): array
    {
        return [
            'missing' => ['__missing__', false],
            'null' => [null, false],
            'empty string' => ['', false],
            'whitespace' => ["  \t ", false],
            'value' => ['12345678', true],
            'zero' => ['0', true],
        ];
    }

    /**
     * @dataProvider requiredValues
     */
    public function test_required_coc_number($value, bool $valid)
    {
        $order = $this->order(['company' => 'Acme BV']);

        $riverty = $this->validRivertyNewPost();
        $billink = [];
        if ($value !== '__missing__') {
            $riverty['buckaroo-afterpaynew-company-coc-registration'] = $value;
            $billink['buckaroo-billink-company-coc-registration'] = $value;
        }

        $this->assertSame($valid, ! in_array('Company registration number is required', $this->errors($this->rivertyNew(), $order, $riverty), true));
        $this->assertSame($valid, ! in_array('Please enter correct COC (KvK) number', $this->errors(new BillinkGateway(), $order, $billink), true));
    }

    /**
     * @dataProvider requiredValues
     */
    public function test_required_terms_acceptance($value, bool $valid)
    {
        $post = ['buckaroo-afterpaynew-birthdate' => $this->adultBirthdate()];
        if ($value !== '__missing__') {
            $post['buckaroo-afterpaynew-accept'] = $value;
        }

        $errors = $this->errors($this->rivertyNew(), $this->order(), $post);

        $this->assertSame($valid, ! in_array('Please accept licence agreements', $errors, true));
    }

    public function test_required_phone_falls_back_to_order_phone()
    {
        $message = 'Please fill in a phone number for In3. This is required in order to use this payment method.';
        $post = ['buckaroo-in3-birthdate' => $this->adultBirthdate(), 'billing_phone' => '0612345678', 'buckaroo-in3-phone' => ''];

        $this->assertContains($message, $this->errors(new In3Gateway(), $this->order(['phone' => '']), $post));
        $this->assertNotContains($message, $this->errors(new In3Gateway(), $this->order(['phone' => '0612345678']), $post));

        $post['buckaroo-in3-phone'] = '0687654321';
        $this->assertNotContains($message, $this->errors(new In3Gateway(), $this->order(['phone' => '']), $post));
    }

    public function test_riverty_house_number_is_read_from_order()
    {
        $post = $this->validRivertyNewPost(['billing_address_1' => 'Hoofdstraat 1']);

        $errors = $this->errors($this->rivertyNew(), $this->order(['address_1' => 'Hoofdstraat']), $post);
        $this->assertContains('Invalid billing address, cannot find house number', $errors);

        $errors = $this->errors($this->rivertyNew(), $this->order([], 100.0, ['address_1' => 'Kerkstraat']), $post);
        $this->assertContains('Invalid shipping address, cannot find house number', $errors);

        $this->assertSame([], $this->errors($this->rivertyNew(), $this->order([], 100.0, ['address_1' => 'Kerkstraat 5']), $post));
    }
}
