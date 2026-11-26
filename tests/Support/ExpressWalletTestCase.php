<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;
use Buckaroo\Woocommerce\Gateways\Express\ExpressRateLimit;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;
use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;

/**
 * Drives the Apple Pay and Google Pay express create-transaction route the way
 * the express button does: a POST with the wallet's nonce and payload.
 */
abstract class ExpressWalletTestCase extends WP_UnitTestCase
{
    public const PAID = 'process_payment reached';

    protected const EXPIRED = 'Your session has expired. Please reload the page and try again.';

    protected const UNAVAILABLE = 'This payment method is not available for this order. Please choose another payment method.';

    protected const TOO_MANY = 'Too many payment attempts. Please wait a few minutes and try again.';

    protected const FAILED = 'Something went wrong while processing your payment. Please try again.';

    /** @var int[] */
    protected $product_ids = [];

    /** @var array<string, mixed> */
    private $post_before = [];

    /** @var array<string, mixed> */
    private $request_before = [];

    /** @var array<string, mixed> */
    private $server_before = [];

    /** @var int */
    private static $visitor = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (! WC()->session) {
            WC()->initialize_session();
        }
        if (! WC()->customer) {
            WC()->customer = new WC_Customer(0, true);
        }
        if (! WC()->cart) {
            WC()->initialize_cart();
        }

        WC()->cart->empty_cart(false);
        WC()->session->set('buckaroo_idin', null);
        WC()->session->set('buckaroo_idin_verified_at', null);
        update_option('woocommerce_currency', 'EUR');
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US']);
        $this->resetIdinCategories();
        $this->post_before = $_POST;
        $this->request_before = $_REQUEST;
        $this->server_before = $_SERVER;
        // Each test is a new visitor: a fresh rate-limit session and address.
        WC()->session->set(ExpressRateLimit::SESSION_KEY, null);
        $_SERVER['REMOTE_ADDR'] = '2001:db8::' . dechex(++self::$visitor);
    }

    protected function tearDown(): void
    {
        $_POST = $this->post_before;
        $_REQUEST = $this->request_before;
        $_SERVER = $this->server_before;
        WC()->cart->empty_cart(false);
        WC()->customer->set_billing_email('');
        wc_clear_notices();
        wp_set_current_user(0);
        $this->resetIdinCategories();

        foreach ($this->product_ids as $product_id) {
            wp_delete_post($product_id, true);
        }

        parent::tearDown();
    }

    public function walletGateways(): array
    {
        return [
            'Apple Pay' => [Express_Availability_Applepay::class, 'buckaroo_applepay'],
            'Google Pay' => [Express_Availability_Googlepay::class, 'buckaroo_googlepay'],
        ];
    }

    /**
     * @param  string|null|false  $nonce  False sends a valid nonce, null sends none.
     */
    protected function assertRejectedWithoutOrder($gateway, string $message, $nonce = false, string $method = 'POST'): void
    {
        $this->postTransaction($this->createSimpleProduct('Rule product', '15.00'));
        $ordersBefore = $this->orderIds();

        $this->assertSame($message, $this->runTransaction($gateway, $nonce, $method));
        $this->assertNull($gateway->paidOrderId, 'The payment must not be started.');
        $this->assertSame($ordersBefore, $this->orderIds(), 'No order may be left behind.');
    }

    protected function invalidDataMessage(string $id): string
    {
        return $id === 'buckaroo_applepay' ? 'ApplePay data is invalid.' : 'Google Pay data is invalid.';
    }

    /**
     * @param  string|null|false  $nonce  False sends a valid nonce, null sends none.
     */
    protected function runTransaction($gateway, $nonce = false, string $method = 'POST'): string
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        unset($_REQUEST['security']);
        if ($nonce === false) {
            $_REQUEST['security'] = wp_create_nonce($gateway::CREATE_TRANSACTION_NONCE);
        } elseif ($nonce !== null) {
            $_REQUEST['security'] = $nonce;
        }

        try {
            $gateway->createTransaction();
        } catch (RuntimeException $result) {
            return $result->getMessage();
        }

        $this->fail('createTransaction() must respond.');
    }

    protected function configureWallet(string $id, array $settings = []): void
    {
        update_option(
            "woocommerce_{$id}_settings",
            array_merge(['enabled' => 'yes', 'mode' => 'test', 'merchant_guid' => 'test-guid'], $settings)
        );
    }

    /**
     * Post the express payment of one product, as approved in the wallet
     * sheet for the amount and with the e-mail address given.
     */
    protected function postTransaction(int $productId, string $amount = '15.00', string $email = 'test@example.com'): void
    {
        $address = [
            'givenName' => 'Test',
            'familyName' => 'Buyer',
            'emailAddress' => $email,
            'addressLines' => ['Test 1'],
            'locality' => 'Amsterdam',
            'postalCode' => '1017GB',
            'countryCode' => 'NL',
        ];
        $_POST = [
            'amount' => $amount,
            'selected_shipping_method' => '',
            'paymentData' => [
                'token' => '{"signature":"test-signature"}',
                'billingContact' => $address,
                'shippingContact' => $address,
            ],
            'items' => [['type' => 'product', 'id' => $productId, 'quantity' => 1]],
        ];
    }

    protected function orderIds(): array
    {
        return wc_get_orders(['limit' => -1, 'return' => 'ids', 'status' => array_keys(wc_get_order_statuses())]);
    }

    protected function createSimpleProduct(string $name, string $price): int
    {
        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_status('publish');
        $product->set_regular_price($price);
        $product->set_price($price);
        $product_id = $product->save();
        $this->product_ids[] = $product_id;

        return $product_id;
    }

    private function resetIdinCategories(): void
    {
        $categories = new ReflectionProperty(IdinProcessor::class, 'idinCategories');
        if (PHP_VERSION_ID < 80100) {
            $categories->setAccessible(true);
        }
        $categories->setValue(null, null);
    }
}

/**
 * Records the order instead of calling Buckaroo, and turns the JSON responses
 * of createTransaction() into exceptions the test can read.
 */
trait Express_Availability_Gateway
{
    /** @var int|null */
    public $paidOrderId;

    /** @var Throwable|null Thrown by the payment step instead of paying. */
    public $paymentError;

    public function process_payment($order_id)
    {
        if ($this->paymentError) {
            throw $this->paymentError;
        }

        $this->paidOrderId = $order_id;

        return ['result' => 'success', 'redirect' => 'https://example.org/pay'];
    }

    public function success_response($result)
    {
        throw new RuntimeException(ExpressWalletTestCase::PAID);
    }

    public function error_response($errorMessage)
    {
        throw new RuntimeException($errorMessage);
    }
}

class Express_Availability_Applepay extends ApplepayGateway
{
    use Express_Availability_Gateway;
}

class Express_Availability_Googlepay extends GooglepayGateway
{
    use Express_Availability_Gateway;
}
