<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewGateway;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaKpGateway;
use Buckaroo\Woocommerce\Gateways\Wero\WeroGateway;
use Buckaroo\Woocommerce\Gateways\ZakelijkOpRekening\ZakelijkOpRekeningGateway;
use Buckaroo\Woocommerce\Hooks\OrderActions;
use Buckaroo\Woocommerce\Order\CaptureAllocation;
use Buckaroo\Woocommerce\Order\OrderMeta;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureAction;
use Buckaroo\Woocommerce\PaymentProcessors\Actions\CaptureResult;
use Buckaroo\Woocommerce\Services\Helper;
use PHPUnit\Framework\TestCase;

/**
 * Captures through the shared CaptureAction, for each gateway that uses it.
 */
class Test_CaptureLocking extends TestCase
{
    use HposStorage;

    /** @var int[] */
    private $productIds = [];

    /** @var int[] */
    private $userIds = [];

    /** @var OtherConnectionLock|null */
    private $otherConnection;

    private const SETTINGS = [
        'woocommerce_buckaroo_creditcard_settings' => ['creditcardpayauthorize' => 'authorize'],
        'woocommerce_buckaroo_afterpaynew_settings' => ['afterpaynewpayauthorize' => 'authorize'],
        'woocommerce_buckaroo_wero_settings' => ['weropayauthorize' => 'authorize'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        update_option('woocommerce_buckaroo_mastersettings_settings', [
            'culture' => 'en-US',
            'merchantkey' => 'test-merchant',
            'secretkey' => 'test-secret',
        ]);
        foreach (self::SETTINGS as $option => $value) {
            update_option($option, $value);
        }
    }

    protected function tearDown(): void
    {
        if ($this->otherConnection !== null) {
            $this->otherConnection->close();
            $this->otherConnection = null;
        }

        $_POST = [];
        $_REQUEST = [];

        foreach ($this->productIds as $productId) {
            wp_delete_post($productId, true);
        }
        foreach ($this->userIds as $userId) {
            wp_delete_user($userId);
        }
        wp_set_current_user(0);

        delete_option('woocommerce_buckaroo_mastersettings_settings');
        foreach (array_keys(self::SETTINGS) as $option) {
            delete_option($option);
        }

        $this->deleteCreatedOrders();
        $this->disableHpos();
        parent::tearDown();
    }

    public static function gateways(): array
    {
        $gateways = [
            'credit card' => [CreditCardGateway::class, 'buckaroo_creditcard'],
            'riverty' => [AfterpayNewGateway::class, 'buckaroo_afterpaynew'],
            'wero' => [WeroGateway::class, 'buckaroo_wero'],
            'klarna kp' => [KlarnaKpGateway::class, 'buckaroo_klarnakp'],
            'zakelijk op rekening' => [ZakelijkOpRekeningGateway::class, 'buckaroo_zakelijkoprekening'],
        ];

        $cases = [];
        foreach ($gateways as $name => $gateway) {
            $cases[$name . ' (hpos)'] = array_merge($gateway, [true]);
            $cases[$name . ' (legacy)'] = array_merge($gateway, [false]);
        }

        return $cases;
    }

    /**
     * @dataProvider gateways
     */
    public function test_capture_is_not_sent_while_another_request_captures_the_order(string $gatewayClass, string $paymentMethod, bool $hpos): void
    {
        $this->useStorage($hpos);
        $order = $this->createCapturableOrder($paymentMethod);
        $this->otherConnection = new OtherConnectionLock();
        $this->assertTrue($this->otherConnection->acquire(CaptureAction::SEND_LOCK_PURPOSE, $order, CaptureAction::SEND_LOCK_KEY));

        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'CAPTURE1'));
        $result = $this->capture(new $gatewayClass(), $order, $client);

        $this->assertSame(CaptureResult::FAILED, $result->getStatus());
        $this->assertSame(0, $client->sendCount);
        $this->assertSame([], OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));

        $this->otherConnection->release(CaptureAction::SEND_LOCK_PURPOSE, $order, CaptureAction::SEND_LOCK_KEY);
        $result = $this->capture(new $gatewayClass(), wc_get_order($order->get_id()), $client);

        $this->assertTrue($result->isSuccess(), $result->getMessage());
        $this->assertSame(1, $client->sendCount);
        $this->assertCount(1, OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));
    }

    /**
     * @dataProvider gateways
     */
    public function test_the_order_stays_locked_while_the_capture_is_sent(string $gatewayClass, string $paymentMethod, bool $hpos): void
    {
        $this->useStorage($hpos);
        $order = $this->createCapturableOrder($paymentMethod);
        $this->otherConnection = new OtherConnectionLock();
        $lockedDuringSend = null;
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'CAPTURE1'));
        $client->onSend = function () use ($order, &$lockedDuringSend) {
            $lockedDuringSend = ! $this->otherConnection->isFree(CaptureAction::SEND_LOCK_PURPOSE, $order, CaptureAction::SEND_LOCK_KEY);
        };

        $result = $this->capture(new $gatewayClass(), $order, $client);

        $this->assertTrue($result->isSuccess(), $result->getMessage());
        $this->assertTrue($lockedDuringSend);
        $this->assertTrue($this->otherConnection->isFree(CaptureAction::SEND_LOCK_PURPOSE, $order, CaptureAction::SEND_LOCK_KEY));
    }

    /**
     * @dataProvider gateways
     */
    public function test_the_same_items_are_captured_only_once(string $gatewayClass, string $paymentMethod, bool $hpos): void
    {
        $this->useStorage($hpos);
        $order = $this->createCapturableOrder($paymentMethod);
        $client = new RecordingBuckarooClient(
            RecordingBuckarooClient::response(190, 'CAPTURE1'),
            RecordingBuckarooClient::response(190, 'CAPTURE2')
        );

        $first = $this->capture(new $gatewayClass(), $order, $client);
        $second = $this->capture(new $gatewayClass(), wc_get_order($order->get_id()), $client);

        $this->assertTrue($first->isSuccess(), $first->getMessage());
        $this->assertSame(CaptureResult::FAILED, $second->getStatus());
        $this->assertSame(1, $client->sendCount);
        $this->assertCount(1, OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));
    }

    /**
     * @dataProvider gateways
     */
    public function test_the_lock_is_released_after_a_transport_error(string $gatewayClass, string $paymentMethod, bool $hpos): void
    {
        $this->useStorage($hpos);
        $order = $this->createCapturableOrder($paymentMethod);
        $client = new RecordingBuckarooClient(new RuntimeException('Timeout'));

        $result = $this->capture(new $gatewayClass(), $order, $client);

        $this->otherConnection = new OtherConnectionLock();
        $this->assertSame(CaptureResult::UNKNOWN, $result->getStatus());
        $this->assertTrue($this->otherConnection->isFree(CaptureAction::SEND_LOCK_PURPOSE, $order, CaptureAction::SEND_LOCK_KEY));
    }

    public function test_capture_with_an_item_of_another_order_is_not_sent(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturableOrder('buckaroo_creditcard');
        $otherOrder = $this->createCapturableOrder('buckaroo_creditcard');
        $foreignItem = current($otherOrder->get_items('line_item'));
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'CAPTURE1'));

        $result = $this->capture(
            new CreditCardGateway(),
            $order,
            $client,
            CaptureAllocation::fromArrays([$foreignItem->get_id() => 2], [$foreignItem->get_id() => 40.00], []),
            40.00
        );

        $this->assertSame(CaptureResult::FAILED, $result->getStatus());
        $this->assertSame(0, $client->sendCount);
    }

    public function test_capture_with_a_negative_item_amount_is_not_sent(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturableOrder('buckaroo_creditcard', true);
        $line = current($order->get_items('line_item'));
        $shipping = current($order->get_items('shipping'));
        $client = new RecordingBuckarooClient(RecordingBuckarooClient::response(190, 'CAPTURE1'));

        $result = $this->capture(
            new CreditCardGateway(),
            $order,
            $client,
            CaptureAllocation::fromArrays(
                [$line->get_id() => 2],
                [$line->get_id() => 40.00, $shipping->get_id() => -5.00],
                []
            ),
            35.00
        );

        $this->assertSame(CaptureResult::FAILED, $result->getStatus());
        $this->assertSame(0, $client->sendCount);
    }

    public function test_capture_request_for_an_order_the_user_may_not_edit_is_rejected(): void
    {
        $this->useStorage(true);
        $order = $this->createCapturableOrder('buckaroo_creditcard');
        $userId = $this->createUser('editor');
        get_user_by('id', $userId)->add_cap('edit_shop_orders');
        wp_set_current_user($userId);
        // WordPress maps edit_shop_order to edit_post before this filter runs.
        $deny = static function ($caps, $cap, $user, $args) use ($order) {
            return in_array($cap, ['edit_shop_order', 'edit_post'], true) && (int) ($args[0] ?? 0) === $order->get_id()
                ? ['do_not_allow']
                : $caps;
        };
        add_filter('map_meta_cap', $deny, 10, 4);

        try {
            $response = $this->callCaptureEndpoint($order);
        } finally {
            remove_filter('map_meta_cap', $deny, 10);
        }

        $this->assertSame(
            'You are not allowed to capture this order.',
            $response['errors']['error_capture'][0][0] ?? null
        );
        $this->assertSame([], OrderMeta::get(wc_get_order($order->get_id()), '_wc_order_captures', false));
    }

    /**
     * @dataProvider storages
     */
    public function test_shop_managers_and_administrators_may_edit_an_order(bool $hpos): void
    {
        // The test install does not run the WooCommerce setup, so add its roles here
        // and reload them, as a new request would.
        WC_Install::create_roles();
        wp_roles()->for_site();

        $this->useStorage($hpos);
        $order = $this->createCapturableOrder('buckaroo_creditcard');

        foreach (['shop_manager', 'administrator'] as $role) {
            wp_set_current_user($this->createUser($role));
            $this->assertTrue(Helper::currentUserCanEditOrder($order), $role);
        }

        wp_set_current_user($this->createUser('customer'));
        $this->assertFalse(Helper::currentUserCanEditOrder($order));
    }

    public static function storages(): array
    {
        return ['hpos' => [true], 'legacy' => [false]];
    }

    private function callCaptureEndpoint(WC_Order $order): array
    {
        $_POST = ['order_id' => (string) $order->get_id()];
        $_REQUEST = ['security' => wp_create_nonce('order-item')];
        $die = static function () {
            return static function () {
                throw new RuntimeException('wp_die');
            };
        };
        add_filter('wp_die_handler', $die);
        add_filter('wp_die_ajax_handler', $die);
        add_filter('wp_doing_ajax', '__return_true');
        ob_start();
        try {
            (new OrderActions())->handleOrderCapture();
        } catch (RuntimeException $exception) {
            // wp_send_json ends the request
        } finally {
            $output = ob_get_clean();
            remove_filter('wp_die_handler', $die);
            remove_filter('wp_die_ajax_handler', $die);
            remove_filter('wp_doing_ajax', '__return_true');
        }

        $decoded = json_decode((string) $output, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function capture(
        AbstractPaymentGateway $gateway,
        WC_Order $order,
        RecordingBuckarooClient $client,
        ?CaptureAllocation $allocation = null,
        float $amount = 40.00
    ): CaptureResult {
        if ($allocation === null) {
            $item = current($order->get_items('line_item'));
            $allocation = CaptureAllocation::fromArrays([$item->get_id() => 2], [$item->get_id() => 40.00], []);
        }

        $method = new ReflectionMethod($gateway, 'executeCapture');
        $method->setAccessible(true);

        return $method->invoke($gateway, $order, $amount, $allocation, $client);
    }

    private function useStorage(bool $hpos): void
    {
        if ($hpos) {
            $this->enableHpos();
        } else {
            $this->disableHpos();
        }
    }

    private function createUser(string $role): int
    {
        $userId = wp_insert_user([
            'user_login' => 'bk_' . $role . '_' . wp_generate_password(6, false),
            'user_pass' => wp_generate_password(),
            'role' => $role,
        ]);
        $this->userIds[] = $userId;

        return $userId;
    }

    private function createCapturableOrder(string $paymentMethod, bool $withShipping = false): WC_Order
    {
        $product = new WC_Product_Simple();
        $product->set_name('Capture product');
        $product->set_regular_price('20');
        $product->set_price('20');
        $product->save();
        $this->productIds[] = $product->get_id();

        $order = $this->createOrder();
        $order->add_product($product, 2);
        if ($withShipping) {
            $shipping = new WC_Order_Item_Shipping();
            $shipping->set_method_title('Flat rate');
            $shipping->set_total('5');
            $order->add_item($shipping);
        }
        $order->set_payment_method($paymentMethod);
        $order->set_currency('EUR');
        $order->set_transaction_id('AUTHORIZE1');
        $order->calculate_totals(false);
        $order->update_meta_data('_wc_order_authorized', 'yes');
        $order->update_meta_data('buckaroo_is_reserved', 'yes');
        $order->update_meta_data('_buckaroo_klarnakp_reservation_number', 'RESERVATION1');
        $order->save();

        return wc_get_order($order->get_id());
    }
}
