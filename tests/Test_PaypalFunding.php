<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Paypal\PaypalGateway;
use PHPUnit\Framework\TestCase;

/**
 * The PayPal button preview has to suppress the same funding sources the
 * storefront does, or it shows buttons the customer never sees.
 *
 * The storefront list lives inside BuckarooSdk.PayPal.initiate(), which builds
 * its own PayPal script URL and which the plugin cannot read at runtime, so the
 * plugin keeps its own copy. This test is the only thing that notices when the
 * two drift apart.
 *
 * It reaches the network, so it is excluded from the default run. Execute it
 * deliberately: vendor/bin/phpunit --group external-http
 *
 * @group external-http
 */
class Test_PaypalFunding extends TestCase
{
    private const SDK_URL = 'https://checkout.buckaroo.nl/api/buckaroosdk/script';

    private function sdkSource(): string
    {
        $response = wp_remote_get(self::SDK_URL, ['timeout' => 20]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->markTestSkipped('Could not fetch the Buckaroo SDK.');
        }

        return (string) wp_remote_retrieve_body($response);
    }

    private function fundingParam(string $source, string $param): string
    {
        $this->assertSame(
            1,
            preg_match('/[?&]' . preg_quote($param, '/') . '=([^&"\']+)/', $source, $matches),
            $param . ' not found in the SDK; the URL it builds has changed shape'
        );

        return $matches[1];
    }

    public function test_disabled_funding_matches_the_sdk()
    {
        $this->assertSame(
            $this->fundingParam($this->sdkSource(), 'disable-funding'),
            PaypalGateway::PREVIEW_DISABLE_FUNDING,
            'PaypalGateway::PREVIEW_DISABLE_FUNDING has drifted from the SDK'
        );
    }

    public function test_enabled_funding_matches_the_sdk()
    {
        $this->assertSame(
            $this->fundingParam($this->sdkSource(), 'enable-funding'),
            PaypalGateway::PREVIEW_ENABLE_FUNDING,
            'PaypalGateway::PREVIEW_ENABLE_FUNDING has drifted from the SDK'
        );
    }
}
