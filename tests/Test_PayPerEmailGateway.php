<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\PayPerEmail\PayPerEmailGateway;
use Buckaroo\Woocommerce\Gateways\PayPerEmail\PayPerEmailProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Test PayPerEmail Gateway
 */
class Test_PayPerEmailGateway extends TestCase
{
    /**
     * Every hook a fake gateway registers on: handleHooks() adds the six admin ones,
     * AbstractPaymentGateway::__construct() the rest. All are snapshotted so the fake
     * never outlives its test.
     */
    private const GATEWAY_HOOKS = [
        'woocommerce_admin_order_actions_end',
        'woocommerce_order_actions',
        'woocommerce_order_action_buckaroo_send_admin_payperemail',
        'wp_ajax_buckaroo_send_admin_payperemail',
        'woocommerce_order_action_buckaroo_create_paylink',
        'wp_ajax_buckaroo_create_paylink',
        'woocommerce_order_button_html',
        'woocommerce_checkout_process',
        'woocommerce_update_options_payment_gateways_buckaroo_payperemail',
        'woocommerce_api_wc_gateway_buckaroo_payperemail',
        'woocommerce_thankyou_buckaroo_payperemail',
    ];

    private const STUB_ORDER_ID = 4242;

    /** @var array<string, WP_Hook|null> */
    private $hookSnapshot = [];

    /** @var string[] */
    private $redirects = [];

    /** @var Closure|null */
    private $redirectSpy = null;

    /** @var WC_Order[] */
    private $orders = [];

    /** @var array<string, mixed> */
    private $serverBackup = [];

    /** @var array<string, mixed> */
    private $postBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;

        foreach ($this->orders as $order) {
            $order->delete(true);
        }
        $this->orders = [];

        delete_transient(get_current_user_id() . 'buckarooAdminNotice');
        $this->clearRateLimitTransients();

        if (function_exists('wc_clear_notices') && WC()->session) {
            wc_clear_notices();
        }

        if ($this->redirectSpy !== null) {
            remove_filter('wp_redirect', $this->redirectSpy);
            $this->redirectSpy = null;
        }

        foreach ($this->hookSnapshot as $hook => $snapshot) {
            remove_all_actions($hook);

            if ($snapshot instanceof WP_Hook) {
                $GLOBALS['wp_filter'][$hook] = $snapshot;
            } else {
                unset($GLOBALS['wp_filter'][$hook]);
            }
        }
        $this->hookSnapshot = [];

        parent::tearDown();
    }

    /**
     * Test gateway class exists
     */
    public function test_gateway_class_exists()
    {
        $this->assertTrue(class_exists(PayPerEmailGateway::class));
    }

    /**
     * Test processor class exists
     */
    public function test_processor_class_exists()
    {
        $this->assertTrue(class_exists(PayPerEmailProcessor::class));
    }

    /**
     * Test gateway has correct payment class
     */
    public function test_gateway_has_correct_payment_class()
    {
        $this->assertEquals(PayPerEmailProcessor::class, PayPerEmailGateway::PAYMENT_CLASS);
    }

    /**
     * Test gateway extends abstract payment gateway
     */
    public function test_gateway_extends_abstract()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->isSubclassOf('Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway'));
    }

    /**
     * Test processor extends abstract payment processor
     */
    public function test_processor_extends_abstract()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->isSubclassOf('Buckaroo\Woocommerce\Gateways\AbstractPaymentProcessor'));
    }

    /**
     * Test gateway has supported currencies property
     */
    public function test_gateway_has_supported_currencies()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasProperty('supportedCurrencies'));
    }

    /**
     * Test gateway supported currencies includes major currencies
     */
    public function test_gateway_supported_currencies_includes_major_currencies()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $property = $reflection->getProperty('supportedCurrencies');
        $property->setAccessible(true);
        
        $defaultValue = $property->getDeclaringClass()->getDefaultProperties()['supportedCurrencies'] ?? [];
        
        $this->assertContains('EUR', $defaultValue);
        $this->assertContains('USD', $defaultValue);
        $this->assertContains('GBP', $defaultValue);
    }

    /**
     * Test gateway has validate_fields method
     */
    public function test_gateway_has_validate_fields_method()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasMethod('validate_fields'));
    }

    /**
     * Test gateway has isVisibleOnFrontend method
     */
    public function test_gateway_has_is_visible_on_frontend_method()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasMethod('isVisibleOnFrontend'));
    }

    /**
     * Test gateway has canShowPayPerEmail method
     */
    public function test_gateway_has_can_show_pay_per_email_method()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $method = $reflection->getMethod('canShowPayPerEmail');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test gateway has canShowPaylink method
     */
    public function test_gateway_has_can_show_paylink_method()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $method = $reflection->getMethod('canShowPaylink');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test processor has getAction method
     */
    public function test_processor_has_get_action_method()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->hasMethod('getAction'));
    }

    /**
     * Test processor getAction returns correct action
     */
    public function test_processor_get_action_returns_payment_invitation()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getAction');
        
        // Method should be public
        $this->assertTrue($method->isPublic());
    }

    /**
     * Test processor has getMethodBody method
     */
    public function test_processor_has_get_method_body()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->hasMethod('getMethodBody'));
    }

    /**
     * Test processor has getExpirationDate method
     */
    public function test_processor_has_get_expiration_date()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getExpirationDate');
        
        $this->assertTrue($method->isPrivate());
    }

    /**
     * Test processor has getAllowedMethods method
     */
    public function test_processor_has_get_allowed_methods()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('getAllowedMethods');
        
        $this->assertTrue($method->isPrivate());
    }

    /**
     * Test processor has extractPayLink method
     */
    public function test_processor_has_extract_pay_link()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $method = $reflection->getMethod('extractPayLink');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test processor has beforeReturnHandler method
     */
    public function test_processor_has_before_return_handler()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->hasMethod('beforeReturnHandler'));
    }

    /**
     * Test processor has unsuccessfulReturnHandler method
     */
    public function test_processor_has_unsuccessful_return_handler()
    {
        $reflection = new ReflectionClass(PayPerEmailProcessor::class);
        $this->assertTrue($reflection->hasMethod('unsuccessfulReturnHandler'));
    }

    /**
     * Test gateway has paymentmethodppe property
     */
    public function test_gateway_has_payment_method_ppe_property()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasProperty('paymentmethodppe'));
    }

    /**
     * Test gateway has frontendVisible property
     */
    public function test_gateway_has_frontend_visible_property()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasProperty('frontendVisible'));
    }

    /**
     * Test gateway has usePayPerLink property
     */
    public function test_gateway_has_use_pay_per_link_property()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $property = $reflection->getProperty('usePayPerLink');
        
        $this->assertTrue($property->isPublic());
    }

    /**
     * Test gateway has init_form_fields method
     */
    public function test_gateway_has_init_form_fields()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasMethod('init_form_fields'));
    }

    /**
     * Test gateway has setProperties method
     */
    public function test_gateway_has_set_properties()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $method = $reflection->getMethod('setProperties');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test gateway has isEnabled method
     */
    public function test_gateway_has_is_enabled()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $method = $reflection->getMethod('isEnabled');
        
        $this->assertTrue($method->isProtected());
    }

    /**
     * Test gateway has handleHooks method
     */
    public function test_gateway_has_handle_hooks()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $this->assertTrue($reflection->hasMethod('handleHooks'));
    }

    /**
     * Test validate_fields method is public
     */
    public function test_validate_fields_is_public()
    {
        $reflection = new ReflectionClass(PayPerEmailGateway::class);
        $method = $reflection->getMethod('validate_fields');

        $this->assertTrue($method->isPublic());
    }

    /**
     * The dropdown order action runs inside WooCommerce's order-save request, which
     * already returns the browser to the order screen. Redirecting from the callback
     * fed the array returned by process_payment() to wp_redirect().
     */
    public function test_payperemail_dropdown_action_does_not_redirect()
    {
        $gateway = $this->registerFakeGateway();
        $order = $this->stubOrder();

        do_action('woocommerce_order_action_buckaroo_send_admin_payperemail', $order);

        $this->assertSame([$order->get_id()], $gateway->processedOrderIds);
        $this->assertSame([], $this->redirects);
    }

    public function test_paylink_dropdown_action_does_not_redirect()
    {
        $gateway = $this->registerFakeGateway();
        $order = $this->stubOrder();

        do_action('woocommerce_order_action_buckaroo_create_paylink', $order);

        $this->assertSame([$order->get_id()], $gateway->processedOrderIds);
        $this->assertSame([], $this->redirects);
    }

    /** Captured inside process_payment(), so it proves the flag is set *before* processing. */
    public function test_paylink_dropdown_action_enables_use_pay_per_link_before_processing()
    {
        $gateway = $this->registerFakeGateway();
        $order = $this->stubOrder();

        do_action('woocommerce_order_action_buckaroo_create_paylink', $order);

        $this->assertSame([true], $gateway->usePayPerLinkWhenProcessed);
    }

    public function test_payperemail_dropdown_action_leaves_use_pay_per_link_off()
    {
        $gateway = $this->registerFakeGateway();
        $order = $this->stubOrder();

        do_action('woocommerce_order_action_buckaroo_send_admin_payperemail', $order);

        $this->assertSame([false], $gateway->usePayPerLinkWhenProcessed);
    }

    /**
     * @dataProvider provide_order_actions
     */
    public function test_order_action_is_sent_for_pending_order(string $action)
    {
        $gateway = $this->registerFakeGateway();

        do_action($action, $this->stubOrder('pending'));

        $this->assertSame([self::STUB_ORDER_ID], $gateway->processedOrderIds);
        $this->assertFalse($this->adminNotice());
    }

    /**
     * @dataProvider provide_order_actions
     */
    public function test_order_action_for_paid_order_is_refused_with_admin_notice(string $action)
    {
        $gateway = $this->registerFakeGateway();

        foreach (['processing', 'completed'] as $status) {
            do_action($action, $this->stubOrder($status));
        }

        $this->assertSame([], $gateway->processedOrderIds);
        $this->assertSame('error', $this->adminNotice()['type'] ?? null);
    }

    /**
     * @dataProvider provide_order_actions
     */
    public function test_order_action_for_cancelled_or_refunded_order_is_refused(string $action)
    {
        $gateway = $this->registerFakeGateway();

        foreach (['cancelled', 'refunded'] as $status) {
            do_action($action, $this->stubOrder($status));
        }

        $this->assertSame([], $gateway->processedOrderIds);
        $this->assertSame('error', $this->adminNotice()['type'] ?? null);
    }

    /**
     * @dataProvider provide_order_actions
     */
    public function test_order_action_is_refused_when_gateway_is_disabled(string $action)
    {
        $gateway = $this->registerFakeGateway(['enabled' => 'no']);

        do_action($action, $this->stubOrder('pending'));

        $this->assertSame([], $gateway->processedOrderIds);
        $this->assertSame('error', $this->adminNotice()['type'] ?? null);
    }

    public function test_payperemail_action_is_refused_when_hidden_in_settings()
    {
        $gateway = $this->registerFakeGateway(['show_PayPerEmail' => 'FALSE']);

        do_action('woocommerce_order_action_buckaroo_send_admin_payperemail', $this->stubOrder('pending'));

        $this->assertSame([], $gateway->processedOrderIds);
        $this->assertSame('error', $this->adminNotice()['type'] ?? null);
    }

    public function test_paylink_action_is_refused_when_hidden_in_settings()
    {
        $gateway = $this->registerFakeGateway(['show_PayLink' => 'FALSE']);

        do_action('woocommerce_order_action_buckaroo_create_paylink', $this->stubOrder('pending'));

        $this->assertSame([], $gateway->processedOrderIds);
        $this->assertSame('error', $this->adminNotice()['type'] ?? null);
    }

    public function test_paylink_can_still_be_created_for_failed_order()
    {
        $gateway = $this->registerFakeGateway();

        do_action('woocommerce_order_action_buckaroo_create_paylink', $this->stubOrder('failed'));

        $this->assertSame([self::STUB_ORDER_ID], $gateway->processedOrderIds);
    }

    public function test_order_actions_dropdown_hides_actions_for_paid_order()
    {
        $this->registerFakeGateway();

        $previous = $GLOBALS['theorder'] ?? null;
        try {
            $GLOBALS['theorder'] = $this->stubOrder('completed');
            $paid = apply_filters('woocommerce_order_actions', []);

            $GLOBALS['theorder'] = $this->stubOrder('pending');
            $pending = apply_filters('woocommerce_order_actions', []);
        } finally {
            $GLOBALS['theorder'] = $previous;
        }

        $this->assertArrayNotHasKey('buckaroo_send_admin_payperemail', $paid);
        $this->assertArrayNotHasKey('buckaroo_create_paylink', $paid);
        $this->assertArrayHasKey('buckaroo_send_admin_payperemail', $pending);
        $this->assertArrayHasKey('buckaroo_create_paylink', $pending);
    }

    public static function provide_order_actions(): array
    {
        return [
            'PayPerEmail' => ['woocommerce_order_action_buckaroo_send_admin_payperemail'],
            'PayLink' => ['woocommerce_order_action_buckaroo_create_paylink'],
        ];
    }

    public function test_checkout_invitation_is_allowed_when_shown_on_checkout()
    {
        $gateway = $this->checkoutGateway();

        $this->assertNull($this->rejectIneligibleOrder($gateway, $this->savedOrder('shopper@example.com')));
    }

    public function test_checkout_invitation_is_refused_when_not_shown_on_checkout()
    {
        $gateway = $this->checkoutGateway('no');

        $result = $this->rejectIneligibleOrder($gateway, $this->savedOrder('shopper@example.com'));

        $this->assertSame(['result' => 'failure'], $result);
    }

    public function test_checkout_invitations_are_limited_per_recipient()
    {
        $gateway = $this->checkoutGateway();

        for ($i = 1; $i <= PayPerEmailGateway::CHECKOUT_RECIPIENT_LIMIT; $i++) {
            $_SERVER['REMOTE_ADDR'] = '198.51.100.' . $i;
            $this->assertNull($this->rejectIneligibleOrder($gateway, $this->savedOrder('Shopper@Example.com')));
        }

        $_SERVER['REMOTE_ADDR'] = '198.51.100.200';
        $result = $this->rejectIneligibleOrder($gateway, $this->savedOrder('shopper@example.com'));

        $this->assertSame(['result' => 'failure'], $result);
    }

    public function test_checkout_invitations_are_limited_per_client()
    {
        $gateway = $this->checkoutGateway();
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';

        for ($i = 1; $i <= PayPerEmailGateway::CHECKOUT_CLIENT_LIMIT; $i++) {
            $this->assertNull($this->rejectIneligibleOrder($gateway, $this->savedOrder("shopper{$i}@example.com")));
        }

        $result = $this->rejectIneligibleOrder($gateway, $this->savedOrder('another@example.com'));

        $this->assertSame(['result' => 'failure'], $result);
    }

    public function test_admin_action_is_not_counted_against_checkout_limits()
    {
        $gateway = $this->checkoutGateway('no');
        $property = new ReflectionProperty(PayPerEmailGateway::class, 'merchantInitiated');
        $property->setAccessible(true);
        $property->setValue($gateway, true);

        $this->assertNull($this->rejectIneligibleOrder($gateway, $this->savedOrder('shopper@example.com')));
    }

    public function test_invitation_is_sent_to_order_billing_email()
    {
        $gateway = $this->checkoutGateway();
        $order = $this->savedOrder('shopper@example.com');
        $_POST['buckaroo-payperemail-email'] = 'other@example.com';

        $processor = $gateway->newPaymentProcessorInstance($order);
        $method = new ReflectionMethod($processor, 'getMethodBody');
        $method->setAccessible(true);

        $this->assertSame('shopper@example.com', $method->invoke($processor)['email']);
    }

    /**
     * Registers the real handleHooks() callbacks on a gateway whose process_payment()
     * records its calls instead of talking to Buckaroo, plus a strictly string-typed
     * wp_redirect filter that a redirect attempt cannot survive.
     */
    private function registerFakeGateway(array $settings = []): PayPerEmailGateway
    {
        $this->snapshotGatewayHooks();

        $gateway = new class extends PayPerEmailGateway
        {
            /** @var int[] */
            public $processedOrderIds = [];

            /** @var bool[] */
            public $usePayPerLinkWhenProcessed = [];

            public function process_payment($order_id)
            {
                $this->processedOrderIds[] = $order_id;
                $this->usePayPerLinkWhenProcessed[] = $this->usePayPerLink;

                return ['result' => 'success', 'redirect' => 'https://checkout.buckaroo.nl/pay/1'];
            }
        };

        $gateway->settings = array_merge(
            $gateway->settings,
            ['enabled' => 'yes', 'show_PayPerEmail' => 'TRUE', 'show_PayLink' => 'TRUE'],
            $settings
        );
        $gateway->handleHooks();

        $this->redirectSpy = function (string $location): string {
            $this->redirects[] = $location;

            // Empty location makes wp_redirect() bail before sending headers.
            return '';
        };
        add_filter('wp_redirect', $this->redirectSpy);

        return $gateway;
    }

    /** Only the id and status are read, so an unsaved order keeps the tests off the database. */
    private function stubOrder(string $status = 'pending'): WC_Order
    {
        $order = new WC_Order();
        $order->set_id(self::STUB_ORDER_ID);
        $order->set_status($status);

        return $order;
    }

    /** Snapshot before constructing a gateway: the constructor registers hooks of its own. */
    private function snapshotGatewayHooks(): void
    {
        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce is not available');
        }

        foreach (self::GATEWAY_HOOKS as $hook) {
            if (array_key_exists($hook, $this->hookSnapshot)) {
                continue;
            }
            $existing = $GLOBALS['wp_filter'][$hook] ?? null;
            $this->hookSnapshot[$hook] = $existing instanceof WP_Hook ? clone $existing : null;
            remove_all_actions($hook);
        }
    }

    private function checkoutGateway(string $shownOnCheckout = 'yes'): PayPerEmailGateway
    {
        $this->snapshotGatewayHooks();
        if (! WC()->session) {
            WC()->initialize_session();
        }
        $_SERVER['REMOTE_ADDR'] = '198.51.100.1';

        $gateway = new PayPerEmailGateway();
        $gateway->settings['enabled'] = 'yes';
        $gateway->frontendVisible = $shownOnCheckout;
        $gateway->minvalue = 0;
        $gateway->maxvalue = 0;

        return $gateway;
    }

    private function savedOrder(string $email): WC_Order
    {
        $order = wc_create_order();
        $order->set_billing_first_name('Test');
        $order->set_billing_last_name('Shopper');
        $order->set_billing_email($email);
        $order->set_billing_country('NL');
        $order->set_currency('EUR');
        $order->set_total(10.0);
        $order->save();

        return $this->orders[] = $order;
    }

    private function rejectIneligibleOrder(PayPerEmailGateway $gateway, WC_Order $order): ?array
    {
        $method = new ReflectionMethod($gateway, 'rejectIneligibleOrder');
        $method->setAccessible(true);

        return $method->invoke($gateway, $order->get_id());
    }

    /** @return array{type: string, message: string}|false */
    private function adminNotice()
    {
        return get_transient(get_current_user_id() . 'buckarooAdminNotice');
    }

    private function clearRateLimitTransients(): void
    {
        global $wpdb;

        $names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_bk_ppe_') . '%'
            )
        );

        foreach ($names as $name) {
            delete_transient(substr($name, strlen('_transient_')));
        }
    }
}
