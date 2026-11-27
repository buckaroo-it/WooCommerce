<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaKpGateway;

/**
 * The Klarna KP request carries the shopper's own address and phone, and none
 * of the Riverty fields the classic checkout posts alongside it (BTI-1880).
 */
class Test_KlarnaKpProcessor extends WP_UnitTestCase
{
    /** @var WC_Order|null */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);

        $this->order = wc_create_order();
        $this->order->set_address(
            [
                'first_name' => 'Jan',
                'last_name' => 'Jansen',
                'company' => 'Jansen BV',
                'address_1' => 'Kerkstraat 1',
                'city' => 'Heerenveen',
                'postcode' => '8441ER',
                'country' => 'NL',
                'phone' => '0612345678',
                'email' => 'jan@example.com',
            ],
            'billing'
        );
        $this->order->set_address(
            [
                'first_name' => 'Piet',
                'last_name' => 'Pietersen',
                'address_1' => 'Dorpsweg 7',
                'city' => 'Utrecht',
                'postcode' => '3511AB',
                'country' => 'NL',
            ],
            'shipping'
        );
        $this->order->set_total('10.00');
        $this->order->save();
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

    public function test_the_request_carries_the_billing_and_shipping_postcodes_of_the_order(): void
    {
        $body = $this->body();

        $this->assertSame('8441ER', $body['billing']['address']['zipcode']);
        $this->assertSame('3511AB', $body['shipping']['address']['zipcode']);
    }

    public function test_the_request_carries_the_billing_phone_and_ignores_the_riverty_fields(): void
    {
        $_POST = wp_slash([
            'buckaroo-afterpaynew-phone' => '0699999999',
            'buckaroo-afterpaynew-company-coc-registration' => '87654321',
        ]);

        $body = $this->body();

        $this->assertSame('0612345678', $body['billing']['phone']['mobile']);
        $this->assertStringNotContainsString('87654321', (string) wp_json_encode($body));
        $this->assertStringNotContainsString('0699999999', (string) wp_json_encode($body));
    }

    private function body(): array
    {
        return (new KlarnaKpGateway())->newPaymentProcessorInstance($this->order)->getBody();
    }
}
