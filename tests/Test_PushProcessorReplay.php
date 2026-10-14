<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\ResponseParser\FormDataParser;
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

    public function test_an_already_recorded_success_push_leaves_a_cancelled_order_cancelled()
    {
        $order = $this->createOrderWithTotal(50.00);
        $this->updateSettlementMeta($order, 'TX_ONE', 50.00);
        $order->set_status('cancelled');
        $order->save();

        $this->push($order, 'TX_ONE', '190', '50.00');

        $stored = wc_get_order($order->get_id());
        $this->assertSame('cancelled', $stored->get_status());
        $this->assertNull($stored->get_date_paid());
        $this->assertSame(['TX_ONE' => 50.00], OrderMeta::get($stored, 'buckaroo_settlement'));
    }

    public function test_an_already_recorded_success_push_leaves_a_failed_order_failed()
    {
        $order = $this->createOrderWithTotal(50.00);
        $this->updateSettlementMeta($order, 'TX_ONE', 50.00);
        $order->set_status('failed');
        $order->save();

        $this->push($order, 'TX_ONE', '190', '50.00');

        $this->assertSame('failed', wc_get_order($order->get_id())->get_status());
    }

    public function test_a_new_success_push_is_recorded_but_does_not_reopen_a_cancelled_order()
    {
        $order = $this->createOrderWithTotal(50.00);
        $order->set_status('cancelled');
        $order->save();

        $this->push($order, 'TX_LATE', '190', '50.00');

        $stored = wc_get_order($order->get_id());
        $this->assertSame('cancelled', $stored->get_status());
        $this->assertSame(['TX_LATE' => 50.00], OrderMeta::get($stored, 'buckaroo_settlement'));
    }

    public function test_a_pending_push_leaves_a_refunded_order_refunded()
    {
        $order = $this->createOrderWithTotal(50.00);
        $order->set_status('refunded');
        $order->save();

        $this->push($order, 'TX_ONE', '791', '50.00');

        $this->assertSame('refunded', wc_get_order($order->get_id())->get_status());
    }

    public function test_the_first_success_push_completes_a_pending_order()
    {
        $order = $this->createOrderWithTotal(50.00);

        $this->push($order, 'TX_ONE', '190', '50.00');

        $stored = wc_get_order($order->get_id());
        $this->assertSame('processing', $stored->get_status());
        $this->assertSame(['TX_ONE' => 50.00], OrderMeta::get($stored, 'buckaroo_settlement'));
        $this->assertSame('ok', OrderMeta::get($stored, '_pushallowed'));
    }

    public function test_a_new_success_push_completes_a_failed_order_on_retry()
    {
        $order = $this->createOrderWithTotal(50.00);
        $order->set_status('failed');
        $order->save();

        $this->push($order, 'TX_RETRY', '190', '50.00');

        $this->assertSame('processing', wc_get_order($order->get_id())->get_status());
    }

    public function test_partial_payments_complete_the_order_when_the_total_is_reached()
    {
        $order = $this->createOrderWithTotal(50.00);

        $this->push($order, 'TX_ONE', '190', '20.00');
        $this->assertSame('pending', wc_get_order($order->get_id())->get_status());

        $this->push(wc_get_order($order->get_id()), 'TX_TWO', '190', '30.00');

        $stored = wc_get_order($order->get_id());
        $this->assertSame('processing', $stored->get_status());
        $this->assertSame(['TX_ONE' => 20.00, 'TX_TWO' => 30.00], OrderMeta::get($stored, 'buckaroo_settlement'));
    }

    private function createOrderWithTotal(float $total): WC_Order
    {
        $order = $this->createOrder();
        $order->set_total($total);
        $order->save();

        return wc_get_order($order->get_id());
    }

    private function push(WC_Order $order, string $transactionKey, string $statusCode, string $amount): void
    {
        if (function_exists('wc_load_cart') && WC()->cart === null) {
            wc_load_cart();
        }

        $responseParser = new FormDataParser([
            'brq_statuscode' => $statusCode,
            'brq_amount' => $amount,
            'brq_currency' => 'EUR',
            'brq_transactions' => $transactionKey,
            'brq_transaction_method' => 'ideal',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);
        $responseParser->set('coreStatus', BuckarooTransactionStatus::fromTransactionStatus($responseParser->getStatusCode()));

        $reflectionMethod = new \ReflectionMethod(PushProcessor::class, 'onSuccess');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke(new PushProcessor(), $order->get_id(), $order, $responseParser);
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
