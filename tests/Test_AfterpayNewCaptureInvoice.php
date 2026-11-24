<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewGateway;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureResult;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

/**
 * Riverty rejects a capture whose invoice number was already used for the order.
 */
class Test_AfterpayNewCaptureInvoice extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        update_option('woocommerce_buckaroo_mastersettings_settings', [
            'culture' => 'en-US',
            'merchantkey' => 'test-merchant',
            'secretkey' => 'test-secret',
        ]);
        update_option('woocommerce_buckaroo_afterpaynew_settings', ['afterpaynewpayauthorize' => 'authorize']);
        add_filter('woocommerce_order_number', [$this, 'shopOrderNumber'], 10, 2);
    }

    protected function tearDown(): void
    {
        remove_filter('woocommerce_order_number', [$this, 'shopOrderNumber'], 10);
        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        delete_option('woocommerce_buckaroo_afterpaynew_settings');

        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    /** Order numbers differ from order ids, as with a sequential order number plugin. */
    public function shopOrderNumber($number, $order): string
    {
        return 'WC' . $order->get_id();
    }

    public static function storages(): array
    {
        return ['hpos' => [true], 'legacy' => [false]];
    }

    /**
     * @dataProvider storages
     */
    public function test_each_capture_of_an_order_sends_a_new_invoice_number(bool $hpos): void
    {
        $this->useStorage($hpos);
        $order = $this->createAuthorizedOrder();
        $client = $this->client(
            RecordingBuckarooClient::response(190, 'CAPTURE1'),
            RecordingBuckarooClient::response(190, 'CAPTURE2'),
            RecordingBuckarooClient::response(190, 'CAPTURE3')
        );

        foreach (['first', 'second', 'third'] as $capture) {
            $result = $this->captureOneUnit(wc_get_order($order->get_id()), $client);
            $this->assertTrue($result->isSuccess(), $capture . ' capture: ' . $result->getMessage());
        }

        $orderNumber = 'WC' . $order->get_id();
        $this->assertSame(
            [$orderNumber, $orderNumber . '-2', $orderNumber . '-3'],
            array_column($client->bodies, 'invoice')
        );
        foreach ($client->bodies as $body) {
            $this->assertSame((string) $order->get_id(), $body['order'], 'Pushes find the order by its id');
            $this->assertSame($order->get_id(), $body['additionalParameters']['real_order_id']);
        }
    }

    public function test_the_authorization_sends_the_order_number(): void
    {
        $this->useStorage(true);
        $order = $this->createAuthorizedOrder();
        $order->delete_meta_data('_wc_order_authorized');
        $order->save();

        $processor = (new AfterpayNewGateway())->newPaymentProcessorInstance(wc_get_order($order->get_id()));

        $this->assertSame('authorize', $processor->getAction());
        $this->assertSame('WC' . $order->get_id(), $processor->getBody()['invoice']);
    }

    public function test_a_rejected_capture_does_not_use_up_an_invoice_number(): void
    {
        $this->useStorage(true);
        $order = $this->createAuthorizedOrder();
        $client = $this->client(
            RecordingBuckarooClient::response(190, 'CAPTURE1'),
            RecordingBuckarooClient::response(490, 'REJECTED2'),
            RecordingBuckarooClient::response(190, 'CAPTURE2')
        );

        $first = $this->captureOneUnit(wc_get_order($order->get_id()), $client);
        $rejected = $this->captureOneUnit(wc_get_order($order->get_id()), $client);
        $retried = $this->captureOneUnit(wc_get_order($order->get_id()), $client);

        $this->assertTrue($first->isSuccess(), $first->getMessage());
        $this->assertSame(CaptureResult::FAILED, $rejected->getStatus());
        $this->assertTrue($retried->isSuccess(), $retried->getMessage());
        $orderNumber = 'WC' . $order->get_id();
        $this->assertSame(
            [$orderNumber, $orderNumber . '-2', $orderNumber . '-2'],
            array_column($client->bodies, 'invoice')
        );
    }

    private function captureOneUnit(WC_Order $order, RecordingBuckarooClient $client): CaptureResult
    {
        $item = current($order->get_items('line_item'));
        $method = new ReflectionMethod(AfterpayNewGateway::class, 'executeCapture');
        $method->setAccessible(true);

        return $method->invoke(
            new AfterpayNewGateway(),
            $order,
            20.00,
            CaptureAllocation::fromArrays([$item->get_id() => 1], [$item->get_id() => 20.00], []),
            $client
        );
    }

    /** Records the request body the way BuckarooClient::process() builds it. */
    private function client(TransactionResponse ...$responses): RecordingBuckarooClient
    {
        return new class (...$responses) extends RecordingBuckarooClient {
            /** @var array<int, array<string, mixed>> */
            public $bodies = [];

            public function process(AbstractProcessor $processor, array $additionalData = []): TransactionResponse
            {
                $this->bodies[] = array_merge($processor->getBody(), $additionalData);

                return parent::process($processor, $additionalData);
            }
        };
    }

    private function useStorage(bool $hpos): void
    {
        if ($hpos) {
            $this->enableHpos();
        } else {
            $this->disableHpos();
        }
    }

    /** Three units at 20.00, authorized with Riverty and not captured yet. */
    private function createAuthorizedOrder(): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('Riverty product');
        $product->set_regular_price('20');
        $product->set_price('20');
        $product->save();
        $this->productIds[] = $product->get_id();

        $order = $this->createOrder();
        $order->add_product($product, 3);
        $order->set_payment_method('buckaroo_afterpaynew');
        $order->set_billing_first_name('Jan');
        $order->set_billing_last_name('Jansen');
        $order->set_billing_address_1('Hoofdstraat 1');
        $order->set_billing_postcode('1234AB');
        $order->set_billing_city('Amsterdam');
        $order->set_billing_country('NL');
        $order->set_billing_email('jan@example.com');
        $order->set_billing_phone('0612345678');
        $order->set_currency('EUR');
        $order->set_transaction_id('AUTHORIZE1');
        $order->calculate_totals(false);
        $order->update_meta_data('_wc_order_authorized', 'yes');
        $order->save();

        return wc_get_order($order->get_id());
    }
}
