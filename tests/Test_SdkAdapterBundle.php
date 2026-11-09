<?php

declare(strict_types=1);

use BuckarooDeps\Buckaroo\Adapter\Api;
use BuckarooDeps\Buckaroo\Adapter\Payments;
use BuckarooDeps\Buckaroo\Adapter\Status;
use BuckarooDeps\Buckaroo\BuckarooClient;
use BuckarooDeps\Buckaroo\Upg\BuckarooUpgClient;
use BuckarooDeps\Buckaroo\Upg\ClientOptions;
use BuckarooDeps\Buckaroo\Upg\Enum\PaymentMethod;
use BuckarooDeps\Buckaroo\Upg\Request\CreateSaleRequest;
use BuckarooDeps\Buckaroo\Upg\Testing\FakeHttpClient;
use BuckarooDeps\Buckaroo\Upg\Testing\Fixtures;
use PHPUnit\Framework\TestCase;

/** Mozart prefixes the adapter and upg-sdk into BuckarooDeps\, like the BPE SDK; this proves the build loads them. */
class Test_SdkAdapterBundle extends TestCase
{
    public function test_the_prefixed_upg_sdk_loads_its_enums_and_requests()
    {
        $request = new CreateSaleRequest(reference: '1001-abc123', currency: 'EUR', totalAmount: '10.00', paymentMethods: [PaymentMethod::Wero]);

        $this->assertSame('Wero', $request->jsonSerialize()['paymentMethods'][0]->value);
    }

    public function test_the_prefixed_adapter_sends_a_wero_payment_to_upg()
    {
        $http = new FakeHttpClient(['POST /v1/sales' => FakeHttpClient::response(Fixtures::sale(['id' => 'sl_123']), 201)]);
        $payments = new Payments(
            new BuckarooClient('website-key', 'secret-key', 'test'),
            new BuckarooUpgClient('apk_test', new ClientOptions(httpClient: $http)),
            ['wero']
        );

        $result = $payments->send('wero', 'pay', [
            'invoice' => '1001',
            'amountDebit' => '10.00',
            'currency' => 'EUR',
            'returnURL' => 'https://shop.example/return',
        ]);

        $this->assertSame(Api::Upg, $result->api());
        $this->assertSame(Status::Pending, $result->status());
        $this->assertSame('upg:sl_123', $result->reference());
        $http->assertSentCount(1);
    }
}
