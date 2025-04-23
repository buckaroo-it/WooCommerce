<?php

namespace Buckaroo\Woocommerce\Services;

use BadMethodCallException;
use BuckarooDeps\Buckaroo\BuckarooClient as BaseBuckarooClient;
use BuckarooDeps\Buckaroo\Config\DefaultConfig;
use BuckarooDeps\Buckaroo\Exceptions\BuckarooException;
use BuckarooDeps\Buckaroo\Handlers\Reply\ReplyHandler;
use BuckarooDeps\Buckaroo\Transaction\Response\TransactionResponse;
use Buckaroo\Woocommerce\Core\Plugin;
use Buckaroo\Woocommerce\Gateways\AbstractProcessor;
use Exception;
use InvalidArgumentException;

/**
 * Class BuckarooClient
 *
 * Serves as a wrapper around the BuckarooClient, providing additional functionality
 * specific to the WooCommerce integration.
 *
 * @package Buckaroo\Woocommerce\Services
 *
 * @mixin BaseBuckarooClient
 */
class BuckarooClient {

	/**
	 * Merchant store key.
	 *
	 * @var string
	 */
	protected string $websiteKey;

	/**
	 * Merchant secret key.
	 *
	 * @var string
	 */
	protected string $secretKey;

	/**
	 * Instance of the base BuckarooClient.
	 *
	 * @var BaseBuckarooClient
	 */
	protected BaseBuckarooClient $buckarooClient;

	/**
	 * Operating mode: 'test' or 'live'.
	 *
	 * @var string
	 */
	protected string $mode;

	/**
	 * BuckarooClient constructor.
	 *
	 * Initializes the BuckarooClient with the provided or stored credentials.
	 *
	 * @param string      $mode Operating mode ('test' or 'live').
	 * @param string|null $websiteKey Optional store key. If null, retrieved from settings.
	 * @param string|null $secretKey Optional secret key. If null, retrieved from settings.
	 *
	 * @throws InvalidArgumentException If required configuration keys are missing.
	 */
	public function __construct( string $mode, ?string $websiteKey = null, ?string $secretKey = null ) {
		$config = get_option( 'woocommerce_buckaroo_mastersettings_settings', array() );

		if ( $websiteKey === null && empty( $config['merchantkey'] ) ) {
			throw new InvalidArgumentException( 'Store Key is required.' );
		}

		if ( $secretKey === null && empty( $config['secretkey'] ) ) {
			throw new InvalidArgumentException( 'Secret key is required.' );
		}

		$this->websiteKey     = $websiteKey ?? $config['merchantkey'];
		$this->secretKey      = $secretKey ?? $config['secretkey'];
		$this->mode           = strtolower( $mode ) === 'test' ? 'test' : 'live';
		$this->buckarooClient = $this->initializeClient();
	}

	/**
	 * Initialize the base BuckarooClient with the appropriate configuration.
	 *
	 * @return BaseBuckarooClient
	 */
	protected function initializeClient(): BaseBuckarooClient {
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
	 * @param mixed|null $data Data to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 * @throws BuckarooException
	 */
	public function isReplyHandlerValid( $data = null, $authHeader = '', $url = '' ): bool {
		$replyHandler = new ReplyHandler( $this->buckarooClient->client()->config(), $data, $authHeader ?? '', $url ?? '' );

		return $replyHandler->validate()->isValid();
	}

	/**
	 * Handle dynamic method calls to the base BuckarooClient.
	 *
	 * @param string $name Name of the method being called.
	 * @param array  $arguments Arguments passed to the method.
	 *
	 * @return mixed The result of the called method.
	 *
	 * @throws BadMethodCallException If the method does not exist on the base BuckarooClient.
	 */
	public function __call( string $name, array $arguments ) {
		if ( ! method_exists( $this->buckarooClient, $name ) ) {
			throw new BadMethodCallException( "Method {$name} does not exist on " . get_class( $this->buckarooClient ) . '.' );
		}

		return $this->buckarooClient->{$name}( ...$arguments );
	}

	/**
	 * Process a transaction using the provided processor and additional data.
	 *
	 * @param AbstractProcessor $processor The processor handling the transaction.
	 * @param array             $additionalData Additional data to merge into the transaction.
	 *
	 * @return TransactionResponse The response from the transaction.
	 *
	 * @throws Exception If the processing fails.
	 */
	public function process( AbstractProcessor $processor, array $additionalData = array() ): TransactionResponse {
		$serviceCode = $processor->gateway->getServiceCode( $processor );
		$action      = $processor->getAction();
		$requestData = array_merge( $processor->getBody(), $additionalData );
        Logger::log( __METHOD__ . '|1|', array( get_class( $processor ), $serviceCode, $action, $requestData ) );

		return $this->method( $serviceCode )->{$action}( $requestData );
	}
}
