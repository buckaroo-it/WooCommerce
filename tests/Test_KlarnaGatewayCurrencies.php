<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaKpGateway;
use Buckaroo\Woocommerce\Gateways\Klarna\KlarnaPayGateway;
use PHPUnit\Framework\TestCase;

/**
 * Klarna (MoR) settles in the local currency of the shopper's market, so it
 * supports more than the EUR default. Klarna Pay later stays EUR only, which is
 * the part of this that is easiest to break: both gateways share the
 * KlarnaGateway base.
 */
class Test_KlarnaGatewayCurrencies extends TestCase
{
    /** @var string */
    private $originalCurrency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCurrency = (string) get_option('woocommerce_currency');

        update_option('woocommerce_buckaroo_mastersettings_settings', [
            'culture' => 'en-US',
            'merchantkey' => 'test-merchant',
            'secretkey' => 'test-secret',
        ]);
    }

    protected function tearDown(): void
    {
        update_option('woocommerce_currency', $this->originalCurrency);

        parent::tearDown();
    }

    public function test_klarna_mor_supports_the_nordic_and_european_currencies()
    {
        $currencies = (new KlarnaPayGateway())->getSupportedCurrencies();

        $this->assertSame(
            ['EUR', 'CHF', 'DKK', 'GBP', 'NOK', 'PLN', 'SEK'],
            $currencies
        );
    }

    public function test_klarna_mor_supports_poland()
    {
        $countries = (new KlarnaPayGateway())->getSupportedCountries();

        $this->assertContains('PL', $countries);
        $this->assertSame(
            ['DE', 'AT', 'SE', 'NO', 'FI', 'DK', 'NL', 'CH', 'GB', 'BE', 'PL'],
            $countries
        );
    }

    /**
     * @dataProvider supportedCurrencyProvider
     */
    public function test_klarna_mor_is_available_for_each_supported_currency(string $currency)
    {
        update_option('woocommerce_currency', $currency);

        $this->assertTrue(
            (new KlarnaPayGateway())->checkCurrencySupported(),
            "Klarna (MoR) should accept $currency"
        );
    }

    /** @return array<string, array{string}> */
    public function supportedCurrencyProvider(): array
    {
        return [
            'EUR' => ['EUR'],
            'CHF' => ['CHF'],
            'DKK' => ['DKK'],
            'GBP' => ['GBP'],
            'NOK' => ['NOK'],
            'PLN' => ['PLN'],
            'SEK' => ['SEK'],
        ];
    }

    public function test_klarna_mor_rejects_a_currency_outside_the_list()
    {
        update_option('woocommerce_currency', 'USD');

        $this->assertFalse((new KlarnaPayGateway())->checkCurrencySupported());
    }

    /**
     * Seven currencies is over the threshold where the admin summarises the list
     * instead of printing every code.
     */
    public function test_klarna_mor_is_labelled_multi_currency()
    {
        $this->assertSame('Multi-currency', (new KlarnaPayGateway())->getCurrencyLabel());
    }

    public function test_klarna_pay_later_stays_euro_only()
    {
        $gateway = new KlarnaKpGateway();

        $this->assertSame(['EUR'], $gateway->getSupportedCurrencies());
        $this->assertNull($gateway->getCurrencyLabel());
    }

    public function test_klarna_pay_later_does_not_inherit_poland()
    {
        $countries = (new KlarnaKpGateway())->getSupportedCountries();

        $this->assertNotContains('PL', $countries);
        $this->assertSame(
            ['DE', 'AT', 'SE', 'NO', 'FI', 'DK', 'NL', 'CH', 'GB', 'BE'],
            $countries
        );
    }

    public function test_klarna_pay_later_is_hidden_for_a_currency_klarna_mor_accepts()
    {
        update_option('woocommerce_currency', 'SEK');

        $this->assertFalse((new KlarnaKpGateway())->checkCurrencySupported());
    }
}
