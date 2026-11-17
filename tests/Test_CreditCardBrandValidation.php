<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\CreditCard\Cards\VisaGateway;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardProcessor;
use Buckaroo\Woocommerce\Order\OrderMeta;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use PHPUnit\Framework\TestCase;

/**
 * Card brand and card method handling of the credit card gateways.
 *
 * Classic and Blocks checkout both run validate_fields() and then
 * process_payment() on the gateway with the payment data in $_POST, so the
 * same checks cover both checkouts.
 */
class Test_CreditCardBrandValidation extends TestCase
{
    use HposStorage;

    private const GENERIC_OPTION = 'woocommerce_buckaroo_creditcard_settings';

    private const VISA_OPTION = 'woocommerce_buckaroo_creditcard_visa_settings';

    /** @var array<string, mixed> */
    private $post = [];

    /** @var array<string, mixed> */
    private $server = [];

    /** @var int[] */
    private $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->post = $_POST;
        $this->server = $_SERVER;
        $_POST = [];
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);

        if (! WC()->session) {
            WC()->initialize_session();
        }
        wc_clear_notices();

        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        $_SERVER = $this->server;
        wc_clear_notices();

        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }

        delete_option(self::GENERIC_OPTION);
        delete_option(self::VISA_OPTION);
        delete_option('woocommerce_buckaroo_mastersettings_settings');
        $this->deleteCreatedOrders();
        parent::tearDown();
    }

    public function test_generic_gateway_accepts_an_enabled_brand(): void
    {
        $gateway = $this->genericGateway(['visa', 'mastercard']);
        $_POST = ['buckaroo_creditcard-creditcard-issuer' => 'mastercard'];

        $gateway->validate_fields();

        $this->assertSame([], $this->errors());
        $this->assertSame('mastercard', $gateway->getSubmittedCardIssuer());
    }

    /**
     * @dataProvider rejectedGenericIssuers
     */
    public function test_generic_gateway_rejects_a_brand_that_is_not_enabled(string $issuer, string $message): void
    {
        $gateway = $this->genericGateway(['visa', 'mastercard']);
        $_POST = ['buckaroo_creditcard-creditcard-issuer' => $issuer];

        $gateway->validate_fields();

        $this->assertSame([$message], $this->errors());
        $this->assertNull($gateway->getSubmittedCardIssuer());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function rejectedGenericIssuers(): array
    {
        return [
            'supported but not enabled' => ['amex', 'A valid credit card is required.'],
            'unknown brand' => ['unknowncard', 'A valid credit card is required.'],
            'empty value' => ['', 'Select a credit or debit card.'],
        ];
    }

    public function test_generic_gateway_requires_a_brand(): void
    {
        $gateway = $this->genericGateway(['visa']);

        $gateway->validate_fields();

        $this->assertSame(['Select a credit or debit card.'], $this->errors());
    }

    public function test_generic_gateway_without_enabled_brands_accepts_none(): void
    {
        $gateway = $this->genericGateway([]);
        $_POST = ['buckaroo_creditcard-creditcard-issuer' => 'visa'];

        $gateway->validate_fields();

        $this->assertSame(['A valid credit card is required.'], $this->errors());
    }

    public function test_visa_gateway_accepts_visa(): void
    {
        $gateway = $this->visaGateway();
        $_POST = ['buckaroo_creditcard_visa-creditcard-issuer' => 'visa'];

        $gateway->validate_fields();

        $this->assertSame([], $this->errors());
        $this->assertSame('visa', $gateway->getSubmittedCardIssuer());
    }

    public function test_visa_gateway_only_accepts_visa(): void
    {
        update_option(self::GENERIC_OPTION, $this->settings('redirect', ['visa', 'amex']));
        $gateway = $this->visaGateway();
        $_POST = ['buckaroo_creditcard_visa-creditcard-issuer' => 'amex'];

        $gateway->validate_fields();

        $this->assertSame(['A valid credit card is required.'], $this->errors());
        $this->assertSame(['visa'], $gateway->getAllowedCardIssuers());
    }

    public function test_hosted_fields_accept_an_enabled_brand_with_a_session(): void
    {
        $this->useHttps();
        $gateway = $this->genericGateway(['visa', 'mastercard'], 'encrypt');
        $_POST = [
            'buckaroo_creditcard-creditcard-issuer' => 'MasterCard',
            'buckaroo_creditcard-encrypted-data' => 'session-token',
        ];

        $gateway->validate_fields();

        $this->assertSame([], $this->errors());
        $this->assertSame('mastercard', $gateway->getSubmittedCardIssuer());
    }

    public function test_hosted_fields_reject_a_brand_that_is_not_enabled(): void
    {
        $this->useHttps();
        $gateway = $this->genericGateway(['visa'], 'encrypt');
        $_POST = [
            'buckaroo_creditcard-creditcard-issuer' => 'Amex',
            'buckaroo_creditcard-encrypted-data' => 'session-token',
        ];

        $gateway->validate_fields();

        $this->assertSame(['A valid credit card is required.'], $this->errors());
    }

    public function test_hosted_fields_require_the_card_session(): void
    {
        $this->useHttps();
        $gateway = $this->genericGateway(['visa'], 'encrypt');
        $_POST = ['buckaroo_creditcard-creditcard-issuer' => 'Visa'];

        $gateway->validate_fields();

        $this->assertSame(['Please complete the card form before proceeding.'], $this->errors());
    }

    public function test_redirect_mode_pays_through_the_payment_page_when_a_session_is_posted(): void
    {
        $this->useHttps();
        $gateway = $this->genericGateway(['visa']);
        $_POST = [
            'buckaroo_creditcard-creditcard-issuer' => 'visa',
            'buckaroo_creditcard-encrypted-data' => 'session-token',
        ];
        $client = $this->client();

        $client->process($this->processor($gateway));

        $this->assertSame('pay', $client->action);
        $this->assertSame('visa', $client->payload['name']);
        $this->assertArrayNotHasKey('sessionId', $client->payload);
    }

    public function test_hosted_fields_mode_pays_with_the_card_session(): void
    {
        $this->useHttps();
        $gateway = $this->genericGateway(['visa'], 'encrypt');
        $_POST = [
            'buckaroo_creditcard-creditcard-issuer' => 'Visa',
            'buckaroo_creditcard-encrypted-data' => 'session-token',
        ];
        $client = $this->client();

        $client->process($this->processor($gateway));

        $this->assertSame('payWithToken', $client->action);
        $this->assertSame('visa', $client->payload['name']);
        $this->assertSame('session-token', $client->payload['sessionId']);
    }

    public function test_hosted_fields_mode_without_a_session_sends_no_request(): void
    {
        $this->useHttps();
        $gateway = $this->genericGateway(['visa'], 'encrypt');
        $_POST = ['buckaroo_creditcard-creditcard-issuer' => 'Visa'];
        $processor = $this->processor($gateway);

        $this->assertSame('payWithToken', $processor->getAction());
        $this->assertNoRequestIsSent($processor);
    }

    public function test_generic_gateway_sends_no_request_for_a_brand_that_is_not_enabled(): void
    {
        $gateway = $this->genericGateway(['visa']);
        $_POST = ['buckaroo_creditcard-creditcard-issuer' => 'amex'];

        $this->assertNoRequestIsSent($this->processor($gateway));
    }

    public function test_generic_gateway_sends_no_request_without_a_brand(): void
    {
        $gateway = $this->genericGateway(['visa']);

        $this->assertNoRequestIsSent($this->processor($gateway));
    }

    public function test_visa_gateway_sends_no_request_for_another_brand(): void
    {
        $gateway = $this->visaGateway();
        $_POST = ['buckaroo_creditcard_visa-creditcard-issuer' => 'amex'];

        $this->assertNoRequestIsSent($this->processor($gateway));
    }

    public function test_visa_gateway_pays_with_visa(): void
    {
        $gateway = $this->visaGateway();
        $_POST = ['buckaroo_creditcard_visa-creditcard-issuer' => 'visa'];
        $client = $this->client();

        $client->process($this->processor($gateway));

        $this->assertSame('visa', $client->service);
        $this->assertSame('pay', $client->action);
        $this->assertSame('visa', $client->payload['name']);
    }

    public function test_authorize_setting_is_kept_for_both_card_methods(): void
    {
        $gateway = $this->genericGateway(['visa'], 'redirect', 'authorize');
        $_POST = ['buckaroo_creditcard-creditcard-issuer' => 'visa'];
        $this->assertSame('authorize', $this->processor($gateway)->getAction());

        $this->useHttps();
        $gateway = $this->genericGateway(['visa'], 'encrypt', 'authorize');
        $this->assertSame('authorizeWithToken', $this->processor($gateway)->getAction());
    }

    public function test_capture_uses_the_brand_stored_on_the_order(): void
    {
        $gateway = $this->genericGateway(['visa'], 'redirect', 'authorize');
        $order = $this->createPaidOrder();
        OrderMeta::update($order, '_wc_order_authorized', 'yes');
        OrderMeta::update($order, '_payment_method_transaction', 'mastercard');
        $_POST = ['buckaroo_creditcard-creditcard-issuer' => 'visa'];
        $client = $this->client();

        $client->process($gateway->newPaymentProcessorInstance(wc_get_order($order->get_id())));

        $this->assertSame('capture', $client->action);
        $this->assertSame('mastercard', $client->payload['name']);
    }

    private function assertNoRequestIsSent(CreditCardProcessor $processor): void
    {
        $client = $this->client();

        try {
            $client->process($processor);
            $this->fail('The payment request was built.');
        } catch (Exception $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->assertSame([], $client->payload);
    }

    /**
     * @param  string[]  $providers
     */
    private function genericGateway(array $providers, string $method = 'redirect', string $flow = 'pay'): CreditCardGateway
    {
        update_option(self::GENERIC_OPTION, $this->settings($method, $providers, $flow));

        return new CreditCardGateway();
    }

    private function visaGateway(): VisaGateway
    {
        update_option(self::VISA_OPTION, $this->settings('redirect', [], 'pay'));

        return new VisaGateway();
    }

    /**
     * @param  string[]  $providers
     * @return array<string, mixed>
     */
    private function settings(string $method, array $providers, string $flow = 'pay'): array
    {
        return [
            'enabled' => 'yes',
            'mode' => 'test',
            'creditcardmethod' => $method,
            'creditcardpayauthorize' => $flow,
            'AllowedProvider' => $providers,
            'show_in_checkout' => [],
        ];
    }

    private function processor(CreditCardGateway $gateway): CreditCardProcessor
    {
        $processor = $gateway->newPaymentProcessorInstance($this->createPaidOrder());
        $this->assertInstanceOf(CreditCardProcessor::class, $processor);

        return $processor;
    }

    private function client(): InMemoryBuckarooClient
    {
        $response = $this->getMockBuilder(TransactionResponse::class)
            ->disableOriginalConstructor()
            ->getMock();

        return new InMemoryBuckarooClient($response);
    }

    private function createPaidOrder(): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('Card brand product');
        $product->set_regular_price('10.00');
        $product->set_price('10.00');
        $product->save();
        $this->productIds[] = $product->get_id();

        $order = $this->createOrder();
        $order->add_product($product, 1);
        $order->set_payment_method('buckaroo_creditcard');
        $order->set_currency('EUR');
        $order->calculate_totals();
        $order->save();

        return wc_get_order($order->get_id());
    }

    private function useHttps(): void
    {
        $_SERVER['HTTPS'] = 'on';
    }

    /**
     * @return string[]
     */
    private function errors(): array
    {
        return array_map(
            static function ($notice) {
                return is_array($notice) ? $notice['notice'] : $notice;
            },
            wc_get_notices('error')
        );
    }
}
