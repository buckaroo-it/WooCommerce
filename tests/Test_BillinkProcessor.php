<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Billink\BillinkProcessor;
use Buckaroo\Woocommerce\Order\OrderDetails;
use PHPUnit\Framework\TestCase;

/**
 * Test the Billink birth date resolution.
 *
 * Billink no longer asks for a date of birth in our checkout. We only pass one
 * on when WooCommerce already knows it, and leave the parameter out otherwise
 * so Billink One can ask for it on its own page.
 */
class Test_BillinkProcessor extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WC_Order class not available');
        }
    }

    /**
     * Build a processor around an order returning $meta for order meta lookups.
     *
     * @param  array<string, string>  $meta
     */
    private function processorForMeta(array $meta, int $customer_id = 0): BillinkProcessor
    {
        $order = $this->createMock(WC_Order::class);
        $order->method('get_customer_id')->willReturn($customer_id);
        $order->method('get_meta')->willReturnCallback(
            static fn ($key) => $meta[$key] ?? ''
        );

        $processor = (new ReflectionClass(BillinkProcessor::class))->newInstanceWithoutConstructor();

        $property = new ReflectionProperty(
            'Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor',
            'order_details'
        );
        $property->setAccessible(true);
        $property->setValue($processor, new OrderDetails($order));

        return $processor;
    }

    /**
     * @return array<string, string>
     */
    private function birthDate(BillinkProcessor $processor): array
    {
        $method = new ReflectionMethod(BillinkProcessor::class, 'getBirthDate');
        $method->setAccessible(true);

        return $method->invoke($processor);
    }

    public function test_birth_date_is_read_from_order_meta()
    {
        $processor = $this->processorForMeta(['billing_birthdate' => '1990-04-21']);

        $this->assertSame(['birthDate' => '21-04-1990'], $this->birthDate($processor));
    }

    public function test_birth_date_is_read_from_any_known_meta_key()
    {
        $processor = $this->processorForMeta(['_billing_date_of_birth' => '21-04-1990']);

        $this->assertSame(['birthDate' => '21-04-1990'], $this->birthDate($processor));
    }

    public function test_birth_date_is_omitted_when_not_available()
    {
        $processor = $this->processorForMeta([]);

        $this->assertSame([], $this->birthDate($processor));
    }

    public function test_birth_date_is_omitted_when_unparsable()
    {
        $processor = $this->processorForMeta(['billing_birthdate' => 'not a date']);

        $this->assertSame([], $this->birthDate($processor));
    }

    public function test_birth_date_can_be_supplied_by_filter()
    {
        $callback = static fn () => '01-02-1985';
        add_filter('buckaroo_billink_birthdate', $callback);

        try {
            $processor = $this->processorForMeta([]);

            $this->assertSame(['birthDate' => '01-02-1985'], $this->birthDate($processor));
        } finally {
            remove_filter('buckaroo_billink_birthdate', $callback);
        }
    }
}
