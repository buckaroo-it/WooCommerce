<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Gateways\Applepay\ApplepayGateway;

/**
 * Apple Pay domain association file set up in the site root.
 */
class Test_ApplepayDomainFile extends WP_UnitTestCase
{
    /** @var array[] Messages written to the WooCommerce log */
    private $logged = [];

    /** @var string */
    private $directory;

    /** @var string|null Where an existing .well-known path is kept during the test */
    private $movedPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = ABSPATH . '.well-known';

        if (file_exists($this->directory)) {
            $this->movedPath = $this->directory . '-kept-' . wp_generate_password(8, false);
            rename($this->directory, $this->movedPath);
        }

        add_filter('woocommerce_logger_log_message', function ($message, $level, $context) {
            $this->logged[] = ['message' => $message, 'level' => $level, 'source' => $context['source'] ?? ''];

            return $message;
        }, 10, 3);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            wp_delete_file($this->directory . '/apple-developer-merchantid-domain-association');
            rmdir($this->directory);
        } elseif (file_exists($this->directory)) {
            wp_delete_file($this->directory);
        }

        if ($this->movedPath !== null) {
            rename($this->movedPath, $this->directory);
        }

        parent::tearDown();
    }

    public function test_domain_association_file_is_copied_to_the_site_root(): void
    {
        (new ApplepayGateway())->handleHooks();

        $this->assertFileExists($this->directory . '/apple-developer-merchantid-domain-association');
        $this->assertSame([], $this->buckarooErrors());
    }

    public function test_failure_to_create_the_directory_is_written_to_the_woocommerce_log(): void
    {
        // A file in place of the directory makes creating the directory fail.
        file_put_contents($this->directory, '');

        (new ApplepayGateway())->handleHooks();

        $errors = $this->buckarooErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Failed to create directory', $errors[0]);
    }

    /**
     * Distinct error messages from the Buckaroo source; the log filter runs
     * once for every WooCommerce log handler
     *
     * @return string[]
     */
    private function buckarooErrors(): array
    {
        $errors = array_filter($this->logged, function ($entry) {
            return $entry['level'] === 'error' && $entry['source'] === 'buckaroo';
        });

        return array_values(array_unique(array_column($errors, 'message')));
    }
}
