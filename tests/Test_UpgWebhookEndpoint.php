<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\PaymentProcessors\UpgWebhookProcessor;
use Buckaroo\Woocommerce\ResponseParser\UpgResponseParser;
use Buckaroo\Woocommerce\Services\UpgSettings;
use BuckarooDeps\Buckaroo\Upg\Testing\Fixtures;
use BuckarooDeps\Buckaroo\Upg\Webhook\WebhookSignature;
use PHPUnit\Framework\TestCase;

/** UPG sends SALE.UPDATE webhooks to their own endpoint; the BPE push endpoint does not change. */
class Test_UpgWebhookEndpoint extends TestCase
{
    use HposStorage;
    use UpgTestSupport;

    private const TEST_SECRET = 'test_secret_of_the_upg_sandbox_webhook_endpoint';

    private const LIVE_SECRET = 'live_secret_of_the_upg_production_webhook_endpoint';

    protected function setUp(): void
    {
        parent::setUp();
        $this->saveOptions('woocommerce_buckaroo_mastersettings_settings', UpgSettings::STATE_OPTION);
        update_option('woocommerce_buckaroo_mastersettings_settings', ['merchantkey' => 'store-key', 'secretkey' => 'secret-key', 'api' => 'upg', 'upg_test_key' => 'apk_test', 'upg_live_key' => 'apk_live']);
        update_option(UpgSettings::STATE_OPTION, [
            'test' => ['methods' => ['wero'], 'webhook_secret' => self::TEST_SECRET],
            'live' => ['methods' => ['wero'], 'webhook_secret' => self::LIVE_SECRET],
        ]);
        wp_cache_flush();
        wc_load_cart();
    }

    protected function tearDown(): void
    {
        $this->deleteCreatedOrders();
        $this->restoreOptions();
        parent::tearDown();
    }

    public function test_a_paid_sale_completes_the_order_once(): void
    {
        $order = $this->order('upg:sl_123');
        $body = $this->saleBody($order, ['status' => 'Paid', 'paidAmount' => '10.00']);
        $headers = $this->sign($body, self::TEST_SECRET, 'delivery-1');

        $this->assertSame(200, (new UpgWebhookProcessor())->handle($headers, $body));
        $order = wc_get_order($order->get_id());
        $this->assertTrue($order->is_paid());
        $notes = count(wc_get_order_notes(['order_id' => $order->get_id()]));

        // UPG delivers at least once: the same delivery again changes nothing.
        $this->assertSame(200, (new UpgWebhookProcessor())->handle($this->sign($body, self::TEST_SECRET, 'delivery-1'), $body));
        $this->assertSame($notes, count(wc_get_order_notes(['order_id' => $order->get_id()])));
    }

    public function test_a_pending_sale_changes_nothing(): void
    {
        $order = $this->order('upg:sl_123');
        $notes = count(wc_get_order_notes(['order_id' => $order->get_id()]));
        $body = $this->saleBody($order, [
            'transactions' => [Fixtures::transaction(['intentType' => 'Pay', 'status' => 'Pending'])],
            '_links' => ['redirect' => ['href' => 'https://wero.example/pay/123']],
        ]);

        $this->assertSame(200, (new UpgWebhookProcessor())->handle($this->sign($body, self::TEST_SECRET, 'delivery-1'), $body));
        $this->assertSame('pending', wc_get_order($order->get_id())->get_status());
        $this->assertSame($notes, count(wc_get_order_notes(['order_id' => $order->get_id()])));
    }

    public function test_a_webhook_signed_with_the_live_secret_is_accepted_too(): void
    {
        $order = $this->order('upg:sl_123');
        $body = $this->saleBody($order, ['status' => 'Paid']);

        $this->assertSame(200, (new UpgWebhookProcessor())->handle($this->sign($body, self::LIVE_SECRET, 'delivery-1'), $body));
        $this->assertTrue(wc_get_order($order->get_id())->is_paid());
    }

    public function test_a_wrong_signature_changes_nothing(): void
    {
        $order = $this->order('upg:sl_123');
        $body = $this->saleBody($order, ['status' => 'Paid']);

        $this->assertSame(401, (new UpgWebhookProcessor())->handle($this->sign($body, 'a_secret_upg_never_used_for_this_shop_at_all', 'delivery-1'), $body));
        $this->assertFalse(wc_get_order($order->get_id())->is_paid());
    }

    public function test_a_replayed_request_is_rejected(): void
    {
        $order = $this->order('upg:sl_123');
        $body = $this->saleBody($order, ['status' => 'Failed']);
        $headers = $this->sign($body, self::TEST_SECRET, 'delivery-1', 'nonce-1');
        (new UpgWebhookProcessor())->handle($headers, $body);

        $paid = $this->saleBody($order, ['status' => 'Paid']);
        $replayed = $this->sign($paid, self::TEST_SECRET, 'delivery-2', 'nonce-1');

        $this->assertSame(401, (new UpgWebhookProcessor())->handle($replayed, $paid));
        $this->assertFalse(wc_get_order($order->get_id())->is_paid());
    }

    public function test_a_retry_with_the_same_headers_is_acknowledged(): void
    {
        $order = $this->order('upg:sl_123');
        $body = $this->saleBody($order, ['status' => 'Paid']);
        $headers = $this->sign($body, self::TEST_SECRET, 'delivery-1', 'nonce-1');

        $this->assertSame(200, (new UpgWebhookProcessor())->handle($headers, $body));
        $notes = count(wc_get_order_notes(['order_id' => $order->get_id()]));

        $this->assertSame(200, (new UpgWebhookProcessor())->handle($headers, $body));
        $this->assertSame($notes, count(wc_get_order_notes(['order_id' => $order->get_id()])));
    }

    public function test_a_webhook_for_an_older_attempt_does_not_change_the_order(): void
    {
        $order = $this->order('upg:sl_newer');
        UpgResponseParser::rememberSale($order, 'upg:sl_older');
        $body = $this->saleBody($order, ['id' => 'sl_older', 'status' => 'Expired']);

        $this->assertSame(200, (new UpgWebhookProcessor())->handle($this->sign($body, self::TEST_SECRET, 'delivery-1'), $body));
        $this->assertSame('pending', wc_get_order($order->get_id())->get_status());
    }

    public function test_a_paid_older_attempt_completes_the_order(): void
    {
        $order = $this->order('upg:sl_newer');
        UpgResponseParser::rememberSale($order, 'upg:sl_older');
        $body = $this->saleBody($order, ['id' => 'sl_older', 'status' => 'Paid']);

        $this->assertSame(200, (new UpgWebhookProcessor())->handle($this->sign($body, self::TEST_SECRET, 'delivery-1'), $body));
        $order = wc_get_order($order->get_id());
        $this->assertTrue($order->is_paid());
        $this->assertSame('upg:sl_older', $order->get_transaction_id());
    }

    /** One UPG account can serve several shops, and order ids repeat between them. */
    public function test_a_paid_sale_this_order_did_not_create_changes_nothing(): void
    {
        $order = $this->order('upg:sl_mine');
        $body = $this->saleBody($order, ['id' => 'sl_of_another_shop', 'status' => 'Paid']);

        $this->assertSame(200, (new UpgWebhookProcessor())->handle($this->sign($body, self::TEST_SECRET, 'delivery-1'), $body));
        $order = wc_get_order($order->get_id());
        $this->assertFalse($order->is_paid());
        $this->assertSame('upg:sl_mine', $order->get_transaction_id());
    }

    public function test_a_failed_sale_fails_the_order(): void
    {
        $order = $this->order('upg:sl_123');
        $body = $this->saleBody($order, ['status' => 'Failed']);

        $this->assertSame(200, (new UpgWebhookProcessor())->handle($this->sign($body, self::TEST_SECRET, 'delivery-1'), $body));
        $this->assertSame('failed', wc_get_order($order->get_id())->get_status());
    }

    public function test_an_event_without_a_sale_status_is_acknowledged(): void
    {
        $body = (string) wp_json_encode(Fixtures::transaction());

        $this->assertSame(200, (new UpgWebhookProcessor())->handle(WebhookSignature::generateHeaders($body, self::TEST_SECRET, UpgSettings::webhookUrl(), 'TRANSACTION.UPDATE'), $body));
    }

    public function test_a_sale_for_an_unknown_order_is_acknowledged(): void
    {
        $body = (string) wp_json_encode(Fixtures::sale(['id' => 'sl_123', 'status' => 'Paid', 'metadata' => ['real_order_id' => '999999999']]));

        $this->assertSame(200, (new UpgWebhookProcessor())->handle($this->sign($body, self::TEST_SECRET, 'delivery-1'), $body));
    }

    private function saleBody(WC_Order $order, array $overrides): string
    {
        return (string) wp_json_encode(Fixtures::sale(array_replace([
            'id' => 'sl_123',
            'totalAmount' => '10.00',
            'metadata' => ['real_order_id' => (string) $order->get_id(), 'invoice' => $order->get_order_number()],
        ], $overrides)));
    }

    private function sign(string $body, string $secret, string $idempotencyKey, ?string $nonce = null): array
    {
        return WebhookSignature::generateHeaders($body, $secret, UpgSettings::webhookUrl(), 'SALE.UPDATE', $idempotencyKey, null, $nonce);
    }
}
