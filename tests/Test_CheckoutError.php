<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewProcessor;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayOldProcessor;
use Buckaroo\Woocommerce\Gateways\GiftCard\GiftCardProcessor;
use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Hooks\InitGateways;
use Buckaroo\Woocommerce\PaymentProcessors\ReturnProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\CheckoutError;
use PHPUnit\Framework\TestCase;

/**
 * The checkout error notice after an unsuccessful payment return is chosen by a
 * fixed error code in the `bck_err` query argument.
 */
class Test_CheckoutError extends TestCase
{
    use HposStorage;

    private const MASTER_SETTINGS = 'woocommerce_buckaroo_mastersettings_settings';

    private const SECRET_KEY = 'TEST_SECRET_KEY';

    private const FAILED_URL = 'http://example.org/?page_id=7';

    /** @var mixed */
    private $masterSettings;

    /** @var array */
    private $get;

    protected function setUp(): void
    {
        parent::setUp();

        $this->masterSettings = get_option(self::MASTER_SETTINGS);
        $this->get = $_GET;

        update_option(self::MASTER_SETTINGS, [
            'merchantkey' => 'TEST_WEBSITE_KEY',
            'secretkey' => self::SECRET_KEY,
        ]);

        if (! WC()->session) {
            WC()->initialize_session();
        }
        wc_clear_notices();
    }

    protected function tearDown(): void
    {
        if ($this->masterSettings === false) {
            delete_option(self::MASTER_SETTINGS);
        } else {
            update_option(self::MASTER_SETTINGS, $this->masterSettings);
        }
        $_GET = $this->get;
        remove_filter('woocommerce_is_checkout', '__return_true');
        wp_deregister_script('buckaroo-blocks');
        if (WC()->session) {
            wc_clear_notices();
        }

        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public function test_payment_failed_code_shows_the_payment_unsuccessful_message(): void
    {
        $this->assertSame($this->failedMessage(), CheckoutError::messageFor(CheckoutError::PAYMENT_FAILED));
    }

    public function test_payment_cancelled_code_shows_the_cancelled_message(): void
    {
        $this->assertSame(
            __('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'),
            CheckoutError::messageFor(CheckoutError::PAYMENT_CANCELLED)
        );
    }

    /**
     * @dataProvider unsupportedValueProvider
     *
     * @param  mixed  $value
     */
    public function test_unsupported_value_shows_the_generic_message(string $label, $value): void
    {
        $_GET[CheckoutError::QUERY_ARG] = is_string($value) ? wp_slash($value) : $value;

        $message = CheckoutError::messageFromRequest();

        $this->assertSame($this->failedMessage(), $message, $label);
        $this->assertStringNotContainsString('<', $message);
        $this->assertStringNotContainsString('example.com', $message);
    }

    public function unsupportedValueProvider(): array
    {
        $markup = '<a href="https://example.com">More information</a>';

        return [
            'unknown code' => ['unknown code', 'unknown_code'],
            'bold markup' => ['bold markup', '<b>Bold</b> text'],
            'link' => ['link', $markup],
            'base64 encoded markup' => ['base64 encoded markup', base64_encode($markup)],
            'base64 encoded legacy message' => ['base64 encoded legacy message', base64_encode('Payment failed.')],
            'malformed base64' => ['malformed base64', '%%%not-base64==='],
            'plain text' => ['plain text', 'Some other text'],
            'code with different case' => ['code with different case', 'PAYMENT_FAILED'],
            'array value' => ['array value', ['payment_cancelled']],
        ];
    }

    public function test_missing_or_empty_error_code_shows_no_message(): void
    {
        unset($_GET[CheckoutError::QUERY_ARG]);
        $this->assertSame('', CheckoutError::messageFromRequest());

        $_GET[CheckoutError::QUERY_ARG] = '';
        $this->assertSame('', CheckoutError::messageFromRequest());
    }

    public function test_error_code_is_added_to_url_with_existing_query(): void
    {
        $this->assertSame(
            self::FAILED_URL . '&bck_err=payment_cancelled',
            CheckoutError::addToUrl(self::FAILED_URL, CheckoutError::PAYMENT_CANCELLED)
        );
        $this->assertSame('http://example.org/checkout/?bck_err=payment_failed', CheckoutError::addToUrl('http://example.org/checkout/'));
    }

    public function test_unknown_code_is_added_to_url_as_payment_failed(): void
    {
        $this->assertSame(
            self::FAILED_URL . '&bck_err=payment_failed',
            CheckoutError::addToUrl(self::FAILED_URL, 'Payment failed.')
        );
    }

    public function test_classic_checkout_shows_the_mapped_message_only(): void
    {
        add_filter('woocommerce_is_checkout', '__return_true');
        $_GET[CheckoutError::QUERY_ARG] = wp_slash('<a href="https://example.com">More information</a>');

        (new InitGateways())->displayBuckarooErrors();

        $this->assertSame([$this->failedMessage()], $this->errorNotices());
    }

    public function test_classic_checkout_shows_the_cancelled_message(): void
    {
        add_filter('woocommerce_is_checkout', '__return_true');
        $_GET[CheckoutError::QUERY_ARG] = CheckoutError::PAYMENT_CANCELLED;

        (new InitGateways())->displayBuckarooErrors();

        $this->assertSame([__('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway')], $this->errorNotices());
    }

    public function test_classic_checkout_does_not_repeat_a_notice_already_added_on_return(): void
    {
        add_filter('woocommerce_is_checkout', '__return_true');
        wc_add_notice($this->failedMessage(), 'error');
        $_GET[CheckoutError::QUERY_ARG] = CheckoutError::PAYMENT_FAILED;

        (new InitGateways())->displayBuckarooErrors();

        $this->assertSame([$this->failedMessage()], $this->errorNotices());
    }

    public function test_classic_checkout_without_error_code_adds_no_notice(): void
    {
        add_filter('woocommerce_is_checkout', '__return_true');
        unset($_GET[CheckoutError::QUERY_ARG]);

        (new InitGateways())->displayBuckarooErrors();

        $this->assertSame([], $this->errorNotices());
    }

    public function test_block_checkout_receives_the_mapped_message_as_plain_text(): void
    {
        wp_register_script('buckaroo-blocks', 'http://example.org/blocks.js', [], '1', true);
        $_GET[CheckoutError::QUERY_ARG] = wp_slash(base64_encode('<a href="https://example.com">More information</a>'));

        (new InitGateways())->initGatewaysOnCheckout();

        $data = (string) wp_scripts()->get_data('buckaroo-blocks', 'data');
        $this->assertStringContainsString(
            'var buckarooCheckoutError = ' . wp_json_encode(['message' => $this->failedMessage()]),
            $data
        );
        $this->assertStringNotContainsString('example.com', $data);
    }

    public function test_block_checkout_without_error_code_receives_an_empty_message(): void
    {
        wp_register_script('buckaroo-blocks', 'http://example.org/blocks.js', [], '1', true);
        unset($_GET[CheckoutError::QUERY_ARG]);

        (new InitGateways())->initGatewaysOnCheckout();

        $this->assertStringContainsString(
            'var buckarooCheckoutError = {"message":""}',
            (string) wp_scripts()->get_data('buckaroo-blocks', 'data')
        );
    }

    public function test_unsigned_return_redirects_with_payment_failed_code(): void
    {
        $order = $this->createIdealOrder();

        $result = (new ReturnProcessor([
            'brq_statuscode' => '490',
            'brq_ordernumber' => (string) $order->get_id(),
            'brq_signature' => 'invalid',
        ]))->handle(new IdealGateway());

        $this->assertSame('failure', $result['result']);
        $this->assertSame(CheckoutError::PAYMENT_FAILED, $this->errorCodeIn($result['redirect']));
    }

    public function test_failed_payment_return_redirects_with_payment_failed_code(): void
    {
        $order = $this->createIdealOrder();
        $order->set_billing_country('NL');
        $order->save();

        $result = (new ReturnProcessor($this->sign([
            'brq_statuscode' => '490',
            'brq_statuscode_detail' => 'S103',
            'brq_statusmessage' => 'Failed',
            'brq_ordernumber' => (string) $order->get_id(),
            'brq_transaction_method' => 'ideal',
            'brq_transactions' => 'IDEALKEY1',
        ])))->handle(new IdealGateway());

        $this->assertSame('failure', $result['result']);
        $this->assertSame(CheckoutError::PAYMENT_FAILED, $this->errorCodeIn($result['redirect']));
    }

    public function test_cancelled_payment_return_redirects_with_payment_cancelled_code(): void
    {
        $order = $this->createIdealOrder();

        $result = (new ReturnProcessor($this->sign([
            'brq_statuscode' => '890',
            'brq_ordernumber' => (string) $order->get_id(),
            'brq_transaction_method' => 'ideal',
            'brq_transactions' => 'IDEALKEY2',
        ])))->handle(new IdealGateway());

        $this->assertSame('failure', $result['result']);
        $this->assertSame(CheckoutError::PAYMENT_CANCELLED, $this->errorCodeIn($result['redirect']));
        $this->assertSame(
            __('Payment cancelled by customer.', 'wc-buckaroo-bpe-gateway'),
            CheckoutError::messageFor($this->errorCodeIn($result['redirect']))
        );
    }

    /**
     * @dataProvider rejectingProcessorProvider
     */
    public function test_gateway_return_handler_redirects_with_payment_failed_code(string $class, array $reply): void
    {
        $processor = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        $result = $processor->unsuccessfulReturnHandler(ResponseParser::make($reply), self::FAILED_URL);

        $this->assertSame('failure', $result['result']);
        $this->assertSame(self::FAILED_URL . '&bck_err=payment_failed', $result['redirect']);
    }

    public function rejectingProcessorProvider(): array
    {
        $rejected = [
            'brq_statuscode' => '690',
            'brq_statusmessage' => 'Rejected',
            'brq_statuscode_detail' => 'S999',
        ];

        return [
            'riverty' => [AfterpayNewProcessor::class, $rejected],
            'afterpay' => [AfterpayOldProcessor::class, $rejected],
            'giftcard' => [GiftCardProcessor::class, ['brq_statuscode' => '490', 'brq_statusmessage' => 'Failed']],
        ];
    }

    private function failedMessage(): string
    {
        return __('Payment unsuccessful. Please try again or choose another payment method.', 'wc-buckaroo-bpe-gateway');
    }

    private function errorNotices(): array
    {
        return array_map(function ($notice) {
            return $notice['notice'];
        }, wc_get_notices('error'));
    }

    private function errorCodeIn(string $url): string
    {
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

        return (string) ($query[CheckoutError::QUERY_ARG] ?? '');
    }

    private function createIdealOrder(): WC_Order
    {
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_ideal');
        $order->set_currency('EUR');
        $order->save();

        return $order;
    }

    private function sign(array $reply): array
    {
        $signed = array_filter($reply, function ($key) {
            return in_array(strtolower(explode('_', $key)[0]), ['brq', 'add', 'cust'], true);
        }, ARRAY_FILTER_USE_KEY);
        ksort($signed, SORT_FLAG_CASE | SORT_STRING);

        $data = '';
        foreach ($signed as $key => $value) {
            $data .= $key . '=' . html_entity_decode($value);
        }

        $reply['brq_signature'] = sha1($data . self::SECRET_KEY);

        return $reply;
    }
}
