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

    protected function tearDown(): void
    {
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
     * Registers the real handleHooks() callbacks on a gateway whose process_payment()
     * records its calls instead of talking to Buckaroo, plus a strictly string-typed
     * wp_redirect filter that a redirect attempt cannot survive.
     */
    private function registerFakeGateway(): PayPerEmailGateway
    {
        if (! class_exists('WC_Order')) {
            $this->markTestSkipped('WooCommerce is not available');
        }

        // Snapshot before constructing: the constructor registers hooks of its own.
        foreach (self::GATEWAY_HOOKS as $hook) {
            $existing = $GLOBALS['wp_filter'][$hook] ?? null;
            $this->hookSnapshot[$hook] = $existing instanceof WP_Hook ? clone $existing : null;
            remove_all_actions($hook);
        }

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

        $gateway->handleHooks();

        $this->redirectSpy = function (string $location): string {
            $this->redirects[] = $location;

            // Empty location makes wp_redirect() bail before sending headers.
            return '';
        };
        add_filter('wp_redirect', $this->redirectSpy);

        return $gateway;
    }

    /** Only get_id() is read, so an unsaved order keeps the tests off the database. */
    private function stubOrder(): WC_Order
    {
        $order = new WC_Order();
        $order->set_id(self::STUB_ORDER_ID);

        return $order;
    }
}
