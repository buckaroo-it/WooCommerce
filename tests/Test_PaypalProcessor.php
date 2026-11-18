<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Paypal\PaypalExpressPayment;
use Buckaroo\Woocommerce\Gateways\Paypal\PaypalGateway;
use Buckaroo\Woocommerce\Gateways\Paypal\PaypalProcessor;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Transaction\Client;
use BuckarooDeps\Buckaroo\Transaction\Request\HttpClient\HttpClientInterface;

class Test_PaypalProcessor extends WP_UnitTestCase
{
    private const PAYPAL_ORDER_ID = '5O190127TN364715T';

    /** @var mixed */
    private $master_settings_before;

    /** @var WC_Order|null */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master_settings_before = get_option('woocommerce_buckaroo_mastersettings_settings', null);
        update_option(
            'woocommerce_buckaroo_mastersettings_settings',
            [
                'merchantkey' => 'website-key',
                'secretkey' => 'secret-key',
                'culture' => 'nl-NL',
            ]
        );

        $this->order = wc_create_order();
        $this->order->set_address(
            [
                'first_name' => 'Jan',
                'last_name' => 'Jansen',
                'address_1' => 'Kerkstraat 1',
                'city' => 'Heerenveen',
                'postcode' => '8441ER',
                'country' => 'NL',
                'phone' => '0612345678',
                'email' => 'jan@example.com',
            ],
            'billing'
        );
        $this->order->set_total('10.00');
        $this->order->save();
    }

    protected function tearDown(): void
    {
        if ($this->order instanceof WC_Order) {
            $this->order->delete(true);
        }
        if ($this->master_settings_before === null) {
            delete_option('woocommerce_buckaroo_mastersettings_settings');
        } else {
            update_option('woocommerce_buckaroo_mastersettings_settings', $this->master_settings_before);
        }

        parent::tearDown();
    }

    public function test_regular_payment_with_seller_protection_sends_the_customer_details(): void
    {
        $processor = $this->processor(true, false);

        $this->assertSame('extraInfo', $processor->getAction());

        $service = $this->service($processor);
        $this->assertSame('Pay,ExtraInfo', $service['Action']);
        $this->assertSellerData($service['Parameters']);
        $this->assertArrayNotHasKey('PayPalOrderId', $service['Parameters']);
    }

    public function test_regular_payment_without_seller_protection_sends_no_customer_details(): void
    {
        $processor = $this->processor(false, false);

        $this->assertSame('pay', $processor->getAction());

        $service = $this->service($processor);
        $this->assertSame('Pay', $service['Action']);
        $this->assertSame([], $service['Parameters']);
    }

    public function test_express_payment_with_seller_protection_sends_the_paypal_order_and_customer_details(): void
    {
        $processor = $this->processor(true, true);

        $this->assertSame(PaypalExpressPayment::ACTION, $processor->getAction());

        $service = $this->service($processor);
        $this->assertSame('Pay,ExtraInfo', $service['Action']);
        $this->assertSame(self::PAYPAL_ORDER_ID, $service['Parameters']['PayPalOrderId']);
        $this->assertSellerData($service['Parameters']);
    }

    public function test_express_payment_without_seller_protection_sends_only_the_paypal_order(): void
    {
        $processor = $this->processor(false, true);

        $this->assertSame('pay', $processor->getAction());

        $service = $this->service($processor);
        $this->assertSame('Pay', $service['Action']);
        $this->assertSame(['PayPalOrderId' => self::PAYPAL_ORDER_ID], $service['Parameters']);
    }

    public function test_express_payment_keeps_the_express_order_reference(): void
    {
        foreach ([true, false] as $seller_protection) {
            $body = $this->processor($seller_protection, true)->getBody();

            $this->assertTrue($body['additionalParameters']['is_paypal_express']);
            $this->assertSame($this->order->get_id(), $body['additionalParameters']['real_order_id']);
        }
    }

    private function processor(bool $seller_protection, bool $express): PaypalProcessor
    {
        $gateway = new PaypalGateway();
        $gateway->settings['sellerprotection'] = $seller_protection ? 'TRUE' : 'FALSE';
        if ($express) {
            $gateway->set_express_order_id(self::PAYPAL_ORDER_ID);
        }

        $processor = $gateway->newPaymentProcessorInstance($this->order);
        $this->assertInstanceOf(PaypalProcessor::class, $processor);

        return $processor;
    }

    /**
     * The PayPal service of the request the client sends for the processor,
     * with its parameters by name.
     */
    private function service(PaypalProcessor $processor): array
    {
        $client = new BuckarooClient('test', 'website-key', 'secret-key');
        $http = new class implements HttpClientInterface {
            /** @var string|null */
            public $sent;

            public function call(string $url, array $headers, string $method, ?string $data = null)
            {
                $this->sent = $data;

                return [null, ['Key' => 'TRANSACTION', 'Status' => ['Code' => ['Code' => 190]]]];
            }
        };
        $property = new ReflectionProperty(Client::class, 'httpClient');
        $property->setAccessible(true);
        $property->setValue($client->client(), $http);

        $client->process($processor);

        $data = json_decode((string) $http->sent, true);
        $services = $data['Services']['ServiceList'] ?? [];
        $this->assertCount(1, $services);
        $service = $services[0];
        $this->assertSame('paypal', $service['name']);

        $parameters = [];
        foreach ($service['parameters'] ?? [] as $parameter) {
            $parameters[$parameter['Name']] = $parameter['Value'];
        }

        return [
            'Action' => $service['action'],
            'Parameters' => $parameters,
        ];
    }

    private function assertSellerData(array $parameters): void
    {
        $this->assertSame('Jan Jansen', $parameters['Name'] ?? null);
        $this->assertSame('Kerkstraat 1', $parameters['Street1'] ?? null);
        $this->assertSame('Heerenveen', $parameters['CityName'] ?? null);
        $this->assertSame('8441ER', $parameters['PostalCode'] ?? null);
        $this->assertSame('NL', $parameters['Country'] ?? null);
        $this->assertSame('0612345678', $parameters['Phone'] ?? null);
    }
}
