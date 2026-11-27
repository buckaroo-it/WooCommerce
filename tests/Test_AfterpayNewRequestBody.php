<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewGateway;

/**
 * The Riverty request carries the Finnish identification number and a careOf
 * that matches the category it is sent with (BTI-1880).
 */
class Test_AfterpayNewRequestBody extends WP_UnitTestCase
{
    private const ID_REQUIRED = 'Please enter your identification number';

    /** @var WC_Order|null */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        if ($this->order instanceof WC_Order) {
            $this->order->delete(true);
        }
        delete_option('woocommerce_buckaroo_mastersettings_settings');

        parent::tearDown();
    }

    public function test_a_finnish_order_sends_the_identification_number(): void
    {
        $_POST = wp_slash(['buckaroo-afterpaynew-identification-number' => '131052-308T']);

        $body = $this->body($this->gateway(), 'FI');

        $this->assertSame('131052-308T', $body['billing']['recipient']['identificationNumber']);
        $this->assertArrayNotHasKey('identificationNumber', $body['shipping']['recipient']);
    }

    public function test_a_finnish_order_without_identification_number_is_rejected(): void
    {
        $gateway = $this->gateway();
        $_POST = ['buckaroo-afterpaynew-accept' => 'ON'];

        $body = $this->body($gateway, 'FI');

        $this->assertArrayNotHasKey('identificationNumber', $body['billing']['recipient']);
        $this->assertContains(self::ID_REQUIRED, $gateway->getOrderValidationErrors($this->order));
    }

    public static function otherCountries(): array
    {
        return ['NL' => ['NL'], 'BE' => ['BE'], 'DE' => ['DE']];
    }

    /**
     * @dataProvider otherCountries
     */
    public function test_other_countries_send_no_identification_number_and_do_not_ask_for_it(string $country): void
    {
        $gateway = $this->gateway();
        $_POST = wp_slash([
            'buckaroo-afterpaynew-identification-number' => '131052-308T',
            'buckaroo-afterpaynew-accept' => 'ON',
        ]);

        $body = $this->body($gateway, $country);

        $this->assertArrayNotHasKey('identificationNumber', $body['billing']['recipient']);
        $this->assertNotContains(self::ID_REQUIRED, $gateway->getOrderValidationErrors($this->order));
    }

    public function test_care_of_is_the_company_of_a_company_order(): void
    {
        $_POST = wp_slash(['buckaroo-afterpaynew-company-coc-registration' => '87654321']);

        $body = $this->body($this->gateway(), 'NL', 'Jansen BV');

        $this->assertSame('Company', $body['billing']['recipient']['category']);
        $this->assertSame('Jansen BV', $body['billing']['recipient']['careOf']);
        $this->assertSame('Jansen BV', $body['shipping']['recipient']['careOf']);
    }

    public function test_care_of_is_the_name_when_business_customers_are_not_accepted(): void
    {
        $body = $this->body($this->gateway(AfterpayNewGateway::CUSTOMER_TYPE_B2C), 'NL', 'Jansen BV');

        $this->assertSame('Person', $body['billing']['recipient']['category']);
        $this->assertSame('Jan Jansen', $body['billing']['recipient']['careOf']);
    }

    public function test_care_of_is_the_name_outside_the_netherlands(): void
    {
        $body = $this->body($this->gateway(), 'DE', 'Jansen GmbH');

        $this->assertSame('Person', $body['billing']['recipient']['category']);
        $this->assertSame('Jan Jansen', $body['billing']['recipient']['careOf']);
        $this->assertSame('Jan Jansen', $body['shipping']['recipient']['careOf']);
    }

    private function gateway(string $customerType = AfterpayNewGateway::CUSTOMER_TYPE_BOTH): AfterpayNewGateway
    {
        $gateway = new AfterpayNewGateway();
        $gateway->settings['customer_type'] = $customerType;
        $gateway->customer_type = $customerType;

        return $gateway;
    }

    private function body(AfterpayNewGateway $gateway, string $country, string $company = ''): array
    {
        $address = [
            'first_name' => 'Jan',
            'last_name' => 'Jansen',
            'company' => $company,
            'address_1' => 'Kerkstraat 1',
            'city' => 'Helsinki',
            'postcode' => '00100',
            'country' => $country,
            'phone' => '0612345678',
            'email' => 'jan@example.com',
        ];

        $this->order = wc_create_order();
        $this->order->set_address($address, 'billing');
        $this->order->set_address($address, 'shipping');
        $this->order->set_total('10.00');
        $this->order->save();

        return $gateway->newPaymentProcessorInstance($this->order)->getBody();
    }
}
