<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;
use Buckaroo\Woocommerce\Gateways\Idin\IdinProcessor;

/**
 * The Apple Pay and Google Pay express routes create the order themselves, so
 * they must apply the checkout rules of the available-gateways filter.
 */
class Test_ExpressWalletAvailability extends WP_UnitTestCase
{
    public const PAID = 'process_payment reached';

    private const EXPIRED = 'Your session has expired. Please reload the page and try again.';

    private const UNAVAILABLE = 'This payment method is not available for this order. Please choose another payment method.';

    /** @var int[] */
    private $product_ids = [];

    /** @var array<string, mixed> */
    private $post_before = [];

    /** @var array<string, mixed> */
    private $request_before = [];

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
    }

    protected function tearDown(): void
    {
        $_POST = $this->post_before;
        $_REQUEST = $this->request_before;
        WC()->cart->empty_cart(false);
        wc_clear_notices();
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
     * @dataProvider walletGateways
     */
    public function test_disabled_wallet_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id, ['enabled' => 'no']);

        $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_wallet_without_credentials_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id, ['merchant_guid' => '']);

        $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_unsupported_store_currency_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);
        update_option('woocommerce_currency', 'USD');

        $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_order_above_max_amount_is_rejected_without_keeping_an_order(string $class, string $id): void
    {
        $this->configureWallet($id, ['maxvalue' => '10']);

        $this->assertRejectedWithoutOrder(new $class(), 'Error while creation of WooCommerce order');
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_idin_product_without_verification_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US', 'useidin' => 'test']);

        $this->assertRejectedWithoutOrder(new $class(), self::UNAVAILABLE);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_verified_idin_customer_can_pay_express(string $class, string $id): void
    {
        $this->configureWallet($id);
        update_option('woocommerce_buckaroo_mastersettings_settings', ['culture' => 'en-US', 'useidin' => 'test']);
        WC()->session->set('buckaroo_idin', 1);
        WC()->session->set('buckaroo_idin_verified_at', time());

        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Verified', '15.00'));

        $this->assertSame(self::PAID, $this->runTransaction($gateway));
        $this->assertSame('pending', wc_get_order($gateway->paidOrderId)->get_status());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_valid_express_payment_reaches_process_payment(string $class, string $id): void
    {
        $this->configureWallet($id, ['minvalue' => '10', 'maxvalue' => '20']);
        $gateway = new $class();
        $this->postTransaction($this->createSimpleProduct('Allowed', '15.00'));

        $this->assertSame(self::PAID, $this->runTransaction($gateway));

        $order = wc_get_order($gateway->paidOrderId);
        $this->assertInstanceOf(WC_Order::class, $order);
        $this->assertSame($id, $order->get_payment_method());
        $this->assertSame(15.0, (float) $order->get_total());
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_request_without_nonce_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);

        $this->assertRejectedWithoutOrder(new $class(), self::EXPIRED, null);
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_request_with_invalid_nonce_is_rejected_without_creating_an_order(string $class, string $id): void
    {
        $this->configureWallet($id);

        $this->assertRejectedWithoutOrder(new $class(), self::EXPIRED, 'invalid');
    }

    /**
     * @dataProvider walletGateways
     */
    public function test_nonce_of_the_other_wallet_is_rejected(string $class, string $id): void
    {
        $this->configureWallet($id);
        $otherAction = $class === Express_Availability_Applepay::class
            ? GooglepayGateway::CREATE_TRANSACTION_NONCE
            : ApplepayGateway::CREATE_TRANSACTION_NONCE;

        $this->assertRejectedWithoutOrder(new $class(), self::EXPIRED, wp_create_nonce($otherAction));
    }

    /**
     * @param  string|null|false  $nonce  False sends a valid nonce, null sends none.
     */
    private function assertRejectedWithoutOrder($gateway, string $message, $nonce = false): void
    {
        $this->postTransaction($this->createSimpleProduct('Rule product', '15.00'));
        $ordersBefore = $this->orderIds();

        $this->assertSame($message, $this->runTransaction($gateway, $nonce));
        $this->assertNull($gateway->paidOrderId, 'The payment must not be started.');
        $this->assertSame($ordersBefore, $this->orderIds(), 'No order may be left behind.');
    }

    /**
     * @param  string|null|false  $nonce  False sends a valid nonce, null sends none.
     */
    private function runTransaction($gateway, $nonce = false): string
    {
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

    private function configureWallet(string $id, array $settings = []): void
    {
        update_option(
            "woocommerce_{$id}_settings",
            array_merge(['enabled' => 'yes', 'mode' => 'test', 'merchant_guid' => 'test-guid'], $settings)
        );
    }

    private function postTransaction(int $productId): void
    {
        $address = [
            'givenName' => 'Test',
            'familyName' => 'Buyer',
            'emailAddress' => 'test@example.com',
            'addressLines' => ['Test 1'],
            'locality' => 'Amsterdam',
            'postalCode' => '1017GB',
            'countryCode' => 'NL',
        ];
        $_POST = [
            'amount' => '15.00',
            'selected_shipping_method' => '',
            'paymentData' => ['billingContact' => $address, 'shippingContact' => $address],
            'items' => [['type' => 'product', 'id' => $productId, 'quantity' => 1]],
        ];
    }

    private function orderIds(): array
    {
        return wc_get_orders(['limit' => -1, 'return' => 'ids', 'status' => array_keys(wc_get_order_statuses())]);
    }

    private function createSimpleProduct(string $name, string $price): int
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

    public function process_payment($order_id)
    {
        $this->paidOrderId = $order_id;

        throw new RuntimeException(Test_ExpressWalletAvailability::PAID);
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
