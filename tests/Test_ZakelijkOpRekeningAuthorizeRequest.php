<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\Sdk\ZakelijkOpRekeningPaymentMethod;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use BuckarooDeps\Buckaroo\Services\PayloadService;
use BuckarooDeps\Buckaroo\Transaction\Client;
use BuckarooDeps\Buckaroo\Transaction\Request\HttpClient\HttpClientInterface;

class Test_ZakelijkOpRekeningAuthorizeRequest extends WP_UnitTestCase
{
    public function test_authorize_sends_the_company_details_and_route(): void
    {
        $sent = $this->send('authorize');

        $this->assertSame('Authorize', $sent['action']);
        $this->assertSame('AbnB2b', $sent['parameters']['Route'][0]['Value'] ?? null);

        foreach (['BillingCustomer', 'ShippingCustomer'] as $group) {
            $this->assertSame('Example B.V.', $this->value($sent['parameters'], $group, 'CompanyName'));
            $this->assertSame('12345678', $this->value($sent['parameters'], $group, 'CocNumber'));
        }
    }

    public function test_full_capture_sends_no_articles(): void
    {
        $sent = $this->send('capture');

        $this->assertSame('Capture', $sent['action']);
        $this->assertSame([], $sent['parameters']);
    }

    /**
     * @return array{action: string, parameters: array<string, array<int, array<string, mixed>>>}
     */
    private function send(string $action): array
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

        $method = new ZakelijkOpRekeningPaymentMethod($client->client(), 'In3');
        $method->setPayload((new PayloadService($this->payload()))->toArray());
        $method->{$action}();

        $data = json_decode((string) $http->sent, true);
        $service = $data['Services']['ServiceList'][0];

        $parameters = [];
        foreach ($service['Parameters'] ?? $service['parameters'] ?? [] as $parameter) {
            $parameters[$parameter['Name']][] = $parameter;
        }

        return ['action' => $service['Action'] ?? $service['action'], 'parameters' => $parameters];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $parameters
     * @return mixed
     */
    private function value(array $parameters, string $group, string $name)
    {
        foreach ($parameters[$name] ?? [] as $parameter) {
            if (($parameter['GroupType'] ?? null) === $group) {
                return $parameter['Value'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $recipient = [
            'category' => 'B2B',
            'companyName' => 'Example B.V.',
            'chamberOfCommerce' => '12345678',
            'firstName' => 'Jan',
            'lastName' => 'Jansen',
        ];
        $address = [
            'street' => 'Kerkstraat',
            'houseNumber' => '1',
            'zipcode' => '8441ER',
            'city' => 'Heerenveen',
            'country' => 'NL',
        ];

        return [
            'invoice' => 'INV-1',
            'amountDebit' => 10.00,
            'route' => 'AbnB2b',
            'billing' => [
                'recipient' => $recipient + ['initials' => 'J', 'customerNumber' => '1', 'phone' => '0612345678', 'country' => 'NL'],
                'email' => 'jan@example.com',
                'phone' => ['phone' => '0612345678'],
                'address' => $address,
            ],
            'shipping' => [
                'recipient' => $recipient,
                'address' => $address,
            ],
            'articles' => [
                ['identifier' => 'SKU-1', 'description' => 'Item', 'quantity' => 1, 'price' => 10.00, 'vatPercentage' => 21, 'vatAmount' => 1.74],
            ],
        ];
    }
}
