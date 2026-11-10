<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Wero\WeroGateway;
use Buckaroo\Woocommerce\Services\UpgSettings;
use BuckarooDeps\Buckaroo\Upg\Testing\FakeHttpClient;
use BuckarooDeps\Buckaroo\Upg\Testing\Fixtures;
use BuckarooDeps\Buckaroo\Upg\Testing\RecordedRequest;
use PHPUnit\Framework\TestCase;

/** A Wero checkout with UPG chosen: pay, then the consumer comes back. */
class Test_UpgCheckout extends TestCase
{
    use HposStorage;
    use UpgTestSupport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saveOptions('woocommerce_buckaroo_mastersettings_settings', 'woocommerce_buckaroo_wero_settings', UpgSettings::STATE_OPTION);
        update_option('woocommerce_buckaroo_mastersettings_settings', [
            'merchantkey' => 'store-key',
            'secretkey' => 'secret-key',
            'api' => 'upg',
            'upg_test_key' => 'apk_test',
            'culture' => 'nl-NL',
            'transactiondescription' => 'Order #{order_number}',
        ]);
        update_option('woocommerce_buckaroo_wero_settings', ['enabled' => 'yes', 'mode' => 'test', 'weropayauthorize' => 'pay']);
        update_option(UpgSettings::STATE_OPTION, ['test' => ['methods' => ['wero'], 'webhook_secret' => str_repeat('s', 40)]]);
        wp_cache_flush();
        wc_load_cart();
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $this->deleteCreatedOrders();
        $this->restoreOptions();
        $_GET = [];
        $_POST = [];
        parent::tearDown();
    }

    public function test_a_wero_payment_goes_to_upg_and_sends_the_consumer_to_the_sale(): void
    {
        $order = $this->order();
        $this->fakeUpg(['POST /v1/sales' => $this->createdSale(['_links' => ['redirect' => ['href' => 'https://wero.example/pay/123']]])]);

        $result = (new WeroGateway())->process_payment($order->get_id());

        $sale = $this->http->recorded()[0]->data();
        $this->assertSame(['result' => 'success', 'redirect' => 'https://wero.example/pay/123'], $result);
        $this->assertSame('upg:sl_123', wc_get_order($order->get_id())->get_transaction_id());
        $this->assertSame((string) $order->get_id(), $sale['metadata']['real_order_id']);
        $this->assertSame('10.00', $sale['totalAmount']);
        $this->assertStringStartsWith($order->get_order_number() . '-', $sale['reference']);
    }

    public function test_paying_again_on_the_same_order_creates_a_new_sale_and_stores_its_reference(): void
    {
        $order = $this->order();
        $ids = ['sl_first', 'sl_second'];
        $this->fakeUpg(['POST /v1/sales' => function (RecordedRequest $request) use (&$ids) {
            return ($this->createdSale(['id' => array_shift($ids)]))($request);
        }]);

        (new WeroGateway())->process_payment($order->get_id());
        (new WeroGateway())->process_payment($order->get_id());

        $references = array_map(fn (RecordedRequest $request): string => $request->data()['reference'], $this->http->recorded());
        $this->assertNotSame($references[0], $references[1]);
        $this->assertSame('upg:sl_second', wc_get_order($order->get_id())->get_transaction_id());
    }

    public function test_a_paid_return_for_an_earlier_attempt_of_the_order_is_accepted(): void
    {
        $order = $this->order();
        $ids = ['sl_first', 'sl_second'];
        $this->fakeUpg(['POST /v1/sales' => function (RecordedRequest $request) use (&$ids) {
            return ($this->createdSale(['id' => array_shift($ids)]))($request);
        }]);
        (new WeroGateway())->process_payment($order->get_id());
        (new WeroGateway())->process_payment($order->get_id());

        $this->returnFor($order, ['id' => 'sl_first', 'status' => 'Paid']);
        $result = (new WeroGateway())->handleReturn();

        $this->assertSame('success', $result['result']);
        $this->assertSame('upg:sl_first', wc_get_order($order->get_id())->get_transaction_id());
    }

    public function test_a_paid_upg_return_shows_the_thank_you_page(): void
    {
        $order = $this->order('upg:sl_123');
        $this->returnFor($order, ['status' => 'Paid']);

        $gateway = new WeroGateway();
        $result = $gateway->handleReturn();

        $this->assertSame('success', $result['result']);
        $this->assertSame($gateway->get_return_url(wc_get_order($order->get_id())), $result['redirect']);
        $this->http->assertSent(fn (RecordedRequest $request): bool => $request->path() === '/v1/sales/reference/1001-abcdef123456');
    }

    public function test_a_failed_upg_return_fails_the_order_and_goes_back_to_checkout(): void
    {
        $order = $this->order('upg:sl_123');
        $this->returnFor($order, ['transactions' => [Fixtures::transaction(['intentType' => 'Pay', 'status' => 'Failed', 'statusConsumerMessage' => 'Your bank declined the payment.'])]]);

        $gateway = new WeroGateway();
        $result = $gateway->handleReturn();

        $order = wc_get_order($order->get_id());
        $this->assertSame('failure', $result['result']);
        $this->assertStringContainsString('bck_err=', $result['redirect']);
        $this->assertSame('failed', $order->get_status());
        $notes = implode("\n", array_map(fn ($note) => $note->content, wc_get_order_notes(['order_id' => $order->get_id()])));
        $this->assertStringContainsString('Your bank declined the payment.', $notes, 'The order note holds the consumer message');
    }

    public function test_a_pending_upg_return_shows_the_thank_you_page_and_leaves_the_order_pending(): void
    {
        $order = $this->order('upg:sl_123');
        $this->returnFor($order, ['transactions' => [Fixtures::transaction(['intentType' => 'Pay', 'status' => 'Pending'])]]);

        $result = (new WeroGateway())->handleReturn();

        $this->assertSame('success', $result['result']);
        $this->assertSame('pending', wc_get_order($order->get_id())->get_status());
    }

    public function test_a_pending_return_never_sends_the_consumer_back_to_the_wallet(): void
    {
        // UPG keeps the redirect link on an Open sale with a Pending attempt (seen in the sandbox).
        $order = $this->order('upg:sl_123');
        $this->returnFor($order, ['_links' => ['redirect' => ['href' => 'https://wero.example/pay/123']]]);

        $gateway = new WeroGateway();
        $result = $gateway->handleReturn();

        $this->assertSame('success', $result['result']);
        $this->assertSame($gateway->get_return_url(wc_get_order($order->get_id())), $result['redirect']);
    }

    public function test_a_return_for_an_older_attempt_changes_nothing(): void
    {
        $order = $this->order('upg:sl_newer');
        $this->returnFor($order, ['status' => 'Failed']);

        $result = (new WeroGateway())->handleReturn();

        $order = wc_get_order($order->get_id());
        $this->assertNull($result);
        $this->assertSame('pending', $order->get_status());
        $this->assertSame('upg:sl_newer', $order->get_transaction_id());
    }

    public function test_a_return_reference_upg_does_not_know_goes_to_the_failed_page(): void
    {
        $this->order('upg:sl_123');
        $_GET = ['wc-api' => 'WC_Gateway_Buckaroo_Wero', 'upg_sale_reference' => 'made-up'];
        $this->fakeUpg(['GET /v1/sales/reference/*' => FakeHttpClient::response(Fixtures::problem(404, 'Not Found'), 404)]);

        $this->assertNull((new WeroGateway())->handleReturn());
    }

    public function test_a_return_upg_cannot_answer_tells_the_consumer_not_to_pay_again(): void
    {
        $this->order('upg:sl_123');
        wc_clear_notices();
        $_GET = ['wc-api' => 'WC_Gateway_Buckaroo_Wero', 'upg_sale_reference' => '1001-abcdef123456'];
        $this->fakeUpg(['GET /v1/sales/reference/*' => FakeHttpClient::response(Fixtures::problem(500, 'Server Error'), 500)]);

        $this->assertNull((new WeroGateway())->handleReturn());

        $notices = implode(' ', array_column(wc_get_notices('notice'), 'notice'));
        $this->assertStringContainsString('do not pay again', $notices);
    }

    public function test_a_return_without_the_upg_parameter_runs_the_bpe_return(): void
    {
        $this->fakeUpg([]);
        $_GET = ['wc-api' => 'WC_Gateway_Buckaroo_Wero'];

        $result = (new WeroGateway())->handleReturn();

        $this->assertSame('failure', $result['result']);
        $this->http->assertNothingSent();
    }

    private function returnFor(WC_Order $order, array $sale): void
    {
        $_GET = ['wc-api' => 'WC_Gateway_Buckaroo_Wero', 'upg_sale_reference' => '1001-abcdef123456'];
        $this->fakeUpg(['GET /v1/sales/reference/1001-abcdef123456' => $this->sale(['metadata' => ['real_order_id' => (string) $order->get_id(), 'invoice' => $order->get_order_number()]] + $sale)]);
    }

    /** Like UPG: the created sale holds the metadata the request sent. */
    private function createdSale(array $overrides = []): Closure
    {
        return function (RecordedRequest $request) use ($overrides) {
            return FakeHttpClient::response($this->sale(['metadata' => $request->data()['metadata']] + $overrides), 201);
        };
    }

    private function sale(array $overrides = []): array
    {
        return Fixtures::sale(array_replace(['id' => 'sl_123', 'transactions' => [Fixtures::transaction(['intentType' => 'Pay', 'status' => 'Pending'])]], $overrides));
    }
}
