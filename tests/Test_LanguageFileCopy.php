<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Install\Migration\MigrationHandler;
use PHPUnit\Framework\TestCase;

/**
 * Bundled translation files are copied to wp-content/languages/plugins.
 */
class Test_LanguageFileCopy extends TestCase
{
    /** @var string */
    private $dest;

    /** @var string[] */
    private $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dest = WP_CONTENT_DIR . '/languages/plugins/';
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_po_and_mo_files_are_copied(): void
    {
        $source   = dirname(BK_PLUGIN_FILE) . '/languages/';
        $expected = array_merge(glob($source . '*.po') ?: [], glob($source . '*.mo') ?: []);

        $this->assertNotEmpty(glob($source . '*.po'), 'Plugin ships .po files');
        $this->assertNotEmpty(glob($source . '*.mo'), 'Plugin ships .mo files');

        $this->runCopy();

        foreach ($expected as $file) {
            $target = $this->dest . basename($file);
            $this->assertFileExists($target);
            $this->assertSame(md5_file($file), md5_file($target));
        }
    }

    public function test_other_files_are_not_copied(): void
    {
        $source = dirname(BK_PLUGIN_FILE) . '/languages/';
        $others = array_filter(
            glob($source . '*') ?: [],
            function ($file) {
                return ! in_array(pathinfo($file, PATHINFO_EXTENSION), ['po', 'mo'], true);
            }
        );

        $this->assertNotEmpty($others, 'Plugin ships non-.po/.mo language files');

        $missingBefore = [];
        foreach ($others as $file) {
            $target = $this->dest . basename($file);
            if (! file_exists($target)) {
                $missingBefore[] = $target;
            }
        }

        $this->runCopy();

        foreach ($missingBefore as $target) {
            $this->assertFileDoesNotExist($target);
        }
    }

    private function runCopy(): void
    {
        $source = dirname(BK_PLUGIN_FILE) . '/languages/';
        foreach (array_merge(glob($source . '*.po') ?: [], glob($source . '*.mo') ?: []) as $file) {
            $target = $this->dest . basename($file);
            if (! file_exists($target)) {
                $this->created[] = $target;
            }
        }

        $reflection = new ReflectionClass(MigrationHandler::class);
        $handler    = $reflection->newInstanceWithoutConstructor();
        $method     = $reflection->getMethod('copy_language_files');
        $method->setAccessible(true);
        $method->invoke($handler);
    }
}
