<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Wero\WeroGateway;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\RefundAction;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\UpgSettings;
use BuckarooDeps\Buckaroo\Upg\Testing\FakeHttpClient;
use BuckarooDeps\Buckaroo\Upg\Testing\Fixtures;
use BuckarooDeps\Buckaroo\Upg\Testing\RecordedRequest;
use PHPUnit\Framework\TestCase;

/** A refund from the WooCommerce admin goes to the API that took the payment. */
class Test_UpgRefund extends TestCase
{
    use HposStorage;
    use UpgTestSupport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saveOptions('woocommerce_buckaroo_mastersettings_settings', 'woocommerce_buckaroo_wero_settings', UpgSettings::STATE_OPTION);
        update_option('woocommerce_buckaroo_mastersettings_settings', ['merchantkey' => 'store-key', 'secretkey' => 'secret-key', 'api' => 'upg', 'upg_test_key' => 'apk_test', 'refund_description' => 'Refund {order_number}']);
        update_option('woocommerce_buckaroo_wero_settings', ['enabled' => 'yes', 'mode' => 'test', 'weropayauthorize' => 'pay']);
        update_option(UpgSettings::STATE_OPTION, ['test' => ['methods' => ['wero']]]);
        wp_cache_flush();
    }

    protected function tearDown(): void
    {
        $this->deleteCreatedOrders();
        $this->restoreOptions();
        parent::tearDown();
    }

    public function test_a_partial_refund_of_a_upg_order_goes_to_upg_and_is_accepted_while_pending(): void
    {
        $order = $this->order('upg:sl_123');
        $this->fakeUpg([
            'GET /v1/sales/sl_123' => $this->paidSale(),
            'POST /v1/sales/transactions/*/refund' => FakeHttpClient::response(Fixtures::transaction(['intentType' => 'Refund', 'status' => 'Pending', 'amount' => '-4.00']), 201),
        ]);

        $result = (new WeroGateway())->process_refund($order->get_id(), 4.00, 'Damaged');

        $this->assertTrue($result);
        $refund = $this->http->recorded(fn (RecordedRequest $request): bool => $request->method() === 'POST')[0];
        $this->assertSame('/v1/sales/transactions/trx_paid/refund', $refund->path());
        $this->assertSame('4.00', $refund->data()['amount']);
        $this->assertStringContainsString('is pending processing', $this->notes($order));
        $this->assertStringContainsString('upg:sl_123', $this->notes($order));
    }

    public function test_a_successful_upg_refund_adds_the_refunded_note(): void
    {
        $order = $this->order('upg:sl_123');
        $this->fakeUpg([
            'GET /v1/sales/sl_123' => $this->paidSale(),
            'POST /v1/sales/transactions/*/refund' => FakeHttpClient::response(Fixtures::transaction(['intentType' => 'Refund', 'status' => 'Successful', 'amount' => '-10.00']), 201),
        ]);

        $this->assertTrue((new WeroGateway())->process_refund($order->get_id(), 10.00, ''));
        $this->assertStringContainsString('Refunded', $this->notes($order));
    }

    public function test_a_failed_upg_refund_is_an_error(): void
    {
        $order = $this->order('upg:sl_123');
        $this->fakeUpg([
            'GET /v1/sales/sl_123' => $this->paidSale(),
            'POST /v1/sales/transactions/*/refund' => FakeHttpClient::response(Fixtures::transaction(['intentType' => 'Refund', 'status' => 'Failed', 'amount' => '-4.00', 'statusConsumerMessage' => 'The refund was declined.']), 201),
        ]);

        $result = (new WeroGateway())->process_refund($order->get_id(), 4.00, '');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertStringContainsString('The refund was declined.', $result->get_error_message());
        $this->assertStringContainsString('Refund failed', $this->notes($order));
    }

    public function test_an_order_paid_on_bpe_refunds_on_bpe_with_upg_chosen(): void
    {
        $order = $this->order('4E8BD922192746C3918BF4077CFDF0DC');
        $this->fakeUpg([]);
        $client = new BuckarooClient('test');
        $bpeHttp = RecordingBpeHttpClient::attachTo($client->client(), ['Key' => 'REFUND-KEY', 'Status' => ['Code' => ['Code' => 190]], 'AmountCredit' => 4.0]);
        $action = new RefundAction((new WeroGateway())->newRefundProcessorInstance($order->get_id(), 4.00, ''), $order->get_id(), null);
        $property = new ReflectionProperty(RefundAction::class, 'buckarooClient');
        $property->setValue($action, $client);

        $this->assertTrue($action->process());
        $this->assertCount(1, $bpeHttp->requests);
        $this->assertStringContainsString('"OriginalTransactionKey":"4E8BD922192746C3918BF4077CFDF0DC"', (string) $bpeHttp->requests[0]['body']);
        $this->http->assertNothingSent();
    }

    private function paidSale(): array
    {
        return Fixtures::sale(['id' => 'sl_123', 'status' => 'Paid', 'transactions' => [Fixtures::transaction(['id' => 'trx_paid', 'intentType' => 'Pay', 'status' => 'Successful'])]]);
    }

    private function notes(WC_Order $order): string
    {
        return implode("\n", array_map(fn ($note) => wp_strip_all_tags($note->content), wc_get_order_notes(['order_id' => $order->get_id()])));
    }
}
