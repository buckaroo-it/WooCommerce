<?php

use WC_Buckaroo\Dependencies\Buckaroo\Config\Config;
use WC_Buckaroo\Dependencies\Buckaroo\BuckarooClient;
use WC_Buckaroo\Dependencies\Buckaroo\Config\DefaultConfig;


class Buckaroo_Client_Processor
{
    private Buckaroo_Sdk_Payload_Interface $payload;

    public function __construct(Buckaroo_Sdk_Payload_Interface $payload)
    {
        $this->payload = $payload;
    }
    public function process(): Buckaroo_Sdk_Response
    {
        $client = $this->get_client()->method($this->payload->get_sdk_code());
        return new Buckaroo_Sdk_Response(
            $client->{$this->payload->get_action()}($this->payload->get_body())
        );
    }


    private function get_client()
    {
        global $wp_version;

        $config = $this->get_configuration();
        return new BuckarooClient(
            new DefaultConfig(
                $this->get_website_key($config),
                $this->get_secret_key($config),
                $this->payload->request_mode() == 'test' ? Config::TEST_MODE : Config::LIVE_MODE,
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
    private function get_website_key(array $config): string
    {
        return $config['merchantkey'] ?? '';
    }

    private function get_secret_key(array $config): string
    {
        return $config['secretkey'] ?? '';
    }

    private function get_configuration(): array
    {
        return get_option('woocommerce_buckaroo_mastersettings_settings', []);
    }
}
