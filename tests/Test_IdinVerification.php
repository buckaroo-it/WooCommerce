<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Idin\IdinController;
use Buckaroo\Woocommerce\Gateways\Idin\IdinGateway;
use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;
use Buckaroo\Woocommerce\ResponseParser\ResponseParser;
use PHPUnit\Framework\TestCase;

/**
 * A signed iDIN reply only verifies the visitor who started that
 * verification, only once, and the verification expires.
 */
class Test_IdinVerification extends TestCase
{
    private const SETTINGS_OPTION = 'woocommerce_buckaroo_mastersettings_settings';

    /** @var mixed */
    private $previousSettings;

    /** @var mixed */
    private $previousSession;

    /** @var array */
    private $previousServer;

    /** @var int */
    private $userId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists('WC_Session_Handler')) {
            $this->markTestSkipped('WooCommerce is not available');
        }

        $this->previousSettings = get_option(self::SETTINGS_OPTION);
        update_option(self::SETTINGS_OPTION, ['useidin' => 'test']);

        $this->previousSession = WC()->session;
        WC()->session = new class () extends WC_Session_Handler {
            public function has_session()
            {
                return true;
            }
        };

        $this->previousServer = $_SERVER;
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        WC()->session = $this->previousSession;
        $_SERVER = $this->previousServer;
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
        wp_set_current_user(0);

        if ($this->userId) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($this->userId);
        }

        if ($this->previousSettings === false) {
            delete_option(self::SETTINGS_OPTION);
        } else {
            update_option(self::SETTINGS_OPTION, $this->previousSettings);
        }

        parent::tearDown();
    }

    public function test_a_signed_reply_without_a_started_verification_does_not_verify()
    {
        $this->sendReply('some-other-visitors-nonce');

        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
        $this->assertEmpty(WC()->session->get('buckaroo_idin'));
    }

    public function test_a_signed_reply_with_a_different_nonce_does_not_verify()
    {
        IdinProcessor::startVerification();

        $this->sendReply('some-other-visitors-nonce');

        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
        $this->assertEmpty(WC()->session->get('buckaroo_idin'));
    }

    public function test_a_signed_reply_without_a_nonce_does_not_verify()
    {
        IdinProcessor::startVerification();

        $this->sendReply(null);

        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
    }

    public function test_the_reply_for_the_started_verification_verifies_a_guest()
    {
        $nonce = IdinProcessor::startVerification();

        $this->sendReply($nonce);

        $this->assertTrue(IdinProcessor::checkCurrentUserIsVerified());
        $this->assertSame('BIN123', WC()->session->get('buckaroo_idin_bin'));
    }

    public function test_the_reply_for_the_started_verification_verifies_a_logged_in_user()
    {
        $this->userId = wp_create_user('idin_' . wp_generate_password(8, false), wp_generate_password());
        wp_set_current_user($this->userId);

        $nonce = IdinProcessor::startVerification();

        $this->sendReply($nonce);

        $this->assertTrue(IdinProcessor::checkCurrentUserIsVerified());
        $this->assertSame('BIN123', get_user_meta($this->userId, 'buckaroo_idin_bin', true));
        $this->assertSame('', get_user_meta($this->userId, 'buckaroo_idin_pending', true));
    }

    public function test_the_same_reply_sent_twice_verifies_only_once()
    {
        $nonce = IdinProcessor::startVerification();

        $this->sendReply($nonce);
        $this->assertTrue(IdinProcessor::checkCurrentUserIsVerified());

        WC()->session->set('buckaroo_idin', 0);

        $this->sendReply($nonce);
        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
    }

    public function test_an_under_eighteen_reply_does_not_verify()
    {
        $nonce = IdinProcessor::startVerification();

        $this->sendReply($nonce, 'False');

        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
    }

    public function test_starting_again_replaces_the_previous_verification()
    {
        $first = IdinProcessor::startVerification();
        IdinProcessor::startVerification();

        $this->sendReply($first);

        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
    }

    public function test_a_reply_for_a_verification_started_too_long_ago_does_not_verify()
    {
        $nonce = IdinProcessor::startVerification();
        WC()->session->set(
            'buckaroo_idin_pending',
            [
                'nonce' => $nonce,
                'created' => time() - IdinProcessor::PENDING_TTL - 1,
            ]
        );

        $this->sendReply($nonce);

        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
    }

    public function test_a_verification_expires()
    {
        IdinProcessor::setCurrentUserIsVerified('BIN123');
        $this->assertTrue(IdinProcessor::checkCurrentUserIsVerified());

        WC()->session->set('buckaroo_idin_verified_at', time() - IdinProcessor::VERIFICATION_TTL - 1);

        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
    }

    public function test_a_verification_without_a_timestamp_is_not_accepted()
    {
        WC()->session->set('buckaroo_idin', 1);

        $this->assertFalse(IdinProcessor::checkCurrentUserIsVerified());
    }

    public function test_reset_clears_a_started_verification()
    {
        $nonce = IdinProcessor::startVerification();

        IdinProcessor::setCurrentUserIsNotVerified();

        $this->assertFalse(IdinProcessor::consumeVerification($nonce));
    }

    public function test_the_identify_request_carries_the_nonce_of_the_started_verification()
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $gateway = new IdinGateway();
        $gateway->issuer = 'BANKNL2Y';

        $body = $gateway->newPaymentProcessorInstance('')->getBody();

        $nonce = $body['additionalParameters']['idin_nonce'] ?? null;
        $this->assertIsString($nonce);
        $this->assertSame($nonce, WC()->session->get('buckaroo_idin_pending')['nonce']);
    }

    public function test_only_listed_issuers_are_accepted()
    {
        $this->assertTrue(IdinProcessor::checkIfValidIssuer('INGBNL2A'));
        $this->assertFalse(IdinProcessor::checkIfValidIssuer('EVILNL2A'));
    }

    /**
     * Send a reply to the return handler as if its signature were valid.
     */
    private function sendReply(?string $nonce, string $isEighteenOrOlder = 'True'): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_SERVER['CONTENT_TYPE']);

        $_POST = [
            'brq_statuscode' => '190',
            'brq_primary_service' => 'IDIN',
            'brq_service_idin_consumerbin' => 'BIN123',
            'brq_service_idin_iseighteenorolder' => $isEighteenOrOlder,
            'brq_test' => 'true',
        ];
        if ($nonce !== null) {
            $_POST['ADD_idin_nonce'] = $nonce;
        }
        $_REQUEST = $_POST;

        $controller = new class () extends IdinController {
            protected function isValidReply(ResponseParser $responseParser): bool
            {
                return true;
            }
        };
        $controller->returnHandler();
    }
}
