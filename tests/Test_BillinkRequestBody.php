<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Billink\BillinkGateway;

/**
 * The Billink request carries the VAT number and careOf the shopper gave
 * (BTI-1880).
 */
class Test_BillinkRequestBody extends WP_UnitTestCase
{
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

    /**
     * The block checkout lower-cases every payment data key before it copies
     * them into $_POST, so the field key is lower case on both checkouts.
     */
    public function test_the_vat_number_is_sent(): void
    {
        $_POST = wp_slash(['buckaroo-billink-vatnumber' => 'NL123456789B01']);

        $body = $this->body('Jansen BV');

        $this->assertSame('NL123456789B01', $body['vATNumber']);
    }

    public function test_care_of_is_the_company_of_a_business_order(): void
    {
        $body = $this->body('Jansen BV');

        $this->assertSame('B2B', $body['billing']['recipient']['category']);
        $this->assertSame('Jansen BV', $body['billing']['recipient']['careOf']);
        $this->assertSame('Jansen BV', $body['shipping']['recipient']['careOf']);
    }

    public function test_care_of_is_the_name_of_a_private_order(): void
    {
        $body = $this->body('');

        $this->assertSame('B2C', $body['billing']['recipient']['category']);
        $this->assertSame('Jan Jansen', $body['billing']['recipient']['careOf']);
        $this->assertSame('Jan Jansen', $body['shipping']['recipient']['careOf']);
    }

    private function body(string $company): array
    {
        $address = [
            'first_name' => 'Jan',
            'last_name' => 'Jansen',
            'company' => $company,
            'address_1' => 'Kerkstraat 1',
            'city' => 'Heerenveen',
            'postcode' => '8441ER',
            'country' => 'NL',
            'phone' => '0612345678',
            'email' => 'jan@example.com',
        ];

        $this->order = wc_create_order();
        $this->order->set_address($address, 'billing');
        $this->order->set_address($address, 'shipping');
        $this->order->set_total('10.00');
        $this->order->save();

        return (new BillinkGateway())->newPaymentProcessorInstance($this->order)->getBody();
    }
}
