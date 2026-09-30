<?php

declare(strict_types=1);

use Buckaroo\Woocommerce\Hooks\TestCredentials;
use PHPUnit\Framework\TestCase;

class Test_CredentialCheck extends TestCase
{
    /** @dataProvider credentialRequests */
    public function test_credential_check_selects_values_and_enforces_access(
        array $submitted,
        ?array $expected,
        string $message,
        bool $valid = true,
        bool $configured = true,
        bool $authorized = true,
        bool $nonceValid = true
    ): void {
        $option = 'woocommerce_buckaroo_mastersettings_settings';
        $original = get_option($option, null);
        $post = $_POST;
        $request = $_REQUEST;
        $user = get_current_user_id();
        $manager = wp_insert_user([
            'user_login' => 'credential-check-' . wp_generate_uuid4(),
            'user_pass' => 'test-only-password',
            'role' => 'subscriber',
        ]);
        if ($authorized) {
            get_user_by('id', $manager)->add_cap('manage_woocommerce');
        }
        wp_set_current_user($manager);
        update_option($option, $configured ? ['merchantkey' => 'stored-store', 'secretkey' => 'stored-secret'] : []);
        $_POST = $submitted;
        $_REQUEST = ['security' => $nonceValid ? wp_create_nonce('buckaroo_admin_ajax') : 'invalid'];
        $seen = null;
        $handler = new TestCredentials(static function ($store, $secret) use (&$seen, $valid): bool {
            $seen = [$store, $secret];
            return $valid;
        });
        $die = static function () {
            return static function ($message) {
                throw new RuntimeException((string) $message);
            };
        };
        add_filter('wp_die_handler', $die);
        add_filter('wp_die_ajax_handler', $die);
        add_filter('wp_doing_ajax', '__return_true');
        try {
            try {
                $handler->handle();
                $this->fail('The credential check must return a response.');
            } catch (RuntimeException $e) {
                $this->assertSame($message, $e->getMessage());
            }
            $this->assertSame($expected, $seen);
        } finally {
            remove_filter('wp_die_handler', $die);
            remove_filter('wp_die_ajax_handler', $die);
            remove_filter('wp_doing_ajax', '__return_true');
            remove_action('wp_ajax_buckaroo_test_credentials', [$handler, 'handle']);
            $_POST = $post;
            $_REQUEST = $request;
            wp_set_current_user($user);
            wp_delete_user($manager);
            if ($original === null) {
                delete_option($option);
            } else {
                update_option($option, $original);
            }
        }
    }
    public function credentialRequests(): array
    {
        return [
            'omitted' => [[], ['stored-store', 'stored-secret'], 'Credentials are OK'],
            'blank' => [['website_key' => '', 'secret_key' => ''], ['stored-store', 'stored-secret'], 'Credentials are OK'],
            'whitespace' => [['secret_key' => '   '], ['stored-store', 'stored-secret'], 'Credentials are OK'],
            'replacement' => [['website_key' => 'new-store', 'secret_key' => 'new-secret'], ['new-store', 'new-secret'], 'Credentials are OK'],
            'mixed' => [['secret_key' => 'new-secret'], ['stored-store', 'new-secret'], 'Credentials are OK'],
            'slashed' => [['secret_key' => "new\\'secret"], ['stored-store', "new'secret"], 'Credentials are OK'],
            'zero' => [['secret_key' => '0'], ['stored-store', '0'], 'Credentials are OK'],
            'rejected' => [[], ['stored-store', 'stored-secret'], 'Credentials are incorrect', false],
            'unconfigured' => [[], null, 'Credentials are incorrect', true, false],
            'first setup' => [['website_key' => 'new-store', 'secret_key' => 'new-secret'], ['new-store', 'new-secret'], 'Credentials are OK', true, false],
            'array input' => [['secret_key' => ['invalid']], null, 'Credentials are incorrect'],
            'unauthorized' => [[], null, 'You are not allowed to perform this action.', true, true, false],
            'invalid nonce' => [[], null, '-1', true, true, true, false],
        ];
    }

}
