<?php

namespace Buckaroo\Woocommerce\SDK;

use BadMethodCallException;
use Buckaroo\BuckarooClient as BaseBuckarooClient;
use Buckaroo\Config\DefaultConfig;
use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\Transaction\Response\TransactionResponse;
use Buckaroo\Woocommerce\Services\Config;

/* @mixin BaseBuckarooClient */
class BuckarooClient
{
    protected array $config;
    protected BaseBuckarooClient $buckarooClient;
    private $processor;

    public function __construct($processor)
    {
        $this->processor = $processor;
        $this->config = get_option('woocommerce_buckaroo_mastersettings_settings', []);
        $this->buckarooClient = $this->initClient();
    }

    protected function initClient(): BaseBuckarooClient
    {
        global $wp_version;

        return new BaseBuckarooClient(
            new DefaultConfig(
                $this->config['merchantkey'] ?? '',
                $this->config['secretkey'] ?? '',
                $this->processor->gateway->getMode() == 'test' ? 'test' : 'live',
                null,
                null,
                null,
                null,
                'Wordpress',
                $wp_version,
                'Buckaroo',
                'Woocommerce Payments Plugin',
                    Config::VERSION
            )
        );
    }

    public function isReplyHandlerValid(mixed $data = null): bool
    {
        $replyHandler = new ReplyHandler($this->buckarooClient->client()->config(), $data);

        return $replyHandler->validate()->isValid();
    }

    public function __call($name, $arguments)
    {
        if (!method_exists($this->buckarooClient, $name)) {
            throw new BadMethodCallException("Method {$name} does not exist.");
        }

        return $this->buckarooClient->{$name}(...$arguments);
    }


    public function process(): TransactionResponse
    {
        $client = $this->method($this->processor->gateway->getServiceCode());
        $res = $client->{$this->processor->getAction()}($this->processor->getBody());
        ray([
            'BuckarooClient -> process result',
            $this->processor->gateway->getServiceCode(),
            $this->processor->getAction(),
            $this->processor->getBody(),
            $res,
        ]);
        return $res;
    }
}