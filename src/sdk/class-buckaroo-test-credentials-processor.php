<?php

use WC_Buckaroo\Dependencies\Buckaroo\Config\Config;
use WC_Buckaroo\Dependencies\Buckaroo\BuckarooClient;
use WC_Buckaroo\Dependencies\Buckaroo\Config\DefaultConfig;


class Buckaroo_Test_Credentials_Processor
{
    private $website_key;

    private $secret_key;

    public function __construct(string $website_key, string $secret_key) {
        $this->website_key = $website_key;
        $this->secret_key = $secret_key;
    }
    public function validate_credentials(): bool
    {
        try {
            $client = $this->get_client()->method('ideal');
            $client->pay();
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }
    private function get_client()
    {
        global $wp_version;

        return new BuckarooClient(
            new DefaultConfig(
                $this->website_key,
                $this->secret_key,
                Config::LIVE_MODE,
                null,
                null,
                null,
                null,
                'Wordpress',
                $wp_version,
                'Buckaroo',
                'Woocommerce Payments Plugin',
                BuckarooConfig::VERSION
            )
        );
    }
}
