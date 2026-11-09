<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Constraints\BuckarooTransactionStatus;
use Buckaroo\Woocommerce\Gateways\AbstractRefundProcessor;
use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\ResponseParser\FormDataParser;
use PHPUnit\Framework\TestCase;

/**
 * A validly signed reply is applied only to an order it belongs to: same
 * currency, same mode, and for failure and pending replies the order's
 * current transaction.
 */
class Test_PaymentReplyMatching extends TestCase
{
    use HposStorage;

    /** @var array */
    private $gateways;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableHpos();
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        $this->gateways = WC()->payment_gateways()->payment_gateways;
    }

    protected function tearDown(): void
    {
        WC()->payment_gateways()->payment_gateways = $this->gateways;
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    /** @dataProvider currencies */
    public function test_a_reply_in_another_currency_does_not_match_the_order(string $replyCurrency, ?string $expected): void
    {
        $order = $this->createPaymentOrder('live');

        $this->assertSame($expected, PushProcessor::replyMismatch($order, $this->reply($order, 'TX_ONE', '190', $replyCurrency)));
    }

    public function currencies(): array
    {
        return [
            'same currency' => ['EUR', null],
            'same currency, other case' => ['eur', null],
            'no currency in the reply' => ['', null],
            'other currency' => ['USD', 'currency'],
        ];
    }

    public function test_a_test_mode_reply_does_not_match_a_live_order(): void
    {
        $order = $this->createPaymentOrder('live');

        $this->assertSame('mode', PushProcessor::replyMismatch($order, $this->reply($order, 'TX_ONE', '190', 'EUR', true)));
        $this->assertNull(PushProcessor::replyMismatch($order, $this->reply($order, 'TX_ONE', '190', 'EUR')));
    }

    public function test_a_test_mode_reply_matches_an_order_placed_or_paid_in_test_mode(): void
    {
        $placedInTest = $this->createPaymentOrder('live');
        OrderMeta::update($placedInTest, '_buckaroo_order_in_test_mode', true);
        $testGateway = $this->createPaymentOrder('test');

        $this->assertNull(PushProcessor::replyMismatch(wc_get_order($placedInTest->get_id()), $this->reply($placedInTest, 'TX_ONE', '190', 'EUR', true)));
        $this->assertNull(PushProcessor::replyMismatch($testGateway, $this->reply($testGateway, 'TX_ONE', '190', 'EUR', true)));
    }

    public function test_only_a_reply_for_another_transaction_is_stale(): void
    {
        $order = $this->createPaymentOrder('live', 'TX_CURRENT');
        $withoutTransaction = $this->createPaymentOrder('live');
        $payPerEmail = $this->createPaymentOrder('live', 'TX_INVITATION');
        $payPerEmail->set_payment_method('buckaroo_payperemail');
        $payPerEmail->save();

        $this->assertTrue(PushProcessor::isStaleReply($order, $this->reply($order, 'TX_EARLIER', '490')));
        $this->assertFalse(PushProcessor::isStaleReply($order, $this->reply($order, 'TX_CURRENT', '490')));
        $this->assertFalse(PushProcessor::isStaleReply($withoutTransaction, $this->reply($withoutTransaction, 'TX_EARLIER', '490')));
        $this->assertFalse(PushProcessor::isStaleReply($payPerEmail, $this->reply($payPerEmail, 'TX_PAYMENT', '490')));
    }

    public function test_a_failure_push_for_an_earlier_transaction_leaves_the_order_pending(): void
    {
        $order = $this->createPaymentOrder('live', 'TX_CURRENT');

        $this->failurePush($order, 'TX_EARLIER');

        $this->assertSame('pending', wc_get_order($order->get_id())->get_status());
    }

    public function test_a_failure_push_for_the_current_transaction_fails_the_order(): void
    {
        $order = $this->createPaymentOrder('live', 'TX_CURRENT');

        $this->failurePush($order, 'TX_CURRENT');

        $this->assertSame('failed', wc_get_order($order->get_id())->get_status());
    }

    public function test_a_failure_push_does_not_fail_a_paid_order(): void
    {
        $order = $this->createPaymentOrder('live', 'TX_CURRENT');
        $order->payment_complete('TX_CURRENT');

        $this->failurePush(wc_get_order($order->get_id()), 'TX_CURRENT');

        $this->assertTrue(wc_get_order($order->get_id())->is_paid());
    }

    public function test_a_pending_push_for_an_earlier_transaction_does_not_put_the_order_on_hold(): void
    {
        $order = $this->createPaymentOrder('live', 'TX_CURRENT');

        $this->successPush($order, 'TX_EARLIER', '791');

        $stored = wc_get_order($order->get_id());
        $this->assertSame('pending', $stored->get_status());
        $this->assertSame('TX_CURRENT', $stored->get_transaction_id());
    }

    public function test_a_pending_push_for_the_current_transaction_puts_the_order_on_hold(): void
    {
        $order = $this->createPaymentOrder('live', 'TX_CURRENT');

        $this->successPush($order, 'TX_CURRENT', '791');

        $this->assertSame('on-hold', wc_get_order($order->get_id())->get_status());
    }

    public function test_a_refund_is_requested_in_the_order_currency(): void
    {
        $orderCurrency = get_woocommerce_currency() === 'GBP' ? 'USD' : 'GBP';
        $order = $this->createPaymentOrder('live', 'TX_CURRENT');
        $order->set_currency($orderCurrency);
        $order->save();
        $gateway = WC()->payment_gateways()->payment_gateways['buckaroo_ideal'];

        $body = (new AbstractRefundProcessor($gateway, new OrderDetails($order), 10.00, 'Test'))->getBody();

        $this->assertSame($orderCurrency, $body['currency']);
    }

    private function createPaymentOrder(string $mode, string $transactionId = ''): WC_Order
    {
        $gateway = new IdealGateway();
        $gateway->settings['mode'] = $mode;
        WC()->payment_gateways()->payment_gateways = ['buckaroo_ideal' => $gateway];

        $order = $this->createOrder();
        $order->set_currency('EUR');
        $order->set_total(50.00);
        $order->set_payment_method('buckaroo_ideal');
        $order->set_transaction_id($transactionId);
        $order->save();

        return wc_get_order($order->get_id());
    }

    private function reply(WC_Order $order, string $transactionKey, string $statusCode, string $currency = 'EUR', bool $test = false): FormDataParser
    {
        $responseParser = new FormDataParser([
            'brq_statuscode' => $statusCode,
            'brq_amount' => '50.00',
            'brq_currency' => $currency,
            'brq_test' => $test ? 'true' : 'false',
            'brq_transactions' => $transactionKey,
            'brq_transaction_method' => 'ideal',
            'brq_ordernumber' => (string) $order->get_id(),
        ]);
        $responseParser->set('coreStatus', BuckarooTransactionStatus::fromTransactionStatus($responseParser->getStatusCode()));

        return $responseParser;
    }

    private function failurePush(WC_Order $order, string $transactionKey): void
    {
        $reflectionMethod = new \ReflectionMethod(PushProcessor::class, 'onFailure');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke(new PushProcessor(), $order, $this->reply($order, $transactionKey, '490'));
    }

    private function successPush(WC_Order $order, string $transactionKey, string $statusCode): void
    {
        if (function_exists('wc_load_cart') && WC()->cart === null) {
            wc_load_cart();
        }

        $reflectionMethod = new \ReflectionMethod(PushProcessor::class, 'onSuccess');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke(new PushProcessor(), $order->get_id(), $order, $this->reply($order, $transactionKey, $statusCode));
    }
}
