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
use Buckaroo\Woocommerce\Gateways\KnakenSettle\KnakenSettleGateway;
use Buckaroo\Woocommerce\Gateways\MbWay\MbWayGateway;
use Buckaroo\Woocommerce\Gateways\Multibanco\MultibancoGateway;
use Buckaroo\Woocommerce\Gateways\PayByBank\PayByBankGateway;
use Buckaroo\Woocommerce\Gateways\Payconiq\PayconiqGateway;
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
            'Payconiq' => [PayconiqGateway::class],
            'Bizum' => [BizumGateway::class],
            'Blik' => [BlikGateway::class],
            'MB WAY' => [MbWayGateway::class],
            'Multibanco' => [MultibancoGateway::class],
            'Swish' => [SwishGateway::class],
            'TWINT' => [TwintGateway::class],
            'PayByBank' => [PayByBankGateway::class],
            'Giftcards' => [GiftCardGateway::class],
            'Knaken Settle' => [KnakenSettleGateway::class],
            'In3' => [In3Gateway::class],
            'Klarna Pay' => [KlarnaPayGateway::class],
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
            'Billink' => [BillinkGateway::class],
            'Riverty' => [AfterpayNewGateway::class],
            'Afterpay' => [AfterpayOldGateway::class],
            'Klarna KP' => [KlarnaKpGateway::class],
            'Apple Pay' => [ApplepayGateway::class],
            'Google Pay' => [GooglepayGateway::class],
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
