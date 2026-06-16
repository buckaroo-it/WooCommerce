<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Install\Migration\Versions\MigrateIdealToIdealWero;
use PHPUnit\Framework\TestCase;

class Test_MigrateIdealToIdealWero extends TestCase
{
    private const OPTION_KEY = 'woocommerce_buckaroo_ideal_settings';

    protected function setUp(): void
    {
        parent::setUp();
        delete_option(self::OPTION_KEY);
    }

    protected function tearDown(): void
    {
        delete_option(self::OPTION_KEY);
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
        update_option(self::OPTION_KEY, [
            'title' => 'iDEAL',
            'description' => 'Some custom description',
            'enabled' => 'yes',
        ]);

        (new MigrateIdealToIdealWero())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('iDEAL | Wero', $settings['title']);
    }

    public function test_preserves_custom_title()
    {
        update_option(self::OPTION_KEY, [
            'title' => 'Betaal met iDEAL | Wero',
            'description' => 'Custom description',
            'enabled' => 'yes',
        ]);

        (new MigrateIdealToIdealWero())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('Betaal met iDEAL | Wero', $settings['title']);
    }

    public function test_preserves_ideal_wero_title()
    {
        update_option(self::OPTION_KEY, [
            'title' => 'iDEAL | Wero',
            'description' => 'Pay with iDEAL | Wero',
            'enabled' => 'yes',
        ]);

        (new MigrateIdealToIdealWero())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('iDEAL | Wero', $settings['title']);
    }

    public function test_resets_legacy_english_description()
    {
        update_option(self::OPTION_KEY, [
            'title' => 'iDEAL',
            'description' => 'Pay with iDEAL',
            'enabled' => 'yes',
        ]);

        (new MigrateIdealToIdealWero())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('', $settings['description']);
    }

    public function test_resets_legacy_dutch_description()
    {
        update_option(self::OPTION_KEY, [
            'title' => 'iDEAL',
            'description' => 'Betaal met iDEAL',
            'enabled' => 'yes',
        ]);

        (new MigrateIdealToIdealWero())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('', $settings['description']);
    }

    public function test_resets_legacy_german_description()
    {
        update_option(self::OPTION_KEY, [
            'title' => 'iDEAL',
            'description' => 'Zahlen mit iDEAL',
            'enabled' => 'yes',
        ]);

        (new MigrateIdealToIdealWero())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('', $settings['description']);
    }

    public function test_resets_legacy_french_description()
    {
        update_option(self::OPTION_KEY, [
            'title' => 'iDEAL',
            'description' => 'Payer avec iDEAL',
            'enabled' => 'yes',
        ]);

        (new MigrateIdealToIdealWero())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('', $settings['description']);
    }

    public function test_preserves_custom_description()
    {
        update_option(self::OPTION_KEY, [
            'title' => 'iDEAL | Wero',
            'description' => 'My custom payment description',
            'enabled' => 'yes',
        ]);

        (new MigrateIdealToIdealWero())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('My custom payment description', $settings['description']);
    }

    public function test_preserves_new_format_description()
    {
        update_option(self::OPTION_KEY, [
            'title' => 'iDEAL | Wero',
            'description' => 'Pay with iDEAL | Wero',
            'enabled' => 'yes',
        ]);

        (new MigrateIdealToIdealWero())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('Pay with iDEAL | Wero', $settings['description']);
    }

    public function test_skips_when_option_does_not_exist()
    {
        (new MigrateIdealToIdealWero())->execute();

        $this->assertFalse(get_option(self::OPTION_KEY));
    }

    public function test_preserves_other_settings()
    {
        update_option(self::OPTION_KEY, [
            'title' => 'iDEAL',
            'description' => 'Pay with iDEAL',
            'enabled' => 'yes',
            'mode' => 'live',
            'extrachargeamount' => '0',
        ]);

        (new MigrateIdealToIdealWero())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('yes', $settings['enabled']);
        $this->assertEquals('live', $settings['mode']);
        $this->assertEquals('0', $settings['extrachargeamount']);
    }

    public function test_no_update_when_nothing_to_migrate()
    {
        $original = [
            'title' => 'iDEAL | Wero',
            'description' => 'My custom description',
            'enabled' => 'yes',
        ];
        update_option(self::OPTION_KEY, $original);

        (new MigrateIdealToIdealWero())->execute();

        $this->assertEquals($original, get_option(self::OPTION_KEY));
    }
}
