<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Services\BuckarooClient;

class TestCredentials
{
    /** @var callable */
    private $checkCredentials;

    public function __construct(?callable $checkCredentials = null)
    {
        $this->checkCredentials = $checkCredentials ?? static function (string $storeKey, string $secretKey): bool {
            return (new BuckarooClient('test', $storeKey, $secretKey))->confirmCredential();
        };
        add_action('wp_ajax_buckaroo_test_credentials', [$this, 'handle']);
    }

    public function handle(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You are not allowed to perform this action.', 'wc-buckaroo-bpe-gateway'), '', ['response' => 403]);
        }

        check_ajax_referer('buckaroo_admin_ajax', 'security');

        if (! is_string($_POST['website_key'] ?? '') || ! is_string($_POST['secret_key'] ?? '')) {
            wp_die(esc_html__('Credentials are incorrect', 'wc-buckaroo-bpe-gateway'));
        }

        // Keys are sent to Buckaroo exactly as entered; only surrounding whitespace is removed.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $storeKey = isset($_POST['website_key']) ? trim(wp_unslash($_POST['website_key'])) : '';
        $secretKey = isset($_POST['secret_key']) ? trim(wp_unslash($_POST['secret_key'])) : '';
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $settings = get_option('woocommerce_buckaroo_mastersettings_settings', []);
        $storeKey = $storeKey === '' ? ($settings['merchantkey'] ?? '') : $storeKey;
        $secretKey = $secretKey === '' ? ($settings['secretkey'] ?? '') : $secretKey;
        if ($storeKey === '' || $secretKey === '') {
            wp_die(esc_html__('Credentials are incorrect', 'wc-buckaroo-bpe-gateway'));
        }

        if (($this->checkCredentials)($storeKey, $secretKey)) {
            wp_die(esc_html__('Credentials are OK', 'wc-buckaroo-bpe-gateway'));
        } else {
            wp_die(esc_html__('Credentials are incorrect', 'wc-buckaroo-bpe-gateway'));
        }
    }
}
