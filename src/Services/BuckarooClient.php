<?php

namespace Buckaroo\Woocommerce\Services;

use BadMethodCallException;
use Buckaroo\Woocommerce\Core\Plugin;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use BuckarooDeps\Buckaroo\Adapter\Contracts\Result;
use BuckarooDeps\Buckaroo\Adapter\Payments;
use BuckarooDeps\Buckaroo\BuckarooClient as BaseBuckarooClient;
use BuckarooDeps\Buckaroo\Config\DefaultConfig;
use BuckarooDeps\Buckaroo\Handlers\Reply\ReplyHandler;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use Exception;
use InvalidArgumentException;

/**
 * Class BuckarooClient
 *
 * Serves as a wrapper around the BuckarooClient, providing additional functionality
 * specific to the WooCommerce integration.
 *
 *
 * @mixin BaseBuckarooClient
 */
class BuckarooClient
{
    /**
     * Merchant store key.
     */
    protected string $websiteKey;

    /**
     * Merchant secret key.
     */
    protected string $secretKey;

    /**
     * Instance of the base BuckarooClient.
     */
    protected BaseBuckarooClient $buckarooClient;

    /**
     * Operating mode: 'test' or 'live'.
     */
    protected string $mode;

    /**
     * Sends each transaction to the BPE SDK or to UPG. Built on the same base client.
     */
    protected Payments $payments;

    /**
     * BuckarooClient constructor.
     *
     * Initializes the BuckarooClient with the provided or stored credentials.
     *
     * @param  string  $mode  Operating mode ('test' or 'live').
     * @param  string|null  $websiteKey  Optional store key. If null, retrieved from settings.
     * @param  string|null  $secretKey  Optional secret key. If null, retrieved from settings.
     *
     * @throws InvalidArgumentException If required configuration keys are missing.
     */
    public function __construct(string $mode, ?string $websiteKey = null, ?string $secretKey = null)
    {
        $config = get_option('woocommerce_buckaroo_mastersettings_settings', []);

        if ($websiteKey === null && empty($config['merchantkey'])) {
            throw new InvalidArgumentException('Store Key is required.');
        }

        if ($secretKey === null && empty($config['secretkey'])) {
            throw new InvalidArgumentException('Secret key is required.');
        }

        $this->websiteKey = $websiteKey ?? $config['merchantkey'];
        $this->secretKey = $secretKey ?? $config['secretkey'];
        $this->mode = strtolower($mode) === 'test' ? 'test' : 'live';
        $this->buckarooClient = $this->initializeClient();
        $this->payments = new Payments($this->buckarooClient, UpgSettings::client($this->mode), UpgSettings::methods($this->mode));
    }

    /**
     * Initialize the base BuckarooClient with the appropriate configuration.
     */
    protected function initializeClient(): BaseBuckarooClient
    {
        global $wp_version;

        return new BaseBuckarooClient(
            new DefaultConfig(
                $this->websiteKey,
                $this->secretKey,
                $this->mode,
                null,
                null,
                null,
                null,
                'WordPress',
                $wp_version,
                'Buckaroo',
                'Woocommerce Payments Plugin',
                Plugin::VERSION
            )
        );
    }

    /**
     * Validate the reply handler with the provided data.
     *
     * @param  mixed|null  $data  Data to validate.
     * @return bool True if valid, false otherwise.
     */
    public function isReplyHandlerValid($data = null, $authHeader = '', $url = ''): bool
    {
        try {
            $replyHandler = new ReplyHandler($this->buckarooClient->client()->config(), $data, $authHeader ?? '', $url ?? '');

            return $replyHandler->validate()->isValid();
        } catch (Exception $e) {
            Logger::log(__METHOD__ . '|1|', [$e->getMessage(), $data]);

            return false;
        }
    }

    /**
     * Handle dynamic method calls to the base BuckarooClient.
     *
     * @param  string  $name  Name of the method being called.
     * @param  array  $arguments  Arguments passed to the method.
     * @return mixed The result of the called method.
     *
     * @throws BadMethodCallException If the method does not exist on the base BuckarooClient.
     */
    public function __call(string $name, array $arguments)
    {
        if (! method_exists($this->buckarooClient, $name)) {
            throw new BadMethodCallException("Method {$name} does not exist on " . get_class($this->buckarooClient) . '.');
        }

        return $this->buckarooClient->{$name}(...$arguments);
    }

    /**
     * Process a transaction using the provided processor and additional data.
     *
     * @param  AbstractProcessor  $processor  The processor handling the transaction.
     * @param  array  $additionalData  Additional data to merge into the transaction.
     * @return Result The answer; on the BPE route raw() is the BPE TransactionResponse, as before.
     *
     * @throws Exception If the processing fails.
     */
    public function process(AbstractProcessor $processor, array $additionalData = []): Result
    {
        $serviceCode = $processor->gateway->getServiceCode($processor);
        $action = $processor->getAction();
        $requestData = array_merge($processor->getBody(), $additionalData);
        Logger::log(__METHOD__ . '|1|', [get_class($processor), $serviceCode, $action, $requestData]);

        return $this->payments->send($serviceCode, $action, $requestData);
    }

    /**
     * The UPG result when the consumer comes back from UPG, otherwise null.
     *
     * @param  array  $query  the query string of the return request
     */
    public function returned(array $query): ?Result
    {
        return $this->payments->returned($query);
    }

    /**
     * Retrieve the current status of a transaction from Buckaroo.
     *
     * @throws Exception If the status request fails.
     */
    public function transactionStatus(string $transactionKey): TransactionResponse
    {
        if (! preg_match('/^[A-Za-z0-9]{1,64}$/', $transactionKey)) {
            throw new InvalidArgumentException('Invalid Buckaroo transaction key.');
        }

        return $this->buckarooClient->transaction($transactionKey)->status();
    }
}
