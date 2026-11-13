<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Transfer\TransferGateway;
use Buckaroo\Woocommerce\Gateways\Transfer\TransferResponse;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\PushProcessor;
use Buckaroo\Woocommerce\PaymentProcessors\ReturnProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\BuckarooClient;
use Buckaroo\Woocommerce\Services\Helper;
use PHPUnit\Framework\TestCase;

/**
 * Handling of transaction keys and transfer bank details in Buckaroo replies.
 */
class Test_ReplyFieldValidation extends TestCase
{
    use HposStorage;

    private const MASTER_SETTINGS = 'woocommerce_buckaroo_mastersettings_settings';

    private const SECRET_KEY = 'TEST_SECRET_KEY';

    /** @var mixed */
    private $masterSettings;

    /** @var array */
    private $post;

    /** @var string|null */
    private $requestMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->masterSettings = get_option(self::MASTER_SETTINGS);
        $this->post = $_POST;
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;

        update_option(self::MASTER_SETTINGS, [
            'merchantkey' => 'TEST_WEBSITE_KEY',
            'secretkey' => self::SECRET_KEY,
        ]);

        if (! WC()->session) {
            WC()->initialize_session();
        }
    }

    protected function tearDown(): void
    {
        update_option(self::MASTER_SETTINGS, $this->masterSettings);
        $_POST = $this->post;
        if ($this->requestMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->requestMethod;
        }
        if (function_exists('wc_clear_notices') && WC()->session) {
            wc_clear_notices();
        }

        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    /**
     * @dataProvider transactionKeyProvider
     *
     * @param  mixed  $key
     */
    public function test_transaction_key_format($key, bool $expected): void
    {
        $this->assertSame($expected, Helper::isValidTransactionKey($key));
    }

    public function transactionKeyProvider(): array
    {
        return [
            'buckaroo key' => ['4E8BD922192746C3918BF4077CFB4CB9', true],
            'special characters' => ['4E8BD922192746C3918BF4077CFB4CB&#57;', false],
            'whitespace' => [' 4E8BD922192746C3918BF4077CFB4CB9', false],
            'empty' => ['', false],
            'too long' => [str_repeat('A', 65), false],
            'array' => [['4E8BD922192746C3918BF4077CFB4CB9'], false],
            'null' => [null, false],
        ];
    }

    public function test_reply_with_an_invalid_transaction_key_format_is_rejected(): void
    {
        $parser = ResponseParser::make(['brq_transactions' => 'ABC&#49;23']);

        $this->assertFalse($parser->hasValidTransactionKeys());
    }

    public function test_reply_with_an_invalid_related_transaction_key_format_is_rejected(): void
    {
        $parser = ResponseParser::make([
            'brq_transactions' => 'REFUNDKEY1',
            'brq_relatedtransaction_refund' => 'PAYKEY&#49;',
        ]);

        $this->assertFalse($parser->hasValidTransactionKeys());
    }

    public function test_pay_per_email_reply_with_several_transactions_is_accepted(): void
    {
        $parser = ResponseParser::make(['brq_transactions' => 'INVITEKEY1,PAYMENTKEY2']);

        $this->assertTrue($parser->hasValidTransactionKeys());
    }

    public function test_json_reply_with_a_valid_key_is_accepted(): void
    {
        $parser = ResponseParser::make(['Key' => '4E8BD922192746C3918BF4077CFB4CB9']);

        $this->assertTrue($parser->hasValidTransactionKeys());
    }

    public function test_refund_push_with_an_invalid_key_format_is_ignored(): void
    {
        $order = $this->createPaidOrder();
        OrderMeta::add($order, '_refundbuckarooREFUNDKEY1', 'ok', true);

        $reply = $this->sign([
            'brq_statuscode' => '190',
            'brq_amount_credit' => '10.00',
            'brq_currency' => 'EUR',
            'brq_ordernumber' => (string) $order->get_id(),
            'brq_transaction_method' => 'ideal',
            'brq_transactions' => 'REFUNDKEY&#49;',
            'brq_relatedtransaction_refund' => 'PAYMENTKEY1',
        ]);
        $this->assertTrue($this->client()->isReplyHandlerValid($reply), 'The reply must pass validation');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $reply;
        (new PushProcessor())->handle();

        $stored = wc_get_order($order->get_id());
        $this->assertSame([], $stored->get_refunds());
        $this->assertSame('processing', $stored->get_status());
        $this->assertSame('', OrderMeta::get($stored, '_refundbuckaroo' . 'REFUNDKEY&#49;'));
    }

    public function test_return_with_an_invalid_key_format_is_rejected(): void
    {
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_transfer');
        $order->save();

        $reply = $this->sign([
            'brq_statuscode' => '792',
            'brq_ordernumber' => (string) $order->get_id(),
            'brq_transaction_method' => 'transfer',
            'brq_transactions' => 'TRANSFERKEY&#49;',
            'brq_SERVICE_transfer_IBAN' => 'NL00TEST0000000002',
        ]);

        $result = (new ReturnProcessor($reply))->handle(new TransferGateway());

        $stored = wc_get_order($order->get_id());
        $this->assertSame('failure', $result['result'] ?? null);
        $this->assertSame('', $stored->get_transaction_id());
        $this->assertSame('', $stored->get_meta('buckaroo_IBAN'));
    }

    public function test_form_reply_bank_details_are_read_from_service_fields(): void
    {
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_transfer');
        $order->save();

        $reply = $this->sign([
            'brq_statuscode' => '792',
            'brq_ordernumber' => (string) $order->get_id(),
            'brq_transaction_method' => 'transfer',
            'brq_transactions' => 'TRANSFERKEY1',
            'brq_SERVICE_transfer_IBAN' => 'NL00TEST0000000002',
            'brq_SERVICE_transfer_PaymentReference' => 'REF-2',
        ]);
        $reply['services'] = [
            [
                'name' => 'transfer',
                'parameters' => [
                    ['name' => 'IBAN', 'value' => 'NL00TEST0000000099'],
                    ['name' => 'PaymentReference', 'value' => 'OTHER-REF'],
                ],
            ],
        ];
        $this->assertTrue($this->client()->isReplyHandlerValid($reply), 'The reply must pass validation');

        (new TransferResponse(ResponseParser::make($reply)))->storeBankDetails(wc_get_order($order->get_id()));

        $stored = wc_get_order($order->get_id());
        $this->assertSame('NL00TEST0000000002', $stored->get_meta('buckaroo_IBAN'));
        $this->assertSame('REF-2', $stored->get_meta('buckaroo_paymentReference'));
    }

    public function test_array_value_in_a_reply_field_fails_validation(): void
    {
        $reply = $this->sign([
            'brq_statuscode' => '190',
            'brq_transactions' => 'PAYMENTKEY1',
        ]);
        $reply['brq_transactions'] = ['PAYMENTKEY1'];

        $this->assertFalse($this->client()->isReplyHandlerValid($reply));
    }

    private function createPaidOrder(): WC_Order
    {
        $order = $this->createOrder();
        $order->set_payment_method('buckaroo_ideal');
        $order->set_total(50.00);
        $order->set_status('processing');
        $order->save();
        OrderMeta::add($order, '_pushallowed', 'ok', true);

        return wc_get_order($order->get_id());
    }

    private function client(): BuckarooClient
    {
        return new BuckarooClient('test');
    }

    /** Adds the signature to a form-post reply. */
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
