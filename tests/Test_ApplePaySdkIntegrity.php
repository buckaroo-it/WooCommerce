<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The Apple Pay SDK is loaded from a pinned version with Apple's published
 * Subresource Integrity hash, so the browser refuses a changed file.
 */
class Test_ApplePaySdkIntegrity extends TestCase
{
    private const SDK_URL = 'https://applepay.cdn-apple.com/jsapi/v1.3.8/apple-pay-sdk.js';

    private const SDK_INTEGRITY = 'sha384-u/9mOkmShCO0v+dqCAZFhiutJuORfzvuyM5i+676iy7mLSWS6rlllHrIt15f/mqH';

    protected function tearDown(): void
    {
        wp_deregister_script('apple_pay_sdk');
        wp_deregister_script('buckaroo_test_other');
        parent::tearDown();
    }

    public function test_printed_apple_sdk_tag_carries_integrity_and_crossorigin()
    {
        wp_register_script('apple_pay_sdk', self::SDK_URL, [], null, false);

        $html = $this->printScript('apple_pay_sdk');

        $this->assertStringContainsString('src="' . self::SDK_URL . '"', str_replace("'", '"', $html));
        $this->assertSame(1, substr_count($html, 'integrity="' . self::SDK_INTEGRITY . '"'));
        $this->assertSame(1, substr_count($html, 'crossorigin="anonymous"'));
    }

    public function test_other_scripts_are_left_alone()
    {
        wp_register_script('buckaroo_test_other', 'https://example.com/other.js', [], null, false);

        $this->assertStringNotContainsString('integrity=', $this->printScript('buckaroo_test_other'));
    }

    public function test_tag_that_already_has_integrity_is_not_changed()
    {
        $tag = '<script src="' . self::SDK_URL . '" integrity="sha384-x" id="apple_pay_sdk-js"></script>';

        $this->assertSame($tag, apply_filters('script_loader_tag', $tag, 'apple_pay_sdk', self::SDK_URL));
    }

    private function printScript(string $handle): string
    {
        ob_start();
        wp_scripts()->do_item($handle);

        return (string) ob_get_clean();
    }
}
