<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Transfer\TransferGateway;
use Buckaroo\Woocommerce\Install\Migration\Versions\KeepTransferSendMailOff;
use PHPUnit\Framework\TestCase;

class Test_KeepTransferSendMailOff extends TestCase
{
    private const OPTION_KEY = 'woocommerce_buckaroo_transfer_settings';

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
        $this->assertInstanceOf(
            \Buckaroo\Woocommerce\Install\Migration\Migration::class,
            new KeepTransferSendMailOff()
        );
    }

    public function test_migration_version_is_set()
    {
        $this->assertEquals('4.11.0', (new KeepTransferSendMailOff())->version);
    }

    public function test_sendmail_defaults_to_true_on_fresh_install()
    {
        $gateway = new TransferGateway();

        $this->assertEquals('TRUE', $gateway->get_option('sendmail'));
    }

    public function test_pins_sendmail_off_when_settings_exist_without_sendmail()
    {
        update_option(self::OPTION_KEY, [
            'enabled' => 'yes',
            'mode' => 'live',
        ]);

        (new KeepTransferSendMailOff())->execute();

        $settings = get_option(self::OPTION_KEY);
        $this->assertEquals('FALSE', $settings['sendmail']);
        $this->assertEquals('yes', $settings['enabled']);
        $this->assertEquals('live', $settings['mode']);
    }

    public function test_gateway_reads_pinned_value_after_migration()
    {
        update_option(self::OPTION_KEY, [
            'enabled' => 'yes',
            'mode' => 'live',
        ]);

        (new KeepTransferSendMailOff())->execute();

        $this->assertEquals('FALSE', (new TransferGateway())->get_option('sendmail'));
    }

    public function test_preserves_saved_sendmail_true()
    {
        $original = [
            'enabled' => 'yes',
            'sendmail' => 'TRUE',
        ];
        update_option(self::OPTION_KEY, $original);

        (new KeepTransferSendMailOff())->execute();

        $this->assertEquals($original, get_option(self::OPTION_KEY));
    }

    public function test_preserves_saved_sendmail_false()
    {
        $original = [
            'enabled' => 'yes',
            'sendmail' => 'FALSE',
        ];
        update_option(self::OPTION_KEY, $original);

        (new KeepTransferSendMailOff())->execute();

        $this->assertEquals($original, get_option(self::OPTION_KEY));
    }

    public function test_skips_when_option_does_not_exist()
    {
        (new KeepTransferSendMailOff())->execute();

        $this->assertFalse(get_option(self::OPTION_KEY));
    }
}
