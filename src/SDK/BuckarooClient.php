<?php

namespace Buckaroo\Woocommerce\SDK;

use BadMethodCallException;
use Buckaroo\BuckarooClient as BaseBuckarooClient;
use Buckaroo\Config\DefaultConfig;
use Buckaroo\Handlers\Reply\ReplyHandler;
use Buckaroo\Transaction\Response\TransactionResponse;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Buckaroo\Woocommerce\Services\Config;

/* @mixin BaseBuckarooClient */
class BuckarooClient
{
    protected string $websiteKey;
    protected string $secretKey;
    protected BaseBuckarooClient $buckarooClient;
    protected string $mode;

    public function __construct(string $mode, ?string $websiteKey = null, ?string $secretKey = null)
    {
        $config = get_option('woocommerce_buckaroo_mastersettings_settings', []);

        $this->websiteKey = $websiteKey ?? $config['merchantkey'];
        $this->secretKey = $secretKey ?? $config['secretkey'];
        $this->mode = $mode;
        $this->buckarooClient = $this->initClient();
    }

    protected function initClient(): BaseBuckarooClient
    {
        global $wp_version;

        return new BaseBuckarooClient(
            new DefaultConfig(
                $this->websiteKey,
                $this->secretKey,
                $this->mode == 'test' ? 'test' : 'live',
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


    public function process(AbstractProcessor $processor, $additionalData = []): TransactionResponse
    {
        ray([
            $processor->gateway->getServiceCode(),
            $processor->getAction(),
            array_merge($processor->getBody(), $additionalData)
        ]);
        $client = $this->method($processor->gateway->getServiceCode());
        $res = $client->{$processor->getAction()}(array_merge($processor->getBody(), $additionalData));
        ray([
            'BuckarooClient -> process result',
            $processor->gateway->getServiceCode(),
            $processor->getAction(),
            $processor->getBody(),
            $res,
        ]);
        return $res;
    }
}