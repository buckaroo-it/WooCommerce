<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use PHPUnit\Framework\TestCase;

/**
 * A push can arrive twice for the same transaction; settlement meta and the
 * _pushallowed lock stop the second one counting as a new payment.
 */
class Test_PushProcessorReplay extends TestCase
{
    use HposStorage;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce is not available');
        }

        $this->enableHpos();
    }

    protected function tearDown(): void
    {
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_settlement_meta_round_trips_through_order_meta()
    {
        $order = $this->createOrder();

        $this->updateSettlementMeta($order, 'TX_ONE', 20.00);

        $this->assertSame(
            ['TX_ONE' => 20.00],
            OrderMeta::get($order->get_id(), 'buckaroo_settlement'),
            'Settlement meta should be readable back through the order meta API'
        );
        $this->assertSame(
            '',
            get_post_meta($order->get_id(), 'buckaroo_settlement', true),
            'Settlement meta should no longer be written to post meta'
        );
    }

    public function test_a_replayed_push_is_not_counted_as_a_new_payment()
    {
        $order = $this->createOrder();

        $first = $this->calculateSettlementState($order, 'TX_ONE', 20.00);
        $this->assertTrue($first['isNewPayment']);
        $this->assertSame(20.00, $first['totalPaid']);

        $this->updateSettlementMeta($order, 'TX_ONE', 20.00);

        $replay = $this->calculateSettlementState($order, 'TX_ONE', 20.00);
        $this->assertFalse($replay['isNewPayment'], 'A replayed transaction key is not a new payment');
        $this->assertSame(20.00, $replay['totalPaid'], 'A replayed push must not inflate the total paid');
    }

    public function test_a_second_partial_payment_is_counted_once_and_added_to_the_total()
    {
        $order = $this->createOrder();

        $this->updateSettlementMeta($order, 'TX_ONE', 20.00);

        $second = $this->calculateSettlementState($order, 'TX_TWO', 30.00);
        $this->assertTrue($second['isNewPayment']);
        $this->assertSame(50.00, $second['totalPaid']);

        $this->updateSettlementMeta($order, 'TX_TWO', 30.00);

        $replay = $this->calculateSettlementState($order, 'TX_TWO', 30.00);
        $this->assertFalse($replay['isNewPayment']);
        $this->assertSame(50.00, $replay['totalPaid']);
    }

    /** Written with $unique = true, so a replay must leave a single value. */
    public function test_a_replayed_push_writes_the_pushallowed_lock_only_once()
    {
        $order = $this->createOrder();

        $this->assertTrue(OrderMeta::add($order, '_pushallowed', 'ok', true));
        $this->assertFalse(OrderMeta::add($order, '_pushallowed', 'ok', true));

        $this->assertSame(['ok'], OrderMeta::get($order->get_id(), '_pushallowed', false));
    }

    private function calculateSettlementState(WC_Order $order, string $transactionKey, float $paidAmount): array
    {
        return $this->callProcessor('calculateSettlementState', $order, $transactionKey, $paidAmount);
    }

    private function updateSettlementMeta(WC_Order $order, string $transactionKey, float $paidAmount): void
    {
        $this->callProcessor('updateSettlementMeta', $order, $transactionKey, $paidAmount);
    }

    /**
     * @return mixed
     */
    private function callProcessor(string $method, WC_Order $order, string $transactionKey, float $paidAmount)
    {
        $processor = new PushProcessor();

        $reflectionMethod = new \ReflectionMethod(PushProcessor::class, $method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke(
            $processor,
            $order,
            $this->createResponseParser($transactionKey),
            $paidAmount
        );
    }

    private function createResponseParser(string $transactionKey): ResponseParser
    {
        $responseParser = $this->getMockBuilder(ResponseParser::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTransactionKey', 'getRelatedTransactionPartialPayment'])
            ->getMockForAbstractClass();

        $responseParser->method('getTransactionKey')->willReturn($transactionKey);
        $responseParser->method('getRelatedTransactionPartialPayment')->willReturn(null);

        return $responseParser;
    }
}
