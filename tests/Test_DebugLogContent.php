<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewGateway;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewProcessor;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardProcessor;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayProcessor;
use Buckaroo\Woocommerce\Gateways\Idin\IdinController;
use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;
use Buckaroo\Woocommerce\Order\OrderArticles;
use Buckaroo\Woocommerce\Order\OrderDetails;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use Buckaroo\Woocommerce\Services\Logger;
use Buckaroo\Woocommerce\Services\LoggerStorage;

/**
 * What the debug log contains for payment requests, replies and errors.
 */
class Test_DebugLogContent extends WP_UnitTestCase
{
    private const MASTER_OPTION = 'woocommerce_buckaroo_mastersettings_settings';

    /** Values that must never be found in the log */
    private const FIXTURES = [
        'SIGNATURE-FIXTURE',
        'WALLET-TOKEN-FIXTURE',
        'SESSION-FIXTURE',
        'wc_order_FIXTURE',
        'Jane-FIXTURE',
        'Doe-FIXTURE',
        'jane.fixture@example.test',
        '+31600000000',
        'Fixturestraat',
        '1234FX',
        'Fixtureville',
        '1990-01-31',
        'COC-FIXTURE',
        'BIN-FIXTURE',
        '192.0.2.10',
        'NL00IBAN-FIXTURE',
    ];

    private $originalMaster;

    private $originalPost;

    private $originalRequest;

    private $originalServer;

    private $previousSession;

    /** @var WC_Order[] */
    private $orders = [];

    protected function setUp(): void
    {
        global $wpdb;

        parent::setUp();
        $this->originalMaster = get_option(self::MASTER_OPTION, null);
        $this->originalPost = $_POST;
        $this->originalRequest = $_REQUEST;
        $this->originalServer = $_SERVER;
        $this->previousSession = WC()->session;

        update_option(self::MASTER_OPTION, [
            'debugmode' => 'on',
            'logstorage' => LoggerStorage::STORAGE_DB,
            'culture' => 'en-US',
            'useidin' => 'test',
        ]);

        $table = $wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE;
        $wpdb->query(
            "CREATE TABLE IF NOT EXISTS $table (
                `id` BIGINT NOT NULL AUTO_INCREMENT ,
                `date` DATETIME NOT NULL ,
                `process_id` VARCHAR(23) NOT NULL ,
                `message` TEXT NOT NULL ,
                `location_id` VARCHAR(255) NOT NULL ,
                PRIMARY KEY (`id`)
            )"
        );
        $wpdb->query("DELETE FROM $table");
        $_POST = [];
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->orders as $order) {
            $order->delete(true);
        }
        WC()->session = $this->previousSession;
        $_POST = $this->originalPost;
        $_REQUEST = $this->originalRequest;
        $_SERVER = $this->originalServer;
        if ($this->originalMaster === null) {
            delete_option(self::MASTER_OPTION);
        } else {
            update_option(self::MASTER_OPTION, $this->originalMaster);
        }
        parent::tearDown();
    }

    public function test_reply_signatures_are_not_logged(): void
    {
        Logger::log('test|form-reply|', ResponseParser::make([
            'brq_statuscode' => '190',
            'brq_transactions' => 'TRX-REFERENCE',
            'brq_signature' => 'SIGNATURE-FIXTURE',
        ]));
        Logger::log('test|json-reply|', ['Key' => 'TRX-REFERENCE', 'Signature' => 'SIGNATURE-FIXTURE']);

        $log = $this->assertLogClean();
        $this->assertStringContainsString('TRX-REFERENCE', $log);
        $this->assertStringContainsString("'190'", $log);
    }

    public function test_wallet_payment_data_is_not_logged(): void
    {
        $token = ['paymentData' => ['data' => 'WALLET-TOKEN-FIXTURE'], 'transactionIdentifier' => 'WALLET-TOKEN-FIXTURE'];

        Logger::log('test|array|', ['paymentData' => $token]);
        Logger::log('test|json-field|', ['paymentData' => wp_json_encode(['token' => $token])]);
        Logger::log('test|json-in-other-field|', ['payload' => wp_json_encode(['paymentData' => $token, 'status' => 'kept-status'])]);
        Logger::log('test|json-message|', wp_json_encode(['token' => ['signature' => 'WALLET-TOKEN-FIXTURE'], 'paymentData' => 'WALLET-TOKEN-FIXTURE']));

        $log = $this->assertLogClean();
        $this->assertStringContainsString('kept-status', $log);
    }

    public function test_google_pay_request_logs_field_names_but_no_token_or_customer_details(): void
    {
        $_POST = ['paymentData' => wp_slash(wp_json_encode(['token' => 'WALLET-TOKEN-FIXTURE']))];
        $gateway = new GooglepayGateway();
        $processor = new GooglepayProcessor(...$this->processorArgs($gateway));

        $body = $processor->getBody();

        $this->assertSame(base64_encode('WALLET-TOKEN-FIXTURE'), $body['paymentData'], 'The request itself is unchanged');
        $log = $this->assertLogClean();
        $this->assertStringContainsString("'request_fields'", $log);
        $this->assertStringContainsString("'paymentData'", $log);
        $this->assertStringContainsString("'amountDebit' => '15.00'", $log);
    }

    public function test_hosted_fields_session_is_sent_but_not_logged_or_stored(): void
    {
        update_option('woocommerce_buckaroo_creditcard_settings', [
            'creditcardmethod' => 'encrypt',
            'AllowedProvider' => ['visa'],
        ]);
        $_SERVER['HTTPS'] = 'on';
        $gateway = new CreditCardGateway();
        $_POST = [
            $gateway->id . '-creditcard-issuer' => 'visa',
            $gateway->id . '-encrypted-data' => 'SESSION-FIXTURE',
        ];
        $processor = new CreditCardProcessor(...$this->processorArgs($gateway));

        $body = $processor->getBody();

        $this->assertSame('SESSION-FIXTURE', $body['sessionId'], 'The request itself is unchanged');
        $this->assertSame('visa', $body['name']);
        $this->assertLogClean();
        $order = wc_get_order(end($this->orders)->get_id());
        $this->assertSame('', OrderMeta::get($order, '_payload_encrypted_card_data'));
    }

    public function test_riverty_birth_date_is_sent_but_not_stored(): void
    {
        $_POST = ['buckaroo-afterpaynew-birthdate' => '31-01-1990'];
        $processor = new AfterpayNewProcessor(...$this->processorArgs(new AfterpayNewGateway()));

        $method = new ReflectionMethod($processor, 'getBirthDate');
        $method->setAccessible(true);
        $data = $method->invoke($processor, 'NL');

        $this->assertSame('1990-01-31', $data['billing']['recipient']['birthDate']);
        $order = wc_get_order(end($this->orders)->get_id());
        $this->assertSame('', OrderMeta::get($order, '_payload_birthday'));
    }

    public function test_order_keys_in_urls_and_messages_are_not_logged(): void
    {
        Logger::log('test|result|', [
            'result' => 'success',
            'redirect' => 'https://shop.test/checkout/order-received/12/?key=wc_order_FIXTURE&utm_source=kept-param',
        ]);
        Logger::log('Redirect to https://shop.test/?order_key=wc_order_FIXTURE&wc-api=kept-api');

        $log = $this->assertLogClean();
        $this->assertStringContainsString('order-received/12/', $log);
        $this->assertStringContainsString('utm_source=kept-param', $log);
        $this->assertStringContainsString('wc-api=kept-api', $log);
    }

    public function test_customer_details_in_a_request_body_are_not_logged(): void
    {
        $contact = [
            'firstName' => 'Jane-FIXTURE',
            'lastName' => 'Doe-FIXTURE',
            'email' => 'jane.fixture@example.test',
            'phone' => ['mobile' => '+31600000000'],
            'birthDate' => '1990-01-31',
            'chamberOfCommerce' => 'COC-FIXTURE',
        ];

        Logger::log('test|body|', [
            'invoice' => 'INV-KEPT',
            'amountDebit' => '12.34',
            'clientIP' => '192.0.2.10',
            'billing' => [
                'recipient' => $contact,
                'address' => ['street' => 'Fixturestraat', 'zipcode' => '1234FX', 'city' => 'Fixtureville'],
            ],
            'shipping' => ['recipient' => $contact],
            'customer' => ['name' => 'Jane-FIXTURE Doe-FIXTURE'],
            'articles' => [['identifier' => 'SKU-KEPT', 'description' => 'Mug', 'price' => '9.99']],
        ]);
        Logger::log('test|wallet-contact|', [
            'billingContact' => [
                'givenName' => 'Jane-FIXTURE',
                'familyName' => 'Doe-FIXTURE',
                'emailAddress' => 'jane.fixture@example.test',
                'phoneNumber' => '+31600000000',
                'addressLines' => ['Fixturestraat 1'],
                'postalCode' => '1234FX',
                'locality' => 'Fixtureville',
                'countryCode' => 'NL',
            ],
        ]);
        Logger::log('Payment started for jane.fixture@example.test');

        $log = $this->assertLogClean();
        foreach (['INV-KEPT', '12.34', 'SKU-KEPT', '9.99', "'countryCode' => 'NL'"] as $kept) {
            $this->assertStringContainsString($kept, $log);
        }
    }

    public function test_push_and_return_replies_keep_status_without_customer_details(): void
    {
        $reply = [
            'brq_statuscode' => '190',
            'brq_statusmessage' => 'Transaction successfully processed',
            'brq_transactions' => 'TRX-REFERENCE',
            'brq_amount' => '12.34',
            'brq_customer_name' => 'Jane-FIXTURE Doe-FIXTURE',
            'brq_service_ideal_consumername' => 'Jane-FIXTURE Doe-FIXTURE',
            'brq_service_ideal_consumeriban' => 'NL00IBAN-FIXTURE',
            'brq_service_transfer_customeremail' => 'jane.fixture@example.test',
            'brq_signature' => 'SIGNATURE-FIXTURE',
        ];

        Logger::log('test|push|', ResponseParser::make($reply));
        Logger::log('test|return|', ResponseParser::make([
            'Key' => 'TRX-REFERENCE',
            'Status' => ['Code' => ['Code' => 190]],
            'Services' => [[
                'Name' => 'ideal',
                'Parameters' => [
                    ['Name' => 'consumerName', 'Value' => 'Jane-FIXTURE Doe-FIXTURE'],
                    ['Name' => 'consumerIBAN', 'Value' => 'NL00IBAN-FIXTURE'],
                    ['Name' => 'consumerIssuer', 'Value' => 'KEPT-ISSUER'],
                ],
            ]],
        ]));

        $log = $this->assertLogClean();
        foreach (['TRX-REFERENCE', 'Transaction successfully processed', '12.34', 'KEPT-ISSUER'] as $kept) {
            $this->assertStringContainsString($kept, $log);
        }
    }

    public function test_idin_reply_does_not_log_the_consumer_bin(): void
    {
        WC()->session = new class () extends WC_Session_Handler {
            public function has_session()
            {
                return true;
            }
        };
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['CONTENT_TYPE']);
        $_POST = [
            'brq_statuscode' => '190',
            'brq_primary_service' => 'IDIN',
            'brq_service_idin_consumerbin' => 'BIN-FIXTURE',
            'brq_service_idin_iseighteenorolder' => 'True',
            'brq_test' => 'true',
            'ADD_idin_nonce' => IdinProcessor::startVerification(),
        ];
        $_REQUEST = $_POST;

        $controller = new class () extends IdinController {
            protected function isValidReply(ResponseParser $responseParser): bool
            {
                return true;
            }
        };
        $controller->returnHandler();

        $this->assertTrue(IdinProcessor::checkCurrentUserIsVerified());
        $log = $this->assertLogClean();
        $this->assertStringContainsString("'is_eighteen_or_older' => 1", $log);
        $this->assertStringContainsString('brq_service_idin_consumerbin', $log);
    }

    public function test_exceptions_are_logged_without_customer_details(): void
    {
        $exception = new RuntimeException(
            'Could not start the payment for jane.fixture@example.test (https://shop.test/?key=wc_order_FIXTURE)',
            0,
            new LogicException('Rejected {"paymentData":"WALLET-TOKEN-FIXTURE","code":"KEPT-CODE"}')
        );

        Logger::log('test|exception|', $exception);

        $log = $this->assertLogClean();
        $this->assertStringContainsString('RuntimeException: Could not start the payment for', $log);
        $this->assertStringContainsString('Caused by: LogicException: Rejected', $log);
        $this->assertStringContainsString('KEPT-CODE', $log);
        $this->assertStringContainsString(__FILE__, $log);
    }

    public function test_idin_identify_logs_errors_through_the_exception_formatter(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/src/Gateways/Idin/IdinController.php');

        $this->assertStringNotContainsString('(string) $th', $source);
        $this->assertStringContainsString('Logger::log(__METHOD__, $th);', $source);
    }

    public function test_objects_are_logged_without_customer_details(): void
    {
        $object = new class () {
            public $status = 'KEPT-STATUS';

            private $email = 'jane.fixture@example.test';

            protected $data = ['firstName' => 'Jane-FIXTURE', 'sessionId' => 'SESSION-FIXTURE'];
        };

        Logger::log('test|object|', $object);

        $log = $this->assertLogClean();
        $this->assertStringContainsString('KEPT-STATUS', $log);
    }

    public function test_request_context_does_not_contain_the_ip_address(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/?wc-api=wc_push_buckaroo',
            'REMOTE_ADDR' => '192.0.2.10',
            'HTTP_X_FORWARDED_FOR' => '192.0.2.10',
        ];

        Logger::log('test|context|', LoggerStorage::request_context());

        $log = $this->assertLogClean();
        $this->assertStringContainsString("'REQUEST_METHOD' => 'POST'", $log);
    }

    private function processorArgs($gateway): array
    {
        $order = wc_create_order();
        $item = new WC_Order_Item_Product();
        $item->set_name('Mug');
        $item->set_subtotal(15);
        $item->set_total(15);
        $order->add_item($item);
        $order->set_billing_first_name('Jane-FIXTURE');
        $order->set_billing_last_name('Doe-FIXTURE');
        $order->set_billing_email('jane.fixture@example.test');
        $order->set_billing_phone('+31600000000');
        $order->set_billing_address_1('Fixturestraat 1');
        $order->set_billing_postcode('1234FX');
        $order->set_billing_city('Fixtureville');
        $order->set_billing_country('NL');
        $order->set_customer_ip_address('192.0.2.10');
        $order->calculate_totals(false);
        $order->save();
        $this->orders[] = $order;

        $details = new OrderDetails($order);

        return [$gateway, $details, new OrderArticles($details, $gateway)];
    }

    /** The log is not empty and contains none of the fixture values */
    private function assertLogClean(): string
    {
        global $wpdb;
        $table = $wpdb->prefix . LoggerStorage::STORAGE_DB_TABLE;
        $log = implode("\n", $wpdb->get_col("SELECT CONCAT(`location_id`, ' ', `message`) FROM $table"));

        $this->assertNotSame('', $log, 'Debug logging must be active');
        foreach (self::FIXTURES as $fixture) {
            $this->assertStringNotContainsString($fixture, $log);
        }

        return $log;
    }
}
