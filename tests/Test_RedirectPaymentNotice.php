<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\AbstractPaymentGateway;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayNewGateway;
use Buckaroo\Woocommerce\Gateways\Afterpay\AfterpayOldGateway;
use Buckaroo\Woocommerce\Gateways\Alipay\AlipayGateway;
use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;
use Buckaroo\Woocommerce\Gateways\Bancontact\BancontactGateway;
use Buckaroo\Woocommerce\Gateways\Belfius\BelfiusGateway;
use Buckaroo\Woocommerce\Gateways\Billink\BillinkGateway;
use Buckaroo\Woocommerce\Gateways\Bizum\BizumGateway;
use Buckaroo\Woocommerce\Gateways\Blik\BlikGateway;
use Buckaroo\Woocommerce\Gateways\CreditCard\Cards\VisaGateway;
use Buckaroo\Woocommerce\Gateways\CreditCard\CreditCardGateway;
use Buckaroo\Woocommerce\Gateways\Eps\EpsGateway;
use Buckaroo\Woocommerce\Gateways\GiftCard\GiftCardGateway;
use Buckaroo\Woocommerce\Gateways\Googlepay\GooglepayGateway;
use Buckaroo\Woocommerce\Gateways\Ideal\IdealGateway;
use Buckaroo\Woocommerce\Gateways\In3\In3Gateway;
use Buckaroo\Woocommerce\Gateways\Kbc\KbcGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaKpGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use Buckaroo\Woocommerce\Gateways\MbWay\MbWayGateway;
use Buckaroo\Woocommerce\Gateways\Multibanco\MultibancoGateway;
use Buckaroo\Woocommerce\Gateways\PayByBank\PayByBankGateway;
use Buckaroo\Woocommerce\Gateways\Paypal\PaypalGateway;
use Buckaroo\Woocommerce\Gateways\PayPerEmail\PayPerEmailGateway;
use Buckaroo\Woocommerce\Gateways\Przelewy24\Przelewy24Gateway;
use Buckaroo\Woocommerce\Gateways\SepaDirectDebit\SepaDirectDebitGateway;
use Buckaroo\Woocommerce\Gateways\Swish\SwishGateway;
use Buckaroo\Woocommerce\Gateways\Transfer\TransferGateway;
use Buckaroo\Woocommerce\Gateways\Trustly\TrustlyGateway;
use Buckaroo\Woocommerce\Gateways\Twint\TwintGateway;
use Buckaroo\Woocommerce\Gateways\WeChatPay\WeChatPayGateway;
use Buckaroo\Woocommerce\Gateways\Wero\WeroGateway;
use Buckaroo\Woocommerce\Hooks\InitGateways;
use PHPUnit\Framework\TestCase;

/**
 * Test the checkout redirect notice
 *
 * Gateways are built without their constructor: the flag does not depend on
 * constructor state, and the real constructors register WooCommerce hooks.
 */
class Test_RedirectPaymentNotice extends TestCase
{
    private const NOTICE_TEXT = 'After submission, you will be redirected to securely complete your payment.';

    /**
     * Methods that send the customer to an external page to finish paying.
     *
     * @return array<string, array{class-string}>
     */
    public function redirectBasedGateways(): array
    {
        return [
            'iDEAL | Wero' => [IdealGateway::class],
            'Wero' => [WeroGateway::class],
            'Bancontact' => [BancontactGateway::class],
            'Belfius' => [BelfiusGateway::class],
            'KBC' => [KbcGateway::class],
            'EPS' => [EpsGateway::class],
            'PayPal' => [PaypalGateway::class],
            'Przelewy24' => [Przelewy24Gateway::class],
            'Trustly' => [TrustlyGateway::class],
            'Alipay' => [AlipayGateway::class],
            'WeChat Pay' => [WeChatPayGateway::class],
            'Bizum' => [BizumGateway::class],
            'Blik' => [BlikGateway::class],
            'MB WAY' => [MbWayGateway::class],
            'Multibanco' => [MultibancoGateway::class],
            'Swish' => [SwishGateway::class],
            'TWINT' => [TwintGateway::class],
            'PayByBank' => [PayByBankGateway::class],
            'Giftcards' => [GiftCardGateway::class],
            'In3' => [In3Gateway::class],
            'Klarna Pay' => [KlarnaPayGateway::class],
            'Klarna KP' => [KlarnaKpGateway::class],
            'Riverty' => [AfterpayNewGateway::class],
            'Riverty Old' => [AfterpayOldGateway::class],
            'Apple Pay' => [ApplepayGateway::class],
            'Google Pay' => [GooglepayGateway::class],
            // Billink One is a checkout hosted by Billink.
            'Billink' => [BillinkGateway::class],
        ];
    }

    /**
     * Methods that complete inside the checkout.
     *
     * @return array<string, array{class-string}>
     */
    public function nonRedirectGateways(): array
    {
        return [
            'SEPA Direct Debit' => [SepaDirectDebitGateway::class],
            'Bank Transfer' => [TransferGateway::class],
            'PayPerEmail' => [PayPerEmailGateway::class],
        ];
    }

    /**
     * @dataProvider redirectBasedGateways
     */
    public function test_redirect_based_gateway_declares_redirect(string $gatewayClass)
    {
        $this->assertTrue($this->makeGateway($gatewayClass)->redirectsToPaymentPage());
    }

    /**
     * @dataProvider nonRedirectGateways
     */
    public function test_non_redirect_gateway_declares_no_redirect(string $gatewayClass)
    {
        $this->assertFalse($this->makeGateway($gatewayClass)->redirectsToPaymentPage());
    }

    /**
     * @dataProvider redirectBasedGateways
     */
    public function test_redirect_based_gateway_renders_the_notice(string $gatewayClass)
    {
        $notice = $this->makeGateway($gatewayClass)->getRedirectNoticeHtml();

        $this->assertStringContainsString('class="buckaroo-redirect-notice"', $notice);
        $this->assertStringContainsString(self::NOTICE_TEXT, $notice);
    }

    /**
     * @dataProvider nonRedirectGateways
     */
    public function test_non_redirect_gateway_renders_no_notice(string $gatewayClass)
    {
        $this->assertSame('', $this->makeGateway($gatewayClass)->getRedirectNoticeHtml());
    }

    /**
     * The classic checkout renders the notice through wp_kses_post().
     */
    public function test_notice_survives_kses()
    {
        if (! function_exists('wp_kses_post')) {
            $this->markTestSkipped('WordPress not available');
        }

        $notice = $this->makeGateway(IdealGateway::class)->getRedirectNoticeHtml();

        $this->assertSame($notice, wp_kses_post($notice));
    }

    public function test_merchant_can_disable_redirect_notice_globally(): void
    {
        $option = 'woocommerce_buckaroo_mastersettings_settings';
        $original = get_option($option, null);

        try {
            update_option($option, array_merge((array) $original, ['show_redirect_notice' => 'no']));
            $this->assertSame('no', get_option($option)['show_redirect_notice'], 'Stored notice setting');
            $gateway = new IdealGateway();
            $this->assertSame('no', $gateway->settings['show_redirect_notice'], 'Gateway notice setting');
            $this->assertSame('', $gateway->getRedirectNoticeHtml());

            ob_start();
            $gateway->payment_fields();
            $this->assertStringNotContainsString('buckaroo-redirect-notice', (string) ob_get_clean());

            $gateway->enabled = 'yes';
            $paymentGateways = WC()->payment_gateways();
            $previousGateways = $paymentGateways->payment_gateways;
            $paymentGateways->payment_gateways = [$gateway->id => $gateway];
            try {
                $blocksData = (new InitGateways())->initGatewaysOnCheckout();
                $this->assertSame('', $blocksData[0]['redirectNotice']);
            } finally {
                $paymentGateways->payment_gateways = $previousGateways;
            }
        } finally {
            if ($original === null) {
                delete_option($option);
            } else {
                update_option($option, $original);
            }
        }
    }

    public function test_developer_can_replace_redirect_notice_text_safely(): void
    {
        $filter = static function () {
            return '<script>unsafe</script>Pay securely';
        };
        add_filter('buckaroo_checkout_redirect_notice_text', $filter);

        try {
            $notice = $this->makeGateway(IdealGateway::class)->getRedirectNoticeHtml();
            $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;Pay securely', $notice);
            $this->assertStringNotContainsString('<script>', $notice);
        } finally {
            remove_filter('buckaroo_checkout_redirect_notice_text', $filter);
        }
    }

    public function test_empty_filtered_text_removes_the_notice_element(): void
    {
        $filter = static function () {
            return '';
        };
        add_filter('buckaroo_checkout_redirect_notice_text', $filter);

        try {
            $this->assertSame('', $this->makeGateway(IdealGateway::class)->getRedirectNoticeHtml());
        } finally {
            remove_filter('buckaroo_checkout_redirect_notice_text', $filter);
        }
    }

    public function test_filter_receives_translated_text_and_gateway(): void
    {
        $gateway = $this->makeGateway(IdealGateway::class);
        $received = [];
        $filter = static function ($text, $filteredGateway) use (&$received) {
            $received = [$text, $filteredGateway];
            return $text;
        };
        add_filter('buckaroo_checkout_redirect_notice_text', $filter, 10, 2);

        try {
            $gateway->getRedirectNoticeHtml();
            $this->assertSame(self::NOTICE_TEXT, $received[0]);
            $this->assertSame($gateway, $received[1]);
        } finally {
            remove_filter('buckaroo_checkout_redirect_notice_text', $filter);
        }
    }

    /** @dataProvider noticeTranslations */
    public function test_notice_catalog_has_the_site_language(string $locale, string $translation): void
    {
        $domain = 'wc-buckaroo-bpe-gateway';
        $catalog = dirname(__DIR__) . '/languages/' . $domain . '-' . $locale . '.mo';

        $this->assertFileExists($catalog);
        $messages = new MO();
        $this->assertTrue($messages->import_from_file($catalog));
        $this->assertSame($translation, $messages->translate(self::NOTICE_TEXT));
    }

    /**
     * @dataProvider noticeTranslations
     */
    public function test_wordpress_renders_notice_in_site_language(string $locale, string $translation): void
    {
        $this->withNoticeLocale($locale, function () use ($translation) {
            $this->assertStringContainsString(
                $translation,
                $this->makeGateway(IdealGateway::class)->getRedirectNoticeHtml()
            );
        });
    }

    /** @dataProvider installedNoticeTranslations */
    public function test_installed_language_pack_notice(string $format, string $locale, ?string $translation, string $expected): void
    {
        $domain = 'wc-buckaroo-bpe-gateway';
        $directory = WP_LANG_DIR . '/plugins';
        wp_mkdir_p($directory);
        $base = $directory . '/' . $domain . '-' . $locale;
        $backups = [];
        foreach (['.mo', '.l10n.php'] as $extension) {
            $file = $base . $extension;
            $backups[$file] = file_exists($file) ? file_get_contents($file) : null;
            if (file_exists($file)) {
                unlink($file);
            }
        }

        try {
            $messages = ['Payment method' => 'Aangepaste betaalmethode'];
            if ($translation !== null) {
                $messages[self::NOTICE_TEXT] = $translation;
            }
            if ($format === 'mo') {
                $catalog = new MO();
                $catalog->set_header('Content-Type', 'text/plain; charset=UTF-8');
                foreach ($messages as $source => $target) {
                    $catalog->add_entry(new Translation_Entry(['singular' => $source, 'translations' => [$target]]));
                }
                $this->assertTrue($catalog->export_to_file($base . '.mo'));
            } else {
                file_put_contents($base . '.l10n.php', '<?php return ' . var_export([
                    'language' => $locale,
                    'messages' => $messages,
                ], true) . ';');
            }
            wp_cache_delete(md5($directory . '/'), 'translation_files');
            $this->withNoticeLocale($locale, function () use ($domain, $expected) {
                $this->assertSame('Aangepaste betaalmethode', __('Payment method', $domain));
                $this->assertStringContainsString(
                    $expected,
                    $this->makeGateway(IdealGateway::class)->getRedirectNoticeHtml()
                );
            });
        } finally {
            foreach ($backups as $file => $contents) {
                if ($contents !== null) {
                    file_put_contents($file, $contents);
                } elseif (file_exists($file)) {
                    unlink($file);
                }
            }
            wp_cache_delete(md5($directory . '/'), 'translation_files');
        }
    }

    public function installedNoticeTranslations(): array
    {
        $cases = [];
        foreach (['mo', 'php'] as $format) {
            foreach ($this->noticeTranslations() as [$locale, $translation]) {
                $cases["stale $format pack, $locale"] = [$format, $locale, null, $translation];
            }
            $cases["custom $format translation"] = [$format, 'nl_NL', 'Uw aangepaste betaalbericht.', 'Uw aangepaste betaalbericht.'];
        }

        return $cases;
    }

    private function withNoticeLocale(string $locale, callable $assertions): void
    {
        $domain = 'wc-buckaroo-bpe-gateway';
        $originalLocale = determine_locale();
        $originalRegistry = $GLOBALS['wp_textdomain_registry'];
        $siteLocale = static function () use ($locale) {
            return $locale;
        };
        unload_textdomain($domain, true);
        WP_Translation_Controller::get_instance()->unload_textdomain($domain);
        $GLOBALS['wp_textdomain_registry'] = new WP_Textdomain_Registry();
        $GLOBALS['wp_textdomain_registry']->set_custom_path($domain, dirname(__DIR__) . '/languages');
        add_filter('pre_determine_locale', $siteLocale);

        try {
            do_action('change_locale', $locale);
            $assertions();
        } finally {
            unload_textdomain($domain, true);
            WP_Translation_Controller::get_instance()->unload_textdomain($domain);
            remove_filter('pre_determine_locale', $siteLocale);
            WP_Translation_Controller::get_instance()->set_locale($originalLocale);
            $GLOBALS['wp_textdomain_registry'] = $originalRegistry;
        }
    }

    public function test_notice_follows_wordpress_locale_switches(): void
    {
        $languages = static function () {
            return ['nl_NL', 'nl_NL_formal', 'de_DE'];
        };
        add_filter('get_available_languages', $languages);
        $switcher = new WP_Locale_Switcher();
        $switcher->init();
        $gateway = $this->makeGateway(IdealGateway::class);

        try {
            foreach (['nl_NL', 'nl_NL_formal', 'de_DE'] as $locale) {
                $this->assertTrue($switcher->switch_to_locale($locale));
                $expected = array_column($this->noticeTranslations(), 1, 0)[$locale];
                $this->assertStringContainsString($expected, $gateway->getRedirectNoticeHtml());
            }
            $switcher->restore_current_locale();
            $this->assertStringContainsString(self::NOTICE_TEXT, $gateway->getRedirectNoticeHtml());
        } finally {
            $switcher->restore_current_locale();
            remove_filter('locale', [$switcher, 'filter_locale']);
            remove_filter('determine_locale', [$switcher, 'filter_locale']);
            remove_filter('get_available_languages', $languages);
        }
    }

    public function noticeTranslations(): array
    {
        return [
            ['nl_NL', 'Na het plaatsen van je bestelling word je veilig doorgestuurd om je betaling af te ronden.'],
            ['nl_NL_formal', 'Na het plaatsen van uw bestelling wordt u veilig doorgestuurd om uw betaling af te ronden.'],
            ['nl_BE', 'Na het plaatsen van je bestelling word je veilig doorgestuurd om je betaling af te ronden.'],
            ['de_DE', 'Nach der Übermittlung werden Sie sicher weitergeleitet, um Ihre Zahlung abzuschließen.'],
            ['de_AT', 'Nach der Übermittlung werden Sie sicher weitergeleitet, um Ihre Zahlung abzuschließen.'],
            ['fr_FR', 'Après validation, vous serez redirigé en toute sécurité pour finaliser votre paiement.'],
            ['fr_BE', 'Après validation, vous serez redirigé en toute sécurité pour finaliser votre paiement.'],
        ];
    }

    public function test_credit_card_redirects_unless_inline_encryption_over_https()
    {
        $cases = [
            ['creditcardmethod' => 'encrypt', 'secure' => true, 'redirects' => false],
            ['creditcardmethod' => 'encrypt', 'secure' => false, 'redirects' => true],
            ['creditcardmethod' => 'redirect', 'secure' => true, 'redirects' => true],
            ['creditcardmethod' => 'redirect', 'secure' => false, 'redirects' => true],
        ];

        foreach ($cases as $case) {
            $gateway = $this->createPartialMock(CreditCardGateway::class, ['get_option', 'isSecure']);
            $gateway->method('get_option')->willReturn($case['creditcardmethod']);
            $gateway->method('isSecure')->willReturn($case['secure']);

            $this->assertSame(
                $case['redirects'],
                $gateway->redirectsToPaymentPage(),
                sprintf(
                    'creditcardmethod=%s, secure=%s',
                    $case['creditcardmethod'],
                    $case['secure'] ? 'yes' : 'no'
                )
            );
        }
    }

    /**
     * The per-card gateways must not fall back to the redirect-by-default.
     */
    public function test_single_card_gateways_inherit_the_credit_card_rule()
    {
        $reflection = new ReflectionClass(VisaGateway::class);

        $this->assertTrue($reflection->isSubclassOf(CreditCardGateway::class));
        $this->assertSame(
            CreditCardGateway::class,
            $reflection->getMethod('redirectsToPaymentPage')->getDeclaringClass()->getName()
        );
    }

    /**
     * @dataProvider stockDescriptions
     */
    public function test_stock_description_is_replaced_by_the_notice(
        string $storedDescription,
        string $label = 'iDEAL | Wero'
    ) {
        $this->assertFalse(
            $this->makeIdealWithDescription($storedDescription, $label)->shouldShowPaymentDescription()
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public function stockDescriptions(): array
    {
        return [
            'never saved' => [''],
            'whitespace only' => ['   '],
            'stored default' => ['Pay with iDEAL | Wero'],
            'stored default, renamed label' => ['Pay with Online banking', 'Online banking'],
        ];
    }

    /**
     * A payment fee suffixes $this->title, but not the stored default.
     */
    public function test_stock_description_is_recognised_when_a_payment_fee_is_configured()
    {
        $gateway = $this->makeIdealWithDescription(
            'Pay with iDEAL | Wero',
            'iDEAL | Wero',
            'iDEAL | Wero (+ 1,50)'
        );

        $this->assertFalse($gateway->shouldShowPaymentDescription());
    }

    /**
     * @dataProvider customDescriptions
     */
    public function test_custom_description_is_kept(string $storedDescription)
    {
        $gateway = $this->makeIdealWithDescription($storedDescription);

        $this->assertTrue($gateway->shouldShowPaymentDescription());
        // The notice is still rendered next to it.
        $this->assertStringContainsString(self::NOTICE_TEXT, $gateway->getRedirectNoticeHtml());
    }

    /**
     * @return array<string, array{string}>
     */
    public function customDescriptions(): array
    {
        return [
            'plain sentence' => ['Betaal veilig met iDEAL bij ons.'],
            'marketing copy' => ['The fastest way to pay — no account needed!'],
            'html' => ['<strong>iDEAL</strong> is free of charge'],
            'similar but not the template' => ['Pay quickly with iDEAL'],
            'starts like the stock text' => ['Pay with iDEAL, no extra fees'],
            'stock text plus a sentence' => ['Pay with iDEAL | Wero. Fast and secure.'],
            'regex metacharacters' => ['Pay with iDEAL (a.*b) [x]'],
        ];
    }

    /**
     * Non-redirect methods keep whatever is configured, stock text included.
     */
    public function test_non_redirect_gateway_always_shows_its_description()
    {
        $gateway = $this->createPartialMock(SepaDirectDebitGateway::class, ['get_option']);
        $gateway->method('get_option')->willReturn('Pay with SEPA Direct Debit');

        $this->assertTrue($gateway->shouldShowPaymentDescription());
    }

    /**
     * New gateways get the notice without having to opt in.
     */
    public function test_redirect_is_the_default_for_the_base_gateway()
    {
        $method = (new ReflectionClass(AbstractPaymentGateway::class))->getMethod('redirectsToPaymentPage');

        $this->assertTrue($method->isPublic());
        $this->assertTrue(
            (new ReflectionClass(AbstractPaymentGateway::class))
                ->newInstanceWithoutConstructor()
                ->redirectsToPaymentPage()
        );
    }

    private function makeGateway(string $gatewayClass): AbstractPaymentGateway
    {
        return (new ReflectionClass($gatewayClass))->newInstanceWithoutConstructor();
    }

    /**
     * iDEAL (redirect-based) with a given stored `description` option.
     *
     * @param  string  $label  the configured front-end label
     * @param  string|null  $titleWithFee  $this->title when a payment fee is configured
     */
    private function makeIdealWithDescription(
        string $storedDescription,
        string $label = 'iDEAL | Wero',
        ?string $titleWithFee = null
    ): IdealGateway {
        $gateway = $this->createPartialMock(IdealGateway::class, ['get_option']);
        $gateway->method('get_option')->willReturnCallback(
            function ($key, $default = null) use ($storedDescription, $label) {
                if ($key === 'description') {
                    return $storedDescription;
                }

                if ($key === 'title') {
                    return $label;
                }

                return $default;
            }
        );
        $gateway->title = $titleWithFee ?? $label;

        return $gateway;
    }
}
