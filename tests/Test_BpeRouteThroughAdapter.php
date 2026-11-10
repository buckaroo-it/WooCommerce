<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Gateways\Wero\WeroGateway;
use Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\Sdk\ZakelijkOpRekeningClient;
use Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\ZakelijkOpRekeningGateway;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Adapter\Api;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

/**
 * With UPG off, every transaction goes through the adapter. The BPE SDK must receive exactly what it
 * received before, when the wrapper called $client->method($serviceCode)->{$action}($payload) itself.
 */
class Test_BpeRouteThroughAdapter extends TestCase
{
    public function calls(): array
    {
        $base = [
            'order' => '1001',
            'invoice' => '1001',
            'currency' => 'EUR',
            'returnURL' => 'https://shop.example/?wc-api=WC_Gateway_Buckaroo_Ideal',
            'cancelURL' => 'https://shop.example/?wc-api=WC_Gateway_Buckaroo_Ideal',
            'pushURL' => 'https://shop.example/?wc-api=wc_push_buckaroo',
            'pushURLFailure' => 'https://shop.example/?wc-api=wc_push_buckaroo',
            'additionalParameters' => ['real_order_id' => 1001],
            'description' => 'Order #1001',
            'clientIP' => '203.0.113.7',
            'culture' => 'nl-NL',
        ];

        return [
            'ideal pay' => [IdealGateway::class, 'pay', $base + ['amountDebit' => '10.00', 'continueOnIncomplete' => true], []],
            'ideal refund' => [IdealGateway::class, 'refund', $base + ['amountCredit' => '4.00', 'originalTransactionKey' => '4E8BD922192746C3918BF4077CFDF0DC'], []],
            'wero authorize' => [WeroGateway::class, 'authorize', $base + ['amountDebit' => '10.00'], []],
            'wero capture' => [WeroGateway::class, 'capture', $base + ['amountDebit' => '10.00'], ['amountDebit' => '6.00', 'originalTransactionKey' => '4E8BD922192746C3918BF4077CFDF0DC']],
        ];
    }

    /** @dataProvider calls */
    public function test_process_sends_what_the_bpe_sdk_received_before(string $gatewayClass, string $action, array $body, array $additionalData)
    {
        $processor = $this->processor(new $gatewayClass(), $action, $body);

        $before = new BuckarooClient('test', 'website-key', 'secret-key');
        $beforeHttp = RecordingBpeHttpClient::attachTo($before->client());
        $before->method($processor->gateway->getServiceCode($processor))->{$action}(array_merge($body, $additionalData));

        $now = new BuckarooClient('test', 'website-key', 'secret-key');
        $nowHttp = RecordingBpeHttpClient::attachTo($now->client());
        $result = $now->process($processor, $additionalData);

        $this->assertCount(1, $beforeHttp->requests);
        $this->assertSame($beforeHttp->requestsWithoutSignature(), $nowHttp->requestsWithoutSignature());
        $this->assertSame(Api::Bpe, $result->api());
        $this->assertInstanceOf(TransactionResponse::class, $result->raw());
    }

    /** ZakelijkOpRekening sends its capture through its own client, not through the adapter; it answers with a Result too. */
    public function test_the_zakelijk_op_rekening_capture_answers_with_a_bpe_result()
    {
        $processor = $this->processor(new ZakelijkOpRekeningGateway(), 'authorize', ['invoice' => '1001', 'currency' => 'EUR', 'amountDebit' => '10.00']);
        $client = new ZakelijkOpRekeningClient('test', 'website-key', 'secret-key');
        $http = RecordingBpeHttpClient::attachTo($client->client());

        $result = $client->process($processor, ['originalTransactionKey' => '4E8BD922192746C3918BF4077CFDF0DC']);

        $this->assertCount(1, $http->requests);
        $this->assertStringContainsString('"action":"Capture"', (string) $http->requests[0]['body']);
        $this->assertSame(Api::Bpe, $result->api());
        $this->assertInstanceOf(TransactionResponse::class, $result->raw());
    }

    private function processor($gateway, string $action, array $body): AbstractProcessor
    {
        $processor = new class($action, $body) extends AbstractProcessor
        {
            private $action;

            private $body;

            public function __construct(string $action, array $body)
            {
                $this->action = $action;
                $this->body = $body;
            }

            public function getAction(): string
            {
                return $this->action;
            }

            public function getBody(): array
            {
                return $this->body;
            }
        };
        $processor->gateway = $gateway;

        return $processor;
    }
}
