<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Install\Migration\Versions\MigrateIdealToIdealWero;
use PHPUnit\Framework\TestCase;

// Stub WordPress functions for unit testing
if (! function_exists('get_option')) {
    function get_option(string $key, $default = false)
    {
        global $wp_test_options;

        return $wp_test_options[$key] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $key, $value, $autoload = null): bool
    {
        global $wp_test_options;
        $wp_test_options[$key] = $value;

        return true;
    }
}

class Test_MigrateIdealToIdealWero extends TestCase
{
    private const OPTION_KEY = 'woocommerce_buckaroo_ideal_settings';

    protected function setUp(): void
    {
        parent::setUp();
        global $wp_test_options;
        $wp_test_options = [];
    }

    protected function tearDown(): void
    {
        global $wp_test_options;
        $wp_test_options = [];
        parent::tearDown();
    }

    public function test_migration_implements_interface()
    {
        $migration = new MigrateIdealToIdealWero();
        $this->assertInstanceOf(
            \Buckaroo\Woocommerce\Install\Migration\Migration::class,
            $migration
        );
    }

    public function test_migration_version_is_set()
    {
        $migration = new MigrateIdealToIdealWero();
        $this->assertEquals('4.7.0', $migration->version);
    }

    public function test_migrates_legacy_ideal_title_to_ideal_wero()
    {
        global $wp_test_options;
        $wp_test_options[self::OPTION_KEY] = [
            'title' => 'iDEAL',
            'description' => 'Some custom description',
            'enabled' => 'yes',
        ];

        (new MigrateIdealToIdealWero())->execute();

        $this->assertEquals('iDEAL | Wero', $wp_test_options[self::OPTION_KEY]['title']);
    }

    public function test_preserves_custom_title()
    {
        global $wp_test_options;
        $wp_test_options[self::OPTION_KEY] = [
            'title' => 'Betaal met iDEAL | Wero',
            'description' => 'Custom description',
            'enabled' => 'yes',
        ];

        (new MigrateIdealToIdealWero())->execute();

        $this->assertEquals('Betaal met iDEAL | Wero', $wp_test_options[self::OPTION_KEY]['title']);
    }

    public function test_preserves_ideal_wero_title()
    {
        global $wp_test_options;
        $wp_test_options[self::OPTION_KEY] = [
            'title' => 'iDEAL | Wero',
            'description' => 'Pay with iDEAL | Wero',
            'enabled' => 'yes',
        ];

        (new MigrateIdealToIdealWero())->execute();

        $this->assertEquals('iDEAL | Wero', $wp_test_options[self::OPTION_KEY]['title']);
    }

    public function test_resets_legacy_english_description()
    {
        global $wp_test_options;
        $wp_test_options[self::OPTION_KEY] = [
            'title' => 'iDEAL',
            'description' => 'Pay with iDEAL',
            'enabled' => 'yes',
        ];

        (new MigrateIdealToIdealWero())->execute();

        $this->assertEquals('', $wp_test_options[self::OPTION_KEY]['description']);
    }

    public function test_resets_legacy_dutch_description()
    {
        global $wp_test_options;
        $wp_test_options[self::OPTION_KEY] = [
            'title' => 'iDEAL',
            'description' => 'Betaal met iDEAL',
            'enabled' => 'yes',
        ];

        (new MigrateIdealToIdealWero())->execute();

        $this->assertEquals('', $wp_test_options[self::OPTION_KEY]['description']);
    }

    public function test_resets_legacy_german_description()
    {
        global $wp_test_options;
        $wp_test_options[self::OPTION_KEY] = [
            'title' => 'iDEAL',
            'description' => 'Zahlen mit iDEAL',
            'enabled' => 'yes',
        ];

        (new MigrateIdealToIdealWero())->execute();

        $this->assertEquals('', $wp_test_options[self::OPTION_KEY]['description']);
    }

    public function test_resets_legacy_french_description()
    {
        global $wp_test_options;
        $wp_test_options[self::OPTION_KEY] = [
            'title' => 'iDEAL',
            'description' => 'Payer avec iDEAL',
            'enabled' => 'yes',
        ];

        (new MigrateIdealToIdealWero())->execute();

        $this->assertEquals('', $wp_test_options[self::OPTION_KEY]['description']);
    }

    public function test_preserves_custom_description()
    {
        global $wp_test_options;
        $wp_test_options[self::OPTION_KEY] = [
            'title' => 'iDEAL | Wero',
            'description' => 'My custom payment description',
            'enabled' => 'yes',
        ];

        (new MigrateIdealToIdealWero())->execute();

        $this->assertEquals('My custom payment description', $wp_test_options[self::OPTION_KEY]['description']);
    }

    public function test_preserves_new_format_description()
    {
        global $wp_test_options;
        $wp_test_options[self::OPTION_KEY] = [
            'title' => 'iDEAL | Wero',
            'description' => 'Pay with iDEAL | Wero',
            'enabled' => 'yes',
        ];

        (new MigrateIdealToIdealWero())->execute();

        $this->assertEquals('Pay with iDEAL | Wero', $wp_test_options[self::OPTION_KEY]['description']);
    }

    public function test_skips_when_option_does_not_exist()
    {
        global $wp_test_options;
        // Option not set — get_option returns false

        (new MigrateIdealToIdealWero())->execute();

        $this->assertArrayNotHasKey(self::OPTION_KEY, $wp_test_options);
    }

    public function test_preserves_other_settings()
    {
        global $wp_test_options;
        $wp_test_options[self::OPTION_KEY] = [
            'title' => 'iDEAL',
            'description' => 'Pay with iDEAL',
            'enabled' => 'yes',
            'mode' => 'live',
            'extrachargeamount' => '0',
        ];

        (new MigrateIdealToIdealWero())->execute();

        $settings = $wp_test_options[self::OPTION_KEY];
        $this->assertEquals('yes', $settings['enabled']);
        $this->assertEquals('live', $settings['mode']);
        $this->assertEquals('0', $settings['extrachargeamount']);
    }

    public function test_no_update_when_nothing_to_migrate()
    {
        global $wp_test_options;
        $original = [
            'title' => 'iDEAL | Wero',
            'description' => 'My custom description',
            'enabled' => 'yes',
        ];
        $wp_test_options[self::OPTION_KEY] = $original;

        (new MigrateIdealToIdealWero())->execute();

        // Settings should be unchanged (no update_option call)
        $this->assertEquals($original, $wp_test_options[self::OPTION_KEY]);
    }
}
