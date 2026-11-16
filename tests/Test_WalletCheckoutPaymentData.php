<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;
use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayProcessor;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayProcessor;
use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;
use PHPUnit\Framework\TestCase;

/**
 * Apple Pay and Google Pay read the wallet token from their own checkout field.
 */
class Test_WalletCheckoutPaymentData extends TestCase
{
    private const APPLE_PAY_TOKEN = [
        'paymentData' => ['version' => 'EC_v1', 'data' => 'APPLEDATA', 'signature' => 'APPLESIG'],
        'transactionIdentifier' => 'APPLETXN',
    ];

    private const GOOGLE_PAY_TOKEN = '{"signature":"GOOGLESIG","protocolVersion":"ECv2","signedMessage":"{\"encryptedMessage\":\"GOOGLEMSG\"}"}';

    /** @var array */
    private $post;

    /** @var WC_Order|null */
    private $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post = $_POST;
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        if ($this->order) {
            $this->order->delete(true);
        }
        parent::tearDown();
    }

    public function test_checkout_templates_use_separate_field_names(): void
    {
        $applePay = $this->renderFields(new ApplepayGateway());
        $googlePay = $this->renderFields(new GooglepayGateway());

        $this->assertStringContainsString('name="buckaroo_applepay_payment_data"', $applePay);
        $this->assertStringContainsString('name="buckaroo_googlepay_payment_data"', $googlePay);
        $this->assertStringNotContainsString('name="paymentData"', $applePay . $googlePay);
    }

    public function test_apple_pay_uses_its_own_field_when_google_pay_is_also_enabled(): void
    {
        // The classic checkout posts the fields of every payment method.
        $_POST = [
            'payment_method' => 'buckaroo_applepay',
            'buckaroo_applepay_payment_data' => wp_slash(json_encode(['token' => self::APPLE_PAY_TOKEN])),
            'buckaroo_googlepay_payment_data' => '',
        ];

        $body = $this->methodBody(new ApplepayProcessor(...$this->processorArgs(new ApplepayGateway())));

        $this->assertSame(base64_encode(json_encode(self::APPLE_PAY_TOKEN)), $body['paymentData']);
    }

    public function test_google_pay_uses_its_own_field_when_apple_pay_is_also_enabled(): void
    {
        $_POST = [
            'payment_method' => 'buckaroo_googlepay',
            'buckaroo_applepay_payment_data' => '',
            'buckaroo_googlepay_payment_data' => wp_slash(json_encode(['token' => self::GOOGLE_PAY_TOKEN])),
        ];

        $body = $this->methodBody(new GooglepayProcessor(...$this->processorArgs(new GooglepayGateway())));

        $this->assertSame(base64_encode(self::GOOGLE_PAY_TOKEN), $body['paymentData']);
    }

    public function test_block_checkout_payment_data_is_still_read(): void
    {
        // The Store API lowercases the key and does not slash-escape the value.
        $_POST = ['paymentdata' => json_encode(['token' => self::APPLE_PAY_TOKEN])];

        $body = $this->methodBody(new ApplepayProcessor(...$this->processorArgs(new ApplepayGateway())));

        $this->assertSame(base64_encode(json_encode(self::APPLE_PAY_TOKEN)), $body['paymentData']);
    }

    public function test_express_payment_data_is_still_read(): void
    {
        $_POST = ['paymentData' => wp_slash(['token' => self::GOOGLE_PAY_TOKEN])];

        $body = $this->methodBody(new GooglepayProcessor(...$this->processorArgs(new GooglepayGateway())));

        $this->assertSame(base64_encode(self::GOOGLE_PAY_TOKEN), $body['paymentData']);
    }

    private function renderFields(AbstractPaymentGateway $gateway): string
    {
        ob_start();
        $gateway->payment_fields();

        return (string) ob_get_clean();
    }

    private function processorArgs(AbstractPaymentGateway $gateway): array
    {
        $this->order = wc_create_order();
        $this->order->set_billing_first_name('Test');
        $this->order->set_billing_last_name('Shopper');
        $this->order->save();
        $details = new OrderDetails($this->order);

        return [$gateway, $details, new OrderArticles($details, $gateway)];
    }

    private function methodBody($processor): array
    {
        $method = new ReflectionMethod($processor, 'getMethodBody');
        $method->setAccessible(true);

        return $method->invoke($processor);
    }
}
