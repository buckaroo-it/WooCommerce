<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Billink\BillinkGateway;

/**
 * The payment request gets a culture also when the master settings have none
 * (BTI-1880).
 */
class Test_PaymentCulture extends WP_UnitTestCase
{
    private const MASTER_SETTINGS = 'woocommerce_buckaroo_mastersettings_settings';

    /** @var WC_Order|null */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->order = wc_create_order();
        $this->order->set_total('10.00');
        $this->order->save();
    }

    protected function tearDown(): void
    {
        if ($this->order instanceof WC_Order) {
            $this->order->delete(true);
        }
        delete_option(self::MASTER_SETTINGS);

        parent::tearDown();
    }

    public function test_the_dynamic_culture_is_used_without_master_settings(): void
    {
        delete_option(self::MASTER_SETTINGS);

        $this->assertSame('en-US', $this->culture());
    }

    public function test_the_dynamic_culture_is_used_when_the_setting_is_missing(): void
    {
        update_option(self::MASTER_SETTINGS, ['merchantkey' => 'website-key']);

        $this->assertSame('en-US', $this->culture());
    }

    public function test_the_configured_culture_is_used(): void
    {
        update_option(self::MASTER_SETTINGS, ['culture' => 'de-DE']);

        $this->assertSame('de-DE', $this->culture());
    }

    private function culture(): string
    {
        return (new BillinkGateway())->newPaymentProcessorInstance($this->order)->getBody()['culture'];
    }
}
