<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Express\ExpressSettings;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;
use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayController;
use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;
use Buckaroo\Woocommerce\Gateways\Paypal\PaypalGateway;
use Buckaroo\Woocommerce\Gateways\PaypalExpress\PaypalExpressController;
use PHPUnit\Framework\TestCase;

/**
 * The express settings contract: the placement widget must round-trip through
 * the legacy button_{location} keys so stored settings keep the shape every
 * existing read site expects.
 */
class Test_ExpressSettings extends TestCase
{
    private function subject(array $stored = [])
    {
        return new class($stored) {
            use ExpressSettings;

            public $id = 'buckaroo_test_express';

            public $form_fields = [];

            private $stored;

            public function __construct(array $stored)
            {
                $this->stored = $stored;
            }

            public function get_option_key()
            {
                return 'woocommerce_' . $this->id . '_settings';
            }

            public function get_option($key, $empty_value = null)
            {
                $value = $this->stored[$key] ?? '';

                return ($value === '' && $empty_value !== null) ? $empty_value : $value;
            }

            protected function expressSettingsSpec(): array
            {
                return ['placements' => ['product', 'cart', 'checkout']];
            }

            public function placements(): array
            {
                return $this->expressPlacementsFromStorage();
            }
        };
    }

    public function test_selected_placements_expand_to_legacy_keys()
    {
        $saved = $this->subject()->expandExpressPlacements(['button_pages' => ['product', 'checkout']]);

        $this->assertSame('TRUE', $saved['button_product']);
        $this->assertSame('FALSE', $saved['button_cart']);
        $this->assertSame('TRUE', $saved['button_checkout']);
    }

    public function test_virtual_key_is_never_stored()
    {
        $saved = $this->subject()->expandExpressPlacements(['button_pages' => ['cart']]);

        $this->assertArrayNotHasKey('button_pages', $saved);
    }

    public function test_clearing_every_placement_disables_all_three()
    {
        $saved = $this->subject()->expandExpressPlacements(['button_pages' => []]);

        $this->assertSame(
            ['FALSE', 'FALSE', 'FALSE'],
            [$saved['button_product'], $saved['button_cart'], $saved['button_checkout']]
        );
    }

    public function test_settings_without_the_widget_are_untouched()
    {
        $existing = ['enabled' => 'yes', 'button_product' => 'TRUE'];

        $this->assertSame($existing, $this->subject()->expandExpressPlacements($existing));
    }

    public function test_widget_reads_current_state_from_legacy_keys()
    {
        update_option('woocommerce_buckaroo_test_express_settings', [
            'button_product' => 'TRUE',
            'button_cart' => 'FALSE',
            'button_checkout' => 'TRUE',
        ]);

        $this->assertSame(['product', 'checkout'], $this->subject()->placements());
    }

    /**
     * An install that never saved these keys keeps the old field default of
     * 'TRUE', so the admin screen shows what it showed before this change.
     */
    public function test_absent_keys_fall_back_to_the_previous_field_default()
    {
        update_option('woocommerce_buckaroo_test_express_settings', []);

        $this->assertSame(['product', 'cart', 'checkout'], $this->subject()->placements());
    }

    public function test_round_trip_is_lossless()
    {
        $selected = ['cart', 'checkout'];
        $saved = $this->subject()->expandExpressPlacements(['button_pages' => $selected]);

        update_option('woocommerce_buckaroo_test_express_settings', $saved);

        $this->assertSame($selected, $this->subject()->placements());
    }

    private function paypalCall(string $method, array $args)
    {
        if (! class_exists('WC_Payment_Gateway')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $gateway = (new ReflectionClass(PaypalGateway::class))->newInstanceWithoutConstructor();
        $m = new ReflectionMethod(PaypalGateway::class, $method);
        $m->setAccessible(true);

        return $m->invokeArgs($gateway, $args);
    }

    public function test_paypal_reads_placements_from_the_express_array()
    {
        $this->assertSame(
            ['product', 'checkout'],
            $this->paypalCall('readExpressPlacements', [['express' => ['checkout', 'product']]])
        );
    }

    public function test_paypal_ignores_the_none_sentinel_when_reading()
    {
        $this->assertSame([], $this->paypalCall('readExpressPlacements', [['express' => ['none']]]));
    }

    public function test_paypal_tolerates_a_non_array_express_value()
    {
        $this->assertSame([], $this->paypalCall('readExpressPlacements', [['express' => 'none']]));
    }

    public function test_paypal_writes_the_selected_placements()
    {
        $saved = $this->paypalCall('writeExpressPlacements', [[], ['cart', 'product']]);

        $this->assertSame(['product', 'cart'], $saved['express']);
    }

    public function test_paypal_stores_the_sentinel_when_nothing_is_selected()
    {
        $saved = $this->paypalCall('writeExpressPlacements', [[], []]);

        $this->assertSame(['none'], $saved['express']);
    }

    public function test_paypal_never_writes_button_location_keys()
    {
        $saved = $this->paypalCall('writeExpressPlacements', [[], ['cart']]);

        $this->assertArrayNotHasKey('button_cart', $saved);
    }

    /**
     * Build a gateway's form_fields without running its constructor, then
     * return the keys of its section headings in render order.
     *
     * @return array<string>
     */
    private function sectionHeadings(string $class, string $id): array
    {
        if (! class_exists('WC_Payment_Gateway')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $gateway = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $gateway->id = $id;
        $gateway->init_form_fields();

        $headings = [];
        foreach ($gateway->form_fields as $key => $field) {
            if (($field['type'] ?? '') === 'title') {
                $headings[] = $key;
            }
        }

        return $headings;
    }

    /**
     * Advanced settings has no fields yet, so its heading must not render.
     */
    public function test_empty_sections_do_not_render()
    {
        $this->assertNotContains(
            'express_advanced_title',
            $this->sectionHeadings(ApplepayGateway::class, 'buckaroo_applepay')
        );
    }

    /**
     * The RFC acceptance criterion: all three express pages render the same
     * shared section headings, in the same order.
     *
     * This replaces an earlier placeholder that asserted PayPal's Graphical
     * section was still empty. PayPal now has button style, rounded shape and
     * a preview, so the three pages finally agree.
     */
    public function test_all_three_render_identical_shared_sections()
    {
        $shared = [];

        foreach ([
            [ApplepayGateway::class, 'buckaroo_applepay'],
            [GooglepayGateway::class, 'buckaroo_googlepay'],
            [PaypalGateway::class, 'buckaroo_paypal'],
        ] as [$class, $id]) {
            $shared[$id] = array_values(array_filter(
                $this->sectionHeadings($class, $id),
                function ($key) {
                    return strpos($key, 'express_') === 0;
                }
            ));
        }

        $this->assertSame(
            ['express_method_specific_title', 'express_graphical_title'],
            $shared['buckaroo_applepay']
        );
        $this->assertSame($shared['buckaroo_applepay'], $shared['buckaroo_googlepay']);
        $this->assertSame($shared['buckaroo_applepay'], $shared['buckaroo_paypal']);
    }

    /**
     * PayPal has no "list as payment method" switch: Enable/Disable governs it.
     */
    public function test_paypal_has_no_list_as_payment_method_field()
    {
        $gateway = (new ReflectionClass(PaypalGateway::class))->newInstanceWithoutConstructor();
        $gateway->id = 'buckaroo_paypal';
        $gateway->init_form_fields();

        $this->assertArrayNotHasKey('checkout_method', $gateway->form_fields);
    }

    // --- appearance-preserving defaults ---
    //
    // Every one of these settings replaced a hardcoded value in the rendering
    // code. The default must reproduce that value exactly, or an install that
    // never touches the setting gets a different button after updating, which
    // is the RFC acceptance criterion "no visual change ... unless the merchant
    // changes a setting".

    private function formField(string $class, string $id, string $key): array
    {
        if (! class_exists('WC_Payment_Gateway')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $gateway = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $gateway->id = $id;
        $gateway->init_form_fields();

        $this->assertArrayHasKey($key, $gateway->form_fields, $id . ' is missing ' . $key);

        return $gateway->form_fields[$key];
    }

    /**
     * @return mixed
     */
    private function fieldDefault(string $class, string $id, string $key)
    {
        return $this->formField($class, $id, $key)['default'] ?? null;
    }

    private function fieldOptions(string $class, string $id, string $key): array
    {
        return $this->formField($class, $id, $key)['options'] ?? [];
    }

    /**
     * applepay.js set buttonstyle from 'black'; googlepay.js collapsed anything
     * not 'white' to black; PayPal was passed no style at all, so it drew its
     * own default, gold.
     */
    public function test_button_style_defaults_reproduce_the_previous_rendering()
    {
        $this->assertSame('black', $this->fieldDefault(ApplepayGateway::class, 'buckaroo_applepay', 'button_style'));
        $this->assertSame('black', $this->fieldDefault(GooglepayGateway::class, 'buckaroo_googlepay', 'button_style'));
        $this->assertSame('gold', $this->fieldDefault(PaypalGateway::class, 'buckaroo_paypal', 'button_style'));
    }

    /**
     * The two wallets differ on purpose: applepay.js hardcoded type="plain"
     * while googlepay.js hardcoded buttonType "pay". Making them agree would
     * restyle every existing Google Pay button, so this asserts the difference
     * rather than the values alone.
     */
    public function test_button_type_defaults_differ_between_the_wallets_on_purpose()
    {
        $apple = $this->fieldDefault(ApplepayGateway::class, 'buckaroo_applepay', 'button_type');
        $google = $this->fieldDefault(GooglepayGateway::class, 'buckaroo_googlepay', 'button_type');

        $this->assertSame('plain', $apple);
        $this->assertSame('pay', $google);
        $this->assertNotSame($apple, $google, 'Apple Pay and Google Pay labels must not be aligned');
    }

    /**
     * PayPal received no shape either, so its own default applies: rect.
     */
    public function test_paypal_shape_defaults_to_not_rounded()
    {
        $this->assertSame('FALSE', $this->fieldDefault(PaypalGateway::class, 'buckaroo_paypal', 'button_rounded'));
    }

    /**
     * And the stored Yes/No maps onto PayPal's own shape vocabulary. This
     * calls the mapper the storefront payload uses, so breaking that ternary
     * fails here - the previous version re-declared it inside the test and so
     * asserted nothing.
     */
    public function test_paypal_rounded_maps_to_paypals_shape_vocabulary()
    {
        $this->assertSame('pill', PaypalExpressController::buttonShape('TRUE'));
        $this->assertSame('rect', PaypalExpressController::buttonShape('FALSE'));

        // get_setting_value() returns null for a setting that was never saved.
        $this->assertSame('rect', PaypalExpressController::buttonShape(null));

        $this->assertSame(
            'rect',
            PaypalExpressController::buttonShape(
                $this->fieldDefault(PaypalGateway::class, 'buckaroo_paypal', 'button_rounded')
            ),
            'the gateway default must still render PayPal a rectangle'
        );
    }

    /**
     * PayPal was passed no label either, so it drew the bare mark.
     */
    public function test_paypal_button_type_defaults_to_the_bare_mark()
    {
        $this->assertSame('paypal', $this->fieldDefault(PaypalGateway::class, 'buckaroo_paypal', 'button_type'));
    }

    /**
     * The SDK also accepts installment, subscribe and donate. They are left out
     * on purpose: installment renders as the bare mark unless style.period is
     * set, and the other two name a flow express checkout does not run.
     */
    public function test_paypal_button_type_options_leave_out_the_unusable_ones()
    {
        $options = $this->fieldOptions(PaypalGateway::class, 'buckaroo_paypal', 'button_type');

        $this->assertSame(
            ['paypal', 'checkout', 'buynow', 'pay'],
            array_keys($options)
        );
    }

    /**
     * A merchant configuring all three wallets in one sitting meets the same
     * choice three times, so the mark-only option leads every list. The key
     * differs per provider - Apple and Google call it plain, PayPal calls it
     * paypal - which is why this asserts the position rather than the name.
     *
     * Google is the one that needs guarding: its default is pay, so an edit
     * that reorders by default would quietly push Plain back down the list.
     */
    public function test_plain_is_the_first_button_type_on_every_wallet()
    {
        $first = [
            'buckaroo_applepay' => [ApplepayGateway::class, 'plain'],
            'buckaroo_googlepay' => [GooglepayGateway::class, 'plain'],
            'buckaroo_paypal' => [PaypalGateway::class, 'paypal'],
        ];

        foreach ($first as $id => [$class, $expected]) {
            $keys = array_keys($this->fieldOptions($class, $id, 'button_type'));

            $this->assertSame($expected, $keys[0] ?? null, $id . ' must list the bare mark first');
        }
    }

    /**
     * A graphical setting that never reaches the storefront is worse than no
     * setting at all: the preview renders the merchant's choice and the button
     * ignores it. Apple Pay's button_style sat in exactly that state, so this
     * asserts the payload the button reads actually carries both settings.
     */
    public function test_applepay_shop_information_delivers_the_button_settings()
    {
        $payload = $this->captureShopInformation([
            'merchant_guid' => 'guid',
            'button_style' => 'white',
            'button_type' => 'buy',
        ]);

        $this->assertSame('white', $payload['button_style'] ?? null);
        $this->assertSame('buy', $payload['button_type'] ?? null);
    }

    /**
     * And an install that never saved them gets the values the button used to
     * hardcode.
     */
    public function test_applepay_shop_information_falls_back_to_the_previous_rendering()
    {
        $payload = $this->captureShopInformation([]);

        $this->assertSame('black', $payload['button_style'] ?? null);
        $this->assertSame('plain', $payload['button_type'] ?? null);
    }

    /**
     * Runs the shop-information endpoint against injected settings, without
     * touching the options table. wp_send_json echoes and then dies, so the
     * ajax die handler is swapped for one that throws.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function captureShopInformation(array $settings): array
    {
        if (! function_exists('wp_send_json')) {
            $this->markTestSkipped('WordPress not available');
        }

        $inject = static function () use ($settings) {
            return $settings;
        };
        $die = static function () {
            return static function () {
                throw new RuntimeException('wp_die');
            };
        };

        add_filter('pre_option_woocommerce_buckaroo_applepay_settings', $inject);
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', $die);
        ob_start();

        try {
            ApplepayController::getShopInformation();
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'wp_die') {
                throw $exception;
            }
        } finally {
            $body = (string) ob_get_clean();
            remove_filter('wp_die_ajax_handler', $die);
            remove_filter('wp_doing_ajax', '__return_true');
            remove_filter('pre_option_woocommerce_buckaroo_applepay_settings', $inject);
        }

        $payload = json_decode($body, true);

        $this->assertIsArray($payload, 'shop information did not return JSON: ' . $body);

        return $payload;
    }

    /**
     * The preview renders through generate_{type}_html(), which WooCommerce
     * looks for on the gateway before reaching its own field types. It is the
     * only field here that buffers output and enqueues a script, so this
     * asserts each wallet produces a row carrying the container id the script
     * later looks the element up by.
     */
    public function test_every_wallet_renders_a_preview_container()
    {
        if (! defined('BK_PLUGIN_FILE') || ! class_exists('WC_Payment_Gateway')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $wallets = [
            'buckaroo_applepay' => ApplepayGateway::class,
            'buckaroo_googlepay' => GooglepayGateway::class,
            'buckaroo_paypal' => PaypalGateway::class,
        ];

        foreach ($wallets as $id => $class) {
            $gateway = (new ReflectionClass($class))->newInstanceWithoutConstructor();
            $gateway->id = $id;
            $gateway->init_form_fields();

            $html = $gateway->generate_express_button_preview_html(
                'button_preview',
                $gateway->form_fields['button_preview']
            );

            $this->assertStringContainsString(
                'id="woocommerce_' . $id . '_button_preview_container"',
                $html,
                $id . ' must render the container the preview script looks up'
            );
        }
    }

    /**
     * A method that declares no preview renders nothing, rather than an empty
     * row the merchant would be left wondering about.
     */
    public function test_a_method_without_a_preview_renders_nothing()
    {
        $this->assertSame('', $this->subject()->generate_express_button_preview_html('button_preview', []));
    }

    /**
     * The preview renders no input, so the browser posts nothing for it, but
     * WooCommerce saves every field that is not a title. Without the filter
     * each save stored button_preview as an empty string. This runs a real
     * save, so it proves the filter is wired into saving rather than only that
     * the method works in isolation.
     */
    public function test_a_save_stores_no_preview_value()
    {
        if (! class_exists('WC_Payment_Gateway')) {
            $this->markTestSkipped('WooCommerce not available');
        }

        $wallets = [
            'buckaroo_applepay' => ApplepayGateway::class,
            'buckaroo_googlepay' => GooglepayGateway::class,
            'buckaroo_paypal' => PaypalGateway::class,
        ];

        foreach ($wallets as $id => $class) {
            $gateway = (new ReflectionClass($class))->newInstanceWithoutConstructor();
            $gateway->id = $id;
            $gateway->init_form_fields();

            $optionKey = 'woocommerce_' . $id . '_settings';
            $post = $_POST;
            delete_option($optionKey);
            $_POST = ['woocommerce_' . $id . '_enabled' => '1'];

            try {
                $gateway->process_admin_options();
                $stored = get_option($optionKey, []);
            } finally {
                $_POST = $post;
                delete_option($optionKey);
            }

            $this->assertArrayHasKey('button_style', $stored, $id . ' save did not run');
            $this->assertArrayNotHasKey('button_preview', $stored, $id . ' stored its preview');
        }
    }
}
