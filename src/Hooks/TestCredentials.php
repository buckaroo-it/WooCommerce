<?php

namespace Buckaroo\Woocommerce\Hooks;

use Buckaroo\Woocommerce\Services\BuckarooClient;

class TestCredentials
{
    public function __construct()
    {
        add_action('wp_ajax_buckaroo_test_credentials', [$this, 'handle']);
    }

    public function handle(): void
    {
        if (! isset($_POST['website_key']) || ! is_string($_POST['website_key'])) {
            wp_die(esc_html__('Credentials are incorrect', 'wc-buckaroo-bpe-gateway'));
        }

        if (! isset($_POST['secret_key']) || ! is_string($_POST['secret_key'])) {
            wp_die(esc_html__('Credentials are incorrect', 'wc-buckaroo-bpe-gateway'));
        }

        $buckarooClient = new BuckarooClient('test', $_POST['website_key'], $_POST['secret_key']);

        if ($buckarooClient->confirmCredential()) {
            wp_die(esc_html__('Credentials are OK', 'wc-buckaroo-bpe-gateway'));
        } else {
            wp_die(esc_html__('Credentials are incorrect', 'wc-buckaroo-bpe-gateway'));
        }
    }
}
